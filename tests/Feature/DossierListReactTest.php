<?php

namespace Tests\Feature;

use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Page « Mes dossiers » en React : mêmes boîtes, mêmes droits et mêmes
 * informations par ligne que l'ancienne page Blade.
 */
class DossierListReactTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_the_page_is_rendered_by_react_with_its_filters(): void
    {
        $user = $this->makeUser(Role::STAFF, 'PRODUCTION');

        $this->actingAs($user)->get(route('dossiers.index', ['box' => 'sent', 'search' => 'abc', 'status' => 'archived']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dossiers/Index')
                ->where('title', 'Mes dossiers')
                ->where('box', 'sent')
                ->where('filters', ['search' => 'abc', 'status' => 'archived'])
                ->has('statuses', count(DossierStatus::cases()))
                ->where('urls.index', route('dossiers.index')));

        // Boîte inconnue : « Reçus » par défaut, comme avant.
        $this->get(route('dossiers.index', ['box' => 'pirate']))->assertInertia(fn (Assert $page) => $page->where('box', 'received'));

        $this->assertFalse(view()->exists('dossiers.index'));
    }

    public function test_each_box_lists_only_the_users_own_dossiers(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $outsider = $this->makeUser(Role::STAFF, 'COLLECTE');
        $open = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Ouvert']);
        $archived = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Classé']);
        $archived->recipients()->update(['status' => RecipientStatus::Archived->value]);
        $archived->forceFill(['status' => DossierStatus::Archived])->save();

        $subjects = fn ($user, string $box) => collect($this->actingAs($user)->get(route('dossiers.index', ['box' => $box]))->inertiaProps('dossiers.data'))->pluck('subject')->sort()->values()->all();

        $this->assertSame(['Ouvert'], $subjects($recipient, 'received'));
        $this->assertSame(['Classé'], $subjects($recipient, 'archived'));
        $this->assertSame(['Classé', 'Ouvert'], $subjects($recipient, 'all'));
        $this->assertSame(['Ouvert'], $subjects($sender, 'sent'));
        $this->assertSame([], $subjects($sender, 'received'));
        $this->assertSame([], $subjects($outsider, 'all'));
    }

    public function test_rows_carry_the_treatment_deadline_and_edit_right_of_the_viewer(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Commande de filets de pêche']);
        $dossier->recipients()->update(['due_at' => now()->subDays(2)]);

        // Destinataire : son traitement, délai dépassé, pas de crayon « Modifier ».
        $this->actingAs($recipient)->get(route('dossiers.index'))->assertInertia(fn (Assert $page) => $page
            ->where('dossiers.data.0.reference', $dossier->reference)
            ->where('dossiers.data.0.subject', 'Commande de filets de pêche')
            ->where('dossiers.data.0.sender', $sender->name)
            ->where('dossiers.data.0.status.mine', true)
            ->where('dossiers.data.0.status.label', RecipientStatus::Received->label())
            ->where('dossiers.data.0.deadline.overdue', true)
            ->where('dossiers.data.0.deadline.label', 'Délai dépassé de 2 jours')
            ->where('dossiers.data.0.recipients.0.name', $recipient->name)
            ->where('dossiers.data.0.showUrl', route('dossiers.show', $dossier))
            ->where('dossiers.data.0.editUrl', null));

        // Expéditeur : statut du dossier et lien « Modifier ».
        $this->actingAs($sender)->get(route('dossiers.index', ['box' => 'sent']))->assertInertia(fn (Assert $page) => $page
            ->where('dossiers.data.0.status.mine', false)
            ->where('dossiers.data.0.editUrl', route('dossiers.edit', $dossier)));
    }

    public function test_pagination_keeps_the_filters(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        foreach (range(1, 17) as $n) {
            $this->sendInternalRequest($sender, [$recipient], ['subject' => "Bon {$n}"]);
        }

        $this->actingAs($sender)->get(route('dossiers.index', ['box' => 'sent', 'search' => 'bon']))->assertInertia(fn (Assert $page) => $page
            ->has('dossiers.data', 15)
            ->where('dossiers.total', 17)
            ->where('dossiers.last_page', 2)
            ->where('dossiers.next_page_url', fn ($url) => str_contains($url, 'box=sent') && str_contains($url, 'search=bon') && str_contains($url, 'page=2')));

        $this->get(route('dossiers.index', ['box' => 'sent', 'search' => 'bon', 'page' => 2]))->assertInertia(fn (Assert $page) => $page->has('dossiers.data', 2));
    }

    public function test_users_without_the_permission_cannot_open_the_list(): void
    {
        $user = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $user->role->permissions()->detach(Permission::query()->where('name', 'view_own_dossiers')->value('id'));

        $this->actingAs($user->fresh())->get(route('dossiers.index'))->assertForbidden();
    }
}
