<?php

namespace Tests\Feature;

use App\Enums\RequestScope;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\UrgencyLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class SendConfirmationTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
    }

    public function test_every_send_entry_point_asks_for_confirmation(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        Department::query()->where('code', 'TECHNIQUE')->update(['manager_id' => $head->id]);

        // Nouvelle demande : fenêtre de confirmation et jeton anti double envoi.
        $this->actingAs($sender)->get(route('dossiers.create'))->assertOk()
            ->assertSee('Confirmer l’envoi')
            ->assertSee('Êtes-vous sûr de vouloir envoyer cette demande à')
            ->assertSee('name="_submission"', false)
            ->assertSee('Aucun destinataire sélectionné');

        // Fiche du dossier : transmission à un autre utilisateur et au responsable.
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $this->actingAs($recipient)->get(route('dossiers.show', $dossier))->assertOk()
            ->assertInertia(fn ($page) => $page->where('myTreatment.canForward', true));
        $treatment = (string) file_get_contents(resource_path('js/components/dossier/MyTreatment.jsx'));
        $this->assertStringContainsString('Êtes-vous sûr de vouloir transmettre ce dossier à', $treatment);
        $this->assertStringContainsString('Êtes-vous sûr de vouloir transmettre ce dossier pour validation à', $treatment);
        $this->assertStringContainsString('<ConfirmDialog', $treatment);
    }

    public function test_the_server_refuses_a_request_without_a_valid_recipient(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $inactive = $this->makeUser(Role::STAFF, 'TECHNIQUE', ['is_active' => false]);

        foreach ([[], [999999], [$inactive->id], [$sender->id]] as $recipients) {
            $this->actingAs($sender)->post(route('dossiers.store'), $this->payload($recipients))->assertSessionHasErrors();
        }

        $this->assertSame(0, Dossier::query()->count());
    }

    public function test_a_second_send_of_the_same_form_is_ignored(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $payload = [...$this->payload([$recipient->id]), '_submission' => (string) Str::uuid()];

        $this->actingAs($sender)->post(route('dossiers.store'), $payload)->assertRedirect();
        $this->post(route('dossiers.store'), $payload)
            ->assertRedirect(route('requests.index'))
            ->assertSessionHas('warning', 'Cette demande a déjà été envoyée : le second envoi a été ignoré.');

        $this->assertSame(1, Dossier::query()->count());
        $this->assertSame(1, $recipient->notifications()->count());

        // Un nouveau formulaire (nouveau jeton) reste envoyable normalement.
        $this->post(route('dossiers.store'), [...$payload, '_submission' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(2, Dossier::query()->count());
    }

    public function test_a_form_sent_back_with_errors_keeps_its_token_and_can_be_resent(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $token = (string) Str::uuid();

        $this->actingAs($sender)->post(route('dossiers.store'), [...$this->payload([]), '_submission' => $token])->assertSessionHasErrors('recipients');
        $this->post(route('dossiers.store'), [...$this->payload([$recipient->id]), '_submission' => $token])->assertSessionHasNoErrors();

        $this->assertSame(1, Dossier::query()->count());
    }

    public function test_forwarding_requires_a_valid_recipient(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($recipient)->post(route('treatments.forward', $treatment), ['user_id' => ''])->assertSessionHasErrors('user_id');
        $this->post(route('treatments.forward', $treatment), ['user_id' => 999999])->assertSessionHasErrors('user_id');

        $this->assertSame(1, $dossier->recipients()->count());
    }

    /**
     * @param  list<int>  $recipients
     * @return array<string, mixed>
     */
    private function payload(array $recipients): array
    {
        return [
            'subject' => 'Demande de fournitures',
            'request_type_id' => RequestType::query()->availableFor(RequestScope::Internal)->where('code', '!=', RequestType::OTHER_CODE)->value('id'),
            'urgency_level_id' => UrgencyLevel::query()->value('id'),
            'due_at' => now()->addDays(5)->toDateString(),
            'description' => 'Merci.',
            'recipients' => $recipients,
        ];
    }
}
