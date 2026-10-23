<?php

namespace Tests\Feature;

use App\Enums\RecipientStatus;
use App\Enums\TransmissionType;
use App\Enums\ValidationStatus;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DossierNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class ValidationWorkflowTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    private User $employeeA;

    private User $employeeB;

    private User $managerB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
        $this->employeeA = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $this->employeeB = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $this->managerB = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        Department::query()->where('code', 'TECHNIQUE')->update(['manager_id' => $this->managerB->id]);
    }

    public function test_full_chain_employee_to_manager_and_back(): void
    {
        Notification::fake();
        $dossier = $this->sendInternalRequest($this->employeeA, [$this->employeeB], ['attachments' => [$this->pdf('devis.pdf')]]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($this->employeeB)->get(route('dossiers.show', $dossier))->assertInertia(fn ($page) => $page->where('myTreatment.canRequestValidation', true));
        $this->post(route('validations.store', $treatment), ['comment' => 'Montant supérieur à mon plafond.'])->assertRedirect();

        $validation = $dossier->validations()->sole();
        $this->assertSame(ValidationStatus::Pending, $validation->status);
        $this->assertTrue($validation->validator->is($this->managerB));
        $this->assertSame(RecipientStatus::InProgress, $treatment->fresh()->status);
        $this->assertTrue($dossier->transmissions()->where('type', TransmissionType::Validation->value)->exists());
        Notification::assertSentTo($this->managerB, DossierNotification::class, fn ($n) => $n->type === 'validation_requested');

        $this->post(route('treatments.respond', $treatment), ['content' => 'Trop tôt'])->assertForbidden();

        $this->actingAs($this->managerB)->get(route('validations.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Validations/Index')->where('pending.0.dossier.reference', $dossier->reference))
            ->assertPropsSee('Montant supérieur à mon plafond.');
        $this->get(route('dossiers.show', $dossier))
            ->assertOk()
            ->assertPropsSee('Merci de traiter cette demande.', 'devis.pdf', 'Montant supérieur à mon plafond.')
            ->assertInertia(fn ($page) => $page->where('validations.0.canDecide', true));

        $this->post(route('validations.decide', $validation), ['decision' => 'approve', 'comment' => 'Accord budgétaire.'])->assertRedirect();

        $validation->refresh();
        $this->assertSame(ValidationStatus::Approved, $validation->status);
        $this->assertNotNull($validation->decided_at);
        Notification::assertSentTo($this->employeeB, DossierNotification::class, fn ($n) => $n->type === 'validation_approved');

        $this->actingAs($this->employeeB)->post(route('treatments.respond', $treatment), ['content' => 'Validé par le responsable, commande passée.'])->assertRedirect();
        $this->assertSame(RecipientStatus::Answered, $treatment->fresh()->status);
        Notification::assertSentTo($this->employeeA, DossierNotification::class, fn ($n) => $n->type === 'response_received');

        $approved = $dossier->activityLogs()->where('action', 'validation.approved')->sole();
        $this->assertSame('Accord budgétaire.', $approved->comment);
        $this->assertSame($this->managerB->id, $approved->user_id);
        $this->assertSame('Montant supérieur à mon plafond.', $dossier->activityLogs()->where('action', 'validation.requested')->sole()->comment);
    }

    public function test_rejection_returns_the_dossier_to_the_employee_who_can_request_again(): void
    {
        $dossier = $this->sendInternalRequest($this->employeeA, [$this->employeeB]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($this->employeeB)->post(route('validations.store', $treatment));
        $validation = $dossier->validations()->sole();

        $this->actingAs($this->managerB)->post(route('validations.decide', $validation), ['decision' => 'reject', 'comment' => 'Pièces manquantes.']);
        $this->assertSame(ValidationStatus::Rejected, $validation->fresh()->status);
        $this->assertTrue($dossier->activityLogs()->where('action', 'validation.rejected')->exists());

        $this->post(route('validations.decide', $validation), ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($this->employeeB)->post(route('validations.store', $treatment))->assertRedirect();
        $this->assertSame(2, $dossier->validations()->count());
    }

    public function test_only_the_requested_manager_can_decide(): void
    {
        $dossier = $this->sendInternalRequest($this->employeeA, [$this->employeeB]);
        $this->actingAs($this->employeeB)->post(route('validations.store', $dossier->recipients()->sole()));
        $validation = $dossier->validations()->sole();

        $otherManager = $this->makeUser(Role::DEPARTMENT_HEAD, 'RSE');
        $this->actingAs($otherManager)->post(route('validations.decide', $validation), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->employeeB)->post(route('validations.decide', $validation), ['decision' => 'approve'])->assertForbidden();

        $this->assertSame(ValidationStatus::Pending, $validation->fresh()->status);
    }

    public function test_cannot_request_validation_without_a_department_manager(): void
    {
        $orphan = $this->makeUser(Role::STAFF, 'ARMEMENT');
        $dossier = $this->sendInternalRequest($this->employeeA, [$orphan]);

        $this->actingAs($orphan)->get(route('dossiers.show', $dossier))->assertInertia(fn ($page) => $page->where('myTreatment.canRequestValidation', false));
        $this->post(route('validations.store', $dossier->recipients()->sole()))->assertForbidden();
    }

    public function test_employee_has_no_validation_list(): void
    {
        $this->actingAs($this->employeeB)->get(route('validations.index'))->assertForbidden();
    }
}
