<?php

namespace App\Http\Controllers;

use App\Enums\DossierOrigin;
use App\Enums\DossierStatus;
use App\Enums\ReceptionChannel;
use App\Enums\RequestScope;
use App\Http\Requests\StoreExternalRequest;
use App\Http\Requests\StoreOfficialResponseRequest;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\RequestType;
use App\Models\UrgencyLevel;
use App\Services\ExternalRequestService;
use App\Support\DossierListItem;
use App\Support\Search;
use App\Support\SubmissionGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Module RH : enregistrement des demandes externes, réponse officielle,
 * archivage.
 */
class ExternalRequestController extends Controller
{
    public function __construct(private readonly ExternalRequestService $externalRequests) {}

    public function index(Request $request): InertiaResponse|JsonResponse
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
                'label' => $dossier->reference.' — '.$dossier->shortSubject(),
                'detail' => collect([$dossier->externalRequester?->full_name, $dossier->externalRequester?->organization, $dossier->status->label()])->filter()->implode(' · '),
                'url' => route('dossiers.show', $dossier),
            ]);
        }

        $user = $request->user();

        return Inertia::render('ExternalRequests/Index', [
            'title' => 'Demandes externes',
            'filters' => ['search' => $request->string('search')->toString(), 'status' => $request->string('status')->toString()],
            'statuses' => collect(DossierStatus::cases())->map(fn (DossierStatus $status) => ['value' => $status->value, 'label' => $status->label()])->values(),
            'dossiers' => $query->paginate(15)->withQueryString()
                ->through(fn (Dossier $dossier) => DossierListItem::for($dossier, $user)),
            'can' => ['create' => $user->can('createExternal', Dossier::class)],
            'urls' => [
                'index' => route('external-requests.index'),
                'create' => route('external-requests.create'),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        Gate::authorize('createExternal', Dossier::class);

        return Inertia::render('ExternalRequests/Create', [
            'title' => 'Nouvelle demande externe',
            'requestTypes' => RequestType::query()->active()->availableFor(RequestScope::External)->orderBy('sort_order')->get(['id', 'name'])
                ->map(fn (RequestType $type) => ['value' => (string) $type->id, 'label' => $type->name])->values(),
            'channels' => collect(ReceptionChannel::cases())->map(fn (ReceptionChannel $channel) => ['value' => $channel->value, 'label' => $channel->label()])->values(),
            'urgencyLevels' => UrgencyLevel::query()->active()->orderBy('sort_order')->get(['id', 'name', 'response_delay_hours'])
                ->map(fn (UrgencyLevel $level) => [
                    'id' => $level->id,
                    'response_delay_hours' => $level->response_delay_hours,
                    'label' => $level->name.' — '.($level->response_delay_hours % 24 === 0 ? ($level->response_delay_hours / 24).' jour(s)' : $level->response_delay_hours.' h'),
                ])->values(),
            'completeTypes' => RequestType::completeFormIds(),
            'otherTypeId' => RequestType::otherId(),
            'today' => now()->toDateString(),
            // Jeton à usage unique : le serveur refuse un second envoi du même formulaire.
            'submission' => (string) Str::uuid(),
            // Le responsable de chaque département est proposé en premier.
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
            'urls' => [
                'store' => route('external-requests.store'),
                'back' => route('external-requests.index'),
            ],
        ]);
    }

    public function store(StoreExternalRequest $request): RedirectResponse
    {
        if (SubmissionGuard::alreadySubmitted($request)) {
            return redirect()->route('external-requests.index')->with('warning', 'Cette demande externe a déjà été enregistrée : le second envoi a été ignoré.');
        }

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
