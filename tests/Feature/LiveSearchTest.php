<?php

namespace Tests\Feature;

use App\Models\Dossier;
use App\Models\ExternalRequester;
use App\Models\Role;
use App\Support\Search;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class LiveSearchTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_one_letter_matches_the_beginning_and_longer_text_matches_anywhere(): void
    {
        $this->assertSame('a%', Search::pattern('a'));
        $this->assertSame('%bon%', Search::pattern(' bon '));
        $this->assertSame('%50\%%', Search::pattern('50%'));
    }

    public function test_suggestions_list_the_matching_dossiers_while_typing(): void
    {
        // Noms fixes : la recherche porte aussi sur l'expéditeur.
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION', ['first_name' => 'Hery', 'last_name' => 'Rakoto', 'name' => 'Hery Rakoto']);
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $voucher = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Bon de commande carburant']);
        $subscription = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Abonnement internet']);

        $this->actingAs($sender)->getJson(route('dossiers.index', ['box' => 'sent', 'search' => 'bon', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonFragment(['url' => route('dossiers.show', $voucher)])
            ->assertJsonFragment(['url' => route('dossiers.show', $subscription)]);

        $this->getJson(route('dossiers.index', ['box' => 'sent', 'search' => 'b', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.label', $voucher->reference.' — Bon de commande ...');
    }

    public function test_dossiers_can_be_searched_by_the_sender_name(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION', ['first_name' => 'Hanta', 'last_name' => 'Razafy']);
        $other = $this->makeUser(Role::STAFF, 'COLLECTE', ['first_name' => 'Paul', 'last_name' => 'Bema']);
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $fromHanta = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Pièces moteur']);
        $this->sendInternalRequest($other, [$recipient], ['subject' => 'Filets de pêche']);

        foreach (['Razafy', 'hanta', 'Hanta Razafy', 'Razafy Hanta'] as $term) {
            $found = $this->actingAs($recipient)->get(route('dossiers.index', ['box' => 'all', 'search' => $term]))
                ->assertOk()
                ->inertiaProps('dossiers.data');

            $this->assertSame(['Pièces moteur'], collect($found)->pluck('subject')->all(), "Recherche « {$term} »");
        }

        $this->getJson(route('dossiers.index', ['box' => 'all', 'search' => 'Razafy', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['url' => route('dossiers.show', $fromHanta)]);

        // Demande externe : recherche sur le nom du demandeur ou son organisme.
        $hr = $this->makeUser(Role::HR_MANAGER, 'RSE');
        Dossier::factory()->external()->create([
            'created_by' => $hr->id,
            'subject' => 'Demande de stage',
            'external_requester_id' => ExternalRequester::factory()->create(['full_name' => 'Lova Andriana', 'organization' => 'Lycée Mahajanga']),
        ]);

        foreach (['Andriana', 'Lycée'] as $term) {
            $found = $this->actingAs($hr)->get(route('dossiers.index', ['box' => 'all', 'search' => $term]))
                ->assertOk()
                ->inertiaProps('dossiers.data');

            $this->assertSame(['Demande de stage'], collect($found)->pluck('subject')->all(), "Recherche « {$term} »");
        }
    }

    public function test_suggestions_never_reveal_dossiers_outside_the_users_access(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], ['subject' => 'Bon de sortie']);

        $this->actingAs($this->makeUser(Role::STAFF, 'RSE'))
            ->getJson(route('dossiers.index', ['box' => 'all', 'search' => 'bon', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_user_list_suggests_people_whose_name_starts_with_the_letter(): void
    {
        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $this->makeUser(Role::STAFF, 'PRODUCTION', ['first_name' => 'Zéphyr', 'last_name' => 'Qwerty', 'username' => 'zephyr.q']);

        $this->actingAs($admin)->getJson(route('admin.users.index', ['search' => 'Q', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonFragment(['label' => 'Zéphyr Qwerty']);

        $this->get(route('admin.users.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Users/Index'));
        $this->assertStringContainsString('<LiveSearch', (string) file_get_contents(resource_path('js/pages/Admin/Users/Index.jsx')));
        $this->assertStringContainsString('role="combobox"', (string) file_get_contents(resource_path('js/components/app/LiveSearch.jsx')));
    }
}
