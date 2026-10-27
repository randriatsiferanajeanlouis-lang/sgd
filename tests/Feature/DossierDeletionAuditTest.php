<?php

namespace Tests\Feature;

use App\Models\DossierActivityLog;
use App\Models\Role;
use App\Services\DossierHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Suppression d'un dossier par son expéditeur (Responsable, RH, Employé) et
 * trace d'audit consultable par l'administrateur.
 */
class DossierDeletionAuditTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_each_role_deletes_its_dossier_and_the_admin_sees_the_trace(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');

        foreach ([Role::DEPARTMENT_HEAD => 'Responsable', Role::HR_MANAGER => 'RH', Role::STAFF => 'Employé'] as $role => $label) {
            $sender = $this->makeUser($role, 'PRODUCTION');
            $dossier = $this->sendInternalRequest($sender, [$recipient]);

            $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier))->assertRedirect();
            $this->delete(route('trash.destroy', $dossier))->assertRedirect(route('trash.index'));

            $this->actingAs($admin)->get(route('history.index', ['kind' => 'deletion', 'search' => $dossier->reference, 'user' => $sender->id]))
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->has('logs.data', 2)
                    ->where('logs.data.0.user', $sender->name)
                    ->where('logs.data.0.role', $sender->role->name)
                    ->where('logs.data.0.result', 'Réussi')
                    ->where('logs.data.0.dossier.reference', $dossier->reference));

            // Le destinataire conserve le dossier partagé.
            $this->actingAs($recipient)->get(route('dossiers.show', $dossier))->assertOk();
        }
    }

    public function test_admin_filters_by_role_user_kind_and_date(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $staff = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        $this->actingAs($staff)->delete(route('dossiers.destroy', $this->sendInternalRequest($staff, [$recipient])));
        $this->actingAs($head)->delete(route('dossiers.destroy', $this->sendInternalRequest($head, [$recipient])));

        $count = fn (array $query) => $this->actingAs($admin)->get(route('history.index', $query))->viewData('page')['props']['logs']['total'];

        $this->assertSame(1, $count(['kind' => 'deletion', 'role' => $staff->role->name, 'user' => $staff->id]));
        $this->assertSame(2, $count(['kind' => 'deletion']));
        $this->assertSame(1, $count(['kind' => 'deletion', 'role' => $head->role->name]));
        $this->assertSame(0, $count(['kind' => 'deletion', 'from' => now()->addDay()->toDateString()]));
        $this->assertSame(2, $count(['kind' => 'deletion', 'from' => now()->toDateString(), 'to' => now()->toDateString()]));
    }

    public function test_a_user_cannot_delete_a_dossier_they_do_not_own_and_nothing_is_logged(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $stranger = $this->makeUser(Role::STAFF, 'FINANCE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $before = DossierActivityLog::query()->count();

        $this->actingAs($recipient)->delete(route('dossiers.destroy', $dossier))->assertForbidden();
        $this->actingAs($stranger)->delete(route('dossiers.destroy', $dossier))->assertForbidden();
        $this->delete(route('trash.destroy', $dossier))->assertForbidden();

        $this->assertSame($before, DossierActivityLog::query()->count());
        $this->assertNull($dossier->fresh()->trashed_by_sender_at);
    }

    public function test_repeated_deletion_writes_a_single_trace(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);
        $this->actingAs($sender);

        $trash = app(\App\Services\DossierTrash::class);
        $trash->trash($dossier);
        $trash->trash($dossier->fresh());
        $trash->purge($dossier->fresh());
        $trash->purge($dossier->fresh());

        $this->assertSame(1, DossierActivityLog::query()->where('dossier_id', $dossier->id)->where('action', 'dossier.trashed')->count());
        $this->assertSame(1, DossierActivityLog::query()->where('dossier_id', $dossier->id)->where('action', 'dossier.purged_by_sender')->count());
    }

    public function test_deletion_is_rolled_back_when_the_trace_cannot_be_written(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);

        $this->mock(DossierHistory::class)->shouldReceive('record')->andThrow(new RuntimeException('journal indisponible'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier));
            $this->fail('La suppression aurait dû échouer.');
        } catch (RuntimeException) {
            $this->assertNull($dossier->fresh()->trashed_by_sender_at);
        }
    }

    public function test_ordinary_users_cannot_alter_or_erase_the_trace(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);
        $this->actingAs($sender)->delete(route('dossiers.destroy', $dossier));

        $log = DossierActivityLog::query()->where('action', 'dossier.trashed')->firstOrFail();
        $this->assertSame($sender->role->name, $log->properties['actor_role']);

        $this->expectException(\LogicException::class);
        $log->delete();
    }
}
