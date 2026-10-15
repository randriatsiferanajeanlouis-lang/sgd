<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DossierOrigin;
use App\Enums\DossierStatus;
use App\Enums\RecipientStatus;
use App\Enums\RequesterType;
use App\Enums\ResponseType;
use App\Models\Dossier;
use App\Models\ExternalRequester;
use App\Models\Response;
use App\Models\UrgencyLevel;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Circuit des demandes externes, dont le RH est la porte d'entrée unique :
 * enregistrement → responsable(s) → décision → retour RH → réponse
 * officielle → archivage.
 */
class ExternalRequestService
{
    public function __construct(
        private readonly DossierReferenceGenerator $references,
        private readonly DossierDispatcher $dispatcher,
        private readonly DocumentStorage $documents,
        private readonly DossierHistory $history,
        private readonly DossierNotifier $notifier,
    ) {}

    /**
     * Échéance calculée à partir de la date de réception et du délai du
     * niveau d'urgence (paramétrable) : Très urgent 24 h, Urgent 3 jours,
     * Normal 7 jours calendaires.
     */
    public static function dueDate(UrgencyLevel $urgency, CarbonInterface $receivedOn, ?CarbonInterface $now = null): CarbonInterface
    {
        $now ??= now();
        $receivedAt = $receivedOn->copy()->setTime($now->hour, $now->minute, $now->second);

        return $urgency->dueDateFrom($receivedAt);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function register(User $hr, array $data, array $attachments = []): Dossier
    {
        return DB::transaction(function () use ($hr, $data, $attachments) {
            $urgency = UrgencyLevel::query()->findOrFail($data['urgency_level_id']);
            $receivedAt = Carbon::parse($data['received_at']);
            $dueAt = self::dueDate($urgency, $receivedAt);

            $requester = ExternalRequester::query()->create([
                'type' => filled($data['requester_organization'] ?? null) ? RequesterType::Organization : RequesterType::Person,
                'full_name' => $data['requester_name'],
                'organization' => $data['requester_organization'] ?? null,
                'email' => $data['requester_email'] ?? null,
                'phone' => $data['requester_phone'] ?? null,
                'created_by' => $hr->id,
            ]);

            $dossier = Dossier::query()->create([
                'reference' => $this->references->next(),
                'origin' => DossierOrigin::External,
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'request_type_id' => $data['request_type_id'],
                'urgency_level_id' => $urgency->id,
                'external_requester_id' => $requester->id,
                'created_by' => $hr->id,
                'reception_channel' => $data['reception_channel'],
                'received_at' => $receivedAt->copy()->setTimeFrom(now()),
                'due_at' => $dueAt,
                'status' => DossierStatus::Sent,
            ]);

            $this->history->record($dossier, 'external.registered', $dossier, newValues: [
                'référence' => $dossier->reference,
                'demandeur' => $requester->full_name.($requester->organization ? ' ('.$requester->organization.')' : ''),
                'urgence' => $urgency->name,
                'échéance' => $dueAt->format('d/m/Y H:i'),
            ]);

            $this->documents->storeMany($dossier, $attachments, $hr, DocumentCategory::Incoming);

            $users = User::query()->with('department')->whereIn('id', $data['recipients'])->get();
            $this->dispatcher->dispatch($dossier, $users, $hr, $dueAt);

            $this->history->record($dossier, 'dossier.sent', $dossier, newValues: [
                'destinataires' => $users->pluck('name')->implode(', '),
                'statut' => DossierStatus::Sent->label(),
            ]);

            return $dossier;
        });
    }

    /**
     * Réponse officielle du RH au demandeur. Toutes les décisions des
     * responsables restent conservées, même divergentes.
     *
     * @param  array{content: string, channel: string, sent_at: string}  $data
     */
    public function recordOfficialResponse(Dossier $dossier, User $hr, array $data, ?UploadedFile $document = null): Response
    {
        if (in_array($dossier->status, [DossierStatus::Answered, DossierStatus::Archived], true)) {
            throw new LogicException('La réponse officielle a déjà été enregistrée.');
        }

        return DB::transaction(function () use ($dossier, $hr, $data, $document) {
            $previous = $dossier->status;

            $response = $dossier->responses()->create([
                'department_id' => $hr->department_id,
                'author_id' => $hr->id,
                'type' => ResponseType::FinalAnswer,
                'content' => $data['content'],
                'is_final' => true,
                'channel' => $data['channel'],
                'sent_to_requester_at' => Carbon::parse($data['sent_at']),
            ]);

            if ($document) {
                $this->documents->store($dossier, $document, $hr, DocumentCategory::Response, $response);
            }

            $dossier->update(['status' => DossierStatus::Answered]);

            $this->history->record($dossier, 'official_response.recorded', $response,
                ['statut' => $previous->label()],
                ['statut' => DossierStatus::Answered->label(), 'canal' => $response->channel?->label(), 'envoyée le' => $response->sent_to_requester_at->format('d/m/Y')],
                $data['content'],
            );

            $this->notifier->notify(
                $dossier->recipients()->with('assignedUser')->get()->pluck('assignedUser'),
                'official_response',
                "La réponse officielle du dossier {$dossier->reference} a été envoyée au demandeur.",
                $dossier,
            );

            return $response;
        });
    }

    /**
     * Archivage par le RH après la réponse officielle : le dossier et tous
     * les traitements encore ouverts sont archivés, chaque changement tracé.
     */
    public function archive(Dossier $dossier): void
    {
        if ($dossier->status !== DossierStatus::Answered) {
            throw new LogicException('Seul un dossier ayant reçu sa réponse officielle peut être archivé.');
        }

        DB::transaction(function () use ($dossier) {
            foreach ($dossier->recipients()->where('status', '!=', RecipientStatus::Archived->value)->get() as $recipient) {
                $previous = $recipient->status;
                $recipient->update(['status' => RecipientStatus::Archived, 'archived_at' => now()]);

                $this->history->record($dossier, 'recipient.archived', $recipient, ['statut' => $previous->label()], ['statut' => RecipientStatus::Archived->label()]);
            }

            $dossier->update(['status' => DossierStatus::Archived, 'archived_at' => now()]);

            $this->history->record($dossier, 'dossier.archived', $dossier, ['statut' => DossierStatus::Answered->label()], ['statut' => DossierStatus::Archived->label()]);
        });
    }
}
