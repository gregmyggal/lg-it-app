# UI-M5 — Validation du mois par lot sur smartphone (directeur/admin) — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Cinquième tranche de la refonte mobile-first (après UI-M1 à UI-M4). Tranche d'interface : l'API et les règles de validation existantes sont inchangées (`GET /timesheets/mois-synthese`, `GET /timesheets`, `POST /timesheets/valider-lot`, `POST /timesheets-mois/pdf-lot`, employeurs du mois).

| | |
|---|---|
| **ID / Titre** | UI-M5 — « Timesheets » direction sur mobile : cartes par professeur, filtres en puces, sélection (appui long ou bouton), barre collante « Valider N · montant », actions secondaires en feuille basse, filtres ordinateur repliés |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — maquettes validées le 2026-10-25 ☑ En dev (livré, vérifié dans le navigateur) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* (front uniquement) |
| **Date / Version** | 2026-10-25 · v1.0 |
| **Liens** | `docs/mockups/UX-MOBILE-01/index.html` (écran « Timesheets » directeur), `docs/mockups/UX-MOBILE-02/index.html`, `TS-01` (validation, lissage), `SyntheseValidationMois.jsx`, `ValiderLotModal.jsx`, `AdminTimesheetsPage.jsx` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** sur smartphone, la validation du mois est un tableau de 11 colonnes (`minWidth 980px`) transformé en cartes de plus de 9 lignes par professeur, avec de petites cases à cocher ; au-dessus, un panneau de filtres (mois, professeur, 4 boutons de vue avec emojis, rafraîchir), 6 tuiles d'indicateurs, un champ de recherche, 2 listes de filtres et 5 boutons d'action.
- **Douleur :** le directeur valide le mois entre deux cours, au téléphone ; il doit défiler longuement, viser de petites cases et ne voit pas le total qu'il valide.
- **Déclencheur :** maquettes UX-MOBILE-01/02 validées (« validation par lot : appui long → sélection → barre collante ») ; décision : directeur et admin utilisent un maximum de fonctions sur mobile.

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif | Indicateur | Cible | Mesure |
|---|---|---|---|
| Voir l'essentiel d'un coup d'œil | lignes par professeur | 3 (nom + employeur, statut + alertes, montant) au lieu de 9+ | recette R1 |
| Valider en peu de gestes | taps pour valider 4 professeurs « à valider » | ≤ 6 (appui long, 3 taps, Valider, confirmer) | recette R2 |
| Savoir ce qu'on valide | total affiché avant confirmation | nombre de professeurs + montant dans la barre collante | recette R2 |
| Aucun libellé hors cadre | débordement horizontal à 360 px | 0 | recette R1 |
| Rien n'est perdu | actions de l'ordinateur toujours accessibles | 100 % (feuille « Actions du mois », vues secondaires repliées) | recette R3 |

**Hors périmètre :** détail d'un professeur (inchangé, déjà en cartes via `Table cards`), validation ligne à ligne, nouvelles règles de lissage, vues « Mois / Profs / Sessions » repensées (restent accessibles, repliées), notifications.

## 3. 🔴 Acteurs, rôles et permissions

**Persona :** Directeur/Staff et Admin (smartphone, ponctuel : une fois par mois, plus les contestations).

| Action | Admin | Staff | Professeur |
|---|---|---|---|
| Voir la synthèse du mois | L | L | — (403) |
| Valider la sélection (`POST /timesheets/valider-lot`) | V | V | — |
| Définir l'employeur, générer les PDF, exporter | M | M | — |

Les droits restent portés par le backend (`can.validate` par saisie, 403 pour un professeur) ; aucune permission nouvelle.

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Nom technique |
|---|---|---|
| Carte professeur | Une ligne de la synthèse du mois | `CarteProfesseurMois` |
| À valider / Attente professeur / Contesté / Prêt PDF / Généré | Statut du mois calculé par le serveur | `STATUTS_MOIS_PROF` |
| Mode sélection | Cases visibles sur les cartes, barre « Valider N » | état local |
| Actions du mois | Feuille basse : employeur, mois précédent, CSV, PDF | `Actions du mois` |

