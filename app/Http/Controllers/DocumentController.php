<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Dossier;
use App\Services\DocumentService;
use App\Services\DocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièces des dossiers : jamais d'URL publique, chaque téléchargement passe
 * par ce contrôleur et DocumentPolicy.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function store(Request $request, Dossier $dossier): RedirectResponse
    {
        Gate::authorize('addDocument', $dossier);

        $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => DocumentStorage::uploadRules(),
        ]);

        $this->documents->add($dossier, $request->file('attachments'), $request->user());

        return back()->with('status', 'Document(s) ajouté(s).');
    }

    public function download(Document $document): StreamedResponse
    {
        Gate::authorize($document->isActive() ? 'download' : 'downloadVersion', $document);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function replace(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('replace', $document);

        $request->validate(['file' => ['required', ...DocumentStorage::uploadRules()]]);

        $newVersion = $this->documents->replace($document, $request->file('file'), $request->user());

        return back()->with('status', "Nouvelle version V{$newVersion->version} enregistrée. La version V{$document->version} reste tracée.");
    }

    public function cancel(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('cancel', $document);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->documents->cancel($document, $request->user(), $validated['reason']);

        return back()->with('status', 'Document annulé. Il reste visible dans l’historique.');
    }

    public function zip(Request $request, Dossier $dossier): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('downloadAll', $dossier);

        $documents = $dossier->documents()->active()->with(['response.dossier', 'dossier'])->get()
            ->filter(fn (Document $document) => $request->user()->can('download', $document));

        if ($documents->isEmpty()) {
            return back()->with('error', 'Aucune pièce active à télécharger.');
        }

        return response()
            ->download($this->documents->zip($dossier, $documents), $dossier->reference.'-pieces.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
