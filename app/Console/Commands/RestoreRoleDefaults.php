<?php

namespace App\Console\Commands;

use Database\Seeders\RoleSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('roles:restore-defaults')]
#[Description('Rattache à chaque rôle ses permissions par défaut (réparation après perte de role_permissions), sans rien retirer.')]
class RestoreRoleDefaults extends Command
{
    public function handle(RoleSeeder $seeder): int
    {
        foreach ($seeder->restoreDefaults() as $role => $count) {
            $this->line("  {$role} : {$count} permission(s)");
        }

        $this->info('Permissions par défaut restaurées.');

        return self::SUCCESS;
    }
}
