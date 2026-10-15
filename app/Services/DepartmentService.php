<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gestion des départements : jamais supprimés, seulement désactivés,
 * afin de conserver employés, dossiers, documents et historique.
 */
class DepartmentService
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Department
    {
        return DB::transaction(function () use ($attributes) {
            $department = Department::query()->create($attributes);

            $this->auditTrail->record('department.created', $department, newValues: $department->only(['name', 'code', 'email']), department: $department);

            return $department;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Department $department, array $attributes): Department
    {
        return DB::transaction(function () use ($department, $attributes) {
            $department->fill($attributes);
            $changes = $department->getDirty();
            $before = array_intersect_key($department->getOriginal(), $changes);
            $department->save();

            if ($changes !== []) {
                $this->auditTrail->record('department.updated', $department, $before, $changes, department: $department);
            }

            return $department;
        });
    }

    public function setActive(Department $department, bool $active): void
    {
        DB::transaction(function () use ($department, $active) {
            $department->update(['is_active' => $active]);

            $this->auditTrail->record(
                $active ? 'department.reactivated' : 'department.deactivated',
                $department,
                ['is_active' => ! $active],
                ['is_active' => $active],
                department: $department,
            );
        });
    }

    /**
     * Le responsable doit être un compte actif du département. Il reçoit le
     * rôle Responsable s'il était Employé ; l'ancien responsable redevient
     * Employé s'il n'était Responsable que pour cette raison.
     */
    public function assignManager(Department $department, ?User $manager): void
    {
        if ($manager && ($manager->department_id !== $department->id || ! $manager->is_active)) {
            throw new InvalidArgumentException('Le responsable doit être un compte actif de ce département.');
        }

        DB::transaction(function () use ($department, $manager) {
            $previous = $department->manager;
            $roles = Role::query()->whereIn('name', [Role::STAFF, Role::DEPARTMENT_HEAD])->pluck('id', 'name');

            if ($previous && $previous->isNot($manager) && $previous->hasRole(Role::DEPARTMENT_HEAD)) {
                $previous->update(['role_id' => $roles[Role::STAFF]]);
            }

            if ($manager && $manager->hasRole(Role::STAFF)) {
                $manager->update(['role_id' => $roles[Role::DEPARTMENT_HEAD]]);
            }

            $department->update(['manager_id' => $manager?->id]);

            $this->auditTrail->record(
                'department.manager_assigned',
                $department,
                ['manager_id' => $previous?->id],
                ['manager_id' => $manager?->id],
                department: $department,
            );
        });
    }
}
