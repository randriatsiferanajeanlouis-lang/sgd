<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_can_reset_a_forgotten_password(): void
    {
        $employee = User::factory()->withRole(Role::STAFF)->create(['username' => 'jean']);
        $oldHash = $employee->password;

        $response = $this->actingAs($this->admin())->post(route('admin.users.reset-password', $employee));

        $temporary = $response->assertSessionHas('temporary_password')->getSession()->get('temporary_password');
        $this->assertSame('jean', $temporary['username']);

        $employee->refresh();
        $this->assertNotSame($oldHash, $employee->password);
        $this->assertTrue(Hash::check($temporary['password'], $employee->password));
        $this->assertTrue($employee->must_change_password);
        $this->assertDatabaseMissing('users', ['password' => $temporary['password']]);
        $this->assertTrue(AuditLog::query()->where('action', 'user.password_reset')->where('target_id', $employee->id)->exists());

        auth()->logout();
        $this->post(route('login.store'), ['username' => 'jean', 'password' => $temporary['password']])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    }

    public function test_employee_cannot_reset_someone_else_password(): void
    {
        $employee = User::factory()->withRole(Role::STAFF)->create();
        $victim = User::factory()->withRole(Role::STAFF)->create();

        $this->actingAs($employee)->post(route('admin.users.reset-password', $victim))->assertForbidden();

        $this->assertFalse($victim->fresh()->must_change_password);
    }

    public function test_initial_admin_and_hr_accounts_must_change_their_password(): void
    {
        $admin = User::query()->firstWhere('username', 'admin');
        $hr = User::query()->firstWhere('username', 'rh');

        $this->assertSame(Role::ADMIN, $admin->role->name);
        $this->assertNull($admin->department_id, 'Le super administrateur n’appartient à aucun département.');
        $this->assertFalse($admin->can('createInternal', \App\Models\Dossier::class));
        $this->assertSame(Role::HR_MANAGER, $hr->role->name);
        $this->assertSame('RSE', $hr->department->code);
        $this->assertTrue($admin->must_change_password);
        $this->assertSame('Responsable RH', $hr->name);
    }

    public function test_temporary_password_must_be_changed_before_anything_else(): void
    {
        $user = $this->userWithTemporaryPassword();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ChangePassword')
                ->where('isMandatory', true));

        $this->put(route('password.change.update'), [
            'current_password' => 'Temporaire1',
            'password' => 'Nouveau2026',
            'password_confirmation' => 'Nouveau2026',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Nouveau2026', $user->password));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_change_password_page_is_rendered_by_react(): void
    {
        $user = User::factory()->withRole(Role::STAFF)->create();

        $this->actingAs($user)->get(route('password.change'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ChangePassword')
                ->where('title', 'Mot de passe')
                ->where('isMandatory', false)
                ->where('urls.update', route('password.change.update'))
                ->where('urls.cancel', route('profile.show')));

        // L'ancienne vue Blade n'existe plus ; titres et champs sont dans la page React.
        $this->assertFalse(view()->exists('auth.change-password'));
        $page = (string) file_get_contents(resource_path('js/pages/Auth/ChangePassword.jsx'));
        foreach (['Remplacez votre mot de passe temporaire', 'Changer mon mot de passe', "'current_password'", "'password'", "'password_confirmation'", '<FormPage'] as $needle) {
            $this->assertStringContainsString($needle, $page);
        }
    }

    public function test_password_change_from_the_react_page_returns_to_the_dashboard_with_a_message(): void
    {
        $user = $this->userWithTemporaryPassword();
        $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];

        // Erreur : retour au formulaire avec le message sous le champ.
        $this->actingAs($user)->from(route('password.change'))->withHeaders($inertia)
            ->put(route('password.change.update'), ['current_password' => 'Faux12345', 'password' => 'Nouveau2026', 'password_confirmation' => 'Nouveau2026'])
            ->assertRedirect(route('password.change'))
            ->assertSessionHasErrors('current_password');

        $this->withHeaders($inertia)
            ->put(route('password.change.update'), ['current_password' => 'Temporaire1', 'password' => 'Nouveau2026', 'password_confirmation' => 'Nouveau2026'])
            ->assertStatus(303)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', __('auth.password_changed'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_password_change_requires_the_current_password_and_a_strong_new_one(): void
    {
        $user = $this->userWithTemporaryPassword();

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'Faux12345',
            'password' => 'Nouveau2026',
            'password_confirmation' => 'Nouveau2026',
        ])->assertSessionHasErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);

        $this->put(route('password.change.update'), [
            'current_password' => 'Temporaire1',
            'password' => 'faible',
            'password_confirmation' => 'faible',
        ])->assertSessionHasErrors('password');

        $this->put(route('password.change.update'), [
            'current_password' => 'Temporaire1',
            'password' => 'Temporaire1',
            'password_confirmation' => 'Temporaire1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    private function admin(): User
    {
        return User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
    }

    private function userWithTemporaryPassword(): User
    {
        return User::factory()->withRole(Role::STAFF, 'RSE')->create([
            'password' => 'Temporaire1',
            'must_change_password' => true,
        ]);
    }
}
