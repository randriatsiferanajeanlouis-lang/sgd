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
                    $user->can('createInternal', Dossier::class) ? self::item('Nouvelle demande', 'dossiers.create', 'dossiers.create', 'plus') : null,
                    $user->hasPermission('view_own_dossiers') ? self::item('Voir tous les dossiers', 'dossiers.index', 'dossiers.index', 'folder') : null,
                    $user->can('viewAnyHistory', Dossier::class) ? self::item('Historique', 'history.index', 'history.*', 'clock') : null,
                    self::item('Notifications', 'notifications.index', 'notifications.*', 'bell'),
                    $user->can('createExternal', Dossier::class) ? self::item('Nouvelle demande externe', 'external-requests.create', 'external-requests.create', 'mail') : null,
                ],
            ],
            [
                'label' => 'Responsable',
                'items' => [
                    $user->hasPermission('view_department_dossiers') ? self::item('Dossiers du département', 'dossiers.department', 'dossiers.department', 'inbox') : null,
                    $user->hasPermission('validate_dossier') ? self::item('Validations', 'validations.index', 'validations.*', 'check') : null,
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
                    $user->hasPermission('manage_departments') ? self::item('Départements', 'admin.departments.index', 'admin.departments.*', 'building') : null,
                    $user->hasPermission('manage_settings') || $user->hasPermission('manage_permissions') ? self::item('Paramètres', 'admin.settings.index', 'admin.settings.*', 'cog') : null,
                    $user->hasPermission('view_global_statistics') ? self::item('Statistiques', 'statistics.index', 'statistics.*', 'chart') : null,                ],
            ],
        ];

        return collect($sections)
            ->map(fn (array $section) => [
                'label' => $section['label'],
                'items' => array_values(array_filter($section['items'], fn (?array $item) => $item !== null && Route::has($item['route']))),
            ])
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return array{label: string, route: string, active: string, icon: string}
     */
    private static function item(string $label, string $route, string $active, string $icon): array
    {
        return compact('label', 'route', 'active', 'icon');
    }
}
