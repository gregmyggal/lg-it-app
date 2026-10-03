# API CLS-01 · Tranche T2 — Professeurs ⇄ classes, remplacement, « Mes classes »

Préfixe `/api`, `Authorization: Bearer <token>` (Sanctum), JSON snake_case. Mêmes conventions que `API_T1_CLASSES.md` (`{data}`, erreurs `{message, errors}`, `can`). Contrat : `docs/requirements/CLS-01-T2-contrat.md`.

**Rôles** : `admin` et `directeur` = staff (toutes les écritures). `professeur` : lecture limitée à **ses** classes et sessions ; aucune écriture. Aucun tarif n'est jamais exposé dans ces réponses.

Enums : `role` = `principal` | `co_enseignant` | `remplacant` (**indicatif**, aucun effet sur rémunération ni timesheets) ; ligne de session `origine` = `classe` | `remplacement`.

## Règles de propagation (RG-8)

Assigner un professeur à une classe l'assigne aux **sessions à venir** (date ≥ aujourd'hui Europe/Brussels, statut `planifiee`/`en_cours`, annulées exclues). Les sessions passées ne sont jamais modifiées. Classe pas encore commencée : toutes les sessions. Les sessions créées plus tard (bis) héritent des professeurs actifs de la classe. **Mêmes règles et même résultat depuis la classe ou depuis le professeur.**

Récapitulatif renvoyé (aperçu, création, modification, terminaison) :
```json
{ "sessions_assignees": 9, "sessions_passees_ignorees": 5, "sessions_deja_assignees": 0 }
```
À la terminaison/modification de fin : `sessions_retirees`, `sessions_conservees` (celles avec timesheet).

**Conflit d'horaire** : le professeur est déjà assigné (non remplacé) à une autre session non annulée le même jour sur un horaire qui se chevauche → **422** (blocage), `conflits` listés :
```json
{ "message": "Conflit d'horaire : …", "errors": { "professeur_id": ["…"] },
  "conflits": [ { "date": "2026-10-07", "session_id": 12, "session_en_conflit_id": 40, "classe_id": 3,
                  "classe": "Scratch Junior", "heure_debut": "15:00", "heure_fin": "18:00" } ] }
```

## Assignation depuis la classe — staff

### `GET /classes/{classe}/professeurs` — staff, ou professeur de la classe
Toutes les assignations (actives et terminées).
```json
{ "data": [ { "id": 1, "professeur_id": 4, "professeur": {"id":4,"nom":"Alice Prof"}, "classe_id": 7,
  "role": "principal", "date_debut": "2026-09-30", "date_fin": null, "actif": true,
  "nb_sessions_assignees": 14, "can": {"update": true, "delete": true} } ] }
```

### `POST /classes/{classe}/professeurs/apercu`
`{ "professeur_id": 4, "role"?: "principal", "date_debut"?: "YYYY-MM-DD", "date_fin"?: null }` → **200** `{ "data": { récapitulatif, "conflits": [] } }` — **n'écrit rien**.

### `POST /classes/{classe}/professeurs`
Même payload. **201** (nouvelle assignation ou réactivation) / **200** (déjà active, idempotent) :
`{ "data": <assignation>, "recapitulatif": { … } }`. **422** : validation ou conflit d'horaire (liste `conflits`). Une assignation terminée est **réactivée** (`date_fin = null`).

### `PUT /classes/{classe}/professeurs/{professeur}`
`role`, `date_debut`, `date_fin` (nullable) → **200** `{ data, recapitulatif }`. Une `date_fin` retire le professeur des sessions futures sans timesheet ; une ré-ouverture re-propage (conflits bloquants).

