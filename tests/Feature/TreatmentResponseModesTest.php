<?php

namespace Tests\Feature;

use App\Enums\RecipientStatus;
use App\Enums\ResponseDecision;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;

/**
 * Réponse à une demande interne : un choix Positive / Négative seul, un texte seul,
 * ou les deux. Au moins l'un des deux est nécessaire.
 */
class TreatmentResponseModesTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    private function respond(array $payload): array
    {
        $sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $dossier = $this->sendInternalRequest($sender, [$recipient]);
        $treatment = $dossier->recipients()->sole();

        $response = $this->actingAs($recipient)->post(route('treatments.respond', $treatment), $payload);

        return [$response, $dossier, $treatment, $sender];
    }

    public function test_positive_choice_alone_is_enough(): void
    {
        [$response, $dossier, $treatment, $sender] = $this->respond(['decision' => 'positive']);

        $response->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('status', 'Votre réponse a été envoyée.');
        $saved = $dossier->responses()->sole();
        $this->assertSame(ResponseDecision::Positive, $saved->decision);
        $this->assertNull($saved->content);
        $this->assertSame(RecipientStatus::Answered, $treatment->fresh()->status);
        $this->assertTrue($dossier->activityLogs()->where('action', 'recipient.answered')->exists());
        $this->assertStringStartsWith('✅ ', \App\Notifications\DossierNotification::displayMessage($sender->notifications()->latest()->first()->data));
    }

    public function test_negative_choice_alone_is_enough(): void
    {
        [$response, $dossier, , $sender] = $this->respond(['decision' => 'negative']);

        $response->assertSessionHasNoErrors();
        $this->assertSame(ResponseDecision::Negative, $dossier->responses()->sole()->decision);
        $this->assertStringStartsWith('❌ ', \App\Notifications\DossierNotification::displayMessage($sender->notifications()->latest()->first()->data));
    }

    public function test_text_alone_is_enough(): void
    {
        [$response, $dossier] = $this->respond(['content' => 'Nous le ferons la semaine prochaine.']);

        $response->assertSessionHasNoErrors();
        $saved = $dossier->responses()->sole();
        $this->assertNull($saved->decision);
        $this->assertSame('Nous le ferons la semaine prochaine.', $saved->content);
    }

    public function test_choice_and_text_are_both_saved(): void
    {
        [$response, $dossier] = $this->respond(['decision' => 'negative', 'content' => 'Budget insuffisant.']);

        $response->assertSessionHasNoErrors();
        $saved = $dossier->responses()->sole();
        $this->assertSame(ResponseDecision::Negative, $saved->decision);
        $this->assertSame('Budget insuffisant.', $saved->content);
        $this->assertSame('Budget insuffisant.', $dossier->activityLogs()->where('action', 'recipient.answered')->sole()->comment);
    }

    public function test_nothing_at_all_is_refused(): void
    {
        [$response, $dossier, $treatment] = $this->respond(['content' => '   ', 'decision' => '']);

        $response->assertSessionHasErrors(['content', 'decision']);
        $this->assertSame(0, $dossier->responses()->count());
        $this->assertNotSame(RecipientStatus::Answered, $treatment->fresh()->status);
    }
}