## 5. 🔴 Parcours et critères d'acceptation

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | directeur sur smartphone | une carte par professeur (nom, employeur, statut, alertes, montant) | parcourir le mois sans défiler en tableau | Must |
| US-2 | directeur | filtrer par statut avec des puces à compteur | aller droit aux « À valider » | Must |
| US-3 | directeur | sélectionner par appui long (ou « Sélectionner ») puis valider | valider un lot au pouce | Must |
| US-4 | directeur | voir « Valider 4 · 612 € » avant de confirmer | savoir ce que je valide | Must |
| US-5 | directeur | retrouver l'employeur, le CSV et les PDF | ne rien perdre par rapport à l'ordinateur | Must |
| US-6 | directeur | ouvrir un professeur d'un tap | consulter, adapter, contester | Must |

### 5.2 Parcours
1. « Timesheets » : navigation de mois (‹ Octobre 2026 ›), recherche, puces de statut (Tous · À valider 4 · Attente prof · Contestés · Prêts PDF · Générés), liste de cartes, bouton « Sélectionner » et « ⋯ Actions du mois ».
2. Tap sur une carte : ouvre le détail du professeur. Appui long (ou « Sélectionner ») : mode sélection ; chaque tap coche/décoche ; « Tout ce qui est à valider » coche d'un coup les professeurs validables.
3. Barre collante : « Valider N · montant » (désactivée si aucune saisie validable) → feuille de validation existante (lissage, plafond) → confirmation par le serveur → toast, sélection vidée.
4. Alternatives : aucune timesheet (état vide), erreur de chargement (Réessayer), refus serveur (message), professeurs ignorés (bandeau récapitulatif conservé), vue détail depuis une notification (inchangée).

### 5.3 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | largeur ≤ 860 px, vue « Validation » | affichage | cartes professeur, aucun `<table>` de 11 colonnes, aucun débordement horizontal | R1 |
| AC-2 | des statuts variés | tap sur une puce | liste filtrée ; compteurs corrects | R1 |
| AC-3 | une carte | appui long (450 ms) ou « Sélectionner » | mode sélection ; la carte est cochée | R2 |
| AC-4 | mode sélection | tap sur une carte | coche/décoche (pas d'ouverture du détail) | R2 |
| AC-5 | des professeurs cochés avec saisies soumises validables | affichage de la barre | « Valider N · {montant} » (N = professeurs cochés, montant = somme de leurs totaux) | R2 |
| AC-6 | aucune saisie validable dans la sélection | affichage de la barre | bouton désactivé | R2 |
| AC-7 | « Valider N » | confirmation de la feuille | `POST /timesheets/valider-lot` comme sur ordinateur ; toast ; sélection vidée ; liste rechargée | R2 |
| AC-8 | une carte hors mode sélection | tap | ouvre le détail du professeur | R1 |
| AC-9 | « Actions du mois » | ouverture | feuille basse : Définir l'employeur, Reprendre le mois précédent, Exporter CSV, Générer les PDF (mêmes conditions d'activation qu'aujourd'hui) | R3 |
| AC-10 | largeur ≤ 860 px, vue « Validation » ou détail | affichage | filtres ordinateur (mois, professeur, vues, rafraîchir) repliés derrière « Autres vues et filtres » | R3 |
| AC-11 | largeur > 860 px | affichage | écran inchangé (tableau, actions en ligne) | R4 |

## 6. 🔴 Règles métier et cycle de vie
- **RG-1 :** validation, lissage, plafond, atomicité : inchangés (serveur).
- **RG-2 :** « validable » = au moins une saisie `soumis` avec `can.validate` pour le professeur coché (règle existante `saisiesChoisies`).
- **RG-3 :** le montant de la barre est la somme des `total_eur` des professeurs cochés (information ; le serveur recalcule).
- **RG-4 :** l'appui long ne bloque pas le défilement ; un défilement annule le déclenchement.
- Statuts : `STATUTS_MOIS_PROF` inchangé.

