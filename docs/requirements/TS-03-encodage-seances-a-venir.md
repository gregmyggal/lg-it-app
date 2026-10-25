# TS-03 — Encodage des heures des séances à venir (« Encoder mon mois »)

| | |
|---|---|
| **ID / Titre** | TS-03 — Le professeur voit et encode toutes les séances de son mois, passées **et à venir** |
| **Statut** | ☑ Brouillon · ☑ Validé · ☑ En dev (implémenté, tests back verts) · ☐ En recette · ☐ Livré |
| **Maquette** | `docs/mockups/TS-03/01-mon-mois-seances-a-venir.html` (états : chargement, vide, erreur, données, séance annulée) |
| **Supersède** | **R-T3-3** (`CLS-01-T3-contrat.md` §2), **D5** (`docs/mockups/CLS-01-T3/WORKFLOW_UX.md`), la question **Q1** tranchée « fenêtre d'encodage à partir du début de séance ». **Supprime** la soumission ligne par ligne (`POST /timesheets/{id}/submit`, bouton « Soumettre les heures » du raccourci « Mes classes »). Le reste de T3 est inchangé. |
| **Liens** | `docs/requirements/CLS-01-T3-contrat.md`, `docs/requirements/TS-02-remise-en-brouillon-du-mois.md`, `docs/requirements/TS-01-T1-adaptation-et-historique.md` |
| **Statut de la revue** | En attente de validation de la maquette par le directeur. **Aucun code applicatif n'est écrit avant validation** (CLAUDE.md). |

---

## 1. Contexte et problème

- **Situation actuelle :** « Encoder mon mois » ne liste que les séances **déjà commencées** (`aCommence`). Une séance du 14/10 n'apparaît donc que le 14/10 à partir de son heure de début. Le professeur encode **mois par mois, en fin de mois** : pour un mois en cours, les séances restantes sont invisibles et impossibles à préparer.
- **Douleur :** le professeur ne peut pas préparer son mois complet ; il doit revenir après chaque séance ou attendre le mois suivant. Ses séances à venir n'apparaissent pas dans son total.
- **Déclencheur :** demande du directeur (verbatim) : *« Lorsqu'un professeur veut encoder sa timesheet, il doit voir toutes les séances de son mois, que la date soit déjà passée ou dans le futur. Il peut encoder une séance même s'il n'a pas encore eu lieu. Le directeur ou l'administrateur doit encore valider et peut donc encore réagir si la séance devait être annulée. Les séances s'affichent mois par mois car le professeur encode mois par mois. »*

## 2. Objectifs et indicateurs de succès

| Objectif métier | Indicateur mesurable | Cible | Comment mesurer |
|---|---|---|---|
| Préparer son mois complet en une fois | Séances du mois visibles et encodables dès le 1er du mois | 100 % des séances du mois visibles | Recette : mois en cours, 3 séances à venir visibles |
| Ne pas soumettre par erreur une séance pas encore tenue | Séances à venir incluses par accident dans une soumission | 0 (case décochée par défaut) | Recette chronométrée + test unitaire du préréglage |
| Garder la capacité de réaction du directeur | Séance à venir annulée avec heures : signalée au directeur | 100 % signalées | Test d'intégration annulation + saisie |

**Hors périmètre (explicite) :**
- Pas de nouveau statut de timesheet ; « À venir » et « Annulée » restent des **états de session / d'affichage** calculés.
- Pas de changement de la validation, du lissage, de la signature ni du PDF.
- Pas de notification automatique au directeur (règle « aucune notification sur changement de session », décision du 2026-09-30). Le signalement se fait **dans les écrans du directeur**.
- Pas d'encodage **après la fin de la période** de la classe (règle existante : pas de session hors période).

## 3. Acteurs, rôles et permissions

**Persona(s) :** Professeur (ordinateur ou mobile, fin de mois). Directeur (validation, réaction à une annulation).

**Matrice rôle × action** (C créer, L lire, M modifier, S supprimer, V valider, — interdit ; « ses propres » = isolé)

| Ressource / Action | Admin | Staff (directeur) | Professeur |
|---|---|---|---|
| Voir les séances **à venir** du mois dans « Encoder mon mois » | — | — | L (ses séances assignées non remplacées, ou remplaçant) |
| Encoder une séance **à venir** (C brouillon) | — | — | C (ses propres, si assigné non remplacé ou remplaçant) |
| Modifier / supprimer ses brouillons à venir | — | — | M S (ses propres brouillons) |
| Soumettre un mois contenant des séances à venir | — | — | C · M (ses propres) |
| Voir une saisie liée à une séance **annulée** | L | L | L (ses propres) |
| Adapter / valider une saisie à venir | V | V | — |

