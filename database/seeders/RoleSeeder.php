<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Catalogue des permissions et rôles par défaut.
     *
     * Les permissions sont ensuite gérées depuis l'interface (manage_permissions) :
     * relancer ce seeder ne retire jamais une permission retirée ou ajoutée
     * à la main. Il n'attribue les valeurs par défaut qu'aux rôles nouvellement
     * créés, et ajoute aux rôles existants uniquement les permissions
     * nouvellement apparues dans le catalogue.
     */
    public function run(): void
    {
        Permission::query()->whereNotIn('name', array_keys($this->permissions()))->delete();

        $newPermissionNames = [];

        foreach ($this->permissions() as $name => [$group, $label]) {
            $permission = Permission::query()->updateOrCreate(['name' => $name], ['group' => $group, 'label' => $label]);

            if ($permission->wasRecentlyCreated) {
                $newPermissionNames[] = $name;
            }
        }

        foreach ($this->roles() as $name => $definition) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name],
                ['label' => $definition['label'], 'description' => $definition['description'], 'is_system' => true],
            );

            $defaults = $role->wasRecentlyCreated
                ? $definition['permissions']
                : array_intersect($definition['permissions'], $newPermissionNames);

            $role->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('name', $defaults)->pluck('id')
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function permissions(): array
    {
        return [
            'create_internal_request' => ['Dossiers', 'Créer une demande interne'],
            'create_external_request' => ['Dossiers', 'Enregistrer une demande externe'],
            'manage_external_requests' => ['Dossiers', 'Suivre les demandes externes et enregistrer la réponse officielle'],
            'view_own_dossiers' => ['Dossiers', 'Voir ses dossiers'],
            'view_department_dossiers' => ['Dossiers', 'Voir les dossiers de son département'],
            'view_all_dossiers' => ['Dossiers', 'Voir tous les dossiers'],
            'respond_dossier' => ['Dossiers', 'Répondre à un dossier'],
            'transmit_dossier' => ['Dossiers', 'Transmettre un dossier'],
            'validate_dossier' => ['Dossiers', 'Valider ou refuser une demande de validation'],
            'reopen_dossier' => ['Dossiers', 'Réouvrir un traitement archivé'],
            'archive_dossier' => ['Dossiers', 'Archiver un traitement'],
            'send_reminder' => ['Dossiers', 'Relancer un destinataire'],
            'view_own_history' => ['Historique', 'Voir l’historique de ses dossiers'],
            'view_department_history' => ['Historique', 'Voir l’historique des dossiers du département'],
            'view_all_history' => ['Historique', 'Voir l’historique de tous les dossiers'],
            'download_document' => ['Documents', 'Télécharger les documents autorisés'],
            'manage_users' => ['Utilisateurs', 'Gérer les utilisateurs'],
            'deactivate_department_user' => ['Utilisateurs', 'Désactiver un employé de son département'],
            'view_departments' => ['Administration', 'Consulter la liste des départements (lecture seule)'],
            'manage_departments' => ['Administration', 'Gérer les départements'],
            'manage_request_types' => ['Administration', 'Gérer les types de demandes'],
            'manage_urgencies' => ['Administration', 'Gérer les niveaux d’urgence'],
            'manage_permissions' => ['Administration', 'Gérer les rôles et permissions'],
            'manage_settings' => ['Administration', 'Gérer les paramètres'],
            'view_department_statistics' => ['Statistiques', 'Voir les statistiques du département'],
            'view_global_statistics' => ['Statistiques', 'Voir les statistiques générales'],
        ];
    }

    /**
     * @return array<string, array{label: string, description: string, permissions: list<string>}>
     */
    private function roles(): array
    {
        $employee = [
            'create_internal_request',
            'view_own_dossiers',
            'view_own_history',
            'respond_dossier',
            'transmit_dossier',
            'archive_dossier',
            'send_reminder',
            'download_document',
        ];

        return [
            Role::STAFF => [
                'label' => 'Employé',
                'description' => 'Crée des demandes internes, traite et répond aux dossiers reçus.',
                'permissions' => $employee,
            ],
            Role::DEPARTMENT_HEAD => [
                'label' => 'Responsable',
                'description' => 'Responsable d’un département : dossiers, employés, historique et statistiques du département, validations.',
                'permissions' => [
                    ...$employee,
                    'view_department_dossiers',
                    'view_department_history',
                    'validate_dossier',
                    'reopen_dossier',
                    'deactivate_department_user',
                    'view_department_statistics',
                    'view_departments',
                ],
            ],
            Role::HR_MANAGER => [
                'label' => 'RH',
                'description' => 'Service RH du département RSE : seul à enregistrer les demandes externes et à envoyer la réponse officielle.',
                'permissions' => [
                    ...$employee,
                    'create_external_request',
                    'manage_external_requests',
                    'view_departments',
                ],
            ],
            // Pas de permission d'historique : l'administrateur voit les dossiers
            // mais pas leur historique détaillé (modifiable depuis l'écran des permissions).
            Role::ADMIN => [
                'label' => 'Administrateur',
                'description' => 'Gère les utilisateurs, départements, rôles, permissions et paramètres.',
                'permissions' => [
                    'create_internal_request',
                    'view_own_dossiers',
                    'view_all_dossiers',
                    'respond_dossier',
                    'transmit_dossier',
                    'archive_dossier',
                    'send_reminder',
                    'download_document',
                    'manage_users',
                    'manage_departments',
                    'manage_request_types',
                    'manage_urgencies',
                    'manage_permissions',
                    'manage_settings',
                    'view_global_statistics',
                ],
            ],
        ];
    }
}
