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
}
