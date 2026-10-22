<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Str;

/**
 * Journal d'activité : connexions, déconnexions et échecs de connexion.
 * Le mot de passe saisi n'est jamais enregistré.
 */
class RecordAuthenticationActivity
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->auditTrail->record('auth.login', $event->user, department: $event->user->department, actor: $event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->auditTrail->record('auth.logout', $event->user, department: $event->user->department, actor: $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $username = Str::limit((string) ($event->credentials['username'] ?? ''), 60, '');

        $this->auditTrail->record(
            'auth.failed',
            $event->user instanceof User ? $event->user : null,
            comment: $username !== '' ? 'Identifiant saisi : '.$username : null,
        );
    }
}
