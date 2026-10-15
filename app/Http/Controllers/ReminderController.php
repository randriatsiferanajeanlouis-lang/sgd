<?php

namespace App\Http\Controllers;

use App\Models\DossierRecipient;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReminderController extends Controller
{
    /**
     * Relance rapide : « Rappel concernant le dossier DOS-… » par défaut.
     */
    public function store(Request $request, DossierRecipient $recipient, ReminderService $reminders): RedirectResponse
    {
        Gate::authorize('remind', $recipient);

        $validated = $request->validate(['message' => ['nullable', 'string', 'max:500']]);

        $reminders->sendManual($recipient, $request->user(), $validated['message'] ?? null);

        return back()->with('status', "Relance envoyée à {$recipient->assignedUser->name}.");
    }
}
