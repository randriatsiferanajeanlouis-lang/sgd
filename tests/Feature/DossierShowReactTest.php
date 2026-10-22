<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Fiche d'un dossier en React : mêmes informations et mêmes droits que
 * l'ancienne vue Blade, chaque action n'étant proposée que si la politique
 * Laravel l'autorise.
 */
class DossierShowReactTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    public function test_the_page_is_rendered_by_react_and_the_blade_views_are_gone(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], ['subject' => 'Réparation chambre froide']);

        $this->actingAs($sender)->get(route('dossiers.show', $dossier))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dossiers/Show')
            ->where('title', $dossier->reference)
            ->where('dossier.reference', $dossier->reference)
            ->where('dossier.subject', 'Réparation chambre froide')
            ->where('dossier.external', false)
            ->where('dossier.status.label', 'Envoyé')
            ->where('dossier.recipientCount', 1)
            ->has('dossier.steps', 4)
            ->where('urls.edit', route('dossiers.edit', $dossier))
            ->where('urls.historyPdf', route('dossiers.history.pdf', $dossier))
            ->where('myTreatment', null)
            ->has('recipients', 1)
            ->has('history.0.rows'));

        $this->assertFalse(view()->exists('dossiers.show'));
        foreach (['summary', 'my-treatment', 'validations', 'recipients', 'responses', 'documents', 'document-link', 'history', 'external-actions'] as $partial) {
            $this->assertFalse(view()->exists("dossiers.partials.{$partial}"), $partial);
        }
    }

    public function test_each_person_only_gets_the_actions_they_are_allowed_to_do(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $other = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);

        // Expéditeur : modifier, supprimer, relancer ; aucun traitement à lui.
        $this->actingAs($sender)->get(route('dossiers.show', $dossier))->assertInertia(fn (Assert $page) => $page
            ->where('can.update', true)
            ->where('can.delete', true)
            ->where('recipients.0.canRemind', true)
            ->where('recipients.0.canReopen', false)
            ->where('myTreatment', null));

        // Destinataire : son traitement (répondre, transmettre), pas de modification du dossier.
        $this->actingAs($recipient)->get(route('dossiers.show', $dossier))->assertInertia(fn (Assert $page) => $page
            ->where('can.update', false)
            ->where('can.delete', false)
            ->where('recipients.0.canRemind', false)
            ->where('myTreatment.canRespond', true)
            ->where('myTreatment.canForward', true)
            ->where('myTreatment.urls.respond', route('treatments.respond', $dossier->recipients()->sole()))
            ->where('myTreatment.forwardTargets', fn ($targets) => collect($targets)->pluck('value')->contains((string) $other->id)
                && ! collect($targets)->pluck('value')->contains((string) $recipient->id)));

        // Personne non concernée : aucun accès.
        $this->actingAs($this->makeUser(Role::STAFF, 'COLLECTE'))->get(route('dossiers.show', $dossier))->assertForbidden();
    }

    public function test_documents_carry_download_links_only_when_allowed(): void
    {
        Storage::fake('dossiers');
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')], ['attachments' => [$this->pdf('devis.pdf')]]);
        $document = $dossier->documents()->sole();

        $this->actingAs($sender)->get(route('dossiers.show', $dossier))->assertInertia(fn (Assert $page) => $page
            ->where('documents.0.name', 'devis.pdf')
            ->where('documents.0.extension', 'PDF')
            ->where('documents.0.kind', 'pdf')
            ->where('documents.0.url', route('documents.download', $document))
            ->where('documents.0.canReplace', true)
            ->where('can.downloadAll', true)
            ->where('urls.zip', route('documents.zip', $dossier)));
    }

    public function test_hr_sees_the_follow_up_of_an_external_request(): void
    {
        $hr = User::query()->firstWhere('username', 'rh');
        $hr->update(['must_change_password' => false]);
        $staff = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $internal = $this->sendInternalRequest($staff, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);

        // Demande interne : pas de bloc « Suivi RH ».
        $this->actingAs($staff)->get(route('dossiers.show', $internal))->assertInertia(fn (Assert $page) => $page->where('hr', null));

        $this->assertStringContainsString('Suivi RH', (string) file_get_contents(resource_path('js/components/dossier/HrSection.jsx')));
    }
}
