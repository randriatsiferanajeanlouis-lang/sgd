<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\DossierActivityLog;
use App\Services\HistoryExporter;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exports A4 de l'historique, soumis au même droit que sa consultation.
 */
class HistoryController extends Controller
{
    public function __construct(private readonly HistoryExporter $exporter) {}

    /**
     * Dernières actions sur tous les dossiers dont l'utilisateur peut lire l'historique.
     */
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('viewAnyHistory', Dossier::class);

        $user = $request->user();
        $search = trim((string) $request->string('search'));

        $logs = DossierActivityLog::query()
            ->whereHas('dossier', fn (Builder $dossier) => $dossier
                ->historyVisibleTo($user)
                ->when($search !== '', fn (Builder $matching) => $matching->where(fn (Builder $term) => $term
                    ->where('reference', 'like', Search::pattern($search))
                    ->orWhere('subject', 'like', Search::pattern($search)))))
            ->with(['user', 'subject', 'dossier:id,reference,subject'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('History/Index', [
            'title' => 'Historique',
            'search' => $search,
            'logs' => $logs->through(fn (DossierActivityLog $log) => [
                'id' => $log->id,
                'dossier' => [
                    'reference' => $log->dossier->reference,
                    'subject' => $log->dossier->subject,
                    'url' => route('dossiers.show', $log->dossier).'#historique',
                ],
                ...$this->exporter->row($log, $user),
            ]),
            'urls' => ['index' => route('history.index')],
        ]);
    }

    public function pdf(Request $request, Dossier $dossier): Response
    {
        Gate::authorize('viewHistory', $dossier);

        return $this->exporter->pdf($dossier, $request->user());
    }

    public function word(Request $request, Dossier $dossier): BinaryFileResponse
    {
        Gate::authorize('viewHistory', $dossier);

        return response()
            ->download($this->exporter->word($dossier, $request->user()), $this->exporter->filename($dossier, 'docx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend();
    }
}
