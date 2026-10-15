# Guide Administrateur

## Connexion

Compte `admin` : le mot de passe temporaire est affiché une seule fois lors de l'installation
(`php artisan db:seed`) ; il doit être changé à la première connexion.

## Utilisateurs

Menu **Administration → Utilisateurs** : recherche (nom, prénom, nom d'utilisateur) et filtres
(département, rôle, actifs / inactifs).

| Action | Comment |
|---|---|
| **Créer** | *Créer un utilisateur* : nom, prénom, nom d'utilisateur (unique), département, fonction, rôle, mot de passe initial (à changer à la première connexion), téléphone, e-mail interne et photo facultatifs |
| **Modifier** | *Modifier* : identité, fonction, rôle, coordonnées (le département ne se modifie pas ici) |
| **Désactiver / Réactiver** | Boutons de la liste ou de la fiche : un compte n'est **jamais supprimé**, son historique reste intact |
| **Réinitialiser le mot de passe** | Un mot de passe temporaire est affiché **une seule fois** : le remettre à l'utilisateur, qui devra le changer |
| **Changer de département** | Fiche utilisateur → *Changer de département* : l'ancien compte est désactivé (historique conservé) et un nouveau compte est créé dans le nouveau département |

Chaque action est inscrite dans le *Journal du compte*.

## Départements

Menu **Administration → Départements** : nom, responsable, employés actifs, dossiers, statut.
Pour créer un département : bouton **Ajout département** sous le champ *Département* du menu latéral.
*Modifier / Responsable* (choisir le responsable parmi les membres actifs : il reçoit le rôle
Responsable), *Désactiver* / *Réactiver* (employés, dossiers et historique sont conservés).

## Paramètres

Menu **Paramètres** :

- **Types de demandes** : ajouter, renommer, choisir la portée (interne, externe, les deux), activer / désactiver ;
- **Urgences** : délai de réponse en heures (Très urgent 24 h, Urgent 72 h, Normal 168 h par défaut) ;
  s'applique aux nouvelles demandes ;
- **Rôles et permissions** : cocher les permissions de chaque rôle (voir [permissions.md](../permissions.md)) ;
  par défaut, l'administrateur ne voit pas l'historique détaillé des dossiers — cocher `view_all_history` pour changer cette règle ;
- **Réglages généraux** : préfixe des numéros de dossier, porte d'entrée RH.

## Statistiques

Menu **Statistiques** : dossiers par statut, demandes internes / externes, traitements ouverts,
délais dépassés, délai moyen de réponse et répartition par département.
