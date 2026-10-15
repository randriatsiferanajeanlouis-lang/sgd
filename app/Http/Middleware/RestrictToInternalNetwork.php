<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Défense en profondeur : en plus du pare-feu et du serveur web, refuse
 * toute requête venant d'une adresse hors des réseaux internes déclarés
 * (ALLOWED_NETWORKS). Liste vide = pas de filtrage applicatif.
 */
class RestrictToInternalNetwork
{
    public function handle(Request $request, Closure $next): Response
    {
        $networks = array_filter(array_map('trim', explode(',', (string) config('app.allowed_networks'))));

        if ($networks !== [] && ! IpUtils::checkIp((string) $request->ip(), $networks)) {
            abort(403, 'Accès réservé au réseau interne de l’entreprise.');
        }

        return $next($request);
    }
}
