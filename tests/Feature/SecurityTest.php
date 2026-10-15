<?php

namespace Tests\Feature;

use App\Models\Dossier;
use App\Models\Role;
use App\Notifications\DossierNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
    }

    public function test_every_application_route_requires_authentication(): void
    {
        $public = ['login', 'login.store'];

        $unprotected = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getActionName(), 'App\\'))
            ->reject(fn ($route) => in_array($route->getName(), $public, true))
            ->reject(fn ($route) => in_array('auth', $route->gatherMiddleware(), true) && in_array('account.usable', $route->gatherMiddleware(), true))
            ->map(fn ($route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unprotected, 'Routes sans auth + account.usable : '.implode(', ', $unprotected));
    }

    public function test_guests_are_redirected_to_login_on_every_get_route(): void
    {
        $dossier = $this->sendInternalRequest($this->makeUser(Role::STAFF), [$this->makeUser(Role::STAFF, 'RSE')], ['attachments' => [$this->pdf()]]);
        auth()->logout();

        collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true) && in_array('auth', $route->gatherMiddleware(), true))
            ->each(function ($route) use ($dossier) {
                $uri = preg_replace(['/\{dossier\}/', '/\{document\}/', '/\{format\}/', '/\{[^}]+\}/'], [$dossier->id, $dossier->documents()->value('id'), 'pdf', '1'], $route->uri());

                $this->get('/'.ltrim($uri, '/'))->assertRedirect(route('login'));
            });
    }

    public function test_changing_the_id_in_the_url_never_exposes_a_foreign_dossier(): void
    {
        $owner = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($owner, [$recipient], ['attachments' => [$this->pdf('secret.pdf')]]);
        $treatment = $dossier->recipients()->sole();
        $document = $dossier->documents()->sole();
        $this->actingAs($recipient)->post(route('treatments.respond', $treatment), ['content' => 'Réponse']);
        $logsBefore = DB::table('dossier_activity_logs')->count();

        $intruder = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $this->actingAs($intruder);

        $attempts = [
            ['get', route('dossiers.show', $dossier)],
            ['get', route('dossiers.edit', $dossier)],
            ['put', route('dossiers.update', $dossier)],
            ['get', route('dossiers.history.pdf', $dossier)],
            ['get', route('dossiers.history.word', $dossier)],
            ['get', route('documents.download', $document)],
            ['get', route('documents.zip', $dossier)],
            ['post', route('documents.store', $dossier)],
            ['post', route('documents.replace', $document)],
            ['post', route('documents.cancel', $document)],
            ['post', route('treatments.respond', $treatment)],
            ['post', route('treatments.archive', $treatment)],
            ['post', route('treatments.forward', $treatment)],
            ['post', route('treatments.reopen', $treatment)],
            ['post', route('reminders.store', $treatment)],
            ['post', route('validations.store', $treatment)],
            ['post', route('external-requests.respond', $dossier)],
            ['post', route('external-requests.archive', $dossier)],
        ];

        foreach ($attempts as [$method, $url]) {
            $status = $this->{$method}($url, ['content' => 'x', 'reason' => 'x', 'comment' => 'x', 'user_id' => $intruder->id])->getStatusCode();
            $this->assertContains($status, [403, 404], "{$method} {$url} a répondu {$status}");
        }

        $this->assertSame($logsBefore, DB::table('dossier_activity_logs')->count(), 'aucune action ne doit avoir été enregistrée');
        $this->assertSame(1, $dossier->responses()->count());
    }

    public function test_security_headers_are_sent(): void
    {
        $this->actingAs($this->makeUser(Role::STAFF))->get(route('dashboard'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');
    }

    public function test_notification_links_cannot_redirect_outside_the_application(): void
    {
        $user = $this->makeUser(Role::STAFF);
        $dossier = Dossier::factory()->create(['created_by' => $user->id, 'originating_department_id' => $user->department_id]);

        foreach (['//evil.example.com', '/\\evil.example.com', 'https://evil.example.com'] as $url) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => DossierNotification::class,
                'data' => ['type' => 'reminder', 'message' => 'Piège', 'url' => $url, 'dossier_id' => $dossier->id],
            ]);
        }

        $this->actingAs($user);
        foreach ($user->notifications as $notification) {
            $this->get(route('notifications.open', $notification->id))->assertRedirect(route('notifications.index'));
        }
    }

    public function test_user_content_is_escaped(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'RSE')], ['subject' => '<script>alert("xss")</script>']);

        $this->actingAs($sender)->get(route('dossiers.show', $dossier))
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_forbidden_page_is_in_french(): void
    {
        $this->actingAs($this->makeUser(Role::STAFF))->get(route('admin.users.index'))
            ->assertForbidden()
            ->assertSee('Vous n’avez pas les droits nécessaires');
    }

    public function test_dossier_page_query_count_stays_bounded(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $recipients = collect(range(1, 5))->map(fn () => $this->makeUser(Role::STAFF, 'RSE'))->all();
        $dossier = $this->sendInternalRequest($sender, $recipients, ['attachments' => array_map(fn ($i) => $this->pdf("piece-{$i}.pdf"), range(1, 8))]);
        foreach ($recipients as $recipient) {
            $this->actingAs($recipient)->post(route('treatments.respond', $dossier->recipients()->where('assigned_user_id', $recipient->id)->sole()), ['content' => 'Ok']);
        }
        $this->flushSession();

        $this->actingAs($sender);
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->get(route('dossiers.show', $dossier))->assertOk();

        $this->assertLessThan(45, $queries, "Page dossier : {$queries} requêtes");
    }
}
