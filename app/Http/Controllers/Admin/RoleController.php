<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rôles et permissions (page React) : permissions regroupées par domaine,
 * une fiche par rôle et une matrice de synthèse. L'enregistrement passe par
 * SettingsController@updateRolePermissions (tracé dans le journal).
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage_permissions');

        $permissions = Permission::query()->orderBy('id')->get(['id', 'name', 'label', 'group', 'description']);
        $order = array_flip(RoleSeeder::GROUPS);

        return Inertia::render('Admin/Roles', [
            'title' => 'Rôles et permissions',
            'groups' => $permissions->groupBy('group')
                ->sortBy(fn ($items, string $group) => $order[$group] ?? PHP_INT_MAX)
                ->map(fn ($items, string $group) => [
                    'name' => $group,
                    'permissions' => $items->map(fn (Permission $permission) => $permission->only(['id', 'name', 'label', 'description']))->values(),
                ])
                ->values(),
            'roles' => Role::query()->with('permissions:id')->withCount(['users', 'users as active_users_count' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('id')->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => $role->label,
                    'description' => $role->description,
                    'users' => $role->users_count,
                    'activeUsers' => $role->active_users_count,
                    'permissions' => $role->permissions->pluck('id')->values(),
                    'updateUrl' => route('admin.settings.roles.permissions', $role),
                    'isOwnRole' => $role->id === request()->user()->role_id,
                ]),
            'lockedPermission' => $permissions->firstWhere('name', 'manage_permissions')?->id,
        ]);
    }
}
