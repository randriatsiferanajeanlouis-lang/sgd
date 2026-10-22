<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Pont Laravel ↔ React (Inertia.js). Les pages React s'affichent dans la
 * mise en page Blade existante (menu, en-tête, pied de page) : seules les
 * pages rendues avec Inertia::render() passent par React, les autres restent
 * en Blade.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * Vue racine : resources/views/inertia.blade.php.
     *
     * @var string
     */
    protected $rootView = 'inertia';

    /**
     * Les visiteurs non connectés (page de connexion) ont leur propre vue
     * racine, sans menu ni en-tête : resources/views/inertia-guest.blade.php.
     */
    public function rootView(Request $request): string
    {
        return $request->user() ? $this->rootView : 'inertia-guest';
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Données partagées avec toutes les pages React.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department' => $user->department?->name,
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
