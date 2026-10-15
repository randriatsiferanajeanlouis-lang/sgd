<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserDeactivatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_creates_an_account_with_a_hashed_password(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.users.store'), $this->validUser());

        $user = User::query()->firstWhere('username', 'jean.rakoto');
        $response->assertRedirect(route('admin.users.edit', $user));

        $this->assertSame('Jean Rakoto', $user->name);
        $this->assertSame('Magasinier', $user->job_title);
        $this->assertSame('PRODUCTION', $user->department->code);
        $this->assertTrue(Hash::check('Secret2026', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertDatabaseMissing('users', ['password' => 'Secret2026']);
        $this->assertTrue(AuditLog::query()->where('action', 'user.created')->whereMorphedTo('target', $user)->exists());
    }

    public function test_username_must_be_unique(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->validUser(['username' => 'RH']))
            ->assertSessionHasErrors(['username' => 'Ce nom d’utilisateur est déjà utilisé.']);

        $this->assertSame(1, User::query()->where('username', 'rh')->count());
    }

    public function test_account_creation_validates_required_fields_and_password_confirmation(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->validUser(['last_name' => '', 'password_confirmation' => 'Autre2026']))
            ->assertSessionHasErrors(['last_name', 'password']);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $head = User::factory()->withRole(Role::DEPARTMENT_HEAD)->create();

        $this->actingAs($head)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($head)->post(route('admin.users.store'), $this->validUser())->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'jean.rakoto']);
    }

    public function test_user_list_can_be_filtered(): void
    {
        User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create(['last_name' => 'Randria']);
        User::factory()->withRole(Role::STAFF, 'COLLECTE')->create(['last_name' => 'Rabe', 'is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.users.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Rabe')
            ->assertDontSee('Randria');

        $this->get(route('admin.users.index', ['search' => 'Randr']))->assertSee('Randria')->assertDontSee('Rabe');
    }

    public function test_admin_update_cannot_change_department(): void
    {
        $user = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create();

        $this->actingAs($this->admin())->put(route('admin.users.update', $user), [
            'last_name' => 'Nouveau',
            'first_name' => 'Nom',
            'job_title' => 'Chef d’équipe',
            'role_id' => $user->role_id,
            'department_id' => Department::query()->firstWhere('code', 'RSE')->id,
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Nom Nouveau', $user->name);
        $this->assertSame('PRODUCTION', $user->department->code);
        $this->assertTrue(AuditLog::query()->where('action', 'user.updated')->whereMorphedTo('target', $user)->exists());
    }

    public function test_changing_department_deactivates_old_account_and_creates_a_new_one(): void
    {
        $old = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create(['username' => 'jean', 'first_name' => 'Jean', 'last_name' => 'Rakoto']);
        $rse = Department::query()->firstWhere('code', 'RSE');

        $response = $this->actingAs($this->admin())->post(route('admin.users.change-department', $old), [
            'department_id' => $rse->id,
            'username' => 'jean.rse',
        ]);

        $new = User::query()->firstWhere('username', 'jean.rse');
        $response->assertRedirect(route('admin.users.edit', $new))->assertSessionHas('temporary_password');

        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame('PRODUCTION', $old->fresh()->department->code);
        $this->assertTrue($new->is_active);
        $this->assertSame($rse->id, $new->department_id);
        $this->assertTrue($new->previousAccount->is($old));
        $this->assertSame('Jean Rakoto', $new->name);
    }

    public function test_admin_can_deactivate_and_reactivate_an_account(): void
    {
        Notification::fake();
        $user = User::factory()->withRole(Role::STAFF)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('users.deactivate', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        Notification::assertNothingSent();

        $this->post(route('admin.users.reactivate', $user))->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame(2, AuditLog::query()->whereMorphedTo('target', $user)->whereIn('action', ['user.deactivated', 'user.reactivated'])->count());
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('users.deactivate', $admin))->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_department_head_lists_and_deactivates_employees_of_their_department_and_admin_is_notified(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $head = User::factory()->withRole(Role::DEPARTMENT_HEAD, 'RSE')->create();
        $employee = User::factory()->withRole(Role::STAFF, 'RSE')->create(['last_name' => 'Rasoa', 'phone' => '0341112233']);
        $outsider = User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create(['last_name' => 'Rabe']);

        $this->actingAs($head)->get(route('department-members.index'))
            ->assertOk()
            ->assertSee('Rasoa')
            ->assertDontSee('0341112233')
            ->assertDontSee('Rabe');

        $this->post(route('users.deactivate', $employee), ['comment' => 'Départ de l’entreprise'])->assertRedirect();
        $this->assertFalse($employee->fresh()->is_active);

        $this->post(route('users.deactivate', $outsider))->assertForbidden();
        $this->assertTrue($outsider->fresh()->is_active);

        Notification::assertSentTo($admin, UserDeactivatedNotification::class, function ($notification) use ($admin) {
            $data = $notification->toArray($admin);

            return $data['department'] === 'RSE' && $data['user'] === $data['user'] && isset($data['date'], $data['time'], $data['manager']);
        });

        $log = AuditLog::query()->where('action', 'user.deactivated')->whereMorphedTo('target', $employee)->sole();
        $this->assertSame($head->id, $log->actor_id);
        $this->assertSame('Départ de l’entreprise', $log->comment);
    }

    public function test_employee_cannot_see_department_members_page(): void
    {
        $this->actingAs(User::factory()->withRole(Role::STAFF)->create())
            ->get(route('department-members.index'))
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->withRole(Role::ADMIN, 'ADMINISTRATION')->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUser(array $overrides = []): array
    {
        return [
            'last_name' => 'Rakoto',
            'first_name' => 'Jean',
            'username' => ' Jean.Rakoto ',
            'department_id' => Department::query()->firstWhere('code', 'PRODUCTION')->id,
            'job_title' => 'Magasinier',
            'role_id' => Role::query()->firstWhere('name', Role::STAFF)->id,
            'password' => 'Secret2026',
            'password_confirmation' => 'Secret2026',
            'is_active' => '1',
            ...$overrides,
        ];
    }
}
