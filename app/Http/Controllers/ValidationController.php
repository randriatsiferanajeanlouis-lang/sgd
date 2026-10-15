<?php

namespace App\Http\Controllers;

use App\Enums\ValidationStatus;
use App\Models\DossierRecipient;
use App\Models\DossierValidation;
use App\Services\ValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ValidationController extends Controller
{
    public function __construct(private readonly ValidationService $validations) {}

    /**
     * Demandes de validation adressées au responsable connecté.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('validate_dossier'), 403);

        return view('validations.index', [
            'pending' => DossierValidation::query()
                ->with(['dossier.urgencyLevel', 'requester'])
                ->where('validator_id', $request->user()->id)
                ->where('status', ValidationStatus::Pending->value)
                ->oldest()
                ->get(),
            'decided' => DossierValidation::query()
                ->with(['dossier', 'requester'])
                ->where('validator_id', $request->user()->id)
                ->where('status', '!=', ValidationStatus::Pending->value)
                ->latest('decided_at')
                ->paginate(15),
        ]);
    }

    public function store(Request $request, DossierRecipient $recipient): RedirectResponse
    {
        Gate::authorize('requestValidation', $recipient);

        $validated = $request->validate(['comment' => ['nullable', 'string', 'max:5000']]);

        $this->validations->request($recipient, $request->user(), $validated['comment'] ?? null);

        return redirect()->route('dossiers.show', $recipient->dossier_id)->with('status', 'Le dossier a été transmis à votre responsable pour validation.');
    }

    public function decide(Request $request, DossierValidation $validation): RedirectResponse
    {
        Gate::authorize('decide', $validation);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->validations->decide($validation, $request->user(), $validated['decision'] === 'approve', $validated['comment'] ?? null);

        return redirect()->route('dossiers.show', $validation->dossier_id)
            ->with('status', $validated['decision'] === 'approve' ? 'Demande validée.' : 'Demande refusée.');
    }
}
