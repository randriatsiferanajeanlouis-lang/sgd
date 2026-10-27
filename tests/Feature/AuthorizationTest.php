<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_only_hr_can_create_external_requests(): void
    {
        $this->assertTrue($this->user(Role::HR_MANAGER, 'RSE')->can('createExternal', Dossier::class));
        $this->assertFalse($this->user(Role::STAFF)->can('createExternal', Dossier::class));
        $this->assertFalse($this->user(Role::DEPARTMENT_HEAD)->can('createExternal', Dossier::class));
        $this->assertFalse($this->user(Role::ADMIN, 'ADMINISTRATION')->can('createExternal', Dossier::class));

        $this->assertTrue($this->user(Role::STAFF)->can('createInternal', Dossier::class));
    }

    public function test_employee_sees_only_dossiers_linked_to_them(): void
    {
        $employee = $this->user(Role::STAFF, 'PRODUCTION');
        $sent = $this->dossierFrom($employee);
        $received = $this->dossierFrom($this->user(Role::STAFF, 'TECHNIQUE'), recipient: $employee);
        $foreign = $this->dossierFrom($this->user(Role::STAFF, 'PRODUCTION'));

        $this->assertTrue($employee->can('view', $sent));
        $this->assertTrue($employee->can('view', $received));
        $this->assertFalse($employee->can('view', $foreign));
        $this->assertEqualsCanonicalizing(
            [$sent->id, $received->id],
            Dossier::query()->visibleTo($employee)->pluck('id')->all(),
        );
    }

    public function test_employee_cannot_download_a_document_of_a_foreign_dossier(): void
    {
        $employee = $this->user(Role::STAFF, 'PRODUCTION');
        $own = $this->documentOn($this->dossierFrom($employee));
        $foreign = $this->documentOn($this->dossierFrom($this->user(Role::STAFF, 'COLLECTE')));

        $this->assertTrue($employee->can('download', $own));
        $this->assertFalse($employee->can('download', $foreign));
    }

    public function test_department_head_sees_only_their_department(): void
    {
        $head = $this->user(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        $fromProduction = $this->dossierFrom($this->user(Role::STAFF, 'PRODUCTION'));
        $toProduction = $this->dossierFrom($this->user(Role::STAFF, 'RSE'), recipient: $this->user(Role::STAFF, 'PRODUCTION'));
        $elsewhere = $this->dossierFrom($this->user(Role::STAFF, 'RSE'), recipient: $this->user(Role::STAFF, 'TECHNIQUE'));

        $this->assertTrue($head->can('view', $fromProduction));
        $this->assertTrue($head->can('view', $toProduction));
        $this->assertFalse($head->can('view', $elsewhere));
        $this->assertTrue($head->can('viewHistory', $toProduction));
        $this->assertFalse($head->can('viewHistory', $elsewhere));
    }

    public function test_hr_sees_every_external_request(): void
    {
        $hr = $this->user(Role::HR_MANAGER, 'RSE');
        $external = Dossier::factory()->external()->create();

        $this->assertTrue($hr->can('view', $external));
        $this->assertFalse($this->user(Role::STAFF, 'RSE')->can('view', $external));
    }

    public function test_admin_sees_dossiers_and_their_detailed_history_but_cannot_change_them(): void
    {
        $admin = $this->user(Role::ADMIN, 'ADMINISTRATION');
        $dossier = $this->dossierFrom($this->user(Role::STAFF));

        $this->assertTrue($admin->can('view', $dossier));
        $this->assertTrue($admin->can('viewHistory', $dossier));

        // Lecture seule : aucune modification, suppression, pièce ni réponse.
        $this->assertFalse($admin->can('update', $dossier));
        $this->assertFalse($admin->can('delete', $dossier));
        $this->assertFalse($admin->can('addDocument', $dossier));
        $this->assertFalse($admin->can('restore', $dossier));
        $this->actingAs($admin)->put(route('dossiers.update', $dossier), ['subject' => 'Modifié'])->assertForbidden();
        $this->delete(route('dossiers.destroy', $dossier))->assertForbidden();
    }

    public function test_admin_history_access_can_be_removed_without_code_change(): void
    {
        $admin = $this->user(Role::ADMIN, 'ADMINISTRATION');
        $dossier = $this->dossierFrom($this->user(Role::STAFF));

        $admin->role->permissions()->detach(Permission::query()->firstWhere('name', 'view_all_history'));

        $this->assertFalse($admin->fresh()->can('viewHistory', $dossier));
    }

    public function test_employee_sees_history_of_own_dossiers_only(): void
    {
        $employee = $this->user(Role::STAFF);

        $this->assertTrue($employee->can('viewHistory', $this->dossierFrom($employee)));
        $this->assertFalse($employee->can('viewHistory', $this->dossierFrom($this->user(Role::STAFF))));
    }

    public function test_department_head_can_deactivate_only_employees_of_their_department(): void
    {
        $head = $this->user(Role::DEPARTMENT_HEAD, 'PRODUCTION');

        $this->assertTrue($head->can('deactivate', $this->user(Role::STAFF, 'PRODUCTION')));
        $this->assertFalse($head->can('deactivate', $this->user(Role::STAFF, 'RSE')));
        $this->assertFalse($head->can('deactivate', $head));
        $this->assertFalse($this->user(Role::STAFF, 'PRODUCTION')->can('deactivate', $this->user(Role::STAFF, 'PRODUCTION')));
    }

    public function test_only_admin_manages_users_and_organisation(): void
    {
        $admin = $this->user(Role::ADMIN, 'ADMINISTRATION');
        $head = $this->user(Role::DEPARTMENT_HEAD);

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('manage_permissions'));
        $this->assertFalse($head->can('viewAny', User::class));
        $this->assertFalse($head->can('create', Department::class));
        $this->assertFalse($head->can('manage_settings'));
    }

    private function user(string $role, string $departmentCode = 'PRODUCTION'): User
    {
        return User::factory()->withRole($role, $departmentCode)->create();
    }

    private function dossierFrom(User $creator, ?User $recipient = null): Dossier
    {
        $dossier = Dossier::factory()->create([
            'created_by' => $creator->id,
            'originating_department_id' => $creator->department_id,
        ]);

        if ($recipient) {
            $dossier->recipients()->create(['department_id' => $recipient->department_id, 'assigned_user_id' => $recipient->id]);
        }

        return $dossier;
    }

    private function documentOn(Dossier $dossier): Document
    {
        return Document::query()->create([
            'dossier_id' => $dossier->id,
            'document_key' => (string) Str::uuid(),
            'original_name' => 'piece.pdf',
            'path' => 'test/piece.pdf',
            'uploaded_by' => $dossier->created_by,
        ]);
    }
}
