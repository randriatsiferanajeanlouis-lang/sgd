<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Créer un utilisateur » en React : départements actifs et rôles proposés,
 * création puis ouverture de la fiche du compte, erreurs renvoyées au formulaire.
 */
class UserCreateReactTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_the_form_offers_active_departments_and_roles(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
        Department::query()->create(['name' => 'Ancien service', 'code' => 'ANCIEN', 'is_active' => false, 'sort_order' => 99]);

        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Create')
            ->where('title', 'Créer un utilisateur')
            ->has('departments', Department::query()->active()->count())
            ->has('roles', Role::query()->count())
            ->where('urls.store', route('admin.users.store'))
            ->where('urls.cancel', route('admin.users.index')))
            ->assertPropsDontSee('Ancien service');

        $this->assertFalse(view()->exists('admin.users.create'));
    }

    public function test_creating_from_the_react_page_opens_the_new_account(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
        $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];
        $payload = [
            'last_name' => 'Rakoto', 'first_name' => 'Jean', 'username' => 'Jean.Rakoto',
            'department_id' => Department::query()->firstWhere('code', 'PRODUCTION')->id,
            'job_title' => 'Magasinier', 'role_id' => Role::query()->firstWhere('name', Role::STAFF)->id,
            'password' => 'Secret2026', 'password_confirmation' => 'Secret2026', 'is_active' => '1',
        ];

        $this->actingAs($admin)->withHeaders($inertia)->post(route('admin.users.store'), $payload)
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('admin.users.edit', User::query()->firstWhere('username', 'jean.rakoto')))
            ->assertSessionHas('status');

        $this->flushHeaders()->from(route('admin.users.create'))->withHeaders($inertia)
            ->post(route('admin.users.store'), [...$payload, 'password_confirmation' => 'Autre2026'])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors(['username', 'password']);
    }
}
