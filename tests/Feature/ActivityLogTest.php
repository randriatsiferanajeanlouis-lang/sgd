<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_logins_logouts_and_failures_are_journalised_without_the_password(): void
    {
        $user = $this->makeUser(Role::STAFF, 'PRODUCTION', ['username' => 'agent.prod', 'password' => 'Secret2026']);

        $this->post(route('login.store'), ['username' => 'agent.prod', 'password' => 'Mauvais2026']);
        $this->post(route('login.store'), ['username' => 'agent.prod', 'password' => 'Secret2026'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->assertSame(['auth.failed', 'auth.login', 'auth.logout'], AuditLog::query()->orderBy('id')->pluck('action')->all());
        $this->assertSame($user->id, AuditLog::query()->firstWhere('action', 'auth.login')->actor_id);
        $this->assertSame('Identifiant saisi : agent.prod', AuditLog::query()->firstWhere('action', 'auth.failed')->comment);
        $this->assertStringNotContainsString('Mauvais2026', AuditLog::query()->get()->toJson());
    }

    public function test_admin_reads_and_filters_the_activity_log(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $this->post(route('login.store'), ['username' => 'inconnu', 'password' => 'x']);
        $this->actingAs($admin)->post(route('admin.users.reset-password', $this->makeUser(Role::STAFF)));

        $this->get(route('admin.activity.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ActivityLog')
            ->has('logs.data', 2)
            ->where('logs.data.0.label', 'Mot de passe réinitialisé')
            ->where('logs.data.0.category', 'Comptes')
            ->where('summary.failed', 1)
            ->has('summary.daily', 14));

        $this->get(route('admin.activity.index', ['category' => 'Connexions']))->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.label', 'Échec de connexion'));

        $this->assertContains(route('admin.activity.index'), collect(Navigation::commands($admin))->flatMap(fn ($group) => array_column($group['items'], 'url'))->all());
    }

    public function test_only_users_with_the_permission_open_the_log(): void
    {
        foreach ([Role::STAFF, Role::DEPARTMENT_HEAD, Role::HR_MANAGER] as $role) {
            $this->actingAs($this->makeUser($role, $role === Role::HR_MANAGER ? 'RSE' : 'PRODUCTION'))
                ->get(route('admin.activity.index'))
                ->assertForbidden();
        }
    }

    public function test_dashboard_recent_activity_respects_history_rights(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $outsider = $this->makeUser(Role::STAFF, 'COLLECTE');
        $dossier = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Pièces moteur']);

        $this->actingAs($recipient)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('activity.items.0.subtitle', $dossier->reference.' — Pièces moteur'));
        $this->actingAs($outsider)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('activity.items', 0));

        // Administrateur : il lit l'historique de tous les dossiers, la page Historique est donc proposée.
        $this->actingAs($this->makeUser(Role::ADMIN, 'ADMINISTRATION'))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('activity.url', route('history.index')));
    }

    public function test_flash_messages_become_toasts_and_the_command_palette_lists_allowed_pages(): void
    {
        $staff = $this->makeUser(Role::STAFF, 'PRODUCTION');

        // Page React : le message de session est transmis à React (props « flash ») et affiché en toast ;
        // les toasts et la recherche rapide (îlots React) restent chargés dans la mise en page.
        $this->actingAs($staff)->withSession(['status' => 'Enregistré avec succès'])->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->where('flash.status', 'Enregistré avec succès'))
            ->assertSee('id="react-islands"', false);
        $this->assertStringContainsString("router.on('success', showFlash)", (string) file_get_contents(resource_path('js/inertia.jsx')));

        $labels = collect(Navigation::commands($staff))->flatMap(fn ($group) => array_column($group['items'], 'label'));
        $this->assertContains('Mon profil', $labels->all());
        $this->assertNotContains('Journal d’activité', $labels->all());
        $this->assertNotContains('Utilisateurs', $labels->all());
    }
}
