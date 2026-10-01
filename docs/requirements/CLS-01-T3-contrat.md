# CLS-01 · Tranche T3 — Contrat technique (heures / timesheets)

**Périmètre T3** : heures rattachables aux sessions (`timesheets.course_session_id`), **encodage libre** sans cours ni session, **écran mensuel « Encoder mon mois »**, raccourci d'encodage depuis « Mes classes », **vue directeur par session** (sessions sans heures), **validation en lot avec lissage**, **statuts normalisés**. **Hors T3** : correction d'une saisie soumise par erreur (Q7), liens par séance (T4), types de cours (T5).

Sources de vérité : `CLS-01-modele-classes.md` (RG-5, RG-9, AC-3, AC-25 à AC-33, Q19 à Q23), `docs/mockups/CLS-01-T3/WORKFLOW_UX.md` (règles **R-T3-1 à 12**, états, textes) et ses mock-ups validés **01** (raccourci), **02-portail-mon-mois**, **03** (directeur), `docs/API_T2_PROFESSEURS_CLASSES.md`, `DEVELOPMENT_STANDARDS.md`.

## 0. Défaut existant à corriger (bloquant pour tout le workflow)

`timesheets.statut_validation` est un `ENUM('brouillon','soumis','valide')` alors que le code écrit `confirmé` et `généré` (valeurs refusées par MySQL) : **la confirmation et la génération PDF échouent aujourd'hui**. T3 normalise les statuts **sans accent** : `brouillon` → `soumis` → `confirme` → `genere`.

## 1. Données (migrations réversibles)

