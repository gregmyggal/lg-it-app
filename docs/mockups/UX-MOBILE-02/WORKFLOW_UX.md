# UX-MOBILE-02 — Directeur et admin : mobile et ordinateur alignés

> Statut : **maquettes à valider** (`index.html` = smartphone, `desktop.html` = ordinateur). Aucun code applicatif.
> Complète `docs/mockups/UX-MOBILE-01` (navigation par onglets, « Aujourd'hui », encodage en 1 tap).

## Décisions validées (directeur)

- Encodage d'une séance en 1 tap ; nouvelle page d'arrivée professeur « Aujourd'hui ».
- Signature : spécimen créé **une fois** par professeur, appliqué à chaque action de signature.
- Admin/directeur : un maximum d'actions sur mobile (infos professeurs, changement de professeur, infos d'une classe, en direct).
- **Aucun écran n'est bloqué sur mobile.** Les écrans denses (création de classe, liens, contenus, années) restent accessibles, avec un simple bandeau informatif « plus confortable sur ordinateur ».
- **Remplacement** : le remplaçant est prévenu par **mail** (+ notification) ; le remplacement en lot est **atomique** (tout ou rien).
- **Seule la direction/l'admin décide** de changer un professeur, déplacer ou créer une séance. Un professeur **ne se déclare jamais absent** dans l'app (aucune action « absence » côté professeur).
- **Tarifs** visibles par la direction **et** l'admin (pas de restriction).
- **Le lieu n'est pas un filtre** (texte libre, affichage seulement).
- L'UX ordinateur est **alignée** sur le mobile : mêmes entrées, libellés, actions et écrans d'impact (voir `desktop.html`).

## Correspondance mobile ↔ ordinateur

| Élément | Smartphone | Ordinateur |
|---|---|---|
| Navigation | Onglets bas : Aujourd'hui · Timesheets · Classes · Profs · Plus | Barre latérale : mêmes entrées + groupe « Plus » |
| Détail séance / fiche | Page plein écran | Panneau latéral, liste visible |
| Actions | Barre collante : Remplacer · Ajouter · Modifier · Plus | Mêmes boutons, même ordre, en tête du panneau |
| Confirmation / saisie courte | Feuille basse | Boîte de dialogue centrée |
| Impact | Liste chiffrée ✓ / ! / • | Identique |
| Remplacer sur plusieurs séances | Étapes (séances → remplaçant → récap) | Une boîte à deux colonnes, même règle atomique |
| Sélection multiple | Appui long | Cases à cocher |
| Recherche | Loupe | Champ + ⌘K |
| Annuler une action | Toast « Annuler » 5 s | Identique |

## Répartition des cas d'usage

| Cas | Mobile |
|---|---|
| Remplacer un prof (une séance / plusieurs, atomique) ; ajouter un prof ponctuel | Complet |
| Annuler, modifier date/heure/lieu d'une séance (avec option « décaler les suivantes ») | Complet |
| « Aujourd'hui » direction, appeler/écrire, recherche, notifications | Complet |
| Fiche prof : coordonnées, compte, archivage, assignations, tarifs (ajout et modification) | Complet |
| Valider un mois, traiter une contestation | Complet |
| Création de classe/année, périodes, liens de cours, cours/stages/formations | Accessibles, bandeau informatif (plus confortables sur ordinateur) |

## À ajouter côté backend (par priorité)

1. Filtre `professeur_id` sur `/sessions` et `/calendar/*`.
2. `GET /sessions/{id}/candidats` : profs actifs avec leurs conflits avant l'action.
3. Remplacement en lot **atomique** avec aperçu ; état « remplaçant à trouver » (`remplace_par = NULL`).
4. **Mail au remplaçant** à chaque remplacement ou ajout de séance (aujourd'hui CLS-04 : pas de notification).
5. Aperçu d'annulation (nombre de saisies d'heures supprimées).
6. `GET /accueil/directeur` et `GET /mon-accueil` (professeur).
7. Compteur d'impact sur `PUT /classes`.
8. v2 : « M'envoyer le lien » par mail (`POST /me/rappel-lien`).

## Découpage proposé

UI-M1 socle (débordements, feuille basse/dialogue, mode sombre) → UI-M2 navigation (onglets + barre latérale alignée) → UI-M3 « Aujourd'hui » professeur + encodage 1 tap → UI-M4 « Mon mois » + signature par spécimen → UI-M5 validation par lot → UI-M6 « Aujourd'hui » direction + fiche séance + remplacement atomique (backend 1-4) → UI-M7 fiche prof / classe mobiles → UI-M8 finitions immersives.
