# Gestion des correspondances — SOGEDIPROMA

Application web interne de **gestion, suivi, traitement, réponse, archivage et traçabilité**
des correspondances internes et externes de la société SOGEDIPROMA.

> Projet réalisé par **Jean Louis RANDRIATSIFERANA**.

Règle directrice : **aucune information métier importante ne disparaît sans trace.**
Chaque action (création, envoi, ouverture, transmission, validation, réponse, document,
modification, relance, archivage, réouverture d'un traitement) est historisée avec son auteur,
sa date, son heure, l'ancienne et la nouvelle valeur.

---

## Pour l'encadreur — vérifier le projet en 10 minutes

| Ce qu'il faut regarder | Où |
|---|---|
| Vue d'ensemble technique (couches, schémas Mermaid) | [`docs/architecture.md`](docs/architecture.md) |
| Modèle de données (MCD, tables, contraintes, triggers) | [`docs/database.md`](docs/database.md) |
| Qui a le droit de faire quoi | [`docs/permissions.md`](docs/permissions.md) |
| Circuits métier (demande interne, externe, validation, échéances) | [`docs/workflows.md`](docs/workflows.md) |
| Logique métier (transactions, historique, notifications) | [`app/Services/`](app/Services) |
| Règles d'accès | [`app/Policies/`](app/Policies) · [`app/Support/Navigation.php`](app/Support/Navigation.php) |
| Tests automatisés (**183 tests, 1 093 vérifications**) | [`tests/Feature/`](tests/Feature) — scénarios complets dans `FunctionalScenariosTest.php` et `GlobalScenariosTest.php` |

Pour lancer l'application en local, voir [Installation](#installation) ; pour exécuter les tests : `php artisan test`.

---

## Rôles

| Rôle | Rôle dans l'application |
|---|---|
| **Administrateur** (super administrateur) | Compte indépendant, rattaché à **aucun département**. Gère les utilisateurs, les départements, les rôles, les permissions et les paramètres ; voit tous les dossiers et les statistiques globales. Il n'envoie ni ne reçoit de dossiers. |
| **Responsable** de département | Dossiers et employés de son département, validations, statistiques du département, réouverture d'un traitement, désactivation d'un employé de son département ; consulte la liste des départements **en lecture seule**. |
| **RH** (service du département RSE) | Porte d'entrée unique des **demandes externes** : enregistrement, transmission aux responsables, réponse officielle, archivage ; consulte la liste des départements en lecture seule. |
| **Employé** | Crée des demandes internes, traite et répond aux dossiers reçus, transmet à un collègue, relance. |

Les permissions de chaque rôle sont modifiables depuis **Administration → Paramètres**.

## Fonctionnalités

- **Authentification** par nom d'utilisateur, comptes créés par l'administrateur, mot de passe temporaire
  à changer à la première connexion, blocage après 5 essais, profil, mode clair/sombre.
- **Départements et utilisateurs** : Administration, RSE, Collecte, Production, Armement, Technique.
- **Demandes internes** : un employé écrit à une ou plusieurs personnes (département → utilisateur) ;
  chaque destinataire a son propre traitement : *Reçu → En cours → Répondu → Archivé*.
- **Validation hiérarchique** : un employé transmet à son responsable, qui valide ou refuse.
- **Demandes externes** : le RH enregistre, transmet aux responsables, reçoit leurs décisions
  (positive/négative), envoie la réponse officielle et archive.
- **Mes dossiers** : boîtes Reçus / Envoyés / Archivés / Tous, **cartes de synthèse** (total général et total
  par département, cliquables pour filtrer) et recherche par **référence, objet ou nom de l'expéditeur**
  (propositions pendant la saisie).
- **Documents versionnés** (V1 → V2), stockage privé, téléchargement contrôlé, archive ZIP des pièces actives.
- **Historique non supprimable** (protégé par des triggers MySQL), consultable dans chaque dossier et sur la
  page **Historique** du menu, export PDF / Word A4.
- **Échéances** selon le niveau d'urgence (24 h / 3 j / 7 j…), relances manuelles et **alertes automatiques** :
  échéance du jour, puis **délai dépassé** — notification dans l'application et **e-mail** au destinataire
  et à l'expéditeur (jamais à l'administrateur), une seule fois par traitement.
- **Tableaux de bord et statistiques** par utilisateur, par département et globales (courbes et barres).
- **Interface** responsive (ordinateur et téléphone), charte graphique bleu roi / bleu nuit, police Manrope
  servie localement.

## Architecture en bref

Laravel 13 (PHP 8.5) · MySQL/MariaDB · Blade + Tailwind CSS 4 + Alpine.js · Chart.js · DomPDF / PhpWord.

Contrôleurs courts → **Services** (logique métier, transactions) → **Policies** (autorisations)
→ **Form Requests** (validation serveur). Une même règle d'accès sert à la fois aux pages, aux listes,
à la recherche et aux exports (ex. `Dossier::visibleTo()`, `Dossier::historyVisibleTo()`).
Détails : [`docs/architecture.md`](docs/architecture.md).

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

Les mots de passe initiaux sont **aléatoires** et affichés une seule fois dans la console ; ils doivent être
changés à la première connexion. Aucun mot de passe n'est stocké dans ce dépôt.

## Configuration

Toute la configuration est dans `.env` (voir [`.env.example`](.env.example), commenté) :
base MySQL, fuseau `Indian/Antananarivo`, réseaux autorisés (`ALLOWED_NETWORKS`, vide = pas de filtrage),
serveur de messagerie (`MAIL_*`) pour les alertes de délai dépassé — avec `MAIL_MAILER=log`, les e-mails
sont seulement écrits dans `storage/logs/laravel.log`.
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
Sous Windows : une tâche du Planificateur de tâches qui lance `php artisan schedule:run` chaque minute.

## Tests

```bash
php artisan test
```

183 tests (autorisations, circuits complets, historique, documents, échéances et e-mails, recherche,
interface). Ils utilisent une base séparée (`SGD_data_test`, voir `phpunit.xml`), jamais la base réelle.

## Documentation

| Document | Public |
|---|---|
| [Architecture](docs/architecture.md), [Base de données](docs/database.md), [Permissions](docs/permissions.md), [Workflows](docs/workflows.md) | Développeurs, encadreur |
| [Guide Administrateur](docs/guides/guide-administrateur.md) · [Guide RH](docs/guides/guide-rh.md) · [Guide Responsable](docs/guides/guide-responsable.md) · [Guide Employé](docs/guides/guide-employe.md) | Utilisateurs |

## Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| Connexion refusée | Identifiant/mot de passe faux, compte désactivé, ou 5 essais échoués | Attendre la fin du blocage ; l'administrateur réactive le compte ou réinitialise le mot de passe |
| Redirigé vers « Changer mon mot de passe » | Mot de passe temporaire | Choisir un nouveau mot de passe |
| Aucune alerte d'échéance | Planificateur arrêté | Lancer `php artisan schedule:work`, vérifier `php artisan schedule:list` |
| Aucun e-mail reçu | `MAIL_MAILER=log` ou adresse e-mail absente sur le compte | Configurer `MAIL_*` dans `.env` ; renseigner l'e-mail de l'utilisateur |
| Modification du `.env` sans effet | Configuration en cache | `php artisan optimize:clear` |
| Styles absents | Interface non compilée | `npm run build` |
| Pièce jointe refusée | Format ou taille | PDF, Word, Excel, images ; 20 Mo et 10 fichiers maximum |
| Erreur technique | Voir `storage/logs/laravel.log` | Ne jamais activer `APP_DEBUG=true` en production |
