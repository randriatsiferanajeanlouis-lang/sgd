<?php

namespace App\Http\Controllers;

use App\Enums\DossierOrigin;
use App\Enums\ReceptionChannel;
use App\Enums\RequestScope;
use App\Http\Requests\StoreExternalRequest;
use App\Http\Requests\StoreOfficialResponseRequest;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\UrgencyLevel;
use App\Services\ExternalRequestService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Module RH : enregistrement des demandes externes, réponse officielle,
 * archivage.
 */
class ExternalRequestController extends Controller
{
    public function __construct(private readonly ExternalRequestService $externalRequests) {}

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage_external_requests'), 403);

        $query = Dossier::query()
            ->where('origin', DossierOrigin::External->value)
            ->with(['requestType', 'creator', 'externalRequester', 'recipients.assignedUser'])
            ->when($request->filled('search'), fn (Builder $query) => $query->where(fn (Builder $term) => $term
                ->where('reference', 'like', Search::pattern($request->string('search')))
                ->orWhere('subject', 'like', Search::pattern($request->string('search')))
                ->orWhereHas('externalRequester', fn (Builder $requester) => $requester
                    ->where('full_name', 'like', Search::pattern($request->string('search')))
                    ->orWhere('organization', 'like', Search::pattern($request->string('search'))))))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->latest('id');

        if (Search::wantsSuggestions()) {
            return Search::suggestions($query, fn (Dossier $dossier) => [
                'label' => $dossier->reference.' — '.$dossier->subject,
                'detail' => collect([$dossier->externalRequester?->full_name, $dossier->externalRequester?->organization, $dossier->status->label()])->filter()->implode(' · '),
                'url' => route('dossiers.show', $dossier),
            ]);
        }

        return view('external-requests.index', [
            'dossiers' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('createExternal', Dossier::class);

        return view('external-requests.create', [
            'requestTypes' => RequestType::query()->active()->availableFor(RequestScope::External)->orderBy('sort_order')->pluck('name', 'id'),
            'urgencyLevels' => UrgencyLevel::query()->active()->orderBy('sort_order')->get(['id', 'name', 'response_delay_hours']),
            'completeTypes' => RequestType::completeFormIds(),
            'channels' => collect(ReceptionChannel::cases())->mapWithKeys(fn (ReceptionChannel $channel) => [$channel->value => $channel->label()]),
            'directory' => Department::query()
                ->active()
                ->orderBy('sort_order')
                ->with(['manager:id', 'users' => fn ($users) => $users->active()->where('id', '!=', $request->user()->id)->orderBy('last_name')])
                ->get()
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'users' => $department->users
                        ->sortByDesc(fn ($member) => $member->id === $department->manager_id)
                        ->map(fn ($member) => [
                            'id' => $member->id,
                            'name' => $member->name,
                            'job_title' => $member->id === $department->manager_id ? 'Responsable — '.$member->job_title : $member->job_title,
                        ])->values()->all(),
                ])->all(),
        ]);
    }

    public function store(StoreExternalRequest $request): RedirectResponse
    {
        $dossier = $this->externalRequests->register($request->user(), $request->safe()->except('attachments'), $request->file('attachments', []));

        return redirect()->route('dossiers.show', $dossier)
            ->with('status', "Demande externe {$dossier->reference} enregistrée et transmise. Échéance : {$dossier->due_at->format('d/m/Y H:i')}.");
    }

    public function respond(StoreOfficialResponseRequest $request, Dossier $dossier): RedirectResponse
    {
        $this->externalRequests->recordOfficialResponse($dossier, $request->user(), $request->safe()->except('document'), $request->file('document'));

        return redirect()->route('dossiers.show', $dossier)->with('status', 'La réponse officielle a été enregistrée.');
    }

    public function archive(Dossier $dossier): RedirectResponse
    {
        Gate::authorize('archiveExternal', $dossier);

        $this->externalRequests->archive($dossier);

        return redirect()->route('dossiers.show', $dossier)->with('status', 'Le dossier a été archivé.');
    }
}
