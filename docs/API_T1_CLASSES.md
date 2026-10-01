# API CLS-01 · Tranche T1 — Années scolaires, calendrier scolaire, classes, sessions

Préfixe `/api`, authentification `Authorization: Bearer <token>` (Sanctum), JSON snake_case. Contrat : `docs/requirements/CLS-01-T1-contrat.md`.

**Rôles** : `admin` et `directeur` (= « staff ») sur tout. Depuis T2, un `professeur` peut **lire** ses classes et ses sessions (listes filtrées côté serveur) ; toute écriture reste réservée au staff (voir `API_T2_PROFESSEURS_CLASSES.md`). Exception : l'import FWB est réservé à `admin`.

## Conventions

- **Succès** : `{ "data": ... }` (Resource) ; listes paginées : `{ "data": [...], "links": {...}, "meta": {...} }` (`?page=&per_page=`, max 100).
- **Erreurs** (toujours `{ "message": "...français..." }`, plus `errors` quand c'est pertinent) :
  - `401` `{"message":"Non authentifié."}` · `403` `{"message":"Action non autorisée."}` · `404`
  - `422` validation : `{"message":"...","errors":{"champ":["détail"]}}` ; règle métier : même forme (`errors` peut être `{}`).
  - `409` conflit métier : `{"message":"..."}` (+ champs additionnels, ex. `nb_sessions`).
- Dates `YYYY-MM-DD`, heures `HH:MM`. Valeurs d'enum sans accent (libellés côté front).
- Les Resources exposent `can` (capacités calculées côté serveur) : le front ne devine rien.

Enums : année `statut` = `brouillon|active|archivee` · calendrier `type` = `vacances|ferie|fermeture`, `source` = `fwb|ecole` · classe `statut` = `active|terminee|archivee` · session `statut` = `planifiee|en_cours|terminee|annulee`.

---

## Années scolaires

### `GET /annees-scolaires` — admin, directeur
Toutes les années (plus récente d'abord), avec `periodes`.

### `POST /annees-scolaires` — admin, directeur
Crée l'année **et ses 2 périodes** (transaction). Ne remplit pas le calendrier (voir import FWB).
```json
{ "libelle": "2027-2028", "date_debut": "2027-08-23", "date_fin": "2028-07-03", "statut": "active",
  "periodes": [ {"numero":1,"date_debut":"2027-08-23","date_fin":"2028-02-18"},
                {"numero":2,"date_debut":"2028-02-21","date_fin":"2028-07-03"} ] }
```
`statut` optionnel (défaut `active`). **201** :
```json
{ "data": { "id": 2, "libelle": "2027-2028", "date_debut": "2027-08-23", "date_fin": "2028-07-03", "statut": "active",
  "periodes": [ {"id":3,"annee_scolaire_id":2,"numero":1,"date_debut":"2027-08-23","date_fin":"2028-02-18"}, {"id":4,"annee_scolaire_id":2,"numero":2,"date_debut":"2028-02-21","date_fin":"2028-07-03"} ],
  "can": { "update": true, "delete": true, "import_fwb": false } } }
```
**422** : libellé déjà utilisé ; `periodes` ≠ 2 éléments ou numéros ≠ {1,2} ; période hors de l'année ; fin avant début ; « La période 2 doit commencer après la fin de la période 1. » (RG-3).

### `GET /annees-scolaires/{annee}` — admin, directeur
Une année avec `periodes`. `404` si inconnue.

### `PUT /annees-scolaires/{annee}` — admin, directeur
Champs optionnels : `libelle`, `date_debut`, `date_fin`, `statut` (archivage = `archivee`), `periodes` (les 2, mêmes règles qu'à la création). **422** `"Des sessions dépassent la nouvelle fin de la période N."` si une session non annulée dépasserait la nouvelle `date_fin`.

### `DELETE /annees-scolaires/{annee}` — admin, directeur
**204**. **409** `"Cette année scolaire contient des classes : archivez-la plutôt que de la supprimer."` Périodes et calendrier sont supprimés en cascade.

---

## Calendrier scolaire

Objet `CalendrierScolaireResource` :
```json
{ "id": 7, "annee_scolaire_id": 1, "date_debut": "2026-10-19", "date_fin": "2026-11-01", "type": "vacances",
  "libelle": "Vacances d'automne (Toussaint)", "source": "fwb", "masque": false, "modifie_manuellement": false,
  "can": { "update": true, "delete": true } }
```

### `GET /annees-scolaires/{annee}/calendrier` — admin, directeur
Filtres : `type`, `source`, `avec_masques=1` (les entrées masquées sont exclues par défaut). Non paginé, trié par `date_debut`.

### `POST /annees-scolaires/{annee}/calendrier` — admin, directeur
`{ "date_debut","date_fin","type","libelle" }` → **201**, `source = "ecole"` (toujours). **422** : `date_fin` < `date_debut`, `type` inconnu, `libelle` manquant.

### `PUT /calendrier-scolaire/{entree}` — admin, directeur
Champs optionnels : `date_debut`, `date_fin`, `type`, `libelle`, `masque` (`false` pour rétablir une entrée FWB masquée). Modifier une entrée `fwb` la marque `modifie_manuellement = true` (l'import ne la touche plus). **200** Resource.

### `DELETE /calendrier-scolaire/{entree}` — admin, directeur
Règle isolée dans `CalendrierScolaireService::supprimer()` (proposition UX non tranchée) :
- entrée `fwb` → **masquée** (`masque = true`), reste masquée aux imports suivants ;
- entrée `ecole` → supprimée physiquement.

**200** `{ "message": "Entrée FWB masquée.", "masque": true }` ou `{ "message": "Entrée supprimée.", "masque": false }`.

### `POST /annees-scolaires/{annee}/calendrier/import-fwb` — **admin uniquement**
Importe `backend/database/data/calendrier_fwb_{libelle}.json` (vacances FWB + jours fériés). **Idempotent** : identité = `cle` du fichier (`cle_fwb`) ; une entrée déjà présente (masquée ou modifiée à la main comprise) est *ignorée*, les entrées `ecole` ne sont jamais touchées ; les entrées hors de l'année sont ignorées.
**200** :
```json
{ "message": "Import FWB terminé : 14 entrée(s) créée(s), 0 ignorée(s).", "creees": 14, "ignorees": 0,
  "source_url": "https://www.enseignement.be/calendrier-scolaire", "verifie": false }
```
**403** pour le directeur. **422** `"Aucun calendrier FWB disponible pour l'année 2040-2041."` si le fichier n'existe pas.
Équivalent CLI : `php artisan calendrier:import-fwb {id|libelle} [--fichier=chemin.json]`.

---

## Classes

Objet `ClasseResource` :
```json
{ "id": 1, "cours_id": 3, "cours": {"id":3,"titre":"React","slug":"react"},
  "annee_scolaire_id": 1, "annee_scolaire": {"id":1,"libelle":"2026-2027"},
  "periode_id": 1, "periode": {"id":1,"annee_scolaire_id":1,"numero":1,"date_debut":"2026-08-24","date_fin":"2027-02-19"},
  "jour_semaine": 3, "heure_debut": "14:00", "heure_fin": "17:00", "lieu": null,
  "date_premiere_session": "2026-10-07", "statut": "active",
  "nb_sessions": 14,
  "prochaine_session": { "id": 12, "seance_numero": 1, "bis_rang": 0, "libelle": "Séance 1", "date": "2026-10-07" },
  "can": { "update": true, "delete": true } }
```
`nb_sessions` = sessions **non annulées** (bis inclus). `prochaine_session` = première session non annulée à partir d'aujourd'hui (`null` sinon). `jour_semaine` : 1 = lundi … 7 = dimanche.

### `GET /classes` — admin, directeur
Filtres : `annee_scolaire_id`, `periode_id`, `cours_id`, `jour_semaine`, `statut` ; pagination `page`, `per_page` (défaut 25, max 100).

### `POST /classes` — admin, directeur
```json
{ "cours_id": 3, "annee_scolaire_id": 1, "periode_id": 1, "jour_semaine": 3,
  "heure_debut": "14:00", "heure_fin": "17:00", "lieu": "Salle A", "date_premiere_session": "2026-10-07" }
```
Crée la classe et ses **14 sessions** en une transaction (`ClasseSessionGenerator`) : pas de +7 jours depuis `date_premiere_session` (recalée sur `jour_semaine` si besoin — la date recalée est celle qui est stockée) ; saute les dates couvertes par une entrée **non masquée** du calendrier de l'année ; `seance_numero` 1..14, `bis_rang` 0. **201** `ClasseResource`.
**422** : champs invalides ; période n'appartenant pas à l'année (`errors.periode_id`) ; **blocage fin de période** :
```json
{ "message": "La séance 14 dépasse la fin de la période 1",
  "errors": { "date_premiere_session": ["La séance 14 dépasse la fin de la période 1"] } }
```

### `POST /classes/apercu` — admin, directeur
Même payload que `POST /classes`, **aucune écriture**. **200** :
```json
{ "data": {
  "date_premiere_session": "2026-10-07", "recale": false,
  "seances": [ {"seance_numero":1,"date":"2026-10-07"}, {"seance_numero":2,"date":"2026-10-14"}, "... 14 éléments" ],
  "dates_sautees": [ {"date":"2026-10-21","libelle":"Vacances d'automne (Toussaint)","type":"vacances"}, {"date":"2026-11-11","libelle":"Armistice","type":"ferie"} ],
  "blocage": null } }
```
En cas de dépassement, `blocage = {"message": "La séance 14 dépasse la fin de la période 1", "periode_numero": 1}` (réponse 200 : c'est l'information affichée avant validation). 422 uniquement pour un payload invalide.

### `GET /classes/{classe}` — admin, directeur
`ClasseResource`.

### `PUT /classes/{classe}` — admin, directeur
Champs optionnels : `heure_debut`, `heure_fin` (fin > début), `lieu` (nullable), `statut`. **Aucune régénération** ; cours, période, jour et date de première séance ne sont pas modifiables (créer une autre classe). Le nouvel horaire / lieu est répercuté sur les sessions **à venir, `planifiee`, qui suivaient encore les valeurs de la classe** (les sessions passées ou ajustées à la main ne changent pas).

### `DELETE /classes/{classe}` — admin, directeur
**204** si aucune dépendance (aucune session passée, commencée, terminée ou annulée, aucune timesheet) ; les sessions sont alors supprimées. Sinon **409** `"Cette classe a des sessions passées, annulées ou des heures encodées : archivez-la plutôt que de la supprimer."` (`PUT statut = archivee`).

### `GET /classes/{classe}/sessions` — admin, directeur
Toutes les sessions (bis et annulées incluses), triées `seance_numero`, `bis_rang`. Non paginé. `CourseSessionResource[]`.

### `POST /classes/{classe}/sessions/bis` — admin, directeur
```json
{ "seance_numero": 5, "date": "2027-01-20", "heure_debut": "14:00", "heure_fin": "17:00", "lieu": "Salle B", "confirmer_depassement": false }
```
`heure_*` et `lieu` optionnels (défaut : valeurs de la classe). Crée une session `planifiee` avec `bis_rang = max + 1` pour la séance ; `remplace_session_id` = dernière session **annulée** de la séance n'ayant pas encore de remplaçante (sinon `null`). **201** `CourseSessionResource`.
- **422** : `seance_numero` hors 1..14 ou absent de la classe (« un bis doit se rattacher à l'une de ses séances ») ; `date` après `periodes.date_fin` → `"Cette date est après la fin de la période N"`.
- **409** si le nombre de sessions actives (hors annulées) dépasserait 14 sans `confirmer_depassement: true` :
  `{ "message": "Cette classe passera à 15 sessions", "errors": {}, "nb_sessions": 15 }`.

---

## Sessions

Objet `CourseSessionResource` :
```json
{ "id": 40, "classe_id": 1,
  "classe": { "id": 1, "cours_id": 3, "cours": {"id":3,"titre":"React"}, "jour_semaine": 3, "periode_id": 1 },
  "seance_numero": 5, "bis_rang": 0, "libelle": "Séance 5", "remplace_session_id": null,
  "date": "2026-11-04", "heure_debut": "14:00", "heure_fin": "17:00", "lieu": null,
  "statut": "planifiee", "motif_annulation": null, "cancelled_at": null,
  "alerte_calendrier": { "libelle": "Fermeture exceptionnelle", "type": "fermeture" },
  "can": { "update": true, "cancel": true, "bis": true } }
```
`libelle` : « Séance 5 », « Séance 5 bis », « Séance 5 bis 2 » (bis rang 2). `alerte_calendrier` = `null` ou `{libelle, type}` quand la date de la session (non annulée, non terminée) est couverte par une entrée **non masquée** du calendrier (ajoutée après coup) — la session n'est **jamais déplacée automatiquement**. `classe` (avec `cours`) est présent dans `GET /sessions`, `GET /classes/{classe}/sessions` et les réponses `PUT`/`cancel`/calendrier. `can.bis` est `false` pour une session annulée. `can.update` est `false` pour une session passée ou annulée ; `can.cancel` `false` si déjà annulée/terminée.

### `GET /sessions` — admin, directeur
Filtres : `classe_id`, `cours_id`, `date_from`, `date_to` (≥ `date_from`), `statut` ; pagination `page`, `per_page` (défaut 50, max 100) ; trié par date puis heure.

### `PUT /sessions/{session}` — admin, directeur (déplacer)
Champs optionnels : `date`, `heure_debut`, `heure_fin`, `lieu`. **200** `CourseSessionResource` (le `seance_numero` ne change jamais).
- **422** : format invalide ; `heure_fin` ≤ `heure_debut` ; `date` > `periodes.date_fin` → `"Cette date est après la fin de la période N"` (le dernier jour de la période est accepté).
- **409** : `"Une session passée ne peut pas être déplacée."` (date < aujourd'hui, fuseau Europe/Brussels, ou `terminee`) ; `"Une session annulée ne peut pas être déplacée."`.

### `POST /sessions/{session}/cancel` — admin, directeur
`{ "motif_annulation": "Professeur malade" }` (obligatoire). Passe la session à `annulee`, renseigne `cancelled_at`, **conserve `seance_numero`**. **200** `CourseSessionResource`. **422** sans motif ; **409** `"Seule une session planifiée ou en cours peut être annulée."`.

---

## Calendrier des sessions — `GET /calendar/month|week|year|agenda` — admin, directeur

Adapté au nouveau modèle (session → classe → cours). `professeur_id` n'existe plus (retour en T2). Filtres communs optionnels : `classe_id`, `cours_id`, `annee_scolaire_id`. Les sessions sont des `CourseSessionResource` (avec `classe.cours`).

| Route | Paramètres | Réponse |
|---|---|---|
| `/calendar/month` | `year`, `month` | `{ year, month, start_date, end_date, data: { "2026-10-07": [sessions] }, summary: {total_sessions, by_status} }` |
| `/calendar/week` | `year`, `week` (ISO) | `{ year, week, start_date, end_date, data: [sessions], summary: {total_sessions, by_day} }` |
| `/calendar/year` | `year` | `{ year, start_date, end_date, data: { "2026-10": [sessions] }, summary: {total_sessions, by_status, by_month} }` |
| `/calendar/agenda` | `from_date`, `to_date` (> from), `statut[]` | `{ from_date, to_date, data: [sessions], summary: {total, by_status} }` |

`GET/POST /calendar/views`, `DELETE /calendar/views/{view}` (préférences de vue par utilisateur) sont inchangés. `GET /calendar/professor/{professeur}` est **supprimée** (retour en T2).

---

## Routes retirées (Sprint 2)

`/cours/{cours}/recurrences*`, `/cours/{cours}/sessions`, `POST /sessions`, `GET|DELETE /sessions/{session}`, `POST /sessions/{session}/in-progress|complete`, `/sessions/{session}/professors*`, `GET /calendar/professor/{professeur}`.
Conservées : `/professeurs/{professeur}/cours*` et `/cours/{cours}/professeurs*` (`professeur_cours`, jusqu'à T2).
