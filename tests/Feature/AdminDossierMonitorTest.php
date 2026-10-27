<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Suivi des dossiers par l'administrateur : tous les dossiers, en lecture
 * seule, y compris ceux que l'expéditeur a supprimés définitivement de son espace.
 */
class AdminDossierMonitorTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_sees_every_dossier_including_those_deleted_by_their_sender(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $kept = $this->sendInternalRequest($sender, [$recipient]);
        $deleted = $this->sendInternalRequest($sender, [$recipient]);

        $this->actingAs($sender)->delete(route('dossiers.destroy', $deleted))->assertRedirect();
        $this->delete(route('trash.destroy', $deleted))->assertRedirect(route('trash.index'));

        // L'expéditeur ne le voit plus nulle part dans son espace.
        $this->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 1));
        $this->get(route('trash.index'))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 0));

        // L'administrateur voit les deux, le second marqué « supprimé définitivement ».
        $this->actingAs($admin)->get(route('admin.dossiers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dossiers/Index')
            ->has('dossiers.data', 2)
            ->where('counts.purged', 1)
            ->where('dossiers.data.0.reference', $deleted->reference)
            ->where('dossiers.data.0.removal.state', 'purged')
            ->where('dossiers.data.1.reference', $kept->reference)
            ->where('dossiers.data.1.removal', null));

        $this->get(route('admin.dossiers.index', ['removal' => 'purged']))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 1)->where('dossiers.data.0.reference', $deleted->reference));

        // La fiche reste consultable, avec l'historique de la suppression, sans pouvoir la modifier.
        $this->get(route('dossiers.show', $deleted))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dossier.removal.state', 'purged')
            ->where('can.viewHistory', true));
        $this->put(route('dossiers.update', $deleted), ['subject' => 'x'])->assertForbidden();
    }

    public function test_only_users_who_see_all_dossiers_get_the_monitor_and_the_removal_flag(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier));

        $this->actingAs($recipient)->get(route('admin.dossiers.index'))->assertForbidden();
        $this->get(route('dossiers.show', $dossier))->assertInertia(fn (Assert $page) => $page->where('dossier.removal', null));
    }
}
