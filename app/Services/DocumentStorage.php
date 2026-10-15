<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\Response;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stockage des pièces : disque privé « dossiers », arborescence
 * AAAA/RÉFÉRENCE/, nom système aléatoire (jamais le nom d'origine).
 */
class DocumentStorage
{
    public const DISK = 'dossiers';

    /**
     * Formats acceptés (contrôlés côté serveur sur le contenu réel du fichier).
     */
    public const ALLOWED_MIMES = 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp';

    public const MAX_KILOBYTES = 20480;

    public function __construct(private readonly DossierHistory $history) {}

    /**
     * @return list<string>
     */
    public static function uploadRules(): array
    {
        return ['file', 'mimes:'.self::ALLOWED_MIMES, 'max:'.self::MAX_KILOBYTES];
    }

    public function store(
        Dossier $dossier,
        UploadedFile $file,
        User $uploader,
        DocumentCategory $category = DocumentCategory::Attachment,
        ?Response $response = null,
        ?Document $replaces = null,
    ): Document {
        $directory = $dossier->created_at->format('Y').'/'.$dossier->reference;
        $extension = Str::lower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $path = Storage::disk(self::DISK)->putFileAs($directory, $file, Str::uuid().'.'.$extension);

        $replaces?->update(['status' => DocumentStatus::Replaced]);

        $document = Document::query()->create([
            'dossier_id' => $dossier->id,
            'response_id' => $response?->id ?? $replaces?->response_id,
            'document_key' => $replaces?->document_key ?? (string) Str::uuid(),
            'version' => $replaces ? $replaces->version + 1 : 1,
            'previous_version_id' => $replaces?->id,
            'category' => $replaces?->category ?? $category,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'disk' => self::DISK,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $uploader->id,
        ]);

        $this->history->record(
            $dossier,
            $replaces ? 'document.replaced' : 'document.added',
            $document,
            $replaces ? ['document' => $replaces->original_name, 'version' => $replaces->version] : null,
            ['document' => $document->original_name, 'version' => $document->version],
        );

        return $document;
    }

    /**
     * @param  iterable<UploadedFile>  $files
     */
    public function storeMany(Dossier $dossier, iterable $files, User $uploader, DocumentCategory $category, ?Response $response = null): void
    {
        foreach ($files as $file) {
            $this->store($dossier, $file, $uploader, $category, $response);
        }
    }
}