### `DELETE /classes/{classe}/professeurs/{professeur}`
Corps optionnel `{ "date_fin": "YYYY-MM-DD" }` (défaut : aujourd'hui). **Termine** l'assignation (la ligne est conservée pour l'historique) : retire le professeur des sessions **futures sans timesheet** ; conserve les sessions passées et celles avec timesheet. **200** `{ data, recapitulatif }` ; **409** si déjà terminée ; **422** si `date_fin` < `date_debut`.

## Assignation depuis le professeur — mêmes services

| Méthode | Route | Rôle |
|---|---|---|
| `GET` | `/professeurs/{professeur}/classes` | staff, ou le professeur lui-même (sinon 403) — assignations avec `classe` (+ `cours`), `co_professeurs` |
| `POST` | `/professeurs/{professeur}/classes/apercu` | staff — payload `{ classe_id, role?, date_debut?, date_fin? }` |
| `POST` | `/professeurs/{professeur}/classes` | staff — idem, même réponse que côté classe |
| `PUT` | `/professeurs/{professeur}/classes/{classe}` | staff |
| `DELETE` | `/professeurs/{professeur}/classes/{classe}` | staff (`date_fin` optionnelle) |

## Remplacement ponctuel (RG-9) — staff

**Aucune contrainte liée aux timesheets** : possible sur une session passée ou à venir (pas annulée) ; les timesheets existantes du remplacé et du remplaçant ne sont ni bloquées, ni supprimées, ni transférées.

### `GET /sessions/{session}/professeurs` — staff, ou professeur concerné
Lignes `session_professors` : `{ id, course_session_id, professeur_id, professeur, role, origine, remplace, remplace_par_professeur_id, remplace_par }`.

### `POST /sessions/{session}/professeurs` — staff (CLS-04)
`{ "professeur_id": 9, "role"?: "principal" }` (défaut `principal`) → **201** `{ data: <session>, avertissements: [...] }`. Ligne `origine = ajout`, session passée ou à venir, aucune timesheet touchée. **409** session annulée ; **422** professeur inactif, déjà sur la session ou remplacé (annuler d'abord le remplacement). Conflit d'horaire = avertissement non bloquant. Côté professeur : `ma_situation.type = 'ajoute'`, la session apparaît dans `remplacements` de `/mes-classes` si elle est à venir.

### `DELETE /sessions/{session}/professeurs/{professeur}` — staff (CLS-04)
Retire un professeur **ajouté** (`origine = ajout`). **200** `{ data: <session> }` ; **409** si la ligne n'est pas un ajout, est impliquée dans un remplacement, ou si le professeur a **déjà une timesheet** pour cette session.

### `POST /sessions/{session}/remplacer`
`{ "professeur_remplace_id": 4, "professeur_remplacant_id": 9 }` → **200**
```json
{ "data": <CourseSessionResource avec professeurs>, "avertissements": [ { "date": "…", "message": "Le remplaçant est déjà assigné à une autre session ce jour-là (…)." } ] }
```
La ligne du remplacé passe `remplace = true` (+ `remplace_par_professeur_id`) ; le remplaçant reçoit une ligne `origine = remplacement`, `role = remplacant`. **409** session annulée ; **422** remplacé non assigné (ou déjà remplacé), remplaçant identique ou déjà assigné à la session. Un conflit d'horaire du remplaçant est un **avertissement non bloquant**. Une re-propagation n'écrase jamais un remplacement.

### `DELETE /sessions/{session}/remplacements/{professeur}`
Annule le remplacement du professeur `{professeur}` (le remplacé) : il est restauré ; la ligne du remplaçant est supprimée (ou devient une ligne de classe s'il y est assigné à cette date). **200** `{ data: <session> }` ; **409** si aucun remplacement à annuler.

## Portail professeur « Mes classes »

### `GET /mes-classes` — professeur (profil professeur requis, sinon 403)
`?inclure_terminees=1` pour inclure les assignations terminées.
```json
{ "data": [ { "assignation_id": 1, "role": "principal", "date_debut": "…", "date_fin": null, "actif": true,
              "classe": <ClasseResource avec cours, prochaine_session, professeurs>,
              "co_professeurs": [ {"id": 9, "nom": "Bob Prof", "role": "co_enseignant"} ] } ],
  "remplacements": [ <CourseSessionResource> ] }
```
`remplacements` : sessions **à venir** non annulées où le professeur intervient comme **remplaçant ponctuel** sans être assigné à la classe.

### `GET /mes-classes/{classe}/sessions` — professeur ayant accès à la classe (sinon 403)
Sessions visibles par le professeur (pour un remplaçant ponctuel : uniquement la/les session(s) remplacée(s)), chacune = `CourseSessionResource` +
```json
{ "ma_situation": { "type": "assignee" | "remplace_par" | "remplacant_de", "professeur": null | {"id": 9, "nom": "…"} },
  "co_professeurs": [ {"id": 9, "nom": "…", "role": "co_enseignant"} ] }
```
`remplace_par` : je suis remplacé (`professeur` = mon remplaçant) ; `remplacant_de` : je remplace `professeur`.

## Isolation (RG-5) — listes filtrées côté serveur pour un professeur

`GET /classes`, `GET /classes/{classe}` (403 hors périmètre), `GET /classes/{classe}/sessions`, `GET /sessions`, `GET /calendar/month|week|year|agenda` ne renvoient que ses classes (assignation actuelle ou passée, ou remplacement ponctuel sur l'une de leurs sessions) et ses sessions (ligne non remplacée, ou session de sa classe pendant la durée de son assignation). Les écritures sur classes, sessions, calendrier scolaire et assignations restent **403**.

Les `ClasseResource` et `CourseSessionResource` exposent `professeurs` (liste légère `id`, `nom`, `role`, `remplace`) — jamais de tarif.

## Droit sur les cours et liens (RG-6)

`Professeur::canAccessCours(Cours)` : le professeur a une assignation **active** (`date_fin` null ou ≥ aujourd'hui) sur au moins une classe de ce cours. Les routes de liens/ressources du cours (`/cours/{id}/liens`, `/cours/{id}/ressources`) s'appuient sur cette règle (un professeur sans classe active de ce cours reçoit 403).

## Routes supprimées

`/professeurs/{professeur}/cours*`, `/cours/{cours}/professeurs*` (table `professeur_cours` et `ProfesseurCoursController` supprimés) → **404**.
