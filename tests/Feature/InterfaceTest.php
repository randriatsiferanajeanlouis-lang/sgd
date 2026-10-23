<?php

namespace Tests\Feature;

use App\Enums\RequestScope;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Permission;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\UrgencyLevel;
use App\Models\User;
use App\Notifications\UserDeactivatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_employee_sidebar_has_only_personal_entries(): void
    {
        $this->actingAs($this->user(Role::STAFF))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertDontSee('Sélectionner un département')
            ->assertDontSee(route('directory.index'))
            ->assertDontSee('Dossiers du département')
            ->assertDontSee('Nouvelle demande externe')
            ->assertDontSee('Utilisateurs')
            ->assertDontSee('Paramètres');
    }

    public function test_department_head_sidebar_adds_employees(): void
    {
        $this->actingAs($this->user(Role::DEPARTMENT_HEAD))->get(route('dashboard'))
            ->assertSee('Employés')
            ->assertDontSee('Utilisateurs');
    }

    public function test_admin_sidebar_has_administration_entries(): void
    {
        $this->actingAs($this->user(Role::ADMIN, 'ADMINISTRATION'))->get(route('dashboard'))
            ->assertSee('Utilisateurs')
            ->assertSee('Paramètres')
            ->assertDontSee('Nouvelle demande externe');
    }

    public function test_header_shows_profile_theme_and_notifications(): void
    {
        $user = $this->user(Role::STAFF);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('Notifications')
            ->assertSee('Changer de thème')
            ->assertSee($user->name)
            ->assertSee('Déconnexion');
    }

    public function test_directory_lists_names_and_job_titles_without_personal_data(): void
    {
        User::factory()->withRole(Role::STAFF, 'TECHNIQUE')->create([
            'first_name' => 'Hery', 'last_name' => 'Rabe', 'job_title' => 'Mécanicien', 'phone' => '0349998877', 'email' => 'hery@example.com',
        ]);
        User::factory()->withRole(Role::STAFF, 'TECHNIQUE')->inactive()->create(['last_name' => 'Parti']);

        $this->actingAs($this->user(Role::STAFF))->get(route('directory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Directory/Index'))
            ->assertPropsSee('Technique', 'Hery Rabe', 'Mécanicien')
            ->assertPropsDontSee('0349998877', 'hery@example.com', 'Parti');
        $this->assertFalse(view()->exists('directory.index'));
    }

    public function test_managers_get_departments_only_in_the_manager_space(): void
    {
        // Responsables de département et RH : pas de « Départements » dans l'espace personnel,
        // seulement dans l'espace Responsable.
        foreach ([[Role::DEPARTMENT_HEAD, 'PRODUCTION'], [Role::HR_MANAGER, 'RSE']] as [$role, $code]) {
            $manager = $this->user($role, $code);

            $this->actingAs($manager)->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee(route('directory.index').'"', false)
                ->assertSee(route('admin.departments.index').'"', false)
                ->assertDontSee('Sélectionner un département');

            $navigation = collect(\App\Support\Navigation::for($manager))->keyBy('label');
            $this->assertNotContains('directory.index', array_column($navigation['Espace personnel']['items'], 'route'));
            $this->assertContains('admin.departments.index', array_column($navigation['Responsable']['items'], 'route'));

            $this->get(route('directory.dossiers', Department::query()->firstWhere('code', 'COLLECTE')))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('Directory/Dossiers')->where('urls.back', route('admin.departments.index')));
        }

        $this->actingAs($this->user(Role::ADMIN, 'ADMINISTRATION'))->get(route('dashboard'))
            ->assertDontSee(route('directory.index'));
    }

    public function test_departments_page_shows_one_card_per_active_department_with_the_dossiers_sent_by_the_user(): void
    {
        Department::query()->create(['name' => 'Ancien service', 'code' => 'ANCIEN', 'is_active' => false, 'sort_order' => 99]);
        $head = $this->user(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        $technique = Department::query()->firstWhere('code', 'TECHNIQUE');
        $recipient = User::factory()->withRole(Role::STAFF, 'TECHNIQUE')->create();

        foreach (['Pièces moteur', 'Vidange'] as $subject) {
            $this->actingAs($head)->post(route('dossiers.store'), [
                'subject' => $subject,
                'request_type_id' => RequestType::query()->availableFor(RequestScope::Internal)->value('id'),
                'urgency_level_id' => UrgencyLevel::query()->active()->value('id'),
                'due_at' => now()->addDays(5)->toDateString(),
                'description' => 'Merci.',
                'recipients' => [$recipient->id],
            ])->assertSessionHasNoErrors();
        }

        $response = $this->actingAs($head)->get(route('directory.index'))
            ->assertOk()
            ->assertPropsDontSee('Ancien service');
        $cards = collect($response->inertiaProps('departments'));

        // Une carte par département actif, dans l'ordre, avec le nombre de dossiers envoyés et le lien vers ces dossiers.
        $this->assertSame(['Administration', 'RSE', 'Collecte', 'Production', 'Armement', 'Technique'], $cards->pluck('name')->all());
        $this->assertSame(Department::query()->active()->count(), $cards->count());
        $this->assertSame(2, $cards->firstWhere('id', $technique->id)['sent']);
        $this->assertSame(route('directory.dossiers', $technique), $cards->firstWhere('id', $technique->id)['dossiersUrl']);
        $this->assertStringContainsString('data-testid="department-card"', (string) file_get_contents(resource_path('js/pages/Directory/Index.jsx')));

        $this->actingAs($head)->get(route('directory.dossiers', $technique))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('department.name', 'Technique')->has('dossiers.data', 2))
            ->assertPropsSee('Pièces moteur', 'Vidange');

        $collecte = Department::query()->firstWhere('code', 'COLLECTE');
        $this->actingAs($head)->get(route('directory.dossiers', $collecte))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('dossiers.data', 0));

        // Seuls les dossiers envoyés par l'utilisateur lui-même sont listés.
        $this->actingAs($recipient)->get(route('directory.dossiers', $technique))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('dossiers.data', 0)->where('urls.back', route('directory.index')));
        $this->assertFalse(view()->exists('directory.dossiers'));
    }

    public function test_history_page_in_the_sidebar_follows_history_permissions(): void
    {
        $head = $this->user(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        $recipient = User::factory()->withRole(Role::STAFF, 'TECHNIQUE')->create();
        $outsider = $this->user(Role::STAFF, 'COLLECTE');

        $this->actingAs($head)->post(route('dossiers.store'), [
            'subject' => 'Pièces moteur',
            'request_type_id' => RequestType::query()->availableFor(RequestScope::Internal)->value('id'),
            'urgency_level_id' => UrgencyLevel::query()->active()->value('id'),
            'due_at' => now()->addDays(5)->toDateString(),
            'description' => 'Merci.',
            'recipients' => [$recipient->id],
        ])->assertSessionHasNoErrors();

        $this->get(route('dashboard'))->assertSee(route('history.index').'"', false);
        $this->get(route('history.index'))->assertOk()->assertInertia(fn ($page) => $page->component('History/Index'))->assertPropsSee('Pièces moteur', 'DOS-');

        $this->actingAs($recipient)->get(route('history.index'))->assertOk()->assertPropsSee('Pièces moteur');
        $this->actingAs($outsider)->get(route('history.index'))->assertOk()->assertPropsDontSee('Pièces moteur');

        // Administrateur : aucune permission d'historique, ni bouton ni page.
        $admin = $this->user(Role::ADMIN, 'ADMINISTRATION');
        $this->actingAs($admin)->get(route('dashboard'))->assertDontSee(route('history.index').'"', false);
        $this->get(route('history.index'))->assertForbidden();
    }

    public function test_footer_no_longer_links_to_dashboard_and_profile(): void
    {
        $footer = str($this->actingAs($this->user(Role::STAFF))->get(route('dashboard'))->getContent())->after('<footer')->before('</footer>');

        $this->assertStringNotContainsString('Tableau de bord', $footer);
        $this->assertStringNotContainsString('Mon profil', $footer);
        $this->assertStringNotContainsString('Notifications', $footer);
        $this->assertStringContainsString('&copy; '.date('Y').' Jean Louis Pro', $footer);
        $this->assertStringNotContainsString('droits réservés', $footer);
        $this->assertStringNotContainsString('SOGEDIPROMA', $footer);

        $aside = str($this->get(route('dashboard'))->getContent())->after('<aside')->before('</aside>');
        $this->assertStringContainsString(route('notifications.index').'"', $aside);

        auth()->logout();
        $this->get(route('login'))->assertInertia(fn ($page) => $page->component('Auth/Login'));
        $this->assertStringContainsString(
            '© {new Date().getFullYear()} Jean Louis Pro',
            (string) file_get_contents(resource_path('js/layouts/GuestLayout.jsx')),
        );
    }

    public function test_my_dossiers_page_shows_the_grand_total_and_one_card_per_department(): void
    {
        $head = $this->user(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        $collecte = Department::query()->firstWhere('code', 'COLLECTE');
        $toTechnique = User::factory()->withRole(Role::STAFF, 'TECHNIQUE')->create();
        $toCollecte = User::factory()->withRole(Role::STAFF, 'COLLECTE')->create();

        foreach ([['Pièces moteur', $toTechnique], ['Vidange', $toTechnique], ['Collecte crevettes', $toCollecte]] as [$subject, $recipient]) {
            $this->actingAs($head)->post(route('dossiers.store'), [
                'subject' => $subject,
                'request_type_id' => RequestType::query()->availableFor(RequestScope::Internal)->value('id'),
                'urgency_level_id' => UrgencyLevel::query()->active()->value('id'),
                'due_at' => now()->addDays(5)->toDateString(),
                'description' => 'Merci.',
                'recipients' => [$recipient->id],
            ])->assertSessionHasNoErrors();
        }

        $response = $this->actingAs($head)->get(route('dossiers.index'))->assertOk();

        $this->assertSame(3, $response->inertiaProps('grandTotal'));
        $totals = collect($response->inertiaProps('departmentTotals'))->mapWithKeys(fn ($row) => [$row['code'] => $row['total']]);
        $this->assertSame(2, $totals['TECHNIQUE']);
        $this->assertSame(1, $totals['COLLECTE']);
        $this->assertSame(3, $totals['PRODUCTION']);
        // Une carte par département actif (data-testid="department-total" dans la page React).
        $this->assertCount(Department::query()->active()->count(), $totals);
        $this->assertStringContainsString('data-testid="department-total"', (string) file_get_contents(resource_path('js/pages/Dossiers/Index.jsx')));

        $subjects = collect($this->get(route('dossiers.index', ['box' => 'all', 'department' => $collecte->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('selectedDepartment', $collecte->id))
            ->inertiaProps('dossiers.data'))->pluck('subject');
        $this->assertTrue($subjects->contains(fn ($subject) => str_contains($subject, 'Collecte crevettes')));
        $this->assertFalse($subjects->contains(fn ($subject) => str_contains($subject, 'Vidange')));

        // Un autre employé ne voit que ses propres dossiers dans les totaux.
        $this->actingAs($toTechnique)->get(route('dossiers.index'))->assertInertia(fn ($page) => $page->where('grandTotal', 2));
        $this->assertSame(0, collect($this->get(route('dossiers.index'))->inertiaProps('departmentTotals'))->firstWhere('id', $collecte->id)['total']);
    }

    public function test_hr_gets_the_read_only_departments_list_with_links_to_the_dossiers_it_sent(): void
    {
        $hr = $this->user(Role::HR_MANAGER, 'RSE');
        $collecte = Department::query()->firstWhere('code', 'COLLECTE');

        $this->actingAs($hr)->get(route('dashboard'))->assertSee(route('admin.departments.index'));

        // Lecture seule (« Lecture seule » et colonne « Mes envois » affichées par la page React quand can.create est faux).
        $this->actingAs($hr)->get(route('admin.departments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.create', false)
                ->where('departments', fn ($rows) => collect($rows)->firstWhere('code', 'COLLECTE')['sentUrl'] === route('directory.dossiers', $collecte)));
        $page = (string) file_get_contents(resource_path('js/pages/Admin/Departments/Index.jsx'));
        $this->assertStringContainsString('Lecture seule', $page);
        $this->assertStringContainsString('<th>Mes envois</th>', $page);

        $this->getJson(route('admin.departments.index', ['search' => 'coll', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonPath('results.0.url', route('directory.dossiers', $collecte));

        $this->get(route('admin.departments.edit', $collecte))->assertForbidden();
    }

    public function test_add_department_button_is_on_the_admin_departments_page(): void
    {
        $this->actingAs($this->user(Role::ADMIN, 'ADMINISTRATION'))->get(route('admin.departments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.create', true)->where('urls.create', route('admin.departments.create')));
        $this->assertStringContainsString('Ajout département', (string) file_get_contents(resource_path('js/pages/Admin/Departments/Index.jsx')));

        foreach ([Role::STAFF, Role::DEPARTMENT_HEAD] as $role) {
            $this->actingAs($this->user($role))->get(route('dashboard'))
                ->assertDontSee('Ajout département')
                ->assertDontSee(route('admin.departments.create'));
        }
    }

    public function test_sidebar_department_members_returns_only_active_names_and_job_titles(): void
    {
        $department = Department::query()->where('code', 'PRODUCTION')->firstOrFail();
        $head = User::factory()->withRole(Role::DEPARTMENT_HEAD, 'PRODUCTION')->create(['first_name' => 'Zo', 'last_name' => 'Zafy', 'job_title' => 'Chef de production']);
        $department->update(['manager_id' => $head->id]);
        User::factory()->withRole(Role::STAFF, 'PRODUCTION')->create([
            'first_name' => 'Jean', 'last_name' => 'Rakoto', 'job_title' => 'Opérateur', 'phone' => '0341112233', 'email' => 'jean@example.com',
        ]);
        User::factory()->withRole(Role::STAFF, 'PRODUCTION')->inactive()->create(['last_name' => 'Parti']);

        $response = $this->actingAs($this->user(Role::STAFF, 'TECHNIQUE'))
            ->getJson(route('directory.members', $department))
            ->assertOk()
            ->assertJsonPath('members.0.last_name', 'Zafy')
            ->assertJsonPath('members.0.is_manager', true)
            ->assertJsonFragment(['first_name' => 'Jean', 'last_name' => 'Rakoto', 'job_title' => 'Opérateur', 'is_manager' => false])
            ->assertDontSee('Parti')
            ->assertDontSee('0341112233')
            ->assertDontSee('jean@example.com');

        $this->assertSame(['id', 'last_name', 'first_name', 'job_title', 'is_manager'], array_keys($response->json('members.0')));
    }

    public function test_sidebar_department_members_handles_empty_inactive_and_guest_cases(): void
    {
        $empty = Department::query()->create(['name' => 'Vide', 'code' => 'VIDE', 'is_active' => true, 'sort_order' => 50]);
        $inactive = Department::query()->create(['name' => 'Fermé', 'code' => 'FERME', 'is_active' => false, 'sort_order' => 51]);
        $staff = $this->user(Role::STAFF);

        $this->actingAs($staff)->getJson(route('directory.members', $empty))->assertOk()->assertExactJson(['members' => []]);
        $this->actingAs($staff)->getJson(route('directory.members', $inactive))->assertNotFound();

        auth()->logout();
        $this->getJson(route('directory.members', $empty))->assertUnauthorized();
    }

    public function test_notification_counter_and_opening_marks_it_as_read(): void
    {
        $admin = $this->user(Role::ADMIN, 'ADMINISTRATION');
        $employee = $this->user(Role::STAFF, 'RSE');
        $admin->notify(new UserDeactivatedNotification($employee, $this->user(Role::DEPARTMENT_HEAD, 'RSE'), now()));

        $this->actingAs($admin)->get(route('dashboard'))->assertSee('data-testid="unread-count">1<', false);

        $notification = $admin->notifications()->first();
        $this->get(route('notifications.open', $notification->id))->assertRedirect(route('admin.users.edit', $employee));

        $this->assertNotNull($notification->fresh()->read_at);
        $this->get(route('dashboard'))->assertDontSee('data-testid="unread-count"', false);
    }

    public function test_user_cannot_open_someone_else_notification(): void
    {
        $admin = $this->user(Role::ADMIN, 'ADMINISTRATION');
        $admin->notify(new UserDeactivatedNotification($this->user(Role::STAFF), $this->user(Role::DEPARTMENT_HEAD), now()));

        $this->actingAs($this->user(Role::STAFF))
            ->get(route('notifications.open', $admin->notifications()->first()->id))
            ->assertNotFound();
    }

    public function test_settings_are_restricted_to_admin(): void
    {
        $this->actingAs($this->user(Role::DEPARTMENT_HEAD))->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($this->user(Role::ADMIN, 'ADMINISTRATION'))->get(route('admin.settings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Settings/Index')
                ->where('urls.roles', route('admin.roles.index'))
                ->where('can.managePermissions', true))
            ->assertPropsSee('Très urgent');
        $this->assertFalse(view()->exists('admin.settings.index'));

        $this->get(route('admin.roles.index'))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Admin/Roles')
                ->where('groups.0.name', 'Demandes')
                ->has('roles', 4));
        $this->actingAs($this->user(Role::DEPARTMENT_HEAD))->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_admin_updates_urgency_delay_and_role_permissions(): void
    {
        $admin = $this->user(Role::ADMIN, 'ADMINISTRATION');
        $urgent = UrgencyLevel::query()->firstWhere('code', 'URGENT');

        $this->actingAs($admin)->put(route('admin.settings.urgency-levels.update', $urgent), [
            'name' => 'Urgent', 'response_delay_hours' => 48, 'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame(48, $urgent->fresh()->response_delay_hours);

        $staff = Role::query()->firstWhere('name', Role::STAFF);
        $keep = Permission::query()->whereIn('name', ['create_internal_request', 'view_own_dossiers'])->pluck('id')->all();
        $this->put(route('admin.settings.roles.permissions', $staff), ['permissions' => $keep])->assertRedirect();

        $this->assertEqualsCanonicalizing(['create_internal_request', 'view_own_dossiers'], $staff->permissions()->pluck('name')->all());
        $this->assertTrue(AuditLog::query()->where('action', 'role.permissions_updated')->exists());
    }

    public function test_admin_cannot_remove_permission_management_from_their_own_role(): void
    {
        $admin = $this->user(Role::ADMIN, 'ADMINISTRATION');

        $this->actingAs($admin)->put(route('admin.settings.roles.permissions', $admin->role), ['permissions' => []]);

        $this->assertTrue($admin->fresh()->hasPermission('manage_permissions'));
    }

    private function user(string $role, string $department = 'PRODUCTION'): User
    {
        return User::factory()->withRole($role, $department)->create();
    }
}
