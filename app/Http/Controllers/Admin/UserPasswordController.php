<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UserPasswordController extends Controller
{
    /**
     * Réinitialisation par l'administrateur : le mot de passe temporaire est
     * affiché une seule fois, puis l'utilisateur doit le remplacer.
     */
    public function store(User $user, UserAccountService $accounts): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $temporaryPassword = $accounts->resetPassword($user);

        return back()->with('temporary_password', [
            'username' => $user->username,
            'password' => $temporaryPassword,
        ]);
    }
}
