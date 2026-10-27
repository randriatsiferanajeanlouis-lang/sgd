<?php

namespace App\Support;

use App\Models\Dossier;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu latéral, construit à partir des permissions de l'utilisateur.
 * Une entrée n'apparaît que si sa route existe et si l'utilisateur y a droit ;
 * le contrôle côté serveur reste fait par les routes et les policies.
 */
class Navigation
{
    /**
     * @return list<array{label: string, items: list<array{label: string, route: string, active: string, icon: string}>}>
     */
    public static function for(User $user): array
    {
        $sections = [
            [
                'label' => 'Espace personnel',
                'items' => [
                    self::item('Tableau de bord', 'dashboard', 'dashboard', 'home'),
                    // « Demande » ouvre la liste des demandes ; « Nouvelle demande » et « Voir les dossiers »
                    // sont des boutons de cette page. Sans droit de créer (administrateur),
                    // « Voir les dossiers » reste une entrée du menu.
                    $user->can('createInternal', Dossier::class)
                        ? self::item('Demande', 'requests.index', 'requests.index|dossiers.create|dossiers.index|external-requests.create', 'send')
                        : ($user->hasPermission('view_own_dossiers') ? self::item('Voir les dossiers', 'dossiers.index', 'dossiers.index', 'folder-open') : null),
                    $user->can('viewAnyHistory', Dossier::class) ? self::item('Historique', 'history.index', 'history.*', 'clock') : null,
                    self::item('Notifications', 'notifications.index', 'notifications.*', 'bell'),
                    $user->can('viewTrash', Dossier::class) ? self::item('Corbeille', 'trash.index', 'trash.*', 'trash') : null,
                ],
            ],
            [
                'label' => 'Responsable',
                'items' => [
                    $user->hasPermission('deactivate_department_user') ? self::item('Employés', 'department-members.index', 'department-members.*', 'users') : null,
                    $user->hasPermission('view_departments') && ! $user->hasPermission('manage_departments')
                        ? self::item('Départements', 'admin.departments.index', 'admin.departments.*', 'building') : null,
                    $user->hasPermission('view_department_statistics') && ! $user->hasPermission('view_global_statistics')
                        ? self::item('Statistiques', 'statistics.index', 'statistics.*', 'chart') : null,
                ],
            ],
            [
                'label' => 'Administration',
                'items' => [
                    $user->hasPermission('manage_users') ? self::item('Utilisateurs', 'admin.users.index', 'admin.users.*', 'users') : null,
                    $user->hasPermission('view_all_dossiers') ? self::item('Suivi des dossiers', 'admin.dossiers.index', 'admin.dossiers.*', 'folder-open') : null,
                    $user->hasPermission('manage_departments') ? self::item('Départements', 'admin.departments.index', 'admin.departments.*', 'building') : null,
                    $user->hasPermission('manage_permissions') ? self::item('Rôles et permissions', 'admin.roles.index', 'admin.roles.*', 'shield') : null,
                    $user->hasPermission('view_activity_log') ? self::item('Journal d’activité', 'admin.activity.index', 'admin.activity.*', 'activity') : null,
                    $user->hasPermission('manage_settings') || $user->hasPermission('manage_permissions') ? self::item('Paramètres', 'admin.settings.index', 'admin.settings.*', 'cog') : null,
                    $user->hasPermission('view_global_statistics') ? self::item('Statistiques', 'statistics.index', 'statistics.*', 'chart') : null,                ],
            ],
        ];

        return collect($sections)
            ->map(fn (array $section) => [
                'label' => $section['label'],
                'items' => self::available($section['items']),
            ])
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * Pages proposées par la recherche rapide (Ctrl K) : le menu de
     * l'utilisateur, plus les pages de son compte.
     *
     * @return list<array{label: string, items: list<array{label: string, url: string}>}>
     */
    public static function commands(User $user): array
    {
        // Sous-menus aplatis, plus un accès direct au formulaire « Nouvelle demande ».
        $groups = array_map(fn (array $section) => [
            'label' => $section['label'],
            'items' => collect($section['items'])
                ->flatMap(fn (array $item) => [$item, ...$item['children']])
                ->map(fn (array $item) => ['label' => $item['label'], 'url' => route($item['route'])])
                ->values()
                ->all(),
        ], self::for($user));

        if ($user->can('createInternal', Dossier::class)) {
            $groups[0]['items'][] = ['label' => 'Nouvelle demande', 'url' => route('dossiers.create')];

            if ($user->hasPermission('view_own_dossiers')) {
                $groups[0]['items'][] = ['label' => 'Voir les dossiers', 'url' => route('dossiers.index')];
            }
        }

        // Plus dans le menu : bouton de la page « Demande », mais toujours trouvable par la recherche rapide.
        if ($user->can('createExternal', Dossier::class)) {
            $groups[0]['items'][] = ['label' => 'Nouvelle demande externe', 'url' => route('external-requests.create')];
        }

        $groups[] = ['label' => 'Mon compte', 'items' => [
            ['label' => 'Mon profil', 'url' => route('profile.show')],
            ['label' => 'Changer mon mot de passe', 'url' => route('password.change')],
        ]];

        return $groups;
    }

    /**
     * Entrées existantes (route déclarée), sous-menus compris.
     *
     * @param  list<array<string, mixed>|null>  $items
     * @return list<array<string, mixed>>
     */
    private static function available(array $items): array
    {
        return collect($items)
            ->filter(fn (?array $item) => $item !== null && Route::has($item['route']))
            ->map(fn (array $item) => [...$item, 'children' => self::available($item['children'])])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>|null>  $children  sous-menu (affiché sous l'entrée)
     * @return array{label: string, route: string, active: string, icon: string, children: list<array<string, mixed>|null>}
     */
    private static function item(string $label, string $route, string $active, string $icon, array $children = []): array
    {
        return compact('label', 'route', 'active', 'icon', 'children');
    }
}
