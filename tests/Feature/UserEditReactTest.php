<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Modifier un utilisateur » en React : valeurs actuelles, actions sur le
 * compte selon les droits, journal, changement de département avec le mot
 * de passe temporaire du nouveau compte affiché une seule fois.
 */
class UserEditReactTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_the_page_carries_the_account_its_rights_and_its_journal(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
        $user = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create(['first_name' => 'Hery', 'last_name' => 'Randria', 'job_title' => 'Magasinier', 'phone' => '034 00 000 00']);
        $this->actingAs($admin)->post(route('users.deactivate', $user), ['comment' => 'Congé long']);

        $this->get(route('admin.users.edit', $user))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Edit')
            ->where('user.lastName', 'Randria')
            ->where('user.firstName', 'Hery')
            ->where('user.jobTitle', 'Magasinier')
            ->where('user.phone', '034 00 000 00')
            ->where('user.active', false)
            ->where('can.deactivate', false)
            ->where('can.reactivate', true)
            ->where('departments', fn ($options) => ! collect($options)->pluck('label')->contains('Production'))
            ->where('auditLogs.0.comment', 'Congé long')
            ->where('auditLogs.0.actor', $admin->name)
            ->where('urls.update', route('admin.users.update', $user)));

        // L'administrateur ne peut pas se désactiver lui-même.
        $this->get(route('admin.users.edit', $admin))->assertInertia(fn (Assert $page) => $page->where('can.deactivate', false));

        $this->assertFalse(view()->exists('admin.users.edit'));
    }

    public function test_changing_department_opens_the_new_account_with_its_temporary_password(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
        $old = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create(['username' => 'hery.r']);
        $technique = Department::query()->firstWhere('code', 'TECHNIQUE');

        $this->actingAs($admin)->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.users.change-department', $old), ['department_id' => $technique->id, 'username' => 'hery.tech', 'job_title' => 'Technicien']);

        $new = User::query()->firstWhere('username', 'hery.tech');
        $this->flushHeaders()->get(route('admin.users.edit', $new))->assertInertia(fn (Assert $page) => $page
            ->where('user.previousUsername', 'hery.r')
            ->where('flash.temporaryPassword.username', 'hery.tech'));
        $this->assertFalse($old->fresh()->is_active);
    }
}
