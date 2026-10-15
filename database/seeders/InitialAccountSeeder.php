<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InitialAccountSeeder extends Seeder
{
    /**
     * Comptes indispensables au démarrage : l'Administrateur et le
     * Responsable RH (service RH du département RSE).
     *
     * Un compte existant n'est jamais modifié. Un compte créé reçoit un
     * mot de passe aléatoire affiché une seule fois dans la console,
     * à changer obligatoirement à la première connexion.
     */
    public function run(): void
    {
        $accounts = [
            [
                'username' => 'admin',
                'first_name' => null,
                'last_name' => 'Administrateur',
                'job_title' => 'Administrateur de l’application',
                'role' => Role::ADMIN,
                'department' => null,
            ],
            [
                'username' => 'rh',
                'first_name' => 'Responsable',
                'last_name' => 'RH',
                'job_title' => 'Responsable des ressources humaines',
                'role' => Role::HR_MANAGER,
                'department' => Department::HR_DEPARTMENT_CODE,
            ],
        ];

        foreach ($accounts as $account) {
            if (User::query()->where('username', $account['username'])->exists()) {
                $this->command?->line("Compte « {$account['username']} » déjà présent : inchangé.");

                continue;
            }

            $temporaryPassword = Str::password(14, symbols: false);

            User::query()->create([
                'name' => $account['last_name'],
                'first_name' => $account['first_name'],
                'last_name' => $account['last_name'],
                'job_title' => $account['job_title'],
                'username' => $account['username'],
                'password' => $temporaryPassword,
                'role_id' => Role::query()->where('name', $account['role'])->value('id'),
                'department_id' => $account['department'] ? Department::query()->where('code', $account['department'])->value('id') : null,
                'must_change_password' => true,
            ]);

            $this->command?->warn("Compte « {$account['username']} » créé — mot de passe temporaire : {$temporaryPassword}");
        }
    }
}