## 7. 🔴 Données et migration
Aucune table, colonne, migration, backfill ni seeder. Aucun impact RGPD (mêmes données que sur ordinateur, accès réservé à la direction).

## 8. Exigences UX/UI
> Workflow : sub-agent UX Expert (UX-MOBILE-01 écran « Timesheets », UX-MOBILE-02). Validé par le directeur le 2026-10-25 ☑

| Écran | Contenu | Actions | Composants |
|---|---|---|---|
| `/admin/timesheets` (≤ 860 px) | Mois, recherche, puces, cartes, barre collante | Ouvrir, Sélectionner, Valider N, Actions du mois | `SyntheseValidationMois`, `CarteProfesseurMois`, `ValiderLotModal` (feuille basse), `AdminModal`, `StatutBadge`, `EmployeurBadge` |

**4 états :** chargement (squelette), vide (« Aucune timesheet pour ce mois »), erreur (Réessayer), succès (toast « {n} timesheet(s) validée(s) »).

- **Textes exacts :** *Sélectionner · Annuler la sélection · Tout ce qui est à valider · Valider {N} · {montant} · Actions du mois · Définir l'employeur… · Reprendre le mois précédent · Exporter CSV · Générer les PDF (zip) · Autres vues et filtres.*
- **Appareils :** ☑ Mobile (priorité) ☑ Tablette ☑ Desktop inchangé.
- **Accessibilité :** chaque carte a un bouton « Ouvrir {nom} » et, en sélection, une case nommée « Sélectionner {nom} » (≥ 44 px) ; l'appui long a toujours l'alternative « Sélectionner » ; compteur de sélection en `aria-live`.
- **Charge cognitive :** 1 écran de tri (puces), 1 geste pour sélectionner, 1 barre qui résume ce qui sera validé.

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Performance | Aucun appel nouveau (mêmes données que le tableau) |
| Compatibilité | iOS Safari ≥ 16, Chrome Android, desktop ; français |
| Sécurité | Inchangée (contrôles serveur) |

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB / Back / API | Aucun | — |
| Front | `components/timesheets/CarteProfesseurMois.jsx`, `SyntheseValidationMois.jsx` (branche mobile), `AdminTimesheetsPage.jsx` (filtres repliés), styles | M |
| Tests | `npm run lint`, `npm run build`, suite back (non impactée) ; vérification navigateur 390/1280 px | S |
| Docs | Ce Canvas | S |

- **Risques :** ne pas casser l'écran ordinateur (même composant, deux rendus) ; l'appui long ne doit pas gêner le défilement ni la sélection de texte. **Retour arrière :** revert du commit. **ADR :** non.

## 11. Plan de recette
| # | Rôle | Scénario | Résultat | AC | OK/KO |
|---|---|---|---|---|---|
| R1 | Directeur (360–390 px) | Ouvrir Timesheets du mois, filtrer, ouvrir un professeur | Cartes, puces, détail | AC-1, 2, 8 | ☐ |
| R2 | Directeur | Appui long, cocher 2–3 professeurs, « Valider N · € », confirmer | Validation en lot, toast, liste à jour | AC-3 à 7 | ☐ |
| R3 | Directeur | « Actions du mois », « Autres vues et filtres » | Toutes les actions accessibles | AC-9, 10 | ☐ |
| R4 | Directeur (1280 px) | Même écran | Inchangé | AC-11 | ☐ |

**Jeu de données :** `lgit_demo` (alice, bob soumis ; chloe/emma confirmés ; david contesté ; felix brouillon).

## 12. Questions ouvertes
Aucune bloquante.

## 13. Validation (DoR)
Maquettes et décisions du directeur du 2026-10-25. ☑ UX Expert ☑ Mock-ups ☑ §1–7 ☑ Permissions ☑ AC testables ☑ Impact données (aucun).
