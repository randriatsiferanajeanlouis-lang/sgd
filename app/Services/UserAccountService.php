<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Notifications\UserDeactivatedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Opérations sensibles sur les comptes, toutes tracées dans le journal d'audit.
 * Un compte n'est jamais supprimé : il est désactivé.
 */
class UserAccountService
{
    private const TRACKED_FIELDS = ['first_name', 'last_name', 'username', 'email', 'phone', 'job_title', 'role_id', 'department_id', 'is_active'];

    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?UploadedFile $photo = null): User
    {
        return DB::transaction(function () use ($attributes, $photo) {
            $user = User::query()->create([
                ...$attributes,
                'photo_path' => $photo?->store('profile-photos', 'local'),
                'must_change_password' => true,
            ]);

            $this->auditTrail->record('user.created', $user, newValues: $user->only(self::TRACKED_FIELDS), department: $user->department);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes) {
            $user->fill($attributes);
            $changes = array_intersect_key($user->getDirty(), array_flip(self::TRACKED_FIELDS));
            $before = array_intersect_key($user->getOriginal(), $changes);

            $user->save();

            if ($changes !== []) {
                $this->auditTrail->record('user.updated', $user, $before, $changes, department: $user->department);
            }

            return $user;
        });
    }

    /**
     * Désactivation : l'historique est conservé. Quand un responsable
     * désactive un employé de son département, les administrateurs sont notifiés.
     */
    public function deactivate(User $target, User $actor, ?string $comment = null): void
    {
        DB::transaction(function () use ($target, $comment) {
            $target->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();

            $this->auditTrail->record('user.deactivated', $target, ['is_active' => true], ['is_active' => false], $comment, $target->department);
        });

        if (! $actor->hasPermission('manage_users')) {
            Notification::send(
                User::query()->active()->whereHas('role.permissions', fn ($query) => $query->where('name', 'manage_users'))->get(),
                new UserDeactivatedNotification($target, $actor, now()),
            );
        }
    }

    public function reactivate(User $target): void
    {
        DB::transaction(function () use ($target) {
            $target->forceFill(['is_active' => true])->save();

            $this->auditTrail->record('user.reactivated', $target, ['is_active' => false], ['is_active' => true], department: $target->department);
        });
    }

    /**
     * Changement de département : l'ancien compte est désactivé (son historique
     * reste attaché) et un nouveau compte est créé dans le nouveau département.
     *
     * @return array{user: User, temporary_password: string}
     */
    public function changeDepartment(User $oldAccount, Department $department, string $newUsername, ?string $jobTitle): array
    {
        $temporaryPassword = Str::password(12, symbols: false);

        $newAccount = DB::transaction(function () use ($oldAccount, $department, $newUsername, $jobTitle, $temporaryPassword) {
            $oldAccount->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();

            $newAccount = User::query()->create([
                'first_name' => $oldAccount->first_name,
                'last_name' => $oldAccount->last_name,
                'username' => $newUsername,
                'email' => null,
                'phone' => $oldAccount->phone,
                'password' => $temporaryPassword,
                'department_id' => $department->id,
                'role_id' => $oldAccount->role_id,
                'job_title' => $jobTitle ?? $oldAccount->job_title,
                'must_change_password' => true,
                'replaces_user_id' => $oldAccount->id,
            ]);

            $this->auditTrail->record(
                'user.department_changed',
                $oldAccount,
                ['department_id' => $oldAccount->department_id, 'username' => $oldAccount->username],
                ['department_id' => $department->id, 'username' => $newUsername, 'new_user_id' => $newAccount->id],
                department: $oldAccount->department,
            );

            $this->auditTrail->record('user.created', $newAccount, newValues: $newAccount->only(self::TRACKED_FIELDS), department: $department);

            return $newAccount;
        });

        return ['user' => $newAccount, 'temporary_password' => $temporaryPassword];
    }

    /**
     * Génère un mot de passe temporaire, le hash, force son changement à la
     * prochaine connexion et invalide les sessions « se souvenir de moi ».
     * Le mot de passe en clair est renvoyé une seule fois pour être remis
     * à l'utilisateur ; il n'est jamais stocké.
     */
    public function resetPassword(User $user): string
    {
        $temporaryPassword = Str::password(12, symbols: false);

        DB::transaction(function () use ($user, $temporaryPassword) {
            $user->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            $this->auditTrail->record('user.password_reset', $user, department: $user->department);
        });

        return $temporaryPassword;
    }
}
