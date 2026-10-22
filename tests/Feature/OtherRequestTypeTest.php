<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\UrgencyLevel;
use App\Models\User;
use App\Services\HistoryExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Type « Autre » : l'expéditeur précise lui-même le type, et les destinataires voient ce texte.
 */
class OtherRequestTypeTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
    }

    public function test_choosing_other_requires_a_precision(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');

        $this->actingAs($sender)->post(route('dossiers.store'), $this->payload([$recipient], ['request_type_other' => '   ']))
            ->assertSessionHasErrors(['request_type_other' => 'Précisez le type de votre demande.']);

        $this->assertSame(0, Dossier::query()->count());
    }

    public function test_the_recipient_sees_the_type_typed_by_the_sender(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');

        $dossier = $this->sendInternalRequest($sender, [$recipient], [
            'request_type_id' => RequestType::otherId(),
            'request_type_other' => '  Demande de véhicule  ',
        ]);

        $this->assertSame('Demande de véhicule', $dossier->request_type_other);
        $this->assertSame('Demande de véhicule', $dossier->typeLabel());

        // Liste « Mes dossiers » et fiche du dossier du destinataire
        $this->actingAs($recipient)->get(route('dossiers.index', ['box' => 'received']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dossiers/Index')->where('dossiers.data.0.type', 'Demande de véhicule'));
        $this->get(route('dossiers.show', $dossier))->assertOk()->assertInertia(fn ($page) => $page->where('dossier.type', 'Demande de véhicule'));

        // Historique exporté et recherche
        $this->assertSame('Demande de véhicule', app(HistoryExporter::class)->data($dossier, $recipient)['header']['Type']);
        $found = $this->get(route('dossiers.index', ['box' => 'all', 'search' => 'véhicule']))->inertiaProps('dossiers.data');
        $this->assertSame([$dossier->id], collect($found)->pluck('id')->all());
    }

    public function test_the_precision_is_ignored_for_any_other_type(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $regularType = RequestType::query()->where('code', '!=', RequestType::OTHER_CODE)->where('scope', '!=', 'external')->value('id');

        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], [
            'request_type_id' => $regularType,
            'request_type_other' => 'Texte qui ne doit pas être gardé',
        ]);

        $this->assertNull($dossier->request_type_other);
        $this->assertSame(RequestType::query()->find($regularType)->name, $dossier->typeLabel());
    }

    public function test_the_forms_show_the_precision_field_for_other(): void
    {
        $otherId = RequestType::otherId();
        $sender = $this->makeUser(Role::STAFF);

        $this->actingAs($sender)->get(route('dossiers.create'))->assertOk()
            ->assertSee('data-testid="other-request-type"', false)
            ->assertSee("type === '{$otherId}'", false);

        $hr = User::query()->firstWhere('username', 'rh');
        $hr->forceFill(['must_change_password' => false])->save();
        $this->actingAs($hr)->get(route('external-requests.create'))->assertOk()->assertSee('data-testid="other-request-type"', false);
    }

    public function test_editing_keeps_history_of_the_precision_and_clears_it_when_the_type_changes(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], [
            'request_type_id' => RequestType::otherId(),
            'request_type_other' => 'Demande de véhicule',
        ]);

        $update = fn (array $data) => $this->actingAs($sender)->put(route('dossiers.update', $dossier), [
            'subject' => $dossier->subject,
            'description' => $dossier->description,
            'urgency_level_id' => $dossier->urgency_level_id,
            'due_at' => now()->addDays(6)->toDateString(),
            ...$data,
        ]);

        $update(['request_type_id' => RequestType::otherId(), 'request_type_other' => 'Location de salle'])->assertSessionHasNoErrors();
        $this->assertSame('Location de salle', $dossier->fresh()->request_type_other);

        $log = $dossier->activityLogs()->where('action', 'dossier.updated')->latest('id')->first();
        $this->assertSame('Demande de véhicule', $log->old_values['type']);
        $this->assertSame('Location de salle', $log->new_values['type']);

        $regularType = RequestType::query()->where('code', '!=', RequestType::OTHER_CODE)->where('scope', '!=', 'external')->value('id');
        $update(['request_type_id' => $regularType, 'request_type_other' => 'Location de salle'])->assertSessionHasNoErrors();
        $this->assertNull($dossier->fresh()->request_type_other);
    }

    public function test_hr_can_precise_the_type_of_an_external_request(): void
    {
        $hr = User::query()->firstWhere('username', 'rh');
        $hr->forceFill(['must_change_password' => false])->save();
        $manager = $this->makeUser(Role::DEPARTMENT_HEAD, 'RSE');
        Department::query()->where('code', 'RSE')->update(['manager_id' => $manager->id]);

        $this->actingAs($hr)->post(route('external-requests.store'), [
            'requester_name' => 'Rabe Soa',
            'requester_organization' => 'Association des pêcheurs',
            'requester_phone' => '034 00 000 00',
            'requester_email' => 'rabe@example.com',
            'request_type_id' => RequestType::otherId(),
            'request_type_other' => 'Demande de visite du site',
            'subject' => 'Visite',
            'received_at' => now()->toDateString(),
            'reception_channel' => 'phone',
            'urgency_level_id' => UrgencyLevel::query()->active()->value('id'),
            'description' => 'Visite pour 10 personnes.',
            'recipients' => [$manager->id],
        ])->assertSessionHasNoErrors();

        $dossier = Dossier::query()->latest('id')->firstOrFail();
        $this->assertSame('Demande de visite du site', $dossier->typeLabel());
        $this->actingAs($manager)->get(route('dossiers.show', $dossier))->assertPropsSee('Demande de visite du site');
    }

    /**
     * @param  list<User>  $recipients
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $recipients, array $overrides = []): array
    {
        return [
            'subject' => 'Besoin particulier',
            'request_type_id' => RequestType::otherId(),
            'urgency_level_id' => UrgencyLevel::query()->active()->value('id'),
            'due_at' => now()->addDays(5)->toDateString(),
            'description' => 'Détail.',
            'recipients' => array_map(fn (User $user) => $user->id, $recipients),
            ...$overrides,
        ];
    }
}
