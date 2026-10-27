<?php

namespace Tests\Feature;

use App\Enums\RecipientStatus;
use App\Models\Dossier;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DossierNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;
use ZipArchive;

class HistoryTraceabilityTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    private User $jean;

    private User $recipient;

    private Dossier $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
        $this->jean = $this->makeUser(Role::STAFF, 'PRODUCTION', ['first_name' => 'Jean', 'last_name' => 'Rakoto']);
        $this->recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $this->dossier = $this->sendInternalRequest($this->jean, [$this->recipient], ['subject' => 'Demande fourniture']);
    }

    public function test_modification_keeps_old_and_new_values_with_author_date_and_time(): void
    {
        Notification::fake();

        $this->actingAs($this->jean)->put(route('dossiers.update', $this->dossier), [
            'subject' => 'Demande fourniture informatique',
            'request_type_id' => $this->dossier->request_type_id,
            'due_at' => $this->dossier->due_at->toDateString(),
            'description' => $this->dossier->description,
            'comment' => 'Précision de l’objet',
        ])->assertRedirect(route('dossiers.show', $this->dossier));

        $log = $this->dossier->activityLogs()->where('action', 'dossier.updated')->sole();
        $this->assertSame(['objet' => 'Demande fourniture'], $log->old_values);
        $this->assertSame(['objet' => 'Demande fourniture informatique'], $log->new_values);
        $this->assertSame('Jean Rakoto', $log->user->name);
        $this->assertSame('Précision de l’objet', $log->comment);
        $this->assertNotNull($log->created_at);
        Notification::assertSentTo($this->recipient, DossierNotification::class, fn ($n) => $n->type === 'dossier_updated');

        $this->get(route('dossiers.show', $this->dossier))
            ->assertInertia(fn ($page) => $page->where('can.viewHistory', true)->has('history'))
            ->assertPropsSee('objet : Demande fourniture → Demande fourniture informatique');
    }

    public function test_only_the_author_can_modify_the_dossier(): void
    {
        $this->actingAs($this->recipient)->get(route('dossiers.edit', $this->dossier))->assertForbidden();
        $this->put(route('dossiers.update', $this->dossier), ['subject' => 'Piraté', 'request_type_id' => 1, 'due_at' => now()->toDateString()])->assertForbidden();

        $this->assertSame('Demande fourniture', $this->dossier->fresh()->subject);
    }

    public function test_history_access_depends_on_the_role(): void
    {
        $technicalHead = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        $otherHead = $this->makeUser(Role::DEPARTMENT_HEAD, 'ARMEMENT');
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');

        $this->actingAs($this->recipient)->get(route('dossiers.show', $this->dossier))->assertInertia(fn ($page) => $page->where('can.viewHistory', true)->whereNot('history', null));
        $this->actingAs($technicalHead)->get(route('dossiers.show', $this->dossier))->assertInertia(fn ($page) => $page->where('can.viewHistory', true)->whereNot('history', null));
        $this->actingAs($technicalHead)->get(route('dossiers.history.pdf', $this->dossier))->assertOk();
        $this->actingAs($otherHead)->get(route('dossiers.show', $this->dossier))->assertForbidden();

        // L'administrateur trace toute l'activité : historique et exports de tous les dossiers.
        $this->actingAs($admin)->get(route('dossiers.show', $this->dossier))->assertOk()->assertInertia(fn ($page) => $page->where('can.viewHistory', true)->whereNot('history', null));
        $this->actingAs($admin)->get(route('dossiers.history.pdf', $this->dossier))->assertOk();
        $this->actingAs($admin)->get(route('dossiers.history.word', $this->dossier))->assertOk();
    }

    public function test_admin_history_rule_is_configurable_from_permissions(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $admin->role->permissions()->detach(Permission::query()->firstWhere('name', 'view_all_history'));

        $this->actingAs($admin->fresh())->get(route('dossiers.show', $this->dossier))->assertInertia(fn ($page) => $page->where('can.viewHistory', false)->where('history', null));
    }

    public function test_pdf_export_is_an_a4_pdf(): void
    {
        $response = $this->actingAs($this->jean)->get(route('dossiers.history.pdf', $this->dossier));

        $response->assertOk()->assertHeader('content-type', 'application/pdf')->assertDownload('historique-'.$this->dossier->reference.'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('/MediaBox [0.000 0.000 595.280 841.890]', $response->getContent());
    }

    public function test_word_export_contains_header_and_actions(): void
    {
        $this->actingAs($this->recipient)->get(route('dossiers.show', $this->dossier));

        $response = $this->actingAs($this->jean)->get(route('dossiers.history.word', $this->dossier));
        $response->assertOk()->assertDownload('historique-'.$this->dossier->reference.'.docx');

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        foreach ([$this->dossier->reference, 'Demande fourniture', 'Expéditeur', 'Destinataires', 'Date création', 'Création du dossier', 'Ouverture', 'Jean Rakoto'] as $expected) {
            $this->assertStringContainsString(htmlspecialchars($expected, ENT_XML1), $xml);
        }
    }

    public function test_department_head_reopens_an_archived_treatment_with_a_reason(): void
    {
        Notification::fake();
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        $treatment = $this->dossier->recipients()->sole();

        $this->actingAs($this->recipient)->post(route('treatments.respond', $treatment), ['content' => 'Fait.']);
        $this->post(route('treatments.archive', $treatment));
        $this->post(route('treatments.reopen', $treatment), ['comment' => 'x'])->assertForbidden();

        $this->actingAs($head)->post(route('treatments.reopen', $treatment), ['comment' => 'Quantité incorrecte'])->assertRedirect();

        $this->assertSame(RecipientStatus::InProgress, $treatment->fresh()->status);
        $log = $this->dossier->activityLogs()->where('action', 'recipient.reopened')->sole();
        $this->assertSame('Archivé', $log->old_values['statut']);
        $this->assertSame('Quantité incorrecte', $log->comment);
        Notification::assertSentTo($this->recipient, DossierNotification::class, fn ($n) => $n->type === 'treatment_reopened');
    }

    public function test_recipient_forwards_the_dossier_to_a_colleague(): void
    {
        Notification::fake();
        $colleague = $this->makeUser(Role::STAFF, 'COLLECTE');

        $this->actingAs($this->recipient)
            ->post(route('treatments.forward', $this->dossier->recipients()->sole()), ['user_id' => $colleague->id, 'comment' => 'Pour ton avis'])
            ->assertRedirect();

        $this->assertSame(RecipientStatus::Received, $this->dossier->recipients()->where('assigned_user_id', $colleague->id)->sole()->status);
        $this->assertSame('Pour ton avis', $this->dossier->activityLogs()->where('action', 'transmission.sent')->sole()->comment);
        Notification::assertSentTo($colleague, DossierNotification::class, fn ($n) => $n->type === 'transmission');

        $this->post(route('treatments.forward', $this->dossier->recipients()->where('assigned_user_id', $this->recipient->id)->sole()), ['user_id' => $colleague->id])
            ->assertSessionHasErrors('user_id');
    }
}
