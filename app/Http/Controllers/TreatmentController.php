<?php

namespace App\Http\Controllers;

use App\Http\Requests\RespondRequest;
use App\Models\DossierRecipient;
use App\Models\User;
use App\Services\TreatmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Actions d'un destinataire sur son propre traitement.
 */
class TreatmentController extends Controller
{
    public function __construct(private readonly TreatmentService $treatments) {}

    public function respond(RespondRequest $request, DossierRecipient $recipient): RedirectResponse
    {
        $this->treatments->respond(
            $recipient,
            $request->user(),
            $request->safe()->only(['content', 'decision', 'justification', 'recommendation']),
            $request->file('attachments', []),
        );

        return redirect()->route('dossiers.show', $recipient->dossier_id)->with('status', 'Votre réponse a été envoyée.');
    }

    public function reopen(Request $request, DossierRecipient $recipient): RedirectResponse
    {
        Gate::authorize('reopen', $recipient);

        $validated = $request->validate(['comment' => ['required', 'string', 'max:1000']]);

        $this->treatments->reopen($recipient, $request->user(), $validated['comment']);

        return redirect()->route('dossiers.show', $recipient->dossier_id)->with('status', 'Le traitement a été réouvert.');
    }

    public function forward(Request $request, DossierRecipient $recipient): RedirectResponse
    {
        Gate::authorize('forward', $recipient);

        $validated = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('is_active', true)->whereNotNull('department_id'),
                Rule::notIn([$request->user()->id]),
                Rule::unique('dossier_recipients', 'assigned_user_id')->where('dossier_id', $recipient->dossier_id),
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], ['user_id.unique' => 'Cette personne est déjà destinataire du dossier.']);

        $target = User::query()->with('department')->findOrFail($validated['user_id']);
        $this->treatments->forward($recipient, $request->user(), $target, $validated['comment'] ?? null);

        return redirect()->route('dossiers.show', $recipient->dossier_id)->with('status', "Dossier transmis à {$target->name}.");
    }

    public function archive(DossierRecipient $recipient): RedirectResponse
    {
        Gate::authorize('archive', $recipient);

        $this->treatments->archive($recipient);

        return redirect()->route('dossiers.show', $recipient->dossier_id)->with('status', 'Votre traitement a été archivé.');
    }
}
