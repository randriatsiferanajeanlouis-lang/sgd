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

        $html = $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->getContent();
        preg_match('/<select[^>]*name="department_id".*?<\/select>/s', $html, $select);

        foreach (Department::query()->active()->pluck('name') as $name) {
            $this->assertStringContainsString(">{$name}</option>", $select[0], "« {$name} » doit être proposé à la création d’un compte.");
        }
    }
}
