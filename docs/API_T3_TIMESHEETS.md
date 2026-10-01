# API CLS-01 · Tranche T3 — Heures liées aux sessions, écran mensuel, validation en lot

Préfixe `/api`, `Authorization: Bearer <token>`, JSON snake_case, erreurs `{message, errors}` en français. Contrat : `docs/requirements/CLS-01-T3-contrat.md`. Règles : `docs/mockups/CLS-01-T3/WORKFLOW_UX.md` (R-T3-1 à 12).

**Statuts d'une saisie** (`statut_validation`, sans accent) : `brouillon` → `soumis` → `confirme` → `genere`. Les libellés accentués sont affichés par le front. *(Correction : l'ancien ENUM `brouillon|soumis|valide` refusait `confirmé`/`généré` écrits par le code.)*
`encodage` (état **calculé** d'une session pour un professeur, pas un statut) : `a_encoder` | `brouillon` | `soumis` | `confirme` | `genere` (le moins avancé l'emporte s'il y a plusieurs saisies).

## Saisie (`TimesheetResource`)
Forme historique + `session` (`id`, `libelle`, `date`, `classe_id`, `classe_libelle`) ou `null`, `montant_brut` (heures × tarif en vigueur ; **le professeur voit ses propres euros**, Q22), `can` (`update`, `delete`, `submit`, `validate`). Les listes sont des **tableaux** (sans enveloppe `data`).

### `GET /timesheets`
Staff : toutes ; professeur : les siennes. Filtres : `professeur_id` (staff), `classe_id`, `course_session_id`, `date_from`, `date_to` (`YYYY-MM-DD`), `statut`, `type_activite` (`animation`|`preparation`).

### `POST /timesheets` — professeur uniquement (le staff n'encode pas : **403**)
- **Liée à une session** : `{ "course_session_id": 12, "type_activite"?: "animation", "nombre_heures"?: 3, "commentaire"?: "…" }` → `professeur_id` = utilisateur connecté (jamais accepté du client), `date_prestation` et `cours_id` = ceux de la session, durée par défaut = durée de la session (0,5–24 h).
  - **403** si le professeur n'est pas assigné à la session, ou en est **remplacé** (il garde ses saisies existantes) ; le **remplaçant** peut encoder.
  - **422** session **annulée** ou **pas encore commencée** ; **422** doublon `(professeur, session, type_activite)` : « Ces heures sont déjà encodées pour cette session. »
- **Libre** (sans cours ni session, ex. préparation un autre jour) : `{ "date_prestation": "2026-11-02", "type_activite": "preparation", "nombre_heures": 2, "cours_id"?: 5, "commentaire"?: "…" }` — aucun motif obligatoire, aucune unicité.
- **201** + saisie.

### `PUT /timesheets/{id}` / `DELETE /timesheets/{id}`
Inchangés (brouillon pour le professeur). Le rattachement à la session n'est pas modifiable : `date_prestation` et `cours_id` sont ignorés pour une saisie liée.

## Écran mensuel — professeur

### `GET /timesheets/mon-mois?annee=2026&mois=10`
```json
{ "annee": 2026, "mois": 10,
  "sessions": [ { "id": 12, "libelle": "Séance 2", "seance_numero": 2, "bis_rang": 0, "date": "2026-10-14",
      "heure_debut": "14:00", "heure_fin": "17:00", "statut": "planifiee", "annulee": false,
      "classe_id": 7, "classe_libelle": "Scratch Junior", "duree_par_defaut": 3,
      "encodage": "a_encoder", "peut_encoder": true,
      "remplace_par": null, "mes_timesheets": [ <saisie> ] } ],
  "libres": [ <saisie sans session> ],
  "synthese": { "heures": 5, "montant": 50, "jours": 2, "depassements": [], "nb_brouillons": 2 },
  "peut_signer": false }
```
Seules les sessions **commencées** où le professeur intervient (même « remplacé ») ou a des heures. `remplace_par` = `{id, nom}` si je suis remplacé sur la session.

### `POST /timesheets/soumettre-mois`
`{ "annee": 2026, "mois": 10, "sessions"?: [ { "course_session_id": 12, "nombre_heures"?: 2.5, "type_activite"?: "animation" } ] }` → `{ "creees": 1, "soumises": 3 }`. **Atomique** : crée d'abord les saisies préremplies incluses (une session déjà saisie est ignorée), puis passe en `soumis` tous les brouillons du mois. **422** (rien n'est modifié) si une session incluse n'a pas commencé, est annulée, ou n'est pas du mois.

### `GET /mes-classes/{classe}/sessions` (étendu)
Chaque session porte en plus : `encodage`, `mes_timesheets`, `duree_par_defaut`, `peut_encoder`.

## Vue directeur et validation — admin, directeur

### `GET /timesheets/sessions-sans-heures?classe_id=&annee_scolaire_id=&date_from=&date_to=` — staff (**403** sinon)
Sessions **terminées** (date passée, ou aujourd'hui une fois l'heure de fin atteinte), non annulées, où au moins un professeur attendu (ligne non remplacée) n'a **aucune** saisie :
`{ "data": [ { "session_id", "libelle", "date", "classe_id", "classe_libelle", "professeurs_sans_heures": [{id, nom}], "jours_de_retard" } ] }`.

### `POST /timesheets/valider-lot`
`{ "ids": [1,2,3], "lissages"?: [ { "timesheet_id": 1, "date_to": "2026-10-14", "montant_to_move": 15.98 } ] }` → `{ "validees": 3, "lissages_appliques": 1 }`.
**Une seule transaction** : les lissages demandés (plafond journalier 44,02 €, mêmes règles que `apply-lissage`) sont appliqués, puis les saisies passent en `confirme` (`validated_by`, `validated_at`, `lissage_applique`). **Aucune saisie n'est exclue pour cause de lissage** (« valider sans lisser » reste possible). **422** et **rien n'est modifié** si une saisie n'est pas validable (non `soumis`, droits) ou si un lissage est impossible : `{ message, errors, invalides: [{id, raison}] }`.

## Sessions
`POST /sessions/{session}/cancel` et `PUT /sessions/{session}` : **409** si la session a des saisies d'heures (le remplacement d'un professeur reste possible).

## Workflow existant (statuts normalisés)
`POST /timesheets/{id}/submit` (→ `soumis`), `/validate` (→ `confirme`), `propose-lissage`/`apply-lissage`, signature (`confirme` requis), PDF (`confirme` → `genere`).
