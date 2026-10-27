<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Enums\ValidationStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Batterie globale demandée après la phase 12 : scénarios réels de bout en
 * bout, notifications réellement enregistrées en base.
 */
class GlobalScenariosTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
    }

    public function test_1_employee_production_to_employee_technique(): void
    {
        $production = $this->makeUser(Role::STAFF, 'PRODUCTION', ['username' => 'prod.agent', 'password' => 'Secret2026']);
        $technique = $this->makeUser(Role::STAFF, 'TECHNIQUE');

        $this->post(route('login.store'), ['username' => 'prod.agent', 'password' => 'Secret2026'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertSee(route('requests.index').'"', false)->assertSee('Corbeille')->assertDontSee('Voir tous les dossiers');
        $dossier = $this->sendInternalRequest($production, [$technique], ['attachments' => [$this->pdf('bon.pdf')]]);

        $this->assertSame(1, $technique->unreadNotifications()->count());
        $notificationId = $technique->notifications()->first()->id;
        $this->actingAs($technique)->get(route('notifications.open', $notificationId))->assertRedirect(route('dossiers.show', $dossier, false));
        $this->followingRedirects()->get(route('notifications.open', $notificationId))->assertOk()->assertSee($dossier->reference);
        $this->assertSame(RecipientStatus::InProgress, $dossier->recipients()->sole()->status);
        $this->assertSame(0, $technique->unreadNotifications()->count());
        $this->get(route('documents.download', $dossier->documents()->sole()))->assertOk();

        $treatment = $dossier->recipients()->sole();
        $this->post(route('treatments.respond', $treatment), ['content' => 'Intervention planifiée.']);
        $this->post(route('treatments.archive', $treatment));

        $this->assertSame(RecipientStatus::Archived, $treatment->fresh()->status);
        $this->assertSame(DossierStatus::Archived, $dossier->fresh()->status);
        $this->assertSame(1, $production->unreadNotifications()->where('data->type', 'response_received')->count());
        $this->assertEqualsCanonicalizing(
            ['dossier.created', 'document.added', 'recipient.received', 'dossier.sent', 'recipient.opened', 'dossier.status_changed',
                'recipient.answered', 'dossier.status_changed', 'recipient.archived', 'dossier.status_changed'],
            $dossier->activityLogs()->pluck('action')->all(),
        );
        $this->actingAs($this->makeUser(Role::STAFF, 'TECHNIQUE'))->get(route('dossiers.show', $dossier))->assertForbidden();
    }

    public function test_2_hierarchical_validation_chain(): void
    {
        $employeeA = $this->makeUser(Role::STAFF, 'COLLECTE');
        $employeeB = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $managerB = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        Department::query()->where('code', 'TECHNIQUE')->update(['manager_id' => $managerB->id]);

        $dossier = $this->sendInternalRequest($employeeA, [$employeeB]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($employeeB)->post(route('validations.store', $treatment), ['comment' => 'Validation requise']);
        $this->actingAs($managerB)->post(route('validations.decide', $dossier->validations()->sole()), ['decision' => 'approve']);
        $this->actingAs($employeeB)->post(route('treatments.respond', $treatment), ['content' => 'Accordé après validation.']);

        $this->assertSame(ValidationStatus::Approved, $dossier->validations()->sole()->status);
        $this->assertSame(RecipientStatus::Answered, $treatment->fresh()->status);
        $this->actingAs($employeeA)->get(route('dossiers.show', $dossier))->assertPropsSee('Accordé après validation.');
        $this->assertSame(1, $employeeA->notifications()->where('data->type', 'response_received')->count());
    }

    public function test_3_hr_stage_request_complete_circuit(): void
    {
        $hr = User::query()->firstWhere('username', 'rh');
        $hr->update(['must_change_password' => false]);
        $manager = $this->makeUser(Role::DEPARTMENT_HEAD, 'RSE');

        $this->actingAs($hr)->post(route('external-requests.store'), [
            'requester_name' => 'Randria Mialy',
            'request_type_id' => RequestType::query()->where('code', 'STAGE')->value('id'),
            'subject' => 'Demande de stage',
            'received_at' => now()->toDateString(),
            'reception_channel' => 'mail',
            'urgency_level_id' => UrgencyLevel::query()->where('code', 'NORMAL')->value('id'),
            'recipients' => [$manager->id],
            'attachments' => [$this->pdf('cv.pdf')],
        ])->assertSessionHasNoErrors();
        $dossier = Dossier::query()->sole();
        $this->assertMatchesRegularExpression('/^DOS-\d{4}-\d{6}$/', $dossier->reference);

        $this->actingAs($manager)->get(route('dossiers.show', $dossier));
        $this->post(route('treatments.respond', $dossier->recipients()->sole()), ['decision' => 'positive', 'content' => 'Favorable.']);
        $this->assertSame(1, $hr->notifications()->where('data->type', 'response_received')->count());

        $this->actingAs($hr)->post(route('external-requests.respond', $dossier), ['content' => 'Stage accepté.', 'channel' => 'mail', 'sent_at' => now()->toDateString()]);
        $this->assertSame(DossierStatus::Answered, $dossier->fresh()->status);
        $this->post(route('external-requests.archive', $dossier));
        $this->assertSame(DossierStatus::Archived, $dossier->fresh()->status);
    }

    public function test_4_multi_recipient_states_stay_independent(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'ARMEMENT');
        $production = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $rse = $this->makeUser(Role::STAFF, 'RSE');
        $administration = $this->makeUser(Role::STAFF, 'ADMINISTRATION');
        $dossier = $this->sendInternalRequest($sender, [$production, $rse, $administration]);

        $this->actingAs($production)->post(route('treatments.respond', $this->treatmentOf($dossier, $production)), ['content' => 'OK']);
        $this->post(route('treatments.archive', $this->treatmentOf($dossier, $production)));
        $this->actingAs($rse)->get(route('dossiers.show', $dossier));
        $this->actingAs($administration)->post(route('treatments.respond', $this->treatmentOf($dossier, $administration)), ['content' => 'Vu']);

        $this->assertSame(RecipientStatus::Archived, $this->treatmentOf($dossier, $production)->status);
        $this->assertSame(RecipientStatus::InProgress, $this->treatmentOf($dossier, $rse)->status);
        $this->assertSame(RecipientStatus::Answered, $this->treatmentOf($dossier, $administration)->status);
    }

    public function test_5_document_versions(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'RSE')], ['attachments' => [$this->pdf('devis.pdf')]]);
        $v1 = $dossier->documents()->sole();

        $this->actingAs($sender)->post(route('documents.replace', $v1), ['file' => $this->pdf('devis-corrige.pdf')]);
        $v2 = Document::query()->where('document_key', $v1->document_key)->where('version', 2)->sole();

        $this->assertSame(DocumentStatus::Replaced, $v1->fresh()->status);
        $this->assertSame(DocumentStatus::Active, $v2->status);
        $this->assertTrue($dossier->activityLogs()->where('action', 'document.replaced')->exists());
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $this->actingAs($admin)->get(route('documents.download', $v1))->assertOk(); // l'administrateur lit l'historique de tous les dossiers
        $this->actingAs($this->makeUser(Role::STAFF, 'COLLECTE'))->get(route('documents.download', $v1))->assertForbidden();
        $this->actingAs($admin)->get(route('documents.download', $v2))->assertOk();
    }

    public function test_6_employee_restrictions(): void
    {
        $employee = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $colleague = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $foreign = $this->sendInternalRequest($colleague, [$this->makeUser(Role::STAFF, 'RSE')], ['attachments' => [$this->pdf()]]);

        $this->actingAs($employee)->get(route('dossiers.show', $foreign))->assertForbidden();
        $this->get(route('external-requests.create'))->assertForbidden();
        $this->get(route('documents.download', $foreign->documents()->sole()))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('dossiers.history.pdf', $foreign))->assertForbidden();
    }

    public function test_7_department_head_scope_validation_and_deactivation(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'PRODUCTION');
        Department::query()->where('code', 'PRODUCTION')->update(['manager_id' => $head->id]);
        $employee = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $ownDept = $this->sendInternalRequest($this->makeUser(Role::STAFF, 'RSE'), [$employee]);
        $otherDept = $this->sendInternalRequest($this->makeUser(Role::STAFF, 'RSE'), [$this->makeUser(Role::STAFF, 'COLLECTE')], ['subject' => 'Autre']);

        $this->actingAs($employee)->post(route('validations.store', $ownDept->recipients()->sole()));

        $this->flushSession();
        $this->actingAs($head)->get(route('dossiers.show', $ownDept))->assertOk();
        $this->get(route('dossiers.show', $otherDept))->assertForbidden();
        $this->post(route('validations.decide', $ownDept->validations()->sole()), ['decision' => 'reject', 'comment' => 'Non'])->assertRedirect();

        $this->post(route('users.deactivate', $employee))->assertRedirect();
        $this->assertFalse($employee->fresh()->is_active);
        $this->assertSame(1, $admin->notifications()->where('data->type', 'user_deactivated')->count());
    }

    public function test_8_notifications_for_each_event(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'COLLECTE');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $manager = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        Department::query()->where('code', 'TECHNIQUE')->update(['manager_id' => $manager->id]);

        $dossier = $this->sendInternalRequest($sender, [$recipient], ['due_at' => now()->addDay()->toDateString()]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($sender)->post(route('reminders.store', $treatment));
        $this->actingAs($recipient)->post(route('validations.store', $treatment));
        $this->actingAs($manager)->post(route('validations.decide', $dossier->validations()->sole()), ['decision' => 'approve']);
        auth()->logout();

        $this->travelTo(now()->addDay()->setTime(9, 0));
        $this->artisan('dossiers:check-deadlines');
        $this->travel(1)->days();
        $this->artisan('dossiers:check-deadlines');

        $this->actingAs($recipient)->post(route('treatments.respond', $treatment), ['content' => 'Terminé']);

        $this->assertEqualsCanonicalizing(
            ['dossier_received', 'reminder', 'validation_approved', 'deadline_today', 'deadline_overdue'],
            $recipient->notifications()->pluck('data')->pluck('type')->all(),
        );
        $this->assertSame(['validation_requested'], $manager->notifications()->pluck('data')->pluck('type')->all());
        $this->assertEqualsCanonicalizing(['deadline_overdue', 'response_received'], $sender->notifications()->pluck('data')->pluck('type')->all());

        $this->actingAs($recipient)->get(route('dashboard'))->assertSee('data-testid="unread-count">5<', false);
    }

    private function treatmentOf(Dossier $dossier, User $user): DossierRecipient
    {
        return $dossier->recipients()->where('assigned_user_id', $user->id)->sole();
    }
}
