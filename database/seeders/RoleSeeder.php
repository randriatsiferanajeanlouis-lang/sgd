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

        foreach ($this->permissions() as $name => [$group, $label, $description]) {
            $permission = Permission::query()->updateOrCreate(['name' => $name], ['group' => $group, 'label' => $label, 'description' => $description]);

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
     * Réparation : rattache à chaque rôle ses permissions par défaut (par exemple
     * après la perte de la table role_permissions). N'en retire jamais aucune.
     *
     * @return array<string, int> nombre de permissions par défaut par rôle
     */
    public function restoreDefaults(): array
    {
        $this->run();

        $counts = [];

        foreach ($this->roles() as $name => $definition) {
            $role = Role::query()->where('name', $name)->firstOrFail();
            $ids = Permission::query()->whereIn('name', $definition['permissions'])->pluck('id');

            $role->permissions()->syncWithoutDetaching($ids);
            $counts[$name] = $role->permissions()->count();
        }

        return $counts;
    }

    /**
     * Groupes, dans l'ordre d'affichage de l'écran « Rôles et permissions ».
     */
    public const GROUPS = [
        'Demandes',
        'Consultation des dossiers',
        'Traitement des dossiers',
        'Historique et supervision',
        'Utilisateurs',
        'Organisation',
        'Paramétrage',
        'Statistiques',
    ];

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> nom => [groupe, libellé, description]
     */
    private function permissions(): array
    {
        return [
            'create_internal_request' => ['Demandes', 'Créer une demande interne', 'Envoyer une demande à des collègues d’autres départements.'],
            'create_external_request' => ['Demandes', 'Enregistrer une demande externe', 'Saisir une demande reçue d’une personne ou d’un organisme extérieur.'],
            'manage_external_requests' => ['Demandes', 'Suivre les demandes externes', 'Suivre les décisions des responsables et enregistrer la réponse officielle au demandeur.'],

            'view_own_dossiers' => ['Consultation des dossiers', 'Voir ses dossiers', 'Dossiers envoyés ou reçus personnellement.'],
            'view_department_dossiers' => ['Consultation des dossiers', 'Voir les dossiers du département', 'Tous les dossiers émis ou reçus par son département.'],
            'view_all_dossiers' => ['Consultation des dossiers', 'Voir tous les dossiers', 'Accès en lecture à l’ensemble des dossiers de l’entreprise.'],

            'respond_dossier' => ['Traitement des dossiers', 'Répondre à un dossier', 'Rédiger la réponse d’un dossier reçu.'],
            'transmit_dossier' => ['Traitement des dossiers', 'Transmettre un dossier', 'Faire suivre un dossier reçu à un autre utilisateur.'],
            'validate_dossier' => ['Traitement des dossiers', 'Valider ou refuser', 'Décider des demandes de validation transmises par les employés.'],
            'reopen_dossier' => ['Traitement des dossiers', 'Réouvrir un traitement', 'Rouvrir un traitement répondu ou archivé, avec un motif.'],
            'archive_dossier' => ['Traitement des dossiers', 'Archiver un traitement', 'Classer un traitement terminé.'],
            'send_reminder' => ['Traitement des dossiers', 'Relancer un destinataire', 'Envoyer une relance à un destinataire en attente.'],
            'download_document' => ['Traitement des dossiers', 'Télécharger les pièces', 'Télécharger les pièces jointes des dossiers accessibles.'],

            'view_own_history' => ['Historique et supervision', 'Historique de ses dossiers', 'Historique détaillé des dossiers auxquels on participe.'],
            'view_department_history' => ['Historique et supervision', 'Historique du département', 'Historique détaillé des dossiers du département.'],
            'view_all_history' => ['Historique et supervision', 'Historique de tous les dossiers', 'Historique détaillé de l’ensemble des dossiers.'],
            'view_activity_log' => ['Historique et supervision', 'Journal d’activité', 'Connexions et actions d’administration : comptes, départements, rôles, paramètres, sauvegardes.'],

            'manage_users' => ['Utilisateurs', 'Gérer les utilisateurs', 'Créer, modifier, désactiver les comptes et réinitialiser les mots de passe.'],
            'deactivate_department_user' => ['Utilisateurs', 'Désactiver un employé du département', 'Le responsable désactive un compte de son département ; les administrateurs sont prévenus.'],

            'view_departments' => ['Organisation', 'Consulter les départements', 'Liste des départements en lecture seule.'],
            'manage_departments' => ['Organisation', 'Gérer les départements', 'Créer, modifier, activer ou désactiver un département et nommer son responsable.'],

            'manage_request_types' => ['Paramétrage', 'Gérer les types de demandes', 'Ajouter ou modifier les types proposés à la création d’une demande.'],
            'manage_urgencies' => ['Paramétrage', 'Gérer les niveaux d’urgence', 'Délais de réponse et couleurs des niveaux d’urgence.'],
            'manage_permissions' => ['Paramétrage', 'Gérer les rôles et permissions', 'Attribuer ou retirer des permissions à chaque rôle.'],
            'manage_settings' => ['Paramétrage', 'Gérer les paramètres', 'Réglages généraux de l’application.'],

            'view_department_statistics' => ['Statistiques', 'Statistiques du département', 'Indicateurs et graphiques de son département.'],
            'view_global_statistics' => ['Statistiques', 'Statistiques générales', 'Indicateurs et graphiques de tous les départements.'],
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
                    'view_activity_log',
                ],
            ],
        ];
    }
}
