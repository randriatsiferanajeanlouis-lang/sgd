<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    /**
     * Annuaire (départements, noms, fonctions) : ouvert à tout utilisateur
     * connecté, pour trouver un destinataire.
     */
    public function viewDirectory(User $user): bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_departments') || $user->hasPermission('view_departments');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_departments');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasPermission('manage_departments');
    }

    public function viewStatistics(User $user, Department $department): bool
    {
        return $user->hasPermission('view_global_statistics')
            || ($user->hasPermission('view_department_statistics') && $user->department_id === $department->id);
    }
}
