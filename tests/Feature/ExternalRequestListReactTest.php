<?php

namespace Tests\Feature;

use App\Enums\DossierStatus;
use App\Models\Dossier;
use App\Models\ExternalRequester;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Liste des demandes externes en React : réservée au RH, seulement les
 * demandes externes, recherche (référence, objet, demandeur, organisme),
 * filtre de statut, recherche instantanée et pagination.
 */
class ExternalRequestListReactTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::query()->firstWhere('username', 'rh');
        $this->hr->update(['must_change_password' => false]);
    }

    public function test_the_list_is_rendered_by_react_with_only_external_requests(): void
    {
        $external = Dossier::factory()->external()->create(['created_by' => $this->hr->id, 'subject' => 'Demande de stage']);
        $this->sendInternalRequest($this->makeUser(Role::STAFF, 'PRODUCTION'), [$this->makeUser(Role::STAFF, 'TECHNIQUE')], ['subject' => 'Interne']);

        $this->actingAs($this->hr)->get(route('external-requests.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ExternalRequests/Index')
            ->where('title', 'Demandes externes')
            ->has('dossiers.data', 1)
            ->where('dossiers.data.0.reference', $external->reference)
            ->where('dossiers.data.0.external', true)
            ->where('dossiers.data.0.showUrl', route('dossiers.show', $external))
            ->has('statuses', count(DossierStatus::cases()))
            ->where('urls.index', route('external-requests.index')));

        $this->assertFalse(view()->exists('external-requests.index'));
        $this->assertFalse(view()->exists('dossiers.partials.filters'));
    }

    public function test_search_status_filter_and_suggestions(): void
    {
        $lova = ExternalRequester::factory()->create(['full_name' => 'Lova Andriana', 'organization' => 'Lycée Mahajanga']);
        $wanted = Dossier::factory()->external()->create(['created_by' => $this->hr->id, 'subject' => 'Demande de stage', 'external_requester_id' => $lova->id]);
        Dossier::factory()->external()->create(['created_by' => $this->hr->id, 'subject' => 'Sponsoring tournoi', 'status' => DossierStatus::Archived]);

        foreach (['Andriana', 'Lycée', $wanted->reference] as $term) {
            $this->actingAs($this->hr)->get(route('external-requests.index', ['search' => $term]))
                ->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 1)->where('dossiers.data.0.id', $wanted->id)->where('filters.search', $term));
        }

        $this->get(route('external-requests.index', ['status' => 'archived']))
            ->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 1)->where('dossiers.data.0.subject', 'Sponsoring tournoi'));

        $this->getJson(route('external-requests.index', ['search' => 'Andriana', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['url' => route('dossiers.show', $wanted)]);
    }

    public function test_pagination_and_access_rules(): void
    {
        Dossier::factory()->external()->count(17)->create(['created_by' => $this->hr->id]);

        $this->actingAs($this->hr)->get(route('external-requests.index'))->assertInertia(fn (Assert $page) => $page
            ->has('dossiers.data', 15)->where('dossiers.total', 17)->where('dossiers.last_page', 2));

        $this->actingAs($this->makeUser(Role::STAFF))->get(route('external-requests.index'))->assertForbidden();
        $this->actingAs($this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE'))->get(route('external-requests.index'))->assertForbidden();
    }
}
