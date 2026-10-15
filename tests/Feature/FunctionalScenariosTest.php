<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Enums\ResponseDecision;
use App\Enums\ValidationStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Phase 21 : les 12 scénarios métier à valider avant déploiement,
 * joués par l'interface HTTP réelle.
 */
class FunctionalScenariosTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
        $this->hr = User::query()->firstWhere('username', 'rh');
        $this->hr->update(['must_change_password' => false]);
    }

    public function test_scenario_01_simple_internal_request(): void
    {
        [$a, $b] = [$this->makeUser(Role::STAFF, 'PRODUCTION'), $this->makeUser(Role::STAFF, 'TECHNIQUE')];
        $dossier = $this->sendInternalRequest($a, [$b]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($b)->get(route('dossiers.show', $dossier))->assertOk();
        $this->post(route('treatments.respond', $treatment), ['content' => 'Fait']);
        $this->post(route('treatments.archive', $treatment));

        $this->assertSame(DossierStatus::Archived, $dossier->fresh()->status);
        $this->assertSame(1, $a->notifications()->where('data->type', 'response_received')->count());
    }

    public function test_scenario_02_multi_recipient_independent_statuses(): void
    {
        $a = $this->makeUser(Role::STAFF, 'COLLECTE');
        $b = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $c = $this->makeUser(Role::STAFF, 'RSE');
        $d = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($a, [$b, $c, $d]);

        $this->actingAs($b)->post(route('treatments.respond', $this->treatment($dossier, $b)), ['content' => 'B']);
        $this->actingAs($c)->get(route('dossiers.show', $dossier));

        $this->assertSame(RecipientStatus::Answered, $this->treatment($dossier, $b)->status);
        $this->assertSame(RecipientStatus::InProgress, $this->treatment($dossier, $c)->status);
        $this->assertSame(RecipientStatus::Received, $this->treatment($dossier, $d)->status);
        $this->assertSame(DossierStatus::InProgress, $dossier->fresh()->status);
    }

    public function test_scenario_03_manager_validation(): void
    {
        [$a, $b] = [$this->makeUser(Role::STAFF, 'COLLECTE'), $this->makeUser(Role::STAFF, 'TECHNIQUE')];
        $manager = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        Department::query()->where('code', 'TECHNIQUE')->update(['manager_id' => $manager->id]);
        $dossier = $this->sendInternalRequest($a, [$b]);

        $this->actingAs($b)->post(route('validations.store', $this->treatment($dossier, $b)), ['comment' => 'À valider']);
        $this->actingAs($manager)->post(route('validations.decide', $dossier->validations()->sole()), ['decision' => 'approve']);
        $this->actingAs($b)->post(route('treatments.respond', $this->treatment($dossier, $b)), ['content' => 'Validé et fait']);

        $this->assertSame(ValidationStatus::Approved, $dossier->validations()->sole()->status);
        $this->assertSame(RecipientStatus::Answered, $this->treatment($dossier, $b)->status);
    }

    public function test_scenario_04_external_request_through_hr(): void
    {
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'RSE');
        $dossier = $this->registerExternal([$head]);

        $this->actingAs($head)->post(route('treatments.respond', $this->treatment($dossier, $head)), ['decision' => 'positive', 'content' => 'OK']);
        $this->actingAs($this->hr)->post(route('external-requests.respond', $dossier), ['content' => 'Accepté', 'channel' => 'email', 'sent_at' => now()->toDateString()]);
        $this->post(route('external-requests.archive', $dossier));

        $this->assertSame(DossierStatus::Archived, $dossier->fresh()->status);
        $this->assertSame(1, $dossier->responses()->where('is_final', true)->count());
    }

    public function test_scenario_05_diverging_answers_are_kept(): void
    {
        $production = $this->makeUser(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        $rse = $this->makeUser(Role::DEPARTMENT_HEAD, 'RSE');
        $dossier = $this->registerExternal([$production, $rse]);

        $this->actingAs($production)->post(route('treatments.respond', $this->treatment($dossier, $production)), ['decision' => 'positive', 'content' => 'Oui']);
        $this->actingAs($rse)->post(route('treatments.respond', $this->treatment($dossier, $rse)), ['decision' => 'negative', 'content' => 'Non']);

        $this->assertEqualsCanonicalizing([ResponseDecision::Positive, ResponseDecision::Negative], $dossier->responses()->pluck('decision')->all());
        $this->assertSame(DossierStatus::InProgress, $dossier->fresh()->status, 'aucune majorité automatique');
    }

    public function test_scenario_06_transmission_to_a_colleague(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'COLLECTE');
        $first = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $colleague = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$first]);

        $this->actingAs($first)->post(route('treatments.forward', $this->treatment($dossier, $first)), [
            'user_id' => $colleague->id, 'comment' => 'Relève du service Technique',
        ])->assertRedirect();

        $this->assertSame(RecipientStatus::Received, $this->treatment($dossier, $colleague)->status);
        $this->assertSame('Relève du service Technique', $dossier->activityLogs()->where('action', 'transmission.sent')->sole()->comment);
        $this->assertSame(1, $colleague->notifications()->where('data->type', 'transmission')->count());
    }

    public function test_scenario_07_modification_keeps_old_and_new_values(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'RSE')], ['subject' => 'Ancien objet']);

        $this->actingAs($sender)->put(route('dossiers.update', $dossier), [
            'subject' => 'Nouvel objet', 'request_type_id' => $dossier->request_type_id, 'due_at' => $dossier->due_at->toDateString(), 'description' => $dossier->description,
        ]);

        $log = $dossier->activityLogs()->where('action', 'dossier.updated')->sole();
        $this->assertSame(['objet' => 'Ancien objet'], $log->old_values);
        $this->assertSame(['objet' => 'Nouvel objet'], $log->new_values);
        $this->assertSame($sender->id, $log->user_id);
    }

    public function test_scenario_08_document_replaced_v1_to_v2(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'RSE')], ['attachments' => [$this->pdf('v1.pdf')]]);
        $v1 = $dossier->documents()->sole();

        $this->actingAs($sender)->post(route('documents.replace', $v1), ['file' => $this->pdf('v2.pdf')]);

        $this->assertSame(DocumentStatus::Replaced, $v1->fresh()->status);
        $this->assertSame(DocumentStatus::Active, Document::query()->where('document_key', $v1->document_key)->where('version', 2)->sole()->status);
    }

    public function test_scenario_09_reminder_notifies_and_is_traced(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'RSE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);

        $this->actingAs($sender)->post(route('reminders.store', $this->treatment($dossier, $recipient)));

        $this->assertSame(1, $recipient->notifications()->where('data->type', 'reminder')->count());
        $this->assertTrue($dossier->activityLogs()->where('action', 'reminder.sent')->exists());
    }

    public function test_scenario_10_deadlines_today_and_overdue_internal_and_external(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(9, 0));
        $sender = $this->makeUser(Role::STAFF);
        $internalRecipient = $this->makeUser(Role::STAFF, 'RSE');
        $this->sendInternalRequest($sender, [$internalRecipient], ['due_at' => now()->addDay()->toDateString()]);
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        $this->registerExternal([$head], 'TRES_URGENT');
        auth()->logout();

        // Lendemain 10 h : l'interne arrive à échéance ce jour, l'externe (24 h) est dépassé.
        $this->travelTo(now()->addDay()->setTime(10, 0));
        $this->artisan('dossiers:check-deadlines');
        $this->assertSame(1, $internalRecipient->notifications()->where('data->type', 'deadline_today')->count());
        $this->assertSame(1, $head->notifications()->where('data->type', 'deadline_overdue')->count());
        $this->assertSame(1, $this->hr->notifications()->where('data->type', 'deadline_overdue')->count());

        $this->travel(1)->days();
        $this->artisan('dossiers:check-deadlines');
        $this->assertSame(1, $internalRecipient->notifications()->where('data->type', 'deadline_overdue')->count());
        $this->assertSame(1, $sender->notifications()->where('data->type', 'deadline_overdue')->count());
    }

    public function test_scenario_11_archived_dossier_is_locked_and_treatment_can_be_reopened(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'RSE');
        $dossier = $this->sendInternalRequest($sender, [$recipient], ['attachments' => [$this->pdf()]]);
        $this->actingAs($recipient)->post(route('treatments.respond', $this->treatment($dossier, $recipient)), ['content' => 'x']);
        $this->post(route('treatments.archive', $this->treatment($dossier, $recipient)));
        $this->assertSame(DossierStatus::Archived, $dossier->fresh()->status);

        $this->actingAs($sender)->get(route('dossiers.edit', $dossier))->assertForbidden();
        $this->post(route('documents.store', $dossier), ['attachments' => [$this->pdf()]])->assertForbidden();
        $this->post(route('documents.replace', $dossier->documents()->sole()), ['file' => $this->pdf()])->assertForbidden();

        $this->actingAs($this->makeUser(Role::DEPARTMENT_HEAD, 'RSE'))
            ->post(route('treatments.reopen', $this->treatment($dossier, $recipient)), ['comment' => 'Complément'])
            ->assertRedirect();

        $this->assertSame($dossier->reference, $dossier->fresh()->reference);
        $this->assertSame(RecipientStatus::InProgress, $this->treatment($dossier, $recipient)->status);
        $this->assertSame(DossierStatus::InProgress, $dossier->fresh()->status);
    }

    public function test_scenario_12_department_head_deactivates_an_employee(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'ARMEMENT');
        $employee = $this->makeUser(Role::STAFF, 'ARMEMENT', ['username' => 'marin', 'password' => 'Secret2026']);

        $this->actingAs($head)->post(route('users.deactivate', $employee), ['comment' => 'Fin de contrat'])->assertRedirect();
        auth()->logout();

        $this->assertSame(1, $admin->notifications()->where('data->type', 'user_deactivated')->count());
        $this->post(route('login.store'), ['username' => 'marin', 'password' => 'Secret2026'])->assertSessionHasErrors(['username' => __('auth.inactive')]);
        $this->assertGuest();
    }

    /**
     * @param  list<User>  $recipients
     */
    private function registerExternal(array $recipients, string $urgency = 'NORMAL'): Dossier
    {
        $this->actingAs($this->hr)->post(route('external-requests.store'), [
            'requester_name' => 'Demandeur externe',
            'request_type_id' => RequestType::query()->where('code', 'STAGE')->value('id'),
            'subject' => 'Demande de stage',
            'received_at' => now()->toDateString(),
            'reception_channel' => 'email',
            'urgency_level_id' => UrgencyLevel::query()->where('code', $urgency)->value('id'),
            'recipients' => array_map(fn (User $user) => $user->id, $recipients),
        ])->assertSessionHasNoErrors();

        return Dossier::query()->latest('id')->firstOrFail();
    }

    private function treatment(Dossier $dossier, User $user): DossierRecipient
    {
        return $dossier->recipients()->where('assigned_user_id', $user->id)->sole();
    }
}
