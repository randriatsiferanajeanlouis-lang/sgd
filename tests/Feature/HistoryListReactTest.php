<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Page « Historique » en React : actions des dossiers visibles, recherche
 * par référence ou objet, pagination par 25 et contenus masqués.
 */
class HistoryListReactTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_the_page_is_rendered_by_react_with_the_rows_of_visible_dossiers(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], ['subject' => 'Pièces moteur']);

        $this->actingAs($sender)->get(route('history.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('History/Index')
            ->where('title', 'Historique')
            ->where('search', '')
            ->where('logs.data.0.dossier.reference', $dossier->reference)
            ->where('logs.data.0.dossier.url', route('dossiers.show', $dossier).'#historique')
            ->has('logs.data.0.date')
            ->has('logs.data.0.time')
            ->has('logs.data.0.user')
            ->has('logs.data.0.action')
            ->has('logs.data.0.details')
            ->where('urls.index', route('history.index')));

        $this->assertFalse(view()->exists('history.index'));
    }

    public function test_search_and_pagination(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $wanted = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Pièces moteur']);
        foreach (range(1, 10) as $n) {
            $this->sendInternalRequest($sender, [$recipient], ['subject' => "Bon {$n}"]);
        }

        $this->actingAs($sender)->get(route('history.index', ['search' => 'moteur']))->assertInertia(fn (Assert $page) => $page
            ->where('search', 'moteur')
            ->where('logs.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['dossier']['reference'] === $wanted->reference)));

        $this->get(route('history.index'))->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 25)
            ->where('logs.per_page', 25)
            ->where('logs.last_page', fn ($last) => $last >= 2));
    }
}
