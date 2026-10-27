<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFormDepartmentsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_create_form_offers_every_active_department_including_collecte(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN)->create();

        $offered = collect($this->actingAs($admin)->get(route('admin.users.create'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Users/Create'))
            ->inertiaProps('departments'))->pluck('label');

        foreach (Department::query()->active()->pluck('name') as $name) {
            $this->assertContains($name, $offered->all(), "« {$name} » doit être proposé à la création d’un compte.");
        }
    }

    public function test_a_deactivated_department_disappears_from_every_user_department_list(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN)->create();
        $employee = User::factory()->withRole(Role::STAFF)->create(["department_id" => Department::query()->where("code", "PRODUCTION")->value("id")]);
        $department = Department::query()->where("code", "COLLECTE")->firstOrFail();

        $this->actingAs($admin)->patch(route("admin.departments.toggle", $department))->assertRedirect();
        $this->assertFalse($department->fresh()->is_active);

        $labels = fn (string $routeName, mixed $params = []) => collect($this->get(route($routeName, $params))->assertOk()->inertiaProps("departments"))->pluck("label")->all();

        $this->assertNotContains($department->name, $labels("admin.users.create"));
        $this->assertNotContains($department->name, $labels("admin.users.edit", $employee));
        $this->assertNotContains($department->name, $labels("admin.users.index"));
    }
}