<?php

namespace Tests\Feature;

use App\Enums\RecipientStatus;
use App\Enums\ReminderReason;
use App\Models\Department;
use App\Models\DossierRecipient;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\UrgencyLevel;
use App\Models\User;
use App\Notifications\DeadlineOverdueAlert;
use App\Notifications\DossierNotification;
use App\Support\Deadline;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class DeadlinesAndRemindersTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
    }

    public function test_deadline_labels_are_in_calendar_days(): void
    {
        $now = Carbon::parse('2026-10-05 10:00');

        $this->assertSame('Échéance dans 3 jours', Deadline::describe(Carbon::parse('2026-10-08 09:00'), now: $now)['label']);
        $this->assertSame('Échéance demain', Deadline::describe(Carbon::parse('2026-10-06 23:59'), now: $now)['label']);
        $this->assertSame('Échéance aujourd’hui', Deadline::describe(Carbon::parse('2026-10-05 23:59'), now: $now)['label']);
        $this->assertSame('Délai dépassé de 2 jours', Deadline::describe(Carbon::parse('2026-10-03 23:59'), now: $now)['label']);
        $this->assertTrue(Deadline::describe(Carbon::parse('2026-10-03 23:59'), now: $now)['overdue']);
        $this->assertNull(Deadline::describe(Carbon::parse('2026-10-03 23:59'), isClosed: true, now: $now));
    }

    public function test_sender_sends_a_quick_reminder_which_is_traced(): void
    {
        Notification::fake();
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($sender)->get(route('dossiers.show', $dossier))->assertInertia(fn ($page) => $page->where('recipients.0.canRemind', true));
        $this->post(route('reminders.store', $treatment))->assertRedirect();

        $reminder = $treatment->reminders()->sole();
        $this->assertSame(ReminderReason::Manual, $reminder->reason);
        $this->assertSame("Rappel concernant le dossier {$dossier->reference}", $reminder->message);
        $this->assertSame("Rappel concernant le dossier {$dossier->reference}", $dossier->activityLogs()->where('action', 'reminder.sent')->sole()->comment);
        Notification::assertSentTo($recipient, DossierNotification::class, fn ($n) => $n->type === 'reminder' && str_contains($n->message, $dossier->reference));
    }

    public function test_reminder_is_refused_to_outsiders_and_on_closed_treatments(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($recipient)->post(route('reminders.store', $treatment))->assertForbidden();
        $this->actingAs($this->makeUser(Role::STAFF, 'TECHNIQUE'))->post(route('reminders.store', $treatment))->assertForbidden();

        $this->actingAs($recipient)->post(route('treatments.respond', $treatment), ['content' => 'Réglé']);
        $this->actingAs($sender)->post(route('reminders.store', $treatment))->assertForbidden();
    }

    public function test_deadline_command_alerts_once_on_the_day_then_once_when_overdue_without_changing_status(): void
    {
        Notification::fake();
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient], ['due_at' => now()->addDays(2)->toDateString()]);
        $this->actingAs($recipient)->get(route('dossiers.show', $dossier));
        auth()->logout();

        $this->travelTo(now()->addDays(2)->setTime(8, 0));
        $this->artisan('dossiers:check-deadlines')->expectsOutputToContain('Échéances du jour : 1')->assertSuccessful();
        $this->artisan('dossiers:check-deadlines')->expectsOutputToContain('Échéances du jour : 0')->assertSuccessful();

        Notification::assertSentToTimes($recipient, DossierNotification::class, 2);
        Notification::assertNotSentTo($sender, DossierNotification::class, fn ($n) => $n->type === 'deadline_today');

        $this->travel(1)->days();
        $this->artisan('dossiers:check-deadlines')->expectsOutputToContain('Délais dépassés : 1');
        $this->artisan('dossiers:check-deadlines')->expectsOutputToContain('Délais dépassés : 0');

        Notification::assertSentTo($recipient, DossierNotification::class, fn ($n) => $n->type === 'deadline_overdue');
        Notification::assertSentTo($sender, DossierNotification::class, fn ($n) => $n->type === 'deadline_overdue');

        $this->assertSame(RecipientStatus::InProgress, $dossier->recipients()->sole()->status);
        $this->assertSame(1, $dossier->activityLogs()->where('action', 'deadline.overdue')->count());
        $this->assertNull($dossier->activityLogs()->where('action', 'deadline.overdue')->sole()->user_id);

        // Bandeau « Statut : En cours — Alerte : délai dépassé. » affiché par la page React.
        $this->actingAs($sender)->get(route('dossiers.show', $dossier))
            ->assertInertia(fn ($page) => $page->where('dossier.status.label', 'En cours')->where('dossier.deadline.overdue', true))
            ->assertPropsSee('Délai dépassé de 1 jour');
        $this->assertStringContainsString('Statut : {dossier.status.label} — Alerte : délai dépassé.', (string) file_get_contents(resource_path('js/components/dossier/Summary.jsx')));
    }

    public function test_external_overdue_alerts_the_recipient_and_hr(): void
    {
        Notification::fake();
        $hr = User::query()->firstWhere('username', 'rh');
        $manager = $this->makeUser(Role::DEPARTMENT_HEAD, 'RSE');
        Department::query()->where('code', 'RSE')->update(['manager_id' => $manager->id]);

        $this->actingAs($hr->forceFill(['must_change_password' => false]))->post(route('external-requests.store'), [
            'requester_name' => 'Rabe Soa',
            'requester_organization' => 'Pêcheurs de Mahajanga',
            'requester_phone' => '034 00 000 00',
            'requester_email' => 'rabe@example.com',
            'description' => 'Livraison incomplète.',
            'request_type_id' => RequestType::query()->where('code', 'RECLAMATION')->value('id'),
            'subject' => 'Réclamation livraison',
            'received_at' => now()->toDateString(),
            'reception_channel' => 'phone',
            'urgency_level_id' => UrgencyLevel::query()->where('code', 'TRES_URGENT')->value('id'),
            'recipients' => [$manager->id],
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->travel(25)->hours();
        $this->artisan('dossiers:check-deadlines')->assertSuccessful();

        Notification::assertSentTo($manager, DossierNotification::class, fn ($n) => $n->type === 'deadline_overdue');
        Notification::assertSentTo($hr, DossierNotification::class, fn ($n) => $n->type === 'deadline_overdue');
    }

    public function test_overdue_request_emails_the_recipient_and_the_sender_once(): void
    {
        Notification::fake();
        $hr = User::query()->firstWhere('username', 'rh');
        $hr->forceFill(['must_change_password' => false, 'email' => 'rh@sogediproma.mg'])->save();
        $collecteHead = $this->makeUser(Role::DEPARTMENT_HEAD, 'COLLECTE', ['email' => 'collecte@sogediproma.mg']);
        $withoutEmail = $this->makeUser(Role::STAFF, 'COLLECTE', ['email' => null]);

        $this->actingAs($hr)->post(route('external-requests.store'), [
            'requester_name' => 'Rabe Soa',
            'requester_organization' => 'Pêcheurs de Mahajanga',
            'requester_phone' => '034 00 000 00',
            'requester_email' => 'rabe@example.com',
            'description' => 'Livraison incomplète.',
            'request_type_id' => RequestType::query()->where('code', 'RECLAMATION')->value('id'),
            'subject' => 'Réclamation livraison',
            'received_at' => now()->toDateString(),
            'reception_channel' => 'phone',
            'urgency_level_id' => UrgencyLevel::query()->where('code', 'TRES_URGENT')->value('id'),
            'recipients' => [$collecteHead->id, $withoutEmail->id],
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->travel(23)->hours();
        $this->artisan('dossiers:check-deadlines')->assertSuccessful();
        Notification::assertNotSentTo($collecteHead, DeadlineOverdueAlert::class);

        $this->travel(2)->hours();
        $this->artisan('dossiers:check-deadlines')->assertSuccessful();
        $this->artisan('dossiers:check-deadlines')->assertSuccessful();

        Notification::assertSentToTimes($collecteHead, DeadlineOverdueAlert::class, 1);
        // L'expéditeur reçoit un e-mail par destinataire en retard (ici deux).
        Notification::assertSentToTimes($hr, DeadlineOverdueAlert::class, 2);
        Notification::assertNotSentTo($withoutEmail, DeadlineOverdueAlert::class);
        Notification::assertSentTo($withoutEmail, DossierNotification::class, fn ($n) => $n->type === 'deadline_overdue');

        $treatment = DossierRecipient::query()->where('assigned_user_id', $collecteHead->id)->sole();
        $toRecipient = (string) (new DeadlineOverdueAlert($treatment))->toMail($collecteHead)->render();
        $toSender = (string) (new DeadlineOverdueAlert($treatment))->toMail($hr)->render();

        $this->assertStringContainsString('délai de réponse recommandé par le demandeur', $toRecipient);
        $this->assertStringContainsString('Vous n’avez pas encore répondu', $toRecipient);
        $this->assertStringContainsString($collecteHead->name.' n’a pas encore répondu', $toSender);
        $this->assertStringContainsString($treatment->dossier->reference, $toSender);
        $this->assertStringContainsString(route('dossiers.show', $treatment->dossier), $toSender);
    }

    public function test_deadline_check_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command);

        $this->assertTrue($events->contains(fn ($command) => str_contains((string) $command, 'dossiers:check-deadlines')));
    }

    public function test_statistics_by_role(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);

        $this->actingAs($this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE'))->get(route('statistics.index'))
            ->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Statistics')
                ->where('scopeLabel', 'Technique')
                ->where('kpis.dossiers', 1)
                ->where('departments', []));
        $this->actingAs($this->makeUser(Role::ADMIN, 'ADMINISTRATION'))->get(route('statistics.index'))
            ->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Statistics')
                ->where('scopeLabel', 'Tous les départements')
                ->has('departments', \App\Models\Department::query()->count()));
        $this->actingAs($sender)->get(route('statistics.index'))->assertForbidden();
    }
}
