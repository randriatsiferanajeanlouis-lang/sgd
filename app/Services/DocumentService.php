<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Gestion documentaire d'un dossier : ajout, nouvelle version, annulation,
 * archive ZIP. Rien n'est supprimé : une version remplacée ou annulée
 * reste enregistrée et tracée.
 */
class DocumentService
{
    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly DossierHistory $history,
        private readonly DossierNotifier $notifier,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function add(Dossier $dossier, array $files, User $user): void
    {
        DB::transaction(function () use ($dossier, $files, $user) {
            $this->storage->storeMany($dossier, $files, $user, DocumentCategory::Attachment);

            $this->notifyParticipants($dossier, count($files) > 1
                ? "{$user->name} a ajouté ".count($files)." documents au dossier {$dossier->reference}."
                : "{$user->name} a ajouté un document au dossier {$dossier->reference}.");
        });
    }

    /**
     * V(n) → REPLACED, V(n+1) → ACTIVE.
     */
    public function replace(Document $document, UploadedFile $file, User $user): Document
    {
        return DB::transaction(function () use ($document, $file, $user) {
            $newVersion = $this->storage->store($document->dossier, $file, $user, replaces: $document);

            $this->notifyParticipants($document->dossier, "{$user->name} a remplacé « {$document->original_name} » (V{$newVersion->version}) dans le dossier {$document->dossier->reference}.");

            return $newVersion;
        });
    }

    public function cancel(Document $document, User $user, string $reason): void
    {
        DB::transaction(function () use ($document, $user, $reason) {
            $document->update([
                'status' => DocumentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
            ]);

            $this->history->record($document->dossier, 'document.cancelled', $document,
                ['document' => $document->original_name, 'version' => $document->version, 'statut' => DocumentStatus::Active->label()],
                ['statut' => DocumentStatus::Cancelled->label()],
                $reason,
            );
        });
    }

    /**
     * Archive ZIP des seules versions actives transmises, dans un fichier
     * temporaire que l'appelant supprime après l'envoi.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function zip(Dossier $dossier, Collection $documents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dossier-zip-');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer l’archive ZIP.');
        }

        $usedNames = [];

        foreach ($documents->filter(fn (Document $document) => $document->isActive()) as $document) {
            $name = Str::ascii($document->original_name);
            $name = isset($usedNames[$name]) ? pathinfo($name, PATHINFO_FILENAME).'-V'.$document->version.'-'.$document->id.'.'.pathinfo($name, PATHINFO_EXTENSION) : $name;
            $usedNames[$name] = true;

            $zip->addFromString($name, Storage::disk($document->disk)->get($document->path));
        }

        $zip->close();

        return $path;
    }

    private function notifyParticipants(Dossier $dossier, string $message): void
    {
        $participants = $dossier->recipients()->with('assignedUser')->get()->pluck('assignedUser')->push($dossier->creator);

        $this->notifier->notify($participants, 'document_added', $message, $dossier);
    }
}
