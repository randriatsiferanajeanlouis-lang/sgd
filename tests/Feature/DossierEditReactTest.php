<?php

namespace Tests\Feature;

use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * « Modifier un dossier » en React : réservé à l'expéditeur, valeurs
 * actuelles préremplies, échéance calculée en lecture seule pour une
 * demande externe, modification tracée.
 */
class DossierEditReactTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_the_sender_gets_the_react_form_with_the_current_values(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], ['subject' => 'Objet initial']);

        $this->actingAs($sender)->get(route('dossiers.edit', $dossier))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dossiers/Edit')
            ->where('title', 'Modifier '.$dossier->reference)
            ->where('dossier.subject', 'Objet initial')
            ->where('dossier.external', false)
            ->where('dossier.request_type_id', $dossier->request_type_id)
            ->where('dossier.due_at', $dossier->due_at->toDateString())
            ->where('otherTypeId', RequestType::otherId())
            ->where('urls.update', route('dossiers.update', $dossier))
            ->where('urls.cancel', route('dossiers.show', $dossier)));

        $this->assertFalse(view()->exists('dossiers.edit'));
    }

    public function test_saving_from_the_react_page_traces_the_change_and_returns_to_the_dossier(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], ['subject' => 'Objet initial']);

        $this->actingAs($sender)->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('dossiers.update', $dossier), [
                'subject' => 'Objet corrigé',
                'request_type_id' => $dossier->request_type_id,
                'urgency_level_id' => '',
                'due_at' => now()->addDays(8)->toDateString(),
                'description' => 'Nouveau détail',
                'comment' => 'Faute de frappe',
                'request_type_other' => '',
            ])
            ->assertRedirect(route('dossiers.show', $dossier))
            ->assertSessionHas('status');

        $this->assertSame('Objet corrigé', $dossier->fresh()->subject);
        $this->assertTrue($dossier->activityLogs()->where('comment', 'Faute de frappe')->exists());
    }

    public function test_a_past_deadline_gets_a_clear_message(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);

        $this->actingAs($sender)->put(route('dossiers.update', $dossier), [
            'subject' => 'Objet', 'request_type_id' => $dossier->request_type_id, 'due_at' => '2020-01-01',
        ])->assertSessionHasErrors(['due_at' => 'La date d’échéance ne peut pas être passée.']);
    }

    public function test_external_request_keeps_its_computed_deadline(): void
    {
        Storage::fake('dossiers');
        $hr = User::query()->firstWhere('username', 'rh');
        $hr->update(['must_change_password' => false]);
        $dossier = Dossier::factory()->external()->create(['created_by' => $hr->id, 'due_at' => now()->addDays(7)]);

        $this->actingAs($hr)->get(route('dossiers.edit', $dossier))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dossier.external', true)
            ->where('dossier.dueLabel', $dossier->due_at->format('d/m/Y H:i')));
    }

    public function test_only_the_sender_can_open_the_page(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);

        $this->actingAs($recipient)->get(route('dossiers.edit', $dossier))->assertForbidden();
        $this->actingAs($this->makeUser(Role::ADMIN, 'ADMINISTRATION'))->get(route('dossiers.edit', $dossier))->assertForbidden();
    }
}
