<?php

namespace App\Support;

use App\Enums\DossierStatus;
use App\Enums\ReceptionChannel;
use App\Enums\ResponseDecision;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\DossierValidation;
use App\Models\Response;
use App\Models\User;
use App\Services\HistoryExporter;
use App\Services\ValidationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Données de la fiche d'un dossier pour la page React (même contenu que
 * l'ancienne vue Blade dossiers/show et ses sections). Chaque action est
 * accompagnée de son droit, calculé ici par les politiques Laravel :
 * React n'affiche que ce que l'utilisateur a le droit de faire.
 */
final class DossierPage
{
    /**
     * @param  Collection<int, Response>  $visibleResponses
     * @param  Collection<string, Collection<int, Document>>  $documentGroups
     * @param  Collection<int|string, string>  $forwardTargets
     * @return array<string, mixed>
     */
    public static function props(Dossier $dossier, User $viewer, ?DossierRecipient $myRecipient, Collection $visibleResponses, Collection $documentGroups, bool $canViewHistory, Collection $forwardTargets): array
    {
        $closed = in_array($dossier->status, [DossierStatus::Answered, DossierStatus::Archived], true);
        $steps = DossierStatus::cases();
        $requester = $dossier->externalRequester;
        $canManageExternal = $dossier->isExternal() && $viewer->hasPermission('manage_external_requests');

        return [
            'title' => $dossier->reference,
            'dossier' => [
                'id' => $dossier->id,
                'reference' => $dossier->reference,
                'subject' => $dossier->subject,
                'description' => $dossier->description,
                'external' => $dossier->isExternal(),
                'removal' => $viewer->hasPermission('view_all_dossiers') ? $dossier->senderRemoval() : null,
                'status' => self::badge($dossier->status),
                'type' => $dossier->typeLabel(),
                'urgency' => $dossier->urgencyLevel ? ['name' => $dossier->urgencyLevel->name, 'color' => $dossier->urgencyLevel->color ?? '#94a3b8'] : null,
                'creator' => self::person($dossier->creator),
                'createdDate' => $dossier->created_at->format('d/m/Y'),
                'createdTime' => $dossier->created_at->format('H:i'),
                'dueDate' => $dossier->due_at?->format('d/m/Y'),
                'dueTime' => $dossier->due_at?->format('H:i'),
                'deadline' => Deadline::describe($dossier->due_at, $closed),
                'receivedAt' => $dossier->received_at?->format('d/m/Y'),
                'receptionChannel' => $dossier->reception_channel?->label(),
                'requester' => $dossier->isExternal() && $requester ? [
                    'name' => $requester->full_name,
                    'organization' => $requester->organization,
                    'contact' => collect([$requester->phone, $requester->email])->filter()->implode(' · '),
                ] : null,
                'answeredCount' => $dossier->recipients->whereNotNull('answered_at')->count(),
                'recipientCount' => $dossier->recipients->count(),
                'steps' => array_map(fn (DossierStatus $step) => $step->label(), $steps),
                'currentStep' => array_search($dossier->status, $steps, true),
            ],
            'can' => [
                'update' => $viewer->can('update', $dossier),
                'delete' => $viewer->can('delete', $dossier),
                'viewHistory' => $canViewHistory,
                'downloadAll' => $viewer->can('downloadAll', $dossier) && $documentGroups->contains(fn ($versions) => $versions->first()->isActive()),
                'addDocument' => $viewer->can('addDocument', $dossier),
            ],
            'urls' => [
                'dashboard' => route('dashboard'),
                'edit' => route('dossiers.edit', $dossier),
                'destroy' => route('dossiers.destroy', $dossier),
                'zip' => route('documents.zip', $dossier),
                'addDocument' => route('documents.store', $dossier),
                'historyPdf' => route('dossiers.history.pdf', $dossier),
                'historyWord' => route('dossiers.history.word', $dossier),
            ],
            'hr' => $canManageExternal ? self::hr($dossier, $viewer) : null,
            'myTreatment' => $myRecipient ? self::treatment($dossier, $viewer, $myRecipient, $forwardTargets) : null,
            'validations' => $dossier->validations->map(fn (DossierValidation $validation) => [
                'id' => $validation->id,
                'requester' => $validation->requester->name,
                'validator' => $validation->validator->name,
                'status' => self::badge($validation->status),
                'createdAt' => $validation->created_at->format('d/m/Y H:i'),
                'requestComment' => $validation->request_comment,
                'decidedAt' => $validation->decided_at?->format('d/m/Y H:i'),
                'decisionComment' => $validation->decision_comment,
                'canDecide' => $viewer->can('decide', $validation),
                'decideUrl' => route('validations.decide', $validation),
            ])->values(),
            'recipients' => $dossier->recipients->map(fn (DossierRecipient $recipient) => [
                'id' => $recipient->id,
                'person' => self::person($recipient->assignedUser, $recipient->department?->name),
                'status' => self::badge($recipient->status),
                'answeredDate' => $recipient->answered_at?->format('d/m/Y'),
                'answeredTime' => $recipient->answered_at?->format('H:i'),
                'archivedDate' => $recipient->archived_at?->format('d/m/Y'),
                'deadline' => self::deadline($recipient->due_at, ! $recipient->status->isOpen()),
                'canRemind' => $viewer->can('remind', $recipient),
                'remindUrl' => route('reminders.store', $recipient),
                'canReopen' => $viewer->can('reopen', $recipient),
                'reopenUrl' => route('treatments.reopen', $recipient),
            ])->values(),
            'responses' => $visibleResponses->map(fn (Response $response) => [
                'id' => $response->id,
                'author' => self::person($response->author),
                'isFinal' => (bool) $response->is_final,
                'decision' => $response->decision ? ['label' => 'Décision '.Str::lower($response->decision->label()), 'color' => $response->decision->color()] : null,
                'date' => $response->created_at->format('d/m/Y'),
                'time' => $response->created_at->format('H:i'),
                'iso' => $response->created_at->toIso8601String(),
                'content' => $response->content,
                'justification' => $response->justification,
                'recommendation' => $response->recommendation,
                'sent' => $response->is_final ? trim(($response->sent_to_requester_at?->format('d/m/Y') ?? '').' · '.($response->channel?->label() ?? '')) : null,
                'documents' => $response->documents->map(fn (Document $document) => self::documentLink($document, $viewer))->values(),
            ])->values(),
            'documents' => $documentGroups->map(fn (Collection $versions) => self::documentGroup($versions, $viewer))->values(),
            'history' => $canViewHistory ? self::history($dossier, $viewer) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function hr(Dossier $dossier, User $viewer): array
    {
        $decisions = $dossier->responses->whereNotNull('decision');

        return [
            'positive' => $decisions->where('decision', ResponseDecision::Positive)->count(),
            'negative' => $decisions->where('decision', ResponseDecision::Negative)->count(),
            'pending' => $dossier->recipients->filter(fn ($recipient) => $recipient->status->isOpen())->count(),
            'canRecordOfficialResponse' => $viewer->can('recordOfficialResponse', $dossier),
            'waitingForDecision' => in_array($dossier->status, [DossierStatus::Sent, DossierStatus::InProgress], true),
            'canArchive' => $viewer->can('archiveExternal', $dossier),
            'channels' => collect(ReceptionChannel::cases())->map(fn (ReceptionChannel $channel) => ['value' => $channel->value, 'label' => $channel->label()])->values(),
            'today' => now()->toDateString(),
            'respondUrl' => route('external-requests.respond', $dossier),
            'archiveUrl' => route('external-requests.archive', $dossier),
        ];
    }

    /**
     * @param  Collection<int|string, string>  $forwardTargets
     * @return array<string, mixed>
     */
    private static function treatment(Dossier $dossier, User $viewer, DossierRecipient $recipient, Collection $forwardTargets): array
    {
        $canRequestValidation = $viewer->can('requestValidation', $recipient);

        return [
            'id' => $recipient->id,
            'status' => self::badge($recipient->status),
            'deadline' => self::deadline($recipient->due_at, ! $recipient->status->isOpen()),
            'pendingValidation' => $recipient->hasPendingValidation(),
            'canRequestValidation' => $canRequestValidation,
            'validatorName' => $canRequestValidation ? ValidationService::validatorFor($viewer)?->name : null,
            'canForward' => $viewer->can('forward', $recipient),
            'forwardTargets' => $forwardTargets->map(fn ($label, $id) => ['value' => (string) $id, 'label' => $label])->values(),
            'canRespond' => $viewer->can('respond', $recipient),
            // Positive / négative pour toute réponse (interne comme externe) : la notification de l'expéditeur la reprend en ✅ / ❌.
            'decisions' => collect(ResponseDecision::cases())->map(fn (ResponseDecision $decision) => ['value' => $decision->value, 'label' => $decision->label()])->values(),
            'canArchive' => $viewer->can('archive', $recipient),
            'urls' => [
                'validation' => route('validations.store', $recipient),
                'forward' => route('treatments.forward', $recipient),
                'respond' => route('treatments.respond', $recipient),
                'archive' => route('treatments.archive', $recipient),
            ],
        ];
    }

    /**
     * @param  Collection<int, Document>  $versions
     * @return array<string, mixed>
     */
    private static function documentGroup(Collection $versions, User $viewer): array
    {
        /** @var Document $latest */
        $latest = $versions->first();
        $extension = Str::upper(pathinfo($latest->original_name, PATHINFO_EXTENSION));

        return [
            'key' => $latest->document_key,
            ...self::documentLink($latest, $viewer),
            'extension' => Str::limit($extension ?: 'FIC', 4, ''),
            'kind' => match (true) {
                $extension === 'PDF' => 'pdf',
                in_array($extension, ['DOC', 'DOCX'], true) => 'word',
                in_array($extension, ['XLS', 'XLSX'], true) => 'excel',
                default => 'other',
            },
            'size' => $latest->size ? ($latest->size >= 1048576 ? number_format($latest->size / 1048576, 1, ',', ' ').' Mo' : max(1, round($latest->size / 1024)).' Ko') : null,
            'uploader' => $latest->uploader->name,
            'date' => $latest->created_at->format('d/m/Y H:i'),
            'responseAuthor' => $latest->response?->author->name,
            'version' => $latest->version,
            'status' => self::badge($latest->status),
            'cancellation' => $latest->cancellation_reason ? [
                'by' => $latest->canceller?->name,
                'date' => $latest->cancelled_at?->format('d/m/Y H:i'),
                'reason' => $latest->cancellation_reason,
            ] : null,
            'canReplace' => $viewer->can('replace', $latest),
            'replaceUrl' => route('documents.replace', $latest),
            'canCancel' => $viewer->can('cancel', $latest),
            'cancelUrl' => route('documents.cancel', $latest),
            'versions' => $versions->count() > 1 ? $versions->map(fn (Document $version) => [
                'id' => $version->id,
                'version' => $version->version,
                'name' => $version->original_name,
                'url' => $viewer->can('downloadVersion', $version) ? route('documents.download', $version) : null,
                'uploader' => $version->uploader->name,
                'date' => $version->created_at->format('d/m/Y H:i'),
                'status' => self::badge($version->status),
            ])->values() : [],
        ];
    }

    /**
     * Lien de téléchargement, seulement si l'utilisateur y a droit.
     *
     * @return array{id: int, name: string, url: ?string}
     */
    private static function documentLink(Document $document, User $viewer): array
    {
        return [
            'id' => $document->id,
            'name' => $document->original_name,
            'url' => $viewer->can('download', $document) ? route('documents.download', $document) : null,
        ];
    }

    /**
     * Historique regroupé par jour (contenus masqués pour les personnes non concernées).
     *
     * @return list<array{date: string, rows: list<array<string, string>>}>
     */
    private static function history(Dossier $dossier, User $viewer): array
    {
        return collect(app(HistoryExporter::class)->rowsFor($dossier, $viewer))
            ->groupBy('date')
            ->map(fn (Collection $rows, string $date) => ['date' => $date, 'rows' => $rows->values()->all()])
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, initials: string, photoUrl: ?string, detail: string}
     */
    private static function person(User $user, ?string $department = null): array
    {
        return [
            'name' => $user->name,
            'initials' => $user->initials(),
            'photoUrl' => $user->photo_path ? route('users.photo', $user) : null,
            'detail' => collect([$user->job_title, $department ?? $user->department?->name])->filter()->implode(' · '),
        ];
    }

    /**
     * @return array{label: string, color: string}
     */
    private static function badge(object $status): array
    {
        return ['label' => $status->label(), 'color' => $status->color()];
    }

    /**
     * Échéance comme le composant Blade <x-deadline> : pastille, ou simple date si le traitement est clos.
     *
     * @return array{label: string, color: ?string, overdue: bool}|null
     */
    private static function deadline(?CarbonInterface $dueAt, bool $closed): ?array
    {
        return Deadline::describe($dueAt, $closed) ?? ($dueAt ? ['label' => $dueAt->format('d/m/Y'), 'color' => null, 'overdue' => false] : null);
    }
}
