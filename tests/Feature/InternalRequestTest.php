<?php

namespace Tests\Feature;

use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\Role;
use App\Notifications\DossierNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

class InternalRequestTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
    }

    public function test_employee_sends_a_request_to_two_people_with_an_attachment(): void
    {
        Notification::fake();
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $first = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $second = $this->makeUser(Role::STAFF, 'TECHNIQUE');

        $this->actingAs($sender)->get(route('dossiers.create'))->assertOk()->assertSee('Département destinataire');

        $dossier = $this->sendInternalRequest($sender, [$first, $second], ['attachments' => [$this->pdf()]]);

        $this->assertMatchesRegularExpression('/^DOS-'.now()->year.'-000001$/', $dossier->reference);
        $this->assertSame(DossierStatus::Sent, $dossier->status);
        $this->assertSame('PRODUCTION', $dossier->originatingDepartment->code);
        $this->assertSame(now()->addDays(5)->endOfDay()->toDateTimeString(), $dossier->due_at->toDateTimeString());
        $this->assertSame([RecipientStatus::Received, RecipientStatus::Received], $dossier->recipients->pluck('status')->all());
        $this->assertSame(2, $dossier->transmissions()->count());

        $document = $dossier->documents()->sole();
        $this->assertStringStartsWith(now()->year.'/'.$dossier->reference.'/', $document->path);
        $this->assertStringNotContainsString('piece', $document->path);
        Storage::disk('dossiers')->assertExists($document->path);

        $this->assertEqualsCanonicalizing(
            ['dossier.created', 'document.added', 'recipient.received', 'recipient.received', 'dossier.sent'],
            $dossier->activityLogs()->pluck('action')->all(),
        );

        Notification::assertSentTo([$first, $second], DossierNotification::class, fn ($notification) => $notification->type === 'dossier_received');
        Notification::assertNotSentTo($sender, DossierNotification::class);
    }

    public function test_references_are_unique_and_sequential(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'RSE');

        $first = $this->sendInternalRequest($sender, [$recipient]);
        $second = $this->sendInternalRequest($sender, [$recipient]);

        $this->assertSame(sprintf('DOS-%d-000001', now()->year), $first->reference);
        $this->assertSame(sprintf('DOS-%d-000002', now()->year), $second->reference);
    }

    public function test_request_is_visible_in_the_recipient_inbox_and_opening_it_starts_the_treatment(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);

        $this->actingAs($recipient)->get(route('dossiers.index', ['box' => 'received']))->assertOk()->assertSee($dossier->reference);

        $this->get(route('dossiers.show', $dossier))->assertOk()->assertInertia(fn ($page) => $page->component('Dossiers/Show')->whereNot('myTreatment', null));

        $treatment = $dossier->recipients()->sole();
        $this->assertSame(RecipientStatus::InProgress, $treatment->status);
        $this->assertNotNull($treatment->read_at);
        $this->assertSame(DossierStatus::InProgress, $dossier->fresh()->status);

        $opened = $dossier->activityLogs()->where('action', 'recipient.opened')->sole();
        $this->assertSame(['statut' => 'Reçu'], $opened->old_values);
        $this->assertSame(['statut' => 'En cours'], $opened->new_values);

        $this->get(route('dossiers.show', $dossier));
        $this->assertSame(1, $dossier->activityLogs()->where('action', 'recipient.opened')->count());
    }

    public function test_sender_opening_the_dossier_does_not_change_recipient_status(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $dossier = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'RSE')]);

        $this->actingAs($sender)->get(route('dossiers.show', $dossier))->assertOk();

        $this->assertSame(RecipientStatus::Received, $dossier->recipients()->sole()->status);
    }

    public function test_recipient_answers_with_an_attachment_then_archives_and_sender_is_notified(): void
    {
        Notification::fake();
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($recipient)->get(route('dossiers.show', $dossier));
        $this->post(route('treatments.respond', $treatment), [
            'content' => 'Fournitures livrées.',
            'attachments' => [$this->pdf('bon-livraison.pdf')],
        ])->assertRedirect(route('dossiers.show', $dossier));

        $treatment->refresh();
        $this->assertSame(RecipientStatus::Answered, $treatment->status);
        $this->assertNotNull($treatment->answered_at);
        $this->assertSame('bon-livraison.pdf', $dossier->responses()->sole()->documents()->sole()->original_name);
        $this->assertSame(DossierStatus::Answered, $dossier->fresh()->status);
        Notification::assertSentTo($sender, DossierNotification::class, fn ($notification) => $notification->type === 'response_received');

        $this->post(route('treatments.respond', $treatment), ['content' => 'Encore'])->assertForbidden();

        $this->post(route('treatments.archive', $treatment))->assertRedirect();
        $this->assertSame(RecipientStatus::Archived, $treatment->fresh()->status);
        $this->assertSame(DossierStatus::Archived, $dossier->fresh()->status);

        $this->actingAs($sender)->get(route('dossiers.show', $dossier))->assertPropsSee('Fournitures livrées.');
    }

    public function test_multiple_recipients_keep_independent_statuses(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        [$a, $b, $c] = [$this->makeUser(Role::STAFF, 'PRODUCTION'), $this->makeUser(Role::STAFF, 'RSE'), $this->makeUser(Role::STAFF, 'ADMINISTRATION')];
        $dossier = $this->sendInternalRequest($sender, [$a, $b, $c]);

        $this->actingAs($a)->get(route('dossiers.show', $dossier));
        $this->post(route('treatments.respond', $dossier->recipients()->where('assigned_user_id', $a->id)->sole()), ['content' => 'OK A']);
        $this->post(route('treatments.archive', $dossier->recipients()->where('assigned_user_id', $a->id)->sole()));

        $this->actingAs($b)->get(route('dossiers.show', $dossier));

        $statuses = $dossier->recipients()->pluck('status', 'assigned_user_id');
        $this->assertSame(RecipientStatus::Archived, $statuses[$a->id]);
        $this->assertSame(RecipientStatus::InProgress, $statuses[$b->id]);
        $this->assertSame(RecipientStatus::Received, $statuses[$c->id]);
        $this->assertSame(DossierStatus::InProgress, $dossier->fresh()->status);
    }

    public function test_a_recipient_cannot_read_another_recipient_answer_but_the_sender_can(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        [$a, $b] = [$this->makeUser(Role::STAFF, 'RSE'), $this->makeUser(Role::STAFF, 'TECHNIQUE')];
        $dossier = $this->sendInternalRequest($sender, [$a, $b]);

        $this->actingAs($a)->post(route('treatments.respond', $dossier->recipients()->where('assigned_user_id', $a->id)->sole()), ['content' => 'Réponse confidentielle de A']);

        $this->actingAs($b)->get(route('dossiers.show', $dossier))->assertPropsDontSee('Réponse confidentielle de A');
        $this->actingAs($sender)->get(route('dossiers.show', $dossier))->assertPropsSee('Réponse confidentielle de A');
    }

    public function test_nobody_else_can_act_on_a_treatment(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $recipient = $this->makeUser(Role::STAFF, 'RSE');
        $outsider = $this->makeUser(Role::STAFF, 'RSE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $treatment = $dossier->recipients()->sole();

        $this->actingAs($outsider)->get(route('dossiers.show', $dossier))->assertForbidden();
        $this->post(route('treatments.respond', $treatment), ['content' => 'Intrus'])->assertForbidden();
        $this->actingAs($sender)->post(route('treatments.respond', $treatment), ['content' => 'Pas moi'])->assertForbidden();

        $this->assertSame(0, $dossier->responses()->count());
    }

    public function test_validation_rejects_bad_requests(): void
    {
        $sender = $this->makeUser(Role::STAFF);
        $inactive = $this->makeUser(Role::STAFF, 'RSE', ['is_active' => false]);

        $this->actingAs($sender)->post(route('dossiers.store'), [
            'subject' => '',
            'due_at' => now()->subDay()->toDateString(),
            'recipients' => [$sender->id, $inactive->id],
            'attachments' => [UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')],
        ])->assertSessionHasErrors(['subject', 'request_type_id', 'due_at', 'recipients.0', 'recipients.1', 'attachments.0']);

        $this->assertSame(0, Dossier::query()->count());
    }

    public function test_department_dossiers_page_and_validations_button_are_removed_for_managers(): void
    {
        $head = $this->makeUser(Role::DEPARTMENT_HEAD, 'TECHNIQUE');
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $toTechnique = $this->sendInternalRequest($sender, [$this->makeUser(Role::STAFF, 'TECHNIQUE')]);
        $this->flushSession();

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('dossiers.department'));
        $this->actingAs($head)->get('/dossiers/department')->assertNotFound();
        $this->get(route('dashboard'))->assertDontSee('Dossiers du département')->assertDontSee(route('validations.index').'"', false);

        $items = collect(\App\Support\Navigation::for($head))->flatMap(fn ($section) => array_column($section['items'], 'label'))->all();
        $this->assertNotContains('Dossiers du département', $items);
        $this->assertNotContains('Validations', $items);

        // Le responsable garde l'accès aux dossiers de son département depuis leur fiche.
        $this->get(route('dossiers.show', $toTechnique))->assertOk();
    }

    public function test_new_request_opens_a_two_column_page_without_modal(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');

        // Le bouton « Nouvelle demande » de la liste « Demande » mène à la page dédiée.
        $this->actingAs($sender)->get(route('requests.index'))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('urls.create', route('dossiers.create')));

        $this->get(route('dossiers.create'))->assertOk()
            ->assertDontSee('id="modal-form"', false)
            ->assertDontSee('formSteps(', false)
            ->assertSee('formColumns(', false)
            ->assertSee('data-step="1"', false)
            ->assertSee('data-step="2"', false)
            ->assertSee('xl:grid-cols-2', false)
            ->assertSeeInOrder(['Demande et échéance', 'Destinataires et description', 'Envoyer la demande']);

        // Erreurs : retour sur la page, colonnes en erreur signalées.
        $this->from(route('dossiers.create'))->post(route('dossiers.store'), ['subject' => ''])
            ->assertRedirect(route('dossiers.create'))
            ->assertSessionHasErrors(['subject', 'recipients']);
        $this->from(route('dossiers.create'))->followingRedirects()->post(route('dossiers.store'), ['subject' => ''])
            ->assertOk()
            ->assertSee('Le formulaire contient des erreurs')
            ->assertSee('ring-red-300', false);

        // Envoi complet : le dossier est créé et le destinataire prévenu.
        $dossier = $this->sendInternalRequest($sender, [$recipient], ['subject' => 'Demande depuis la nouvelle page']);
        $this->assertSame('Demande depuis la nouvelle page', $dossier->subject);
        $this->assertSame(1, $recipient->notifications()->count());
    }

    public function test_a_complete_form_type_requires_urgency_and_description(): void
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $type = RequestType::query()->firstWhere('code', 'MATERIEL');
        $type->update(['requires_complete_form' => true]);

        $this->actingAs($sender)->get(route('dossiers.create'))
            ->assertOk()
            ->assertSeeInOrder(['Demande et échéance', 'Destinataires et description']);

        $payload = [
            'subject' => 'Gants de protection',
            'request_type_id' => $type->id,
            'due_at' => now()->addDays(3)->toDateString(),
            'recipients' => [$recipient->id],
        ];

        $this->post(route('dossiers.store'), $payload)->assertSessionHasErrors(['urgency_level_id', 'description']);

        $type->update(['requires_complete_form' => false]);
        $this->post(route('dossiers.store'), $payload)->assertSessionHasNoErrors();
    }
}