> 👉 Alimente : `TimesheetPolicy::createForSession` (inchangée sur les droits, seule la règle de date change), `MesClassesController`, `TimesheetMoisController::monMois`, tests 403.

## 4. Glossaire métier

| Terme métier (affiché) | Définition | Nom technique |
|---|---|---|
| **Séance à venir** | Séance dont le début n'est pas encore passé (date future, ou aujourd'hui avant l'heure de début) | Calculé : `! aCommence(session)` · `est_a_venir` dans l'API |
| **Séance passée** | Séance dont le début est passé | Calculé : `aCommence(session)` |
| **À encoder** | Séance sans aucune saisie du professeur (passée **ou** à venir) | `encodage = a_encoder` (inchangé) |
| **Annulée** | Séance `statut = annulee` | `course_sessions.statut` (inchangé) |
| **Séance annulée avec heures** | Séance annulée ayant encore des saisies du professeur | Calculé : `annulee` + `mes_timesheets` non vide |

## 5. Parcours utilisateurs et user stories

### 5.1 User stories

| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | professeur | voir **toutes** les séances de mon mois, passées et à venir | préparer mon mois complet sans attendre la fin de chaque séance | **Must** |
| US-2 | professeur | encoder les heures d'une séance à venir (brouillon, puis soumission) | soumettre mon mois d'un seul passage | **Must** |
| US-3 | professeur | voir clairement qu'une séance est « À venir » et ne pas la soumettre par erreur | ne pas déclarer des heures pas encore tenues | **Must** |
| US-4 | directeur | voir les séances à venir encodées et, si une séance est annulée, les heures concernées | réagir avant de valider | **Must** |
| US-5 | professeur | voir une séance annulée après mon encodage et retirer mes heures | ne pas garder de brouillon fantôme | **Should** |

### 5.2 Parcours nominal (professeur)
1. Menu **Timesheets** → « Encoder mon mois » (mois en cours par défaut).
2. La section **1. Mes séances du mois** liste **toutes** les séances du mois : passées (préremplies, cochées) puis à venir (badge **À venir**, **décochées** par défaut, durée préremplie modifiable).
3. Chaque séance s'encode par son bouton **« Encoder »** (ses heures deviennent un brouillon). **Il n'y a pas de case à cocher.**
4. Il encode et complète sa timesheet **au fil du mois** (séances passées et à venir, autres heures).
5. Quand le mois est complet, « Soumettre le mois » envoie **automatiquement toutes les heures encodées du mois**, en une seule fois (R-T3-11).
6. Après soumission, le mois est verrouillé pour le professeur. Il n'y a qu'une soumission par mois.
7. Il navigue « Mois précédent / Mois suivant » : chaque mois est autonome.

