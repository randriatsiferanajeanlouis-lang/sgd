<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Liste « Utilisateurs » en React : actions selon les droits, filtres et
 * mot de passe temporaire affiché une seule fois après réinitialisation.
 */
class UsersListReactTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_rows_carry_only_the_allowed_actions(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create(['last_name' => 'Aaa']);
        $active = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create(['last_name' => 'Bbb']);
        $inactive = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->inactive()->create(['last_name' => 'Ccc']);

        $rows = collect($this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->inertiaProps('users.data'))->keyBy('id');

        $this->assertFalse($rows[$admin->id]['canDeactivate'], 'pas de désactivation de son propre compte');
        $this->assertTrue($rows[$active->id]['canDeactivate']);
        $this->assertFalse($rows[$active->id]['canReactivate']);
        $this->assertTrue($rows[$inactive->id]['canReactivate']);
        $this->assertFalse($rows[$inactive->id]['canDeactivate']);
        $this->assertSame(route('admin.users.edit', $active), $rows[$active->id]['editUrl']);
    }

    public function test_reset_password_shows_the_temporary_password_once_on_the_list(): void
    {
        $admin = User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
        $user = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create(['username' => 'hery.r']);

        $this->actingAs($admin)->from(route('admin.users.index'))
            ->post(route('admin.users.reset-password', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->get(route('admin.users.index'))->assertInertia(fn (Assert $page) => $page
            ->where('flash.temporaryPassword.username', 'hery.r')
            ->where('flash.temporaryPassword.password', fn ($password) => is_string($password) && strlen($password) >= 8));

        // Affiché une seule fois.
        $this->get(route('admin.users.index'))->assertInertia(fn (Assert $page) => $page->where('flash.temporaryPassword', null));
        $this->assertStringContainsString('<TemporaryPasswordBanner', (string) file_get_contents(resource_path('js/pages/Admin/Users/Index.jsx')));
    }
}
