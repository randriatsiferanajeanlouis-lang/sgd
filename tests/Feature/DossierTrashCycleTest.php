<?php

namespace Tests\Feature;

use App\Models\DossierActivityLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Cycle de suppression côté expéditeur : liste → corbeille → restauration ou
 * suppression définitive, à l'unité et par lot, sans toucher aux destinataires.
 */
class DossierTrashCycleTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_single_delete_moves_the_dossier_to_the_trash_then_restore_brings_it_back(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $other = $this->sendInternalRequest($sender, [$recipient]);

        $this->actingAs($sender)->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page
            ->has('dossiers.data', 2)
            ->where('dossiers.data.0.deleteUrl', fn ($url) => is_string($url)));

        $this->delete(route('dossiers.destroy', $dossier))->assertRedirect();

        // Disparu de la liste active, présent dans la corbeille, la ligne existe toujours en base.
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 1)->where('dossiers.data.0.id', $other->id));
        $this->get(route('trash.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 1)->where('dossiers.data.0.id', $dossier->id));
        $this->assertDatabaseHas('dossiers', ['id' => $dossier->id]);

        // Le destinataire garde le dossier partagé.
        $this->actingAs($recipient)->get(route('dossiers.show', $dossier))->assertOk();

        $this->actingAs($sender)->post(route('trash.restore', $dossier))->assertRedirect(route('trash.index'));
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 2));
        $this->get(route('trash.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 0));
    }

    public function test_bulk_delete_then_bulk_restore_and_bulk_purge(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $a = $this->sendInternalRequest($sender, [$recipient]);
        $b = $this->sendInternalRequest($sender, [$recipient]);
        $c = $this->sendInternalRequest($sender, [$recipient]);
        $this->actingAs($sender);

        $this->post(route('requests.trash'), ['ids' => [$a->id, $b->id]])->assertRedirect();
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 1)->where('dossiers.data.0.id', $c->id));
        $this->get(route('trash.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 2));

        $this->post(route('trash.restore-many'), ['ids' => [$a->id]])->assertRedirect(route('trash.index'));
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 2));

        $this->delete(route('trash.destroy-many'), ['ids' => [$b->id]])->assertRedirect(route('trash.index'));
        $this->get(route('trash.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 0));
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 2));
        $this->assertNotNull($b->fresh()->purged_by_sender_at);
        $this->assertDatabaseHas('dossiers', ['id' => $b->id]);
    }

    public function test_a_purged_dossier_cannot_be_restored_or_purged_again_and_its_trace_remains(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier));
        $this->delete(route('trash.destroy', $dossier))->assertRedirect();

        $this->post(route('trash.restore', $dossier))->assertForbidden();
        $this->post(route('trash.restore-many'), ['ids' => [$dossier->id]])->assertForbidden();
        $this->delete(route('trash.destroy-many'), ['ids' => [$dossier->id]])->assertForbidden();

        $this->get(route('trash.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 0));
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 0));
        $this->get(route('dossiers.index', ['box' => 'all']))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 0));

        $actions = DossierActivityLog::query()->where('dossier_id', $dossier->id)->pluck('action');
        $this->assertContains('dossier.trashed', $actions);
        $this->assertContains('dossier.purged_by_sender', $actions);
        $this->actingAs($recipient)->get(route('dossiers.show', $dossier))->assertOk();
    }

    public function test_a_batch_with_a_foreign_dossier_is_refused_entirely(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $other = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $mine = $this->sendInternalRequest($sender, [$recipient]);
        $foreign = $this->sendInternalRequest($other, [$recipient], ['subject' => 'Autre demande']);
        $this->assertNotSame($mine->id, $foreign->id);

        $this->actingAs($sender)->post(route('requests.trash'), ['ids' => [$mine->id, $foreign->id]])->assertForbidden();

        $this->assertNull($mine->fresh()->trashed_by_sender_at);
        $this->assertNull($foreign->fresh()->trashed_by_sender_at);
        $this->post(route('requests.trash'), ['ids' => []])->assertSessionHasErrors('ids');
        $this->post(route('requests.trash'), ['ids' => [999999]])->assertNotFound();
    }

    public function test_a_trashed_dossier_leaves_the_department_list_of_its_own_sender(): void
    {
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        $colleague = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($head, [$recipient]);

        $this->actingAs($head)->delete(route('dossiers.destroy', $dossier));

        $this->get(route('dossiers.index', ['box' => 'all']))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 0));
        $this->actingAs($recipient)->get(route('dossiers.index', ['box' => 'all']))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 1));
    }

    public function test_deleting_from_the_dossiers_list_stays_on_the_list(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);

        $this->actingAs($sender)->from(route('dossiers.index'))->delete(route('dossiers.destroy', $dossier))->assertRedirect(route('dossiers.index'));
        $this->from(route('dossiers.show', $dossier))->delete(route('dossiers.destroy', $dossier))->assertForbidden();
    }
}
