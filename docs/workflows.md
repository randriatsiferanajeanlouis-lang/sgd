# Workflows métier

## Statuts

| Niveau | Statuts | Remarque |
|---|---|---|
| Dossier (vu par l'expéditeur) | Envoyé → En cours → Répondu → Archivé | déduit des traitements |
| Traitement d'un destinataire | Reçu → En cours → Répondu → Archivé | indépendant pour chaque destinataire |
| Document | Active / Remplacée / Annulée | une seule version active |
| Validation | À valider / Validé / Refusé | sous-étape, le traitement reste « En cours » |

Il n'existe **pas** de statut « En attente » ni « En retard » : un délai dépassé est une **alerte**
(« Statut : En cours — Alerte : délai dépassé »).

## Demande interne

```mermaid
sequenceDiagram
    actor A as Employé A (expéditeur)
    participant App as Application
    actor B as Employé B (destinataire)
    A->>App: Nouvelle demande (objet, type, urgence, échéance, destinataires, pièces)
    App->>App: Référence DOS-AAAA-NNNNNN, traitements « Reçu », historique
    App-->>B: 🔔 Nouveau dossier
    B->>App: Ouvre le dossier
    App->>App: Traitement Reçu → En cours (historisé)
    B->>App: Répond (texte + pièces)
    App-->>A: 🔔 Nouvelle réponse
    B->>App: Archive son traitement
    App->>App: Tous les traitements archivés → dossier Archivé
```

Plusieurs destinataires : chacun suit ce cycle **indépendamment** (ex. Production → Archivé,
RSE → En cours, Administration → Reçu).

**Transmission** : un destinataire peut « Transmettre à un autre utilisateur » ; le collègue reçoit
le dossier avec son propre traitement et une notification.

## Validation hiérarchique

```mermaid
sequenceDiagram
    actor A as Employé A
    actor B as Employé B
    actor R as Responsable de B
    A->>B: Demande interne
    B->>R: Transmettre au responsable (commentaire)
    Note over B: Ne peut pas répondre tant que la validation est en attente
    R->>B: Valider / Refuser (commentaire)
    B->>A: Réponse
```

## Demande externe (RH)

```mermaid
sequenceDiagram
    actor D as Demandeur externe
    actor RH as RH (département RSE)
    participant App as Application
    actor Resp as Responsable(s)
    D->>RH: Courrier, e-mail, téléphone, dépôt
    RH->>App: Nouvelle demande externe (demandeur, type, canal, urgence, pièces)
    App->>App: Échéance = réception + délai de l'urgence (24 h / 3 j / 7 j)
    App-->>Resp: 🔔 Nouveau dossier
    Resp->>App: Ouvre (En cours), décision Positive / Négative + justification
    App-->>RH: 🔔 Décision reçue (retour RH)
    RH->>App: Réponse officielle (texte, canal, date d'envoi, document)
    App->>App: Dossier Répondu
    RH->>App: Archiver
    RH-->>D: Réponse transmise
```

Si plusieurs responsables répondent différemment, **toutes les décisions sont conservées** ;
aucune majorité n'est calculée : le RH décide de la réponse officielle.

## Échéances et relances

| Événement | Destinataires de la notification |
|---|---|
| Échéance aujourd'hui | le destinataire |
| Délai dépassé (interne) | le destinataire + l'expéditeur |
| Délai dépassé (externe) | le destinataire + le RH |
| Relance manuelle (« Relancer ») | le destinataire relancé |

La commande `dossiers:check-deadlines` (toutes les heures) n'envoie jamais deux fois la même alerte.

## Archivage et réouverture

- Un dossier archivé est **verrouillé** : plus de modification ni d'ajout ou remplacement de pièce.
- Le responsable du département peut **réouvrir un traitement** répondu ou archivé (motif obligatoire) :
  même numéro, historique intact, le traitement repasse « En cours ».
