<?php

namespace Tests\Feature;

use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class RequestsAndTrashTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
    }

    public function test_the_demande_page_lists_only_my_requests_with_view_buttons(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $other = $this->makeUser(Role::STAFF, 'COLLECTE');
        $mine = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], ['subject' => 'Ma demande de matériel']);
        $foreign = $this->sendInternalRequest($other, [$sender], ['subject' => 'Demande reçue d’un collègue']);

        // Le menu « Demande » mène à la liste ; « Nouvelle demande » est en haut à droite de cette liste.
        $this->actingAs($sender)->get(route('dashboard'))
            ->assertSee(route('requests.index').'"', false)
            ->assertDontSee('Nouvelle demande</a>', false);

        $this->get(route('requests.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Requests/Index')
            ->where('urls.create', route('dossiers.create'))
            ->has('dossiers.data', 1)
            ->where('dossiers.data.0.id', $mine->id)
            ->where('dossiers.data.0.subject', 'Ma demande de matériel')
            ->where('dossiers.data.0.showUrl', route('dossiers.show', $mine))
            ->where('dossiers.data.0.status.label', 'Envoyé')
            ->where('counts.sent', 1)
            ->where('filters.group', true));

        $this->assertNotSame($mine->id, $foreign->id);
    }

    public function test_requests_list_sorts_groups_and_exports(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $first = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Alpha']);
        $second = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Bêta']);
        $foreign = $this->sendInternalRequest($recipient, [$sender], ['subject' => 'Autre expéditeur']);

        $this->actingAs($sender)->get(route('requests.index', ['sort' => 'subject', 'dir' => 'asc', 'group' => '0']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'subject')
                ->where('filters.group', false)
                ->where('dossiers.data.0.subject', 'Alpha')
                ->where('dossiers.data.1.subject', 'Bêta'));

        $this->get(route('requests.index', ['search' => 'Bêta']))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 1));

        $csv = $this->get(route('requests.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Alpha', $csv);
        $this->assertStringContainsString($second->reference, $csv);
        $this->assertStringNotContainsString('Autre expéditeur', $csv);

        // Plus de suppression groupée ni de raccourci « Corbeille » dans cette liste.
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page
            ->missing('trashCount')
            ->missing('urls.trash')
            ->missing('urls.destroyMany')
            ->missing('dossiers.data.0.canDelete'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('requests.destroy-many'));
        $this->assertNotSame($first->id, $foreign->id);
    }

    public function test_demande_page_holds_new_request_and_view_dossiers_buttons(): void
    {
        foreach ([[Role::STAFF, 'PRODUCTION'], [Role::DEPARTMENT_HEAD, 'TECHNIQUE'], [Role::HR_MANAGER, 'RSE']] as [$role, $code]) {
            $user = $this->makeUser($role, $code);
            $items = collect(\App\Support\Navigation::for($user)[0]['items'])->keyBy('label');

            // Menu latéral : « Demande » seul, sans sous-menu ni « Voir les dossiers ».
            $this->assertSame('requests.index', $items['Demande']['route'], $role);
            $this->assertSame([], $items['Demande']['children']);
            $this->assertArrayNotHasKey('Voir les dossiers', $items->all());
            $this->assertArrayNotHasKey('Voir tous les dossiers', $items->all());

            $aside = str($this->actingAs($user)->get(route('dashboard'))->getContent())->after('<aside')->before('</aside>');
            $this->assertStringNotContainsString('Voir les dossiers', $aside);
            $this->assertStringNotContainsString(route('dossiers.index').'"', $aside);

            // Page « Demande » : « Nouvelle demande » et « Voir les dossiers » côte à côte.
            $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page
                ->where('urls.create', route('dossiers.create'))
                ->where('urls.dossiers', route('dossiers.index')));
            $this->get(route('dossiers.index'))->assertOk();
        }

        // Administrateur : ne crée pas de demande, garde un accès direct à la liste des dossiers.
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $items = collect(\App\Support\Navigation::for($admin)[0]['items'])->keyBy('label');
        $this->assertArrayNotHasKey('Demande', $items->all());
        $this->assertSame('dossiers.index', $items['Voir les dossiers']['route']);
        $this->actingAs($admin)->get(route('dossiers.create'))->assertForbidden();

        // Les droits d'accès aux dossiers ne changent pas.
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);
        $this->actingAs($this->makeUser(Role::STAFF, 'COLLECTE'))->get(route('dossiers.show', $dossier))->assertForbidden();
    }

    public function test_editing_updates_the_same_dossier_seen_by_the_recipient(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);

        $this->actingAs($sender)->get(route('dossiers.show', $dossier))->assertSee('Modifier')->assertSee('Supprimer');

        $this->put(route('dossiers.update', $dossier), [
            'subject' => 'Objet corrigé après envoi',
            'request_type_id' => $dossier->request_type_id,
            'description' => 'Description corrigée',
            'due_at' => now()->addDays(7)->toDateString(),
        ])->assertRedirect(route('dossiers.show', $dossier));

        $this->assertSame(1, Dossier::query()->count(), 'Aucun doublon n’est créé.');
        $this->actingAs($recipient)->get(route('dossiers.show', $dossier))->assertOk()->assertSee('Objet corrigé après envoi');
    }

    public function test_delete_moves_to_the_sender_trash_without_touching_the_recipient(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Dossier à jeter']);
        $historyBefore = DB::table('dossier_activity_logs')->where('dossier_id', $dossier->id)->count();

        $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier))->assertRedirect(route('requests.index'));

        $this->assertNotNull($dossier->fresh()->trashed_by_sender_at);
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 0));
        $this->get(route('dossiers.index', ['box' => 'all']))->assertDontSee('Dossier à jeter');
        $this->get(route('dossiers.show', $dossier))->assertForbidden();
        $this->get(route('trash.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dossiers/Trash')
            ->has('dossiers.data', 1)
            ->where('dossiers.data.0.subject', 'Dossier à jeter')
            ->where('dossiers.data.0.restore_url', route('trash.restore', $dossier)));

        // Le destinataire garde le dossier, son traitement et tout l'historique.
        $this->actingAs($recipient)->get(route('dossiers.show', $dossier))->assertOk()->assertSee('Mon traitement');
        $this->get(route('dossiers.index'))->assertSee('Dossier à jeter');
        $this->assertGreaterThan($historyBefore, DB::table('dossier_activity_logs')->where('dossier_id', $dossier->id)->count());
    }

    public function test_restore_brings_the_dossier_back(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], ['subject' => 'Dossier restauré']);

        $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier));
        $this->post(route('trash.restore', $dossier))->assertRedirect(route('trash.index'));

        $this->assertNull($dossier->fresh()->trashed_by_sender_at);
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->where('dossiers.data.0.subject', 'Dossier restauré'));
        $this->get(route('dossiers.show', $dossier))->assertOk();
    }

    public function test_permanent_delete_removes_it_from_the_sender_only(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Dossier purgé']);

        $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier));
        $this->delete(route('trash.destroy', $dossier))->assertRedirect(route('trash.index'));

        $this->get(route('trash.index'))->assertInertia(fn (Assert $page) => $page->component('Dossiers/Trash')->has('dossiers.data', 0));
        $this->post(route('trash.restore', $dossier))->assertForbidden();
        $this->assertDatabaseHas('dossiers', ['id' => $dossier->id]);
        $this->actingAs($recipient)->get(route('dossiers.show', $dossier))->assertOk()->assertSee('Dossier purgé');
    }

    public function test_only_the_sender_can_delete_restore_or_purge(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);

        $this->actingAs($recipient)->get(route('dossiers.show', $dossier))->assertOk()->assertDontSee('Placer le dossier');
        $this->delete(route('dossiers.destroy', $dossier))->assertForbidden();

        $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier));

        $this->actingAs($recipient)->post(route('trash.restore', $dossier))->assertForbidden();
        $this->delete(route('trash.destroy', $dossier))->assertForbidden();
        $this->assertNull($dossier->fresh()->purged_by_sender_at);
    }

    public function test_a_trashed_dossier_can_no_longer_be_edited(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);

        $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier));

        $this->put(route('dossiers.update', $dossier), [
            'subject' => 'Tentative',
            'request_type_id' => RequestType::query()->value('id'),
        ])->assertForbidden();
    }
}
