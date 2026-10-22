<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DossierOrigin;
use App\Enums\DossierStatus;
use App\Models\Dossier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Création et envoi d'une demande interne, en une seule transaction :
 * référence, dossier, pièces jointes, destinataires, historique, notifications.
 */
class InternalRequestService
{
    public function __construct(
        private readonly DossierReferenceGenerator $references,
        private readonly DossierDispatcher $dispatcher,
        private readonly DocumentStorage $documents,
        private readonly DossierHistory $history,
    ) {}

    /**
     * @param  array{subject: string, request_type_id: int, request_type_other?: string|null, urgency_level_id?: int|null, due_at: string, description?: string|null, recipients: list<int>}  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function create(User $creator, array $data, array $attachments = []): Dossier
    {
        return DB::transaction(function () use ($creator, $data, $attachments) {
            $dueAt = Carbon::parse($data['due_at'])->endOfDay();

            $dossier = Dossier::query()->create([
                'reference' => $this->references->next(),
                'origin' => DossierOrigin::Internal,
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'request_type_id' => $data['request_type_id'],
                'request_type_other' => $data['request_type_other'] ?? null,
                'urgency_level_id' => $data['urgency_level_id'] ?? null,
                'originating_department_id' => $creator->department_id,
                'created_by' => $creator->id,
                'received_at' => now(),
                'due_at' => $dueAt,
                'status' => DossierStatus::Sent,
            ]);

            $this->history->record($dossier, 'dossier.created', $dossier, newValues: [
                'référence' => $dossier->reference,
                'objet' => $dossier->subject,
                'échéance' => $dueAt->format('d/m/Y'),
            ]);

            $this->documents->storeMany($dossier, $attachments, $creator, DocumentCategory::Attachment);

            $users = User::query()->with('department')->whereIn('id', $data['recipients'])->get();
            $this->dispatcher->dispatch($dossier, $users, $creator, $dueAt);

            $this->history->record($dossier, 'dossier.sent', $dossier, newValues: [
                'destinataires' => $users->pluck('name')->implode(', '),
                'statut' => DossierStatus::Sent->label(),
            ]);

            return $dossier;
        });
    }
}