1. `statut_validation` : colonne `string(20)` défaut `brouillon` (plus d'ENUM) ; **données** : `valide` → `confirme`, `confirmé` → `confirme`, `généré` → `genere` ; `down()` rétablit l'ENUM d'origine (valeurs hors enum ramenées à `valide`).
2. Contrainte d'unicité `UNIQUE(professeur_id, course_session_id, type_activite)` (les saisies sans session, `course_session_id` NULL, ne sont pas contraintes) ; index `(professeur_id, date_prestation)`.
3. Aucune migration de données de sessions : les anciennes saisies sans session restent « hors séance ».

Partout dans le code (modèle `Timesheet` constantes `STATUT_*`, `isLocked()` = `soumis`, `confirme`, `genere`, `TimesheetPolicy`, `TimesheetController`, `TimesheetLissingService`, `TimesheetSignatureService`, `TimesheetPdfService`, seeders, tests) : utiliser les nouvelles valeurs ; les libellés accentués sont affichés par le front (`utils/statuts.js`).

## 2. Règles (rappel : R-T3 du workflow UX)

- **R-T3-1** unicité (professeur, session, type d'activité), `422` « Ces heures sont déjà encodées pour cette session. »
- **R-T3-2** peut **créer** pour une session : le professeur assigné à cette session **non remplacé** (`session_professors.remplace = false`), ou son remplaçant. Le remplacé : `403`, mais il garde ses saisies et leur workflow. Le rôle est sans effet. **Seuls les professeurs créent** des saisies (le staff n'encode pas pour un professeur — Q11).
- **R-T3-3** encodage lié à une session seulement si elle a **commencé** (`date` passée, ou aujourd'hui avec `heure_debut` ≤ maintenant, Europe/Brussels) et n'est pas **annulée** : sinon `422`.
- **R-T3-4** à la création liée à une session : `professeur_id` = utilisateur connecté, `date_prestation` et `cours_id` = ceux de la session (non fournis par le client), `nombre_heures` par défaut = durée de la session, modifiable (0,5 – 24 h), `type_activite` défaut `animation`.
- **R-T3-5** **encodage libre** : `course_session_id` et `cours_id` facultatifs, commentaire facultatif, **aucun motif obligatoire** ; `date_prestation`, `type_activite`, `nombre_heures` requis.
- **R-T3-6** le professeur ne modifie/supprime que ses saisies en `brouillon` ; l'admin conserve ses droits actuels (voir policy).
- **R-T3-7 / AC-25** remplacer un professeur ne crée, ne modifie, ne supprime ni ne transfère aucune timesheet.
- **R-T3-8 / AC-33 / Q14** annuler ou déplacer une session ayant des saisies : `409` expliqué ; supprimer classe/session avec saisies : déjà `409` (T1).
- **R-T3-9** « session sans heures » : session **terminée** (passée) non annulée où au moins un professeur attendu (ligne `session_professors` non remplacée) n'a **aucune** saisie, tous types confondus.
- **R-T3-10** un professeur ne voit que **ses** saisies et **ses** montants (Q22) ; aucun tarif n'est exposé dans classes/sessions/calendrier.
- **R-T3-11** « soumettre le mois » : crée d'abord les saisies préremplies incluses (sessions cochées), puis passe en `soumis` tous les brouillons du mois, **en une transaction**.
- **R-T3-12** validation (admin ou directeur) unitaire ou **en lot**, avec **lissage appliqué puis validation dans la même transaction** ; aucune saisie n'est exclue pour cause de lissage ; plafond journalier existant (44,02 €) inchangé. Un lot est atomique : si une saisie n'est pas validable, `422` avec la liste et rien n'est modifié.

## 3. API (préfixe `/api`, `auth:sanctum`, conventions T1/T2 : `{message, errors}`, `can`)

| Méthode | Route | Rôle | Notes |
|---|---|---|---|
| `GET` | `/timesheets` | staff : tout ; professeur : les siennes | **modifié** : filtres `professeur_id` (staff), `classe_id`, `course_session_id`, `date_from`, `date_to`, `statut`, `type_activite` ; chaque saisie porte `session` (`id`, `libelle`, `date`, `classe_id`, `classe_libelle`) ou `null`, `montant_brut` (le sien, nul si aucun tarif), `can` ; **forme de liste inchangée** (tableau) pour ne pas casser le front existant |
| `POST` | `/timesheets` | professeur | **modifié** : voir R-T3-1 à 5 ; `professeur_id` n'est plus accepté du client ; `201` + saisie |
| `PUT/DELETE` | `/timesheets/{id}` | selon policy | inchangé (brouillon) ; le rattachement `course_session_id` n'est pas modifiable |
| `GET` | `/timesheets/mon-mois?annee=&mois=` | professeur | sessions **commencées** du mois où je suis éligible ou ai déjà des heures : `encodage` calculé (`a_encoder`\|`brouillon`\|`soumis`\|`confirme`\|`genere`), `mes_timesheets`, `duree_par_defaut`, `ma_situation`/`remplace_par`, `annulee` ; saisies **libres** du mois ; synthèse `{heures, montant, jours, depassements}` (via `TimesheetLissingService`) ; `peut_signer` |
| `POST` | `/timesheets/soumettre-mois` | professeur | `{annee, mois, sessions?: [{course_session_id, nombre_heures?, type_activite?}]}` → `{creees, soumises}` ; atomique |
| `GET` | `/timesheets/sessions-sans-heures?classe_id=&annee_scolaire_id=&date_from=&date_to=` | staff | R-T3-9 : `{session, classe_libelle, professeurs_sans_heures:[{id,nom}], jours_de_retard}` |
| `POST` | `/timesheets/valider-lot` | admin, directeur | `{ids:[…], lissages:[{timesheet_id, date_to, montant_to_move}]}` → `{validees, lissages_appliques}` ; atomique ; `422` si une saisie n'est pas validable |
| `GET` | `/mes-classes/{classe}/sessions` | professeur | **étendu** : `encodage`, `mes_timesheets`, `duree_par_defaut`, `peut_encoder` pour chaque session |
| `POST` | `/sessions/{session}/cancel`, `PUT /sessions/{session}` | staff | **modifié** : `409` si la session a des saisies (R-T3-8) |

Existants **conservés** (statuts normalisés) : `submit`, `validate`, `propose-lissage`, `apply-lissage`, signature, `can-sign-month`, `sign-month`, PDF.

## 4. Policies

`TimesheetPolicy` : `create` réservé aux professeurs (profil requis) ; nouvelle `createForSession(User, CourseSession)` (R-T3-2/3) ; `viewAny` staff ou professeur ; lecture/écriture du professeur limitée à ses propres saisies (inchangé) ; `validateEntry` admin, ou directeur sur `soumis` ; `validerLot` = `validateEntry` pour chaque saisie.

## 5. Tests (base `lgit_test`, `scripts/test-backend.sh`)

Migration de statuts (données `valide`/`confirmé` converties, rollback) ; création liée à une session (déduction des champs, durée par défaut, 403 remplacé, 403 non assigné, 422 non commencée/annulée, 422 doublon, autre type accepté, deux professeurs indépendants AC-3, remplaçant OK, AC-25 : le remplacement ne touche aucune saisie) ; encodage libre (AC-30) ; `mon-mois` (sessions, états, synthèse, isolation) ; `soumettre-mois` atomique (AC-31) ; `sessions-sans-heures` (AC-26) ; `valider-lot` avec lissage atomique (AC-32) ; blocage annuler/déplacer avec heures (AC-33) ; isolation professeur sur `GET /timesheets` ; workflow existant (confirmer, signer mois, PDF) sur les nouvelles valeurs de statut.

## 6. Front (étape suivante, mock-ups validés)

`pages/TimesheetsPage.jsx` devient l'écran mensuel (**02-portail-mon-mois**) ; feuille d'encodage depuis « Mes classes » (**01**) ; `AdminTimesheetsPage` : vue par session + validation en lot avec lissage (**03**) ; `utils/statuts.js` : statuts de timesheet normalisés (libellés accentués) ; euros visibles pour le professeur (ses propres montants) ; 4 états partout.
