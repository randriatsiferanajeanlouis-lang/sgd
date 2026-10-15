<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * À chaque requête d'un utilisateur connecté :
 *  - un compte désactivé perd immédiatement l'accès, même avec une session ouverte ;
 *  - un mot de passe temporaire doit être remplacé avant toute autre action.
 */
class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => __('auth.inactive')]);
        }

        if ($user->must_change_password && ! $request->routeIs('password.change', 'password.change.update', 'logout')) {
            return redirect()->route('password.change')->with('warning', __('auth.must_change_password'));
        }

        return $next($request);
    }
}
