<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Formulaire « Créer ou modifier un département » en React : valeurs
 * actuelles, choix du responsable parmi les comptes actifs du département,
 * retour à la liste avec message, erreurs renvoyées au formulaire.
 */
class DepartmentFormReactTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_create_and_edit_forms_carry_the_current_values_and_the_possible_managers(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
        $rse = Department::query()->firstWhere('code', 'RSE');
        $member = User::factory()->withRole(Role::STAFF, 'RSE')->create(['first_name' => 'Hanta', 'last_name' => 'Rasoa', 'name' => 'Hanta Rasoa', 'job_title' => 'Chargée RSE']);
        User::factory()->withRole(Role::STAFF, 'RSE')->inactive()->create(['name' => 'Ancien compte']);
        User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create(['name' => 'Autre département']);

        $this->actingAs($admin)->get(route('admin.departments.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Departments/Form')
            ->where('title', 'Créer un département')
            ->where('department.id', null)
            ->has('members', 0)
            ->where('urls.save', route('admin.departments.store'))
            ->where('urls.cancel', route('admin.departments.index')));

        $this->get(route('admin.departments.edit', $rse))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('title', 'Modifier un département')
            ->where('department.code', 'RSE')
            ->where('department.name', $rse->name)
            ->where('members', fn ($members) => collect($members)->contains(['value' => (string) $member->id, 'label' => 'Hanta Rasoa — Chargée RSE'])
                && ! collect($members)->pluck('label')->contains(fn ($label) => str_starts_with($label, 'Ancien compte') || str_starts_with($label, 'Autre département')))
            ->where('urls.save', route('admin.departments.update', $rse)));

        $this->assertFalse(view()->exists('admin.departments.form'));
    }

    public function test_saving_from_the_react_form_returns_to_the_list_or_shows_the_errors(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
        $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($admin)->withHeaders($inertia)
            ->post(route('admin.departments.store'), ['name' => 'Logistique', 'code' => 'logistique et transport', 'email' => '', 'description' => ''])
            ->assertRedirect(route('admin.departments.index'))
            ->assertSessionHas('status', 'Le département a été créé.');
        $this->assertSame('LOGISTIQUE_ET_TRANSPORT', Department::query()->firstWhere('name', 'Logistique')->code);

        $this->from(route('admin.departments.create'))->withHeaders($inertia)
            ->post(route('admin.departments.store'), ['name' => 'Production', 'code' => 'PRODUCTION'])
            ->assertRedirect(route('admin.departments.create'))
            ->assertSessionHasErrors(['name', 'code']);
    }
}
