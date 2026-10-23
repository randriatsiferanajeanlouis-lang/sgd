<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Page « Validations » en React : demandes en attente du responsable
 * connecté puis décisions prises ; réservée aux responsables.
 */
class ValidationsListReactTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_pending_then_decided_validations_of_the_connected_manager(): void
    {
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        Department::query()->where('code', 'TECHNIQUE')->update(['manager_id' => $head->id]);
        $employee = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($this->makeUser(Role::STAFF, 'PRODUCTION'), [$employee], ['subject' => 'Achat de matériel']);
        $this->actingAs($employee)->post(route('validations.store', $dossier->recipients()->sole()), ['comment' => str_repeat('Long commentaire. ', 20)]);

        $this->actingAs($head)->get(route('validations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Validations/Index')
            ->where('title', 'Validations')
            ->has('pending', 1)
            ->where('pending.0.dossier.subject', 'Achat de matériel')
            ->where('pending.0.requester', $employee->name)
            ->where('pending.0.comment', fn ($comment) => mb_strlen($comment) <= 123)
            ->has('pending.0.deadline.label')
            ->has('decided.data', 0));

        $validation = $dossier->validations()->sole();
        $this->post(route('validations.decide', $validation), ['decision' => 'approve'])->assertRedirect(route('dossiers.show', $dossier));

        $this->get(route('validations.index'))->assertInertia(fn (Assert $page) => $page
            ->has('pending', 0)
            ->has('decided.data', 1)
            ->where('decided.data.0.status.label', 'Validé')
            ->where('decided.data.0.dossier.url', route('dossiers.show', $dossier)));

        $this->assertFalse(view()->exists('validations.index'));
    }

    public function test_only_managers_can_open_the_page(): void
    {
        $this->actingAs($this->makeUser(Role::STAFF, 'TECHNIQUE'))->get(route('validations.index'))->assertForbidden();
    }
}
