<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_sees_the_six_departments_with_counts(): void
    {
        $production = Department::query()->firstWhere('code', 'PRODUCTION');
        $employee = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create();
        Dossier::factory()->create(['created_by' => $employee->id, 'originating_department_id' => $production->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.departments.index'))->assertOk();

        $response->assertInertia(fn ($page) => $page->component('Admin/Departments/Index')->where('can.create', true));
        $this->assertSame(['Administration', 'RSE', 'Collecte', 'Production', 'Armement', 'Technique'], collect($response->inertiaProps('departments'))->pluck('name')->all());

        $counts = collect($response->inertiaProps('departments'))->firstWhere('code', 'PRODUCTION');
        $this->assertSame(1, $counts['activeUsersCount']);
        $this->assertSame(1, $counts['dossiersCount']);
        $this->assertFalse(view()->exists('admin.departments.index'));
    }

    public function test_admin_creates_and_updates_a_department(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.departments.store'), ['name' => 'Logistique', 'code' => 'logistique'])
            ->assertRedirect(route('admin.departments.index'));

        $department = Department::query()->firstWhere('code', 'LOGISTIQUE');
        $this->assertNotNull($department);

        $this->put(route('admin.departments.update', $department), ['name' => 'Logistique et transport', 'code' => 'LOGISTIQUE'])
            ->assertRedirect();
        $this->assertSame('Logistique et transport', $department->fresh()->name);

        $this->post(route('admin.departments.store'), ['name' => 'Production', 'code' => 'PROD2'])->assertSessionHasErrors('name');
    }

    public function test_deactivated_department_keeps_its_users_and_dossiers(): void
    {
        $production = Department::query()->firstWhere('code', 'PRODUCTION');
        $employee = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create();
        $dossier = Dossier::factory()->create(['created_by' => $employee->id, 'originating_department_id' => $production->id]);

        $this->actingAs($this->admin())->patch(route('admin.departments.toggle', $production))->assertRedirect();

        $this->assertFalse($production->fresh()->is_active);
        $this->assertTrue($employee->fresh()->department->is($production));
        $this->assertTrue($dossier->fresh()->originatingDepartment->is($production));
        $this->assertTrue(AuditLog::query()->where('action', 'department.deactivated')->exists());

        $this->patch(route('admin.departments.toggle', $production));
        $this->assertTrue($production->fresh()->is_active);
    }

    public function test_assigning_a_manager_gives_the_department_head_role(): void
    {
        $rse = Department::query()->firstWhere('code', 'RSE');
        $first = User::factory()->withRole(Role::STAFF, 'RSE')->create();
        $second = User::factory()->withRole(Role::STAFF, 'RSE')->create();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.departments.update', $rse), ['name' => 'RSE', 'code' => 'RSE', 'manager_id' => $first->id]);
        $this->assertTrue($rse->fresh()->manager->is($first));
        $this->assertSame(Role::DEPARTMENT_HEAD, $first->fresh()->role->name);

        $this->put(route('admin.departments.update', $rse), ['name' => 'RSE', 'code' => 'RSE', 'manager_id' => $second->id]);
        $this->assertSame(Role::STAFF, $first->fresh()->role->name);
        $this->assertSame(Role::DEPARTMENT_HEAD, $second->fresh()->role->name);
    }

    public function test_manager_must_belong_to_the_department(): void
    {
        $rse = Department::query()->firstWhere('code', 'RSE');
        $outsider = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create();

        $this->actingAs($this->admin())
            ->put(route('admin.departments.update', $rse), ['name' => 'RSE', 'code' => 'RSE', 'manager_id' => $outsider->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertNull($rse->fresh()->manager_id);
    }

    public function test_non_admin_cannot_manage_departments(): void
    {
        $head = User::factory()->withRole(Role::DEPARTMENT_HEAD)->create();

        $response = $this->actingAs($head)->get(route('admin.departments.index'))->assertOk();

        // Lecture seule : pas de bouton « Ajout département », ni Modifier / Désactiver.
        $response->assertInertia(fn ($page) => $page->where('can.create', false)
            ->where('departments', fn ($rows) => collect($rows)->every(fn ($row) => $row['canUpdate'] === false)));

        $this->actingAs($head)->get(route('admin.departments.create'))->assertForbidden();
        $this->actingAs($head)->post(route('admin.departments.store'), ['name' => 'Test', 'code' => 'TEST'])->assertForbidden();

        $dept = Department::query()->first();
        $this->actingAs($head)->get(route('admin.departments.edit', $dept))->assertForbidden();
        $this->actingAs($head)->put(route('admin.departments.update', $dept), ['name' => $dept->name, 'code' => $dept->code])->assertForbidden();
        $this->actingAs($head)->patch(route('admin.departments.toggle', $dept))->assertForbidden();
    }

    public function test_cards_show_users_per_department_and_link_to_their_list(): void
    {
        $admin = $this->admin();
        $production = Department::query()->firstWhere('code', 'PRODUCTION');
        User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create(['is_active' => false]);

        $response = $this->actingAs($admin)->get(route('admin.departments.index'))->assertOk();

        $card = collect($response->inertiaProps('cards'))->firstWhere('code', 'PRODUCTION');
        $this->assertSame(1, $card['usersCount']);
        $this->assertSame(0, $card['activeUsersCount']);
        $this->assertSame(User::query()->where('is_active', true)->count(), $response->inertiaProps('totals.active'));
        $this->assertSame(route('admin.users.index', ['department' => $production->id]), $card['usersUrl']);
        $response->assertInertia(fn ($page) => $page->where('can.manageUsers', true));
    }

    public function test_search_filters_departments_and_suggests_while_typing(): void
    {
        $this->actingAs($this->admin());

        $names = collect($this->get(route('admin.departments.index', ['search' => 'P']))->inertiaProps('departments'))->pluck('code');
        $this->assertSame(['PRODUCTION'], $names->all());

        $this->getJson(route('admin.departments.index', ['search' => 'coll', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.label', 'Collecte');
    }

    private function admin(): User
    {
        return User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
    }
}
