<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Activation/désactivation d'un compte, partagée entre l'administrateur
 * (tous les comptes) et le responsable (employés de son département).
 */
class UserStatusController extends Controller
{
    public function __construct(private readonly UserAccountService $accounts) {}

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $validated = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        $this->accounts->deactivate($user, $request->user(), $validated['comment'] ?? null);

        return back()->with('status', "Le compte de {$user->name} a été désactivé. Son historique est conservé.");
    }

    public function reactivate(User $user): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $this->accounts->reactivate($user);

        return back()->with('status', "Le compte de {$user->name} a été réactivé.");
    }
}
