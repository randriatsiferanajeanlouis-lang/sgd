# Gestion des correspondances — SOGEDIPROMA

Application web interne de **gestion, suivi, traitement, réponse, archivage et traçabilité**
des correspondances internes et externes de SOGEDIPROMA.

Règle directrice : **aucune information métier importante ne disparaît sans trace.**
Chaque action (création, envoi, ouverture, transmission, validation, réponse, document,
modification, relance, archivage, réouverture d'un traitement) est historisée avec son auteur,
sa date, son heure, l'ancienne et la nouvelle valeur.

## Fonctionnalités

- **Authentification** par nom d'utilisateur, comptes créés par l'administrateur, profil, mode clair/sombre.
- **Rôles et permissions** : Employé, Responsable, RH, Administrateur ; permissions modifiables.
- **Départements et utilisateurs** : Administration, RSE, Collecte, Production, Armement, Technique.
- **Demandes internes** : un employé écrit à une ou plusieurs personnes (département → utilisateur) ;
  chaque destinataire a son propre traitement : *Reçu → En cours → Répondu → Archivé*.
- **Validation hiérarchique** : un employé transmet à son responsable, qui valide ou refuse.
- **Demandes externes** : le **RH** (service du département RSE) est la porte d'entrée unique ;
  il enregistre, transmet aux responsables, reçoit leurs décisions (positive/négative),
  envoie la réponse officielle et archive.
- **Documents versionnés** (V1 → V2), stockage privé, téléchargement contrôlé, archive ZIP des pièces actives.
- **Historique non supprimable** (protégé par des triggers MySQL), export PDF / Word A4.
- **Notifications internes**, échéances (24 h / 3 j / 7 j pour l'externe), alertes de retard, relances.

## Architecture en bref

Laravel 13 (PHP 8.5) · MySQL/MariaDB · Blade + Tailwind CSS 4 + Alpine.js.
Contrôleurs courts → **Services** (logique métier, transactions) → **Policies** (autorisations)
→ **Form Requests** (validation). Détails : [`docs/architecture.md`](docs/architecture.md).

## Prérequis

PHP ≥ 8.3 (testé en 8.5) avec `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `intl`, `zip`, `dom` ·
MySQL 8 / MariaDB 10.4+ · Composer 2 · Node.js 20+ et npm (compilation de l'interface).

## Installation

```bash
composer install
cp .env.example .env            # compléter DB_*, APP_URL ; APP_ENV=local et APP_DEBUG=true en développement
php artisan key:generate
php artisan migrate --seed      # référentiels + comptes admin et rh (mots de passe affichés une seule fois)
npm install && npm run build
php artisan serve               # http://127.0.0.1:8000 (développement)
```

## Configuration

Toute la configuration est dans `.env` (voir [`.env.example`](.env.example), commenté) :
base MySQL, fuseau `Indian/Antananarivo`, réseaux autorisés (`ALLOWED_NETWORKS`, vide = pas de filtrage).
Les types de demandes, délais d'urgence, permissions et le préfixe des numéros se règlent depuis
**Administration → Paramètres**.

## Base de données

```bash
php artisan migrate             # jamais migrate:fresh sur une base contenant des données
php artisan db:seed             # ne crée que ce qui manque, n'écrase jamais un réglage
```

Schéma et MCD : [`docs/database.md`](docs/database.md).

## Planificateur

`php artisan schedule:run` doit tourner **chaque minute** (ou `php artisan schedule:work`) :
les alertes d'échéance (`dossiers:check-deadlines`) sont envoyées toutes les heures, sans doublon.

## Tests

```bash
php artisan test
```

Les tests utilisent une base séparée (`SGD_data_test`, voir `phpunit.xml`), jamais la base réelle.

## Documentation

| Document | Public |
|---|---|
| [Architecture](docs/architecture.md), [Base de données](docs/database.md), [Permissions](docs/permissions.md), [Workflows](docs/workflows.md) | Développeurs, encadreur |
| [Guide Administrateur](docs/guides/guide-administrateur.md) · [Guide RH](docs/guides/guide-rh.md) · [Guide Responsable](docs/guides/guide-responsable.md) · [Guide Employé](docs/guides/guide-employe.md) | Utilisateurs |

## Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| Aucune alerte d'échéance | Planificateur arrêté | Lancer `php artisan schedule:work`, vérifier `php artisan schedule:list` |
| Modification du `.env` sans effet | Configuration en cache | `php artisan optimize:clear` |
| Styles absents | Interface non compilée | `npm run build` |
| Pièce jointe refusée | Format ou taille | PDF, Word, Excel, images ; 20 Mo et 10 fichiers maximum |
| Erreur technique | Voir `storage/logs/laravel.log` | Ne jamais activer `APP_DEBUG=true` en production |