### 5.3 Cas alternatifs
| Cas | Comportement attendu |
|---|---|
| Séance à venir **remplacée** (professeur remplacé) | Ligne « Remplacée par … », pas d'encodage possible (R-T3-2). Brouillon déjà créé : conservé, verrouillé à l'encodage. |
| Séance à venir **annulée** sans heures | Absente de la liste à encoder ; visible « Annulée » (comme aujourd'hui). |
| Séance à venir **annulée avec brouillon** | Ligne « Annulée », brouillon hors soumission, bouton « Supprimer ces heures » (US-5). |
| Séance à venir **annulée avec heures soumises** | Ligne « Annulée » verrouillée ; le directeur voit le signalement (US-4). |
| Séance d'**aujourd'hui** non commencée | Traitée comme à venir (encodable). |
| Doublon (professeur, séance, type) | `422` inchangé (R-T3-1). |

## 6. Règles métier

| # | Règle | Remplace |
|---|---|---|
| **RG-TS3-1** | Une séance à venir **non annulée** est encodable (brouillon puis soumission) par le professeur assigné non remplacé ou le remplaçant. | R-T3-3 (`aCommence` requis) |
| **RG-TS3-2** | `POST /timesheets` avec `course_session_id` : plus de contrôle `aCommence`. Restent : session non annulée, droit `createForSession`, unicité (professeur, session, type). | R-T3-3 |
| **RG-TS3-3** | `GET /timesheets/mon-mois` et `GET /mes-classes/{id}/sessions` renvoient **toutes** les séances du mois (passées et à venir), annulées incluses, avec `est_a_venir: bool`. Les séances hors commençées sans heures ne sont plus filtrées. | filtre `aCommence` dans `monMois` |
| **RG-TS3-4** | Aucune case à cocher. Chaque séance à encoder propose sa durée défrayable (modifiable) et un bouton « Encoder » qui crée un brouillon. Les séances à venir sont encodables de la même façon. | révisé |
| **RG-TS3-5** | La soumission accepte les séances à venir ; le directeur reste le seul à valider (inchangé). | — |
| **RG-TS3-6** | **Annulation d'une séance :** refusée (409) si une de ses heures est **validée** (confirmée, contestée, générée). Sinon, **toutes ses heures encodées (brouillon et soumises) sont supprimées** avec la séance, en une transaction. | révisé (remplace R-T3-8) |
| **RG-TS3-12** | **Déplacement d'une séance :** refusé (409) si une heure est validée. Sinon les heures non validées **suivent la nouvelle date** de la séance ; elles ne sont jamais perdues. | révisé (remplace R-T3-8) |
| **RG-TS3-7** | La navigation est mensuelle ; on n'encode que dans une période de classe existante (pas de séance hors période). | — |
| **RG-TS3-8** | **Une seule soumission par professeur et par mois, toujours sur le mois entier.** `POST /timesheets/soumettre-mois {annee, mois}` (sans liste de sessions) passe en « soumis » **toutes** les heures encodées (brouillons) du mois, en une transaction. Aucune heure encodée → `422`. Un mois déjà soumis refuse toute nouvelle soumission : `422`. | révisé |
| **RG-TS3-13** | **Remise en brouillon par la direction (TS-02, révisé) :** tout le mois repasse en brouillon — soumises, confirmées, contestées — et **les signatures des lignes sont retirées**. Le professeur corrige puis soumet son mois complet à nouveau. Un mois dont un PDF a été **généré** ne se rouvre pas (`422`). | révisé (remplace TS-02 « seulement les lignes soumises ») |
| **RG-TS3-9** | **Aucune soumission ligne par ligne.** La route `POST /timesheets/{id}/submit`, son contrôleur, sa policy `submit` et le champ `can.submit` sont **supprimés**. Le raccourci « Mes classes » enregistre seulement un brouillon ; sa modale n'a pas de bouton « Soumettre ». | nouveau |
| **RG-TS3-10** | Mois soumis : le professeur ne peut plus **créer** de saisie dans ce mois (`422`), ni modifier/supprimer une saisie soumise (inchangé). Le mois n'est rouvert que par une **remise en brouillon** du mois par la direction (TS-02) ; le professeur le soumet alors à nouveau, entièrement, une fois. | nouveau |
| **RG-TS3-11** | `GET /timesheets/mon-mois` expose `mois_soumis: bool`. Si vrai, l'écran désactive « Enregistrer », « Ajouter des heures » et « Soumettre le mois », avec un bandeau explicatif. | nouveau |

## 7. Impact technique (indicatif, pour l'architecte)

- **Données :** aucune migration prévue (`est_a_venir` est calculé ; `statut` de session existant).
- **API :** champs ajoutés (`est_a_venir`, `mois_soumis`). Filtre `aCommence` retiré de `monMois` et de `peutEncoder` ; **`creer()` ne vérifie plus `aCommence`**. **Retirés :** `POST /timesheets/{id}/submit`, `TimesheetController::submit`, `TimesheetPolicy::submit`, `can.submit`, `soumettreSaisie()` (front).
- **Garde mensuelle (déjà en place, à conserver) :** `TimesheetService::moisSoumis()` ; `creer()` et `soumettreMois()` refusent un mois déjà soumis (422).
- **Front :** `SessionsDuMoisTable` (badge « À venir », case décochée par défaut), `TimesheetsPage` (bandeau, texte d'aide « Seules les sessions commencées… » remplacé), `MesClasseSessionsPage` / `EncoderSessionModal` (bouton « Encoder » activé pour les séances à venir ; le raccourci hérite de la même règle).
- **Tests :** `TimesheetRemiseBrouillonTest` (resoumission par le mois entier), `TimesheetMoisTest` (une seule soumission, pas de route ligne par ligne, `mois_soumis`). Les cas « 422 session non commencée » documentent l'ancienne règle de TS-03 : ils seront remplacés à l'implémentation de RG-TS3-1/2.
- **Base de test :** `lgit_test` uniquement (`scripts/test-backend.sh`).

## 8. Décisions UX (workflow intégré aux trajets actuels)

Workflow produit sans parcours parallèle : on étend « Encoder mon mois » (la page existante) et « Mes classes » (raccourci). Aucune nouvelle entrée de menu.

- **D-TS3-1 — Toutes les séances du mois, dans l'ordre des dates.** Pas de séparation « passées / à venir » en deux listes : une seule chronologie évite de chercher. Le badge « À venir » suffit à distinguer.
- **D-TS3-2 — Pas de case à cocher : encodage par séance.** Chaque séance s'encode par son bouton « Encoder » ; la soumission prend automatiquement toutes les heures encodées. Une séance à venir ne part donc que si le professeur l'a encodée. *Écarté :* cases à cocher (le professeur doit penser à les cocher, source d'oublis) ; un bouton global « Enregistrer » (un seul geste inutile).
- **D-TS3-3 — Libellé d'état distinct.** « À venir » (information, bleu) et « À encoder » (action, ambre) sont deux badges séparés, texte et couleur ensemble.
- **D-TS3-4 — Annulation.** Ligne barrée, libellée « Annulée », brouillon hors soumission avec « Supprimer ces heures ». Une saisie déjà soumise reste verrouillée et visible.
- **D-TS3-5 — Mois par mois, comme aujourd'hui.** Pas de vue « tout l'exercice ». Les séances d'un mois futur sont accessibles en changeant de mois.
- **D-TS3-6 — Une soumission, le mois entier.** Le professeur remplit au fil du mois ; aucune ligne ne part seule. Le seul geste de soumission est « Soumettre le mois », disponible une fois le mois complet, et il n'existe qu'une fois. *Écarté :* soumettre une séance ou une ligne depuis « Mes classes » (fragmente le mois et multiplie les validations) ; plusieurs soumissions par mois (la direction ne sait plus quoi valider).

## 9. Questions à trancher avant développement

| # | Question | Recommandation | Bloquant ? |
|---|---|---|---|
| **Q1** (tranchée : RG-TS3-6) | **Annulation d'une séance à venir qui a déjà des heures.** Aujourd'hui `R-T3-8` bloque l'annulation (409) dès qu'il existe une saisie : le directeur ne peut donc pas « réagir » sans supprimer les heures d'abord. Que voulez-vous ? | **(a) Recommandé :** autoriser l'annulation ; les brouillons de cette séance sortent de la soumission ; les saisies soumises restent verrouillées et apparaissent au directeur avec le signal « Séance annulée avec heures » (il les adapte, les supprime ou les valide). Aucune suppression automatique. | **Oui** |
| Q2 | Séances à venir **décochées** par défaut (D-TS3-2) ? | **Remplacée** : plus de case à cocher, chaque séance s'encode par « Encoder » (D-TS3-2). | — |
| Q3 | Une séance **remplacée** ayant déjà un brouillon : on le garde (verrouillé à l'encodage) ou on le signale au remplacé ? | Garder sans notification (RG-9, AC-25 : aucune contrainte liée aux heures lors d'un remplacement). | Non |

## 10. Critères d'acceptation (Given / When / Then)

| ID | Given | When | Then |
|---|---|---|---|
| AC-TS3-1 | Un professeur assigné à 4 séances d'octobre, dont 3 à venir | il ouvre « Encoder mon mois » en octobre | les 4 séances s'affichent, les 3 à venir portent « À venir » et sont décochées |
| AC-TS3-2 | Séance du 21/10 à venir, non annulée | il encode 3 h en brouillon | `201`, la saisie apparaît dans son mois, `montant` calculé |
| AC-TS3-3 | Séance à venir et professeur **non** assigné | `POST /timesheets` avec `course_session_id` | `403` |
| AC-TS3-4 | Séance **annulée** | `POST /timesheets` avec `course_session_id` | `422` « session annulée » |
| AC-TS3-5 | Deux brouillons à venir et un passé, cochés | « Soumettre le mois » | les 3 deviennent `soumis`, `201`/`200`, transaction atomique |
| AC-TS3-6 | Séance à venir, heures soumises | le directeur ouvre la vue timesheets | la saisie est listée et validable ; elle n'est pas validée automatiquement |
| AC-TS3-7 | Séance à venir annulée avec brouillon (Q1 = a) | le professeur ouvre son mois | ligne « Annulée », hors total et soumission, bouton « Supprimer » |
| AC-TS3-8 | Séance à venir annulée avec saisie soumise (Q1 = a) | le directeur liste les saisies | signal « Séance annulée avec heures » visible, aucune modification automatique |
| AC-TS3-9 | Professeur, mois sans séance ni saisie | il ouvre le mois | état **vide** (« Rien à encoder pour ce mois ») |
| AC-TS3-10 | Erreur réseau au chargement | il ouvre le mois | état **erreur** avec « Réessayer », saisies non perdues |
| AC-TS3-11 | Professeur sur un mois donné | il change de mois | seules les séances de ce mois sont listées |
| AC-TS3-12 | Séance à venir, accès mobile | il ouvre le mois sur 375 px | une séance = une carte, pas de défilement horizontal |
| AC-TS3-13 | Mois soumis une fois | il soumet à nouveau le même mois | `422`, aucune saisie modifiée |
| AC-TS3-14 | Brouillon d'un mois non soumis | il appelle `POST /timesheets/{id}/submit` | `404` : la soumission ligne par ligne n'existe pas |
| AC-TS3-15 | Mois soumis | il ajoute une saisie (libre ou liée à une séance) de ce mois | `422`, aucune saisie créée |
| AC-TS3-16 | Mois soumis, puis remis en brouillon par la direction (TS-02) | il corrige puis soumet le mois entier | `200`, une nouvelle soumission du mois entier est acceptée |
| AC-TS3-17 | Mois soumis | il ouvre « Encoder mon mois » | bandeau « Ce mois est soumis », boutons Encoder / Ajouter / Soumettre désactivés |
| AC-TS3-18 | Séance avec heures brouillon et soumises, aucune validée | le directeur l'annule | séance annulée, toutes ses heures supprimées |
| AC-TS3-19 | Séance avec une heure confirmée | le directeur l'annule ou la déplace | `409`, aucune heure modifiée |
| AC-TS3-20 | Séance avec heure soumise (non validée) | le directeur change sa date | l'heure suit la nouvelle date, elle reste soumise |
| AC-TS3-21 | Mois avec heures soumises, confirmées et contestées, sans PDF | la direction remet le mois en brouillon | toutes les lignes sont en brouillon, signatures retirées, une trace par ligne |
| AC-TS3-22 | Mois avec un PDF généré | la direction tente une remise en brouillon | `422`, aucune ligne modifiée

## 11. Tests (base `lgit_test`)

- Feature : AC-TS3-1 à AC-TS3-17 (API) ; `est_a_venir` présent sur `mon-mois` et `mes-classes/{id}/sessions`.
- Déjà couverts et verts (512 tests back) : AC-TS3-13, 14, 15, 16 (`TimesheetMoisTest`, `TimesheetRemiseBrouillonTest`).
- Front : préréglage (passées cochées, à venir décochées), badge « À venir », état annulée, états vide/erreur/chargement (AC-TS3-9, 10).
- Non-régression : `npm run lint && npm run build` ; `scripts/test-backend.sh`.

## 12. Definition of Ready (DoR)

- [ ] Workflow UX défini et intégré aux trajets actuels (§8)
- [ ] Maquette `docs/mockups/TS-03/01-mon-mois-seances-a-venir.html` présentée et **validée**
- [ ] **Q1 tranchée** (bloquant)
- [ ] Q2 et Q3 validées ou acceptées par défaut
- [ ] Critères Given/When/Then écrits (§10)
- [ ] Matrice rôle × action remplie (§3)
- [ ] Impact données identifié : aucune migration (§7)
- [ ] Règle « une seule soumission, mois entier, aucune soumission par ligne » validée (RG-TS3-8 à 11, D-TS3-6)
- [ ] Règles d'annulation, de déplacement et de remise (RG-TS3-6, 12, 13) validées : « validé » = confirmé, contesté ou généré

---

> **Validation attendue de votre part :** (1) la maquette, (2) la réponse à **Q1**, (3) Q2 et Q3 (ou « par défaut »). Dès validation, le développement suit le découpage en tranches verticales (DB inchangée → API → front → tests).
