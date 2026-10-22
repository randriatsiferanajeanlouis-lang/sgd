<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class ViewButtonTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
    }

    public function test_every_dossier_list_has_a_view_button_on_each_row(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $button = 'aria-label="Voir le dossier '.$dossier->reference.'"';

        // Mes dossiers (destinataire et expéditeur)
        // (page React : lien de la ligne transmis, bouton « Voir » dans DossierTable.jsx)
        $this->actingAs($recipient)->get(route('dossiers.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('dossiers.data.0.showUrl', route('dossiers.show', $dossier)));
        $this->actingAs($sender)->get(route('dossiers.index', ['box' => 'sent']))
            ->assertInertia(fn ($page) => $page->where('dossiers.data.0.reference', $dossier->reference));
        $table = (string) file_get_contents(resource_path('js/components/app/DossierTable.jsx'));
        $this->assertStringContainsString('aria-label={`Voir le dossier ${dossier.reference}`}', $table);
        $this->assertStringContainsString('href={dossier.showUrl}', $table);

        // Dossiers envoyés à un département (annuaire)
        $this->get(route('directory.dossiers', Department::query()->firstWhere('code', 'TECHNIQUE')))->assertSee($button, false);

        // Historique
        $this->get(route('history.index'))->assertOk()->assertSee($button, false);
    }

    public function test_lists_show_at_most_fifteen_words_of_the_subject(): void
    {
        $long = 'un deux trois quatre cinq six sept huit neuf dix onze douze treize quatorze quinze seize dix-sept dix-huit';
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient], ['subject' => $long]);
        // 15 caractères au plus, coupés sur un mot entier, puis « ... ».
        $short = 'un deux trois ...';
        $subject = fn (string $text) => (new \App\Models\Dossier(['subject' => $text]))->shortSubject();

        $this->assertSame($short, $dossier->shortSubject());
        $this->assertSame('Mr le directeur', $subject('Mr le directeur'), '15 caractères : texte entier, sans « ... ».');
        $this->assertSame('Mr le direction ...', $subject('Mr le direction , cette demande est arrivé aujourd’hui'));
        $this->assertSame('Mr le directeur ...', $subject('Mr le directeur, merci de votre aide'));
        $this->assertSame('Demandeexceptio ...', $subject('Demandeexceptionnellementlongue sans espace'));
        $this->assertSame('Objet court', $subject('Objet court'));
        $this->assertSame('Arrivée à ...', $subject('Arrivée à Mahajanga demain'), 'Les caractères accentués restent intacts.');
        $this->assertSame('Livraison À ...', $subject('Livraison À Mahajanga'));

        // Mes dossiers (destinataire) : objet abrégé, objet complet en info-bulle ; la fiche garde l'objet complet.
        // (page React : objet complet transmis, abrégé à l'affichage par shortText(), complet en info-bulle)
        $this->actingAs($recipient)->get(route('dossiers.index'))
            ->assertInertia(fn ($page) => $page->where('dossiers.data.0.subject', $long));
        $table = (string) file_get_contents(resource_path('js/components/app/DossierTable.jsx'));
        $this->assertStringContainsString('{shortText(dossier.subject)}', $table);
        $this->assertStringContainsString('title={dossier.subject}', $table);
        $this->get(route('dossiers.show', $dossier))->assertSee($long);

        // Historique : référence entière sur une ligne, objet abrégé.
        $this->get(route('history.index'))
            ->assertSee('whitespace-nowrap text-brand-500', false)
            ->assertSee('>'.$short.'</span>', false);

        // Suggestions de recherche : objet abrégé.
        $this->getJson(route('dossiers.index', ['box' => 'all', 'search' => 'quatorze', 'suggest' => 1]))
            ->assertJsonFragment(['label' => $dossier->reference.' — '.$short]);

        // Tableau de bord : référence et objet complet transmis à React, abrégé par shortText().
        $this->get(route('dashboard'))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('activity.items.0.reference', $dossier->reference)
            ->where('activity.items.0.subject', $long));

        // Mes demandes (React) : l'objet d'origine est transmis intact (abrégé à l'affichage).
        $this->actingAs($sender)->get(route('requests.index'))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('dossiers.data.0.subject', $long)
            ->missing('dossiers.data.0.subjectShort'));

        // La base garde l'objet complet.
        $this->assertSame($long, $dossier->fresh()->subject);
    }

    public function test_hr_external_list_and_validations_have_view_buttons(): void
    {
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        Department::query()->where('code', 'TECHNIQUE')->update(['manager_id' => $head->id]);
        $employee = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($this->makeUser(Role::STAFF, 'PRODUCTION'), [$employee]);

        $this->actingAs($employee)->post(route('validations.store', $dossier->recipients()->sole()))->assertRedirect();
        $this->actingAs($head)->get(route('validations.index'))->assertOk()->assertSee('aria-label="Voir le dossier '.$dossier->reference.'"', false);

        $this->actingAs($this->makeUser(Role::HR_MANAGER, 'RSE'))->get(route('external-requests.index'))->assertOk()->assertSee('Actions');
    }
}
