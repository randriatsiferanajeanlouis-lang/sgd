<?php

namespace Tests\Feature;

use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Enums\RequestScope;
use App\Enums\ResponseDecision;
use App\Enums\TransmissionType;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\UrgencyLevel;
use App\Models\User;
use App\Notifications\DossierNotification;
use App\Services\ExternalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class ExternalRequestTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    private User $hr;

    private User $rseManager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
        $this->hr = User::query()->firstWhere('username', 'rh');
        $this->hr->update(['must_change_password' => false]);
        $this->rseManager = $this->makeUser(Role::DEPARTMENT_HEAD, 'RSE', ['first_name' => 'Hanta', 'last_name' => 'Rasoa']);
        Department::query()->where('code', 'RSE')->update(['manager_id' => $this->rseManager->id]);
    }

    public function test_initial_external_request_types_are_available(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Demande d’emploi', 'Demande de stage', 'Demande d’information', 'Demande administrative', 'Sponsoring', 'Intervention', 'Réclamation', 'Autre'],
            RequestType::query()->active()->availableFor(RequestScope::External)->pluck('name')->all(),
        );
    }

    public function test_due_dates_follow_urgency_levels_in_calendar_days(): void
    {
        Carbon::setTestNow('2026-10-05 09:30:00');
        $received = Carbon::parse('2026-10-05');

        $this->assertSame('2026-10-06 09:30', ExternalRequestService::dueDate($this->urgency('TRES_URGENT'), $received)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-08 09:30', ExternalRequestService::dueDate($this->urgency('URGENT'), $received)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 09:30', ExternalRequestService::dueDate($this->urgency('NORMAL'), $received)->format('Y-m-d H:i'));

        Carbon::setTestNow();
    }

    public function test_complete_hr_scenario_from_registration_to_archiving(): void
    {
        Notification::fake();

        // RH enregistre une demande de stage avec CV, urgence normale, pour le responsable RSE.
        $this->actingAs($this->hr)->get(route('external-requests.create'))->assertOk()->assertSee('Date d’échéance (calculée)');
        $dossier = $this->registerStageRequest([$this->rseManager]);

        $this->assertMatchesRegularExpression('/^DOS-\d{4}-\d{6}$/', $dossier->reference);
        $this->assertTrue($dossier->isExternal());
        $this->assertSame('Rakotobe Fara', $dossier->externalRequester->full_name);
        $this->assertSame(now()->addDays(7)->format('Y-m-d H:i'), $dossier->due_at->format('Y-m-d H:i'));
        $this->assertSame('cv.pdf', $dossier->documents()->sole()->original_name);
        Notification::assertSentTo($this->rseManager, DossierNotification::class, fn ($n) => $n->type === 'dossier_received');

        // Le responsable ouvre : En cours, puis décision positive.
        $treatment = $dossier->recipients()->sole();
        $this->actingAs($this->rseManager)->get(route('dossiers.show', $dossier))->assertSee('Envoyer la décision au RH');
        $this->assertSame(RecipientStatus::InProgress, $treatment->fresh()->status);

        $this->post(route('treatments.respond', $treatment), [
            'decision' => ResponseDecision::Positive->value,
            'content' => 'Profil intéressant.',
            'justification' => 'Formation adaptée au service.',
            'recommendation' => 'Stage de 3 mois à partir de novembre.',
        ])->assertRedirect();

        $this->assertSame(RecipientStatus::Answered, $treatment->fresh()->status);
        $this->assertSame(ResponseDecision::Positive, $dossier->responses()->sole()->decision);
        $this->assertTrue($dossier->transmissions()->where('type', TransmissionType::Return->value)->exists());
        Notification::assertSentTo($this->hr, DossierNotification::class, fn ($n) => $n->type === 'response_received' && str_contains($n->message, 'Positive'));

        // Le responsable ne peut ni archiver ni répondre officiellement.
        $this->post(route('treatments.archive', $treatment))->assertForbidden();
        $this->post(route('external-requests.respond', $dossier), ['content' => 'x', 'channel' => 'email', 'sent_at' => now()->toDateString()])->assertForbidden();

        // Retour RH : réponse officielle puis archivage.
        $this->actingAs($this->hr)->get(route('dossiers.show', $dossier))->assertSee('Profil intéressant.')->assertSee('1 décision(s) positive(s)');
        $this->post(route('external-requests.respond', $dossier), [
            'content' => 'Votre demande de stage est acceptée.',
            'channel' => 'email',
            'sent_at' => now()->toDateString(),
            'document' => $this->pdf('lettre-acceptation.pdf'),
        ])->assertRedirect();

        $dossier->refresh();
        $this->assertSame(DossierStatus::Answered, $dossier->status);
        $official = $dossier->responses()->where('is_final', true)->sole();
        $this->assertSame('Votre demande de stage est acceptée.', $official->content);
        $this->assertSame('lettre-acceptation.pdf', $official->documents()->sole()->original_name);
        Notification::assertSentTo($this->rseManager, DossierNotification::class, fn ($n) => $n->type === 'official_response');

        $this->post(route('external-requests.respond', $dossier), ['content' => 'bis', 'channel' => 'email', 'sent_at' => now()->toDateString()])->assertForbidden();

        $this->post(route('external-requests.archive', $dossier))->assertRedirect();
        $this->assertSame(DossierStatus::Archived, $dossier->fresh()->status);
        $this->assertSame(RecipientStatus::Archived, $treatment->fresh()->status);

        $this->assertSame(
            ['external.registered', 'document.added', 'recipient.received', 'dossier.sent', 'recipient.opened', 'dossier.status_changed',
                'recipient.answered', 'transmission.sent', 'document.added', 'official_response.recorded', 'recipient.archived', 'dossier.archived'],
            $dossier->activityLogs()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_diverging_decisions_are_all_kept(): void
    {
        $production = $this->makeUser(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        $dossier = $this->registerStageRequest([$this->rseManager, $production]);

        $this->actingAs($this->rseManager)->post(route('treatments.respond', $dossier->recipients()->where('assigned_user_id', $this->rseManager->id)->sole()), [
            'decision' => 'positive', 'content' => 'Favorable.',
        ]);
        $this->actingAs($production)->post(route('treatments.respond', $dossier->recipients()->where('assigned_user_id', $production->id)->sole()), [
            'decision' => 'negative', 'content' => 'Pas de place.',
        ]);

        $this->assertEqualsCanonicalizing([ResponseDecision::Positive, ResponseDecision::Negative], $dossier->responses()->pluck('decision')->all());
        $this->actingAs($this->hr)->get(route('dossiers.show', $dossier))
            ->assertSee('Favorable.')
            ->assertSee('Pas de place.')
            ->assertSee('1 décision(s) positive(s)')
            ->assertSee('1 décision(s) négative(s)');
    }

    public function test_external_decision_is_mandatory(): void
    {
        $dossier = $this->registerStageRequest([$this->rseManager]);

        $this->actingAs($this->rseManager)
            ->post(route('treatments.respond', $dossier->recipients()->sole()), ['content' => 'Sans décision'])
            ->assertSessionHasErrors('decision');
    }

    public function test_official_response_requires_a_decision_first(): void
    {
        $dossier = $this->registerStageRequest([$this->rseManager]);

        $this->actingAs($this->hr)
            ->post(route('external-requests.respond', $dossier), ['content' => 'Trop tôt', 'channel' => 'email', 'sent_at' => now()->toDateString()])
            ->assertForbidden();
    }

    public function test_only_hr_registers_external_requests(): void
    {
        foreach ([$this->makeUser(Role::STAFF, 'RSE'), $this->rseManager, $this->makeUser(Role::ADMIN, 'ADMINISTRATION')] as $user) {
            $this->actingAs($user)->get(route('external-requests.create'))->assertForbidden();
            $this->post(route('external-requests.store'), $this->stagePayload([$this->rseManager]))->assertForbidden();
        }

        $this->assertSame(0, Dossier::query()->count());
    }

    public function test_hr_lists_external_requests_and_employees_cannot(): void
    {
        $dossier = $this->registerStageRequest([$this->rseManager]);

        $this->actingAs($this->hr)->get(route('external-requests.index', ['search' => 'Rakotobe']))->assertOk()->assertSee($dossier->reference);
        $this->actingAs($this->makeUser(Role::STAFF))->get(route('external-requests.index'))->assertForbidden();
    }

    public function test_form_is_split_into_two_steps(): void
    {
        $this->actingAs($this->hr)->get(route('external-requests.create'))
            ->assertOk()
            ->assertSeeInOrder(['Demande et demandeur', 'Réception, transmission et description', 'Enregistrer et transmettre'])
            ->assertSee('data-step="1"', false)
            ->assertSee('data-step="2"', false)
            ->assertSee('formColumns(', false)
            ->assertSee('xl:grid-cols-2', false)
            ->assertDontSee('id="modal-form"', false);

        // Le bouton « Nouvelle demande externe » mène à cette page.
        $this->get(route('external-requests.index'))->assertSee(route('external-requests.create'));
    }

    public function test_sponsoring_requires_every_field_including_the_organisation(): void
    {
        $sponsoring = RequestType::query()->where('code', 'SPONSORING')->value('id');
        $payload = [
            ...$this->stagePayload([$this->rseManager]),
            'request_type_id' => $sponsoring,
            'requester_organization' => '',
            'requester_phone' => '',
            'requester_email' => '',
            'description' => '',
        ];

        $this->actingAs($this->hr)->post(route('external-requests.store'), $payload)
            ->assertSessionHasErrors([
                'requester_organization' => 'L’organisme est obligatoire pour ce type de demande.',
                'requester_phone', 'requester_email', 'description',
            ]);

        // Les autres types gardent ces champs facultatifs.
        $this->post(route('external-requests.store'), [...$payload, 'request_type_id' => RequestType::query()->where('code', 'STAGE')->value('id')])
            ->assertSessionHasNoErrors();
    }

    public function test_every_type_except_internship_requires_a_complete_form(): void
    {
        $this->assertSame(
            ['STAGE'],
            RequestType::query()->where('requires_complete_form', false)->pluck('code')->all(),
        );

        $this->actingAs($this->makeUser(Role::ADMIN, 'ADMINISTRATION'))
            ->post(route('admin.settings.request-types.store'), ['name' => 'Partenariat local', 'scope' => 'external']);
        $this->assertTrue(RequestType::query()->firstWhere('name', 'Partenariat local')->requires_complete_form);
    }

    public function test_complete_form_can_be_switched_on_for_any_type_from_the_settings(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $stage = RequestType::query()->firstWhere('code', 'STAGE');

        $this->actingAs($admin)->put(route('admin.settings.request-types.update', $stage), [
            'name' => $stage->name, 'scope' => $stage->scope->value, 'requires_complete_form' => '1', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($stage->fresh()->requires_complete_form);
        $this->actingAs($this->hr)->post(route('external-requests.store'), [...$this->stagePayload([$this->rseManager]), 'requester_organization' => ''])
            ->assertSessionHasErrors('requester_organization');
    }

    /**
     * @param  list<User>  $recipients
     */
    private function registerStageRequest(array $recipients): Dossier
    {
        $this->actingAs($this->hr)->post(route('external-requests.store'), $this->stagePayload($recipients))->assertSessionHasNoErrors();

        return Dossier::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  list<User>  $recipients
     * @return array<string, mixed>
     */
    public function test_external_request_is_confirmed_and_never_registered_twice(): void
    {
        $this->actingAs($this->hr)->get(route('external-requests.create'))
            ->assertSee('Êtes-vous sûr de vouloir transmettre cette demande externe à')
            ->assertSee('name="_submission"', false);

        $payload = [...$this->stagePayload([$this->rseManager]), '_submission' => (string) \Illuminate\Support\Str::uuid()];
        $this->post(route('external-requests.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('external-requests.store'), $payload)->assertRedirect(route('external-requests.index'))->assertSessionHas('warning');

        $this->assertSame(1, Dossier::query()->count());
        $this->post(route('external-requests.store'), [...$payload, 'recipients' => [], '_submission' => (string) \Illuminate\Support\Str::uuid()])
            ->assertSessionHasErrors('recipients');
    }

    private function stagePayload(array $recipients): array
    {
        return [
            'requester_name' => 'Rakotobe Fara',
            'requester_organization' => 'Université d’Antananarivo',
            'requester_phone' => '034 12 345 67',
            'requester_email' => 'fara@example.com',
            'request_type_id' => RequestType::query()->where('code', 'STAGE')->value('id'),
            'subject' => 'Demande de stage en gestion',
            'received_at' => now()->toDateString(),
            'reception_channel' => 'email',
            'urgency_level_id' => $this->urgency('NORMAL')->id,
            'description' => 'Étudiante en master, disponible dès novembre.',
            'recipients' => array_map(fn (User $user) => $user->id, $recipients),
            'attachments' => [$this->pdf('cv.pdf')],
        ];
    }

    private function urgency(string $code): UrgencyLevel
    {
        return UrgencyLevel::query()->where('code', $code)->firstOrFail();
    }
}
