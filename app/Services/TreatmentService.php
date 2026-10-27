<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\RecipientStatus;
use App\Enums\ResponseType;
use App\Enums\TransmissionType;
use App\Models\DossierRecipient;
use App\Models\Response;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Traitement individuel d'un destinataire : ouverture, réponse, archivage,
 * réouverture. Ne modifie jamais le traitement des autres destinataires.
 */
class TreatmentService
{
    public function __construct(
        private readonly DossierHistory $history,
        private readonly DossierNotifier $notifier,
        private readonly DocumentStorage $documents,
        private readonly DossierStatusSynchronizer $statuses,
    ) {}

    /**
     * Première ouverture par le destinataire : Reçu → En cours.
     */
    public function open(DossierRecipient $recipient): void
    {
        if ($recipient->status !== RecipientStatus::Received) {
            return;
        }

        DB::transaction(function () use ($recipient) {
            $recipient->update(['status' => RecipientStatus::InProgress, 'read_at' => now()]);

            $this->history->record($recipient->dossier, 'recipient.opened', $recipient,
                ['statut' => RecipientStatus::Received->label()],
                ['statut' => RecipientStatus::InProgress->label()],
            );

            $this->statuses->sync($recipient->dossier);
        });
    }

    /**
     * @param  array{content?: string|null, decision?: string|null, justification?: string|null, recommendation?: string|null}  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function respond(DossierRecipient $recipient, User $author, array $data, array $attachments = []): Response
    {
        if (! $recipient->status->isOpen()) {
            throw new LogicException('Ce traitement n’est plus ouvert.');
        }

        return DB::transaction(function () use ($recipient, $author, $data, $attachments) {
            $dossier = $recipient->dossier;
            $previous = $recipient->status;

            $response = $recipient->responses()->create([
                'dossier_id' => $dossier->id,
                'department_id' => $author->department_id,
                'author_id' => $author->id,
                'type' => ResponseType::Answer,
                'content' => $data['content'] ?? null,
                'decision' => $data['decision'] ?? null,
                'justification' => $data['justification'] ?? null,
                'recommendation' => $data['recommendation'] ?? null,
            ]);

            $this->documents->storeMany($dossier, $attachments, $author, DocumentCategory::Response, $response);

            $recipient->update(['status' => RecipientStatus::Answered, 'answered_at' => now(), 'read_at' => $recipient->read_at ?? now()]);

            $this->history->record($dossier, 'recipient.answered', $response,
                ['statut' => $previous->label()],
                array_filter([
                    'statut' => RecipientStatus::Answered->label(),
                    'décision' => $response->decision?->label(),
                ]),
                $data['content'] ?? null,
            );

            if ($dossier->isExternal()) {
                $this->returnToHr($recipient, $author);
            }

            $this->statuses->sync($dossier);

            $this->notifier->notify(
                $dossier->creator,
                'response_received',
                $response->decision
                    ? "{$author->name} a rendu une décision {$response->decision->label()} sur le dossier {$dossier->reference}."
                    : "{$author->name} a répondu au dossier {$dossier->reference}.",
                $dossier,
                $response->decision,
            );

            return $response;
        });
    }

    /**
     * Réouverture d'un traitement répondu ou archivé : il repasse En cours.
     */
    public function reopen(DossierRecipient $recipient, User $actor, string $comment): void
    {
        DB::transaction(function () use ($recipient, $actor, $comment) {
            $previous = $recipient->status;
            $recipient->update(['status' => RecipientStatus::InProgress, 'archived_at' => null]);

            $this->history->record($recipient->dossier, 'recipient.reopened', $recipient,
                ['statut' => $previous->label()],
                ['statut' => RecipientStatus::InProgress->label(), 'destinataire' => $recipient->assignedUser->name],
                $comment,
            );

            $this->statuses->sync($recipient->dossier);

            $this->notifier->notify($recipient->assignedUser, 'treatment_reopened',
                "{$actor->name} a réouvert votre traitement du dossier {$recipient->dossier->reference} : {$comment}", $recipient->dossier);
        });
    }

    /**
     * Transmission à un autre utilisateur : il devient destinataire à son
     * tour, avec son propre traitement ; celui de l'expéditeur est inchangé.
     */
    public function forward(DossierRecipient $recipient, User $actor, User $target, ?string $comment): DossierRecipient
    {
        return DB::transaction(function () use ($recipient, $actor, $target, $comment) {
            $dossier = $recipient->dossier;

            $newRecipient = $dossier->recipients()->create([
                'department_id' => $target->department_id,
                'assigned_user_id' => $target->id,
                'status' => RecipientStatus::Received,
                'due_at' => $recipient->due_at,
            ]);

            $dossier->transmissions()->create([
                'dossier_recipient_id' => $newRecipient->id,
                'from_department_id' => $actor->department_id,
                'to_department_id' => $target->department_id,
                'transmitted_by' => $actor->id,
                'type' => TransmissionType::Forward,
                'comment' => $comment,
                'transmitted_at' => now(),
            ]);

            $this->history->record($dossier, 'transmission.sent', $newRecipient,
                newValues: ['transmission' => TransmissionType::Forward->label(), 'vers' => $target->name, 'département' => $target->department?->name],
                comment: $comment,
            );

            $this->statuses->sync($dossier);

            $this->notifier->notify($target, 'transmission', "{$actor->name} vous a transmis le dossier {$dossier->reference}.", $dossier);

            return $newRecipient;
        });
    }

    /**
     * Demande externe : la décision du responsable retourne au RH.
     */
    private function returnToHr(DossierRecipient $recipient, User $author): void
    {
        $dossier = $recipient->dossier;

        $dossier->transmissions()->create([
            'dossier_recipient_id' => $recipient->id,
            'from_department_id' => $author->department_id,
            'to_department_id' => $dossier->creator->department_id,
            'transmitted_by' => $author->id,
            'type' => TransmissionType::Return,
            'transmitted_at' => now(),
        ]);

        $this->history->record($dossier, 'transmission.sent', $recipient, newValues: [
            'transmission' => TransmissionType::Return->label(),
            'vers' => $dossier->creator->name,
        ]);
    }

    public function archive(DossierRecipient $recipient): void
    {
        if ($recipient->status !== RecipientStatus::Answered) {
            throw new LogicException('Seul un traitement répondu peut être archivé.');
        }

        DB::transaction(function () use ($recipient) {
            $recipient->update(['status' => RecipientStatus::Archived, 'archived_at' => now()]);

            $this->history->record($recipient->dossier, 'recipient.archived', $recipient,
                ['statut' => RecipientStatus::Answered->label()],
                ['statut' => RecipientStatus::Archived->label()],
            );

            $this->statuses->sync($recipient->dossier);
        });
    }
}
