<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('manage_users');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('manage_users');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('manage_users');
    }

    /**
     * Liste des employés de son propre département (vue Responsable).
     */
    public function viewDepartmentMembers(User $actor): bool
    {
        return $actor->hasPermission('manage_users') || $actor->hasPermission('deactivate_department_user');
    }

    /**
     * L'administrateur peut désactiver tout compte sauf le sien ; un
     * responsable uniquement un employé actif de son département qui n'est
     * ni lui-même ni un administrateur.
     */
    public function deactivate(User $actor, User $target): bool
    {
        if ($actor->is($target) || ! $target->is_active) {
            return false;
        }

        if ($actor->hasPermission('manage_users')) {
            return true;
        }

        return $actor->hasPermission('deactivate_department_user')
            && $actor->department_id !== null
            && $actor->department_id === $target->department_id
            && ! $target->hasRole(Role::ADMIN);
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $actor->hasPermission('manage_users') && ! $target->is_active;
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->hasPermission('manage_users') && $actor->isNot($target);
    }

    public function changeDepartment(User $actor, User $target): bool
    {
        return $actor->hasPermission('manage_users') && $target->is_active && $actor->isNot($target)
            && ! $target->hasRole(Role::ADMIN);
    }
}
