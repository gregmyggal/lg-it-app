# API CLS-02 — Classe sur deux périodes

Étend `API_T1_CLASSES.md`. Source de vérité des règles : `docs/requirements/CLS-02-classe-deux-periodes.md`.

## Modèle de réponse `Classe` (GET /classes, GET /classes/{id}, POST, PUT)
Retirés de la racine : `cours_id`, `cours`, `periode_id`, `periode`, `date_premiere_session`, `heures_defrayables`.
Ajoutés :
```json
{
  "id": 1, "annee_scolaire_id": 1, "annee_scolaire": {"id":1,"libelle":"2026-2027"},
  "titre": "Scratch → Python",
  "jour_semaine": 3, "heure_debut": "14:00", "heure_fin": "17:00", "lieu": "…", "duree_seance": 3.0,
  "statut": "active",
  "periodes": [
    {
      "id": 10,                       // id de classe_periodes
      "periode_id": 1, "numero": 1,
      "periode": {"id":1,"numero":1,"date_debut":"2026-09-01","date_fin":"2027-01-31"},
      "cours_id": 5, "cours": {"id":5,"titre":"Scratch","slug":"scratch"},
      "date_premiere_session": "2026-10-07",
      "date_derniere_session": "2027-01-27",   // dernière session non annulée
      "statut": "active",              // active | annulee
      "motif_annulation": null,
      "nb_sessions": 14,               // non annulées, bis inclus
      "nb_hors_periode": 0,
      "heures_defrayables": 2.0
    }
  ],
  "nb_sessions": 28,
  "alerte_periode_2": null,            // ou {"date_fin_periode_1":"2027-01-27","jours_restants":20,"message":"La période 2 est à planifier"}
  "prochaine_session": {"id":1,"seance_numero":3,"bis_rang":0,"periode_numero":1,"libelle":"P1 · Séance 3","date":"…"},
  "professeurs": [ … inchangé … ],
  "can": {"update":true,"delete":true}
}
```
`titre` = « Cours P1 → Cours P2 » ; cours seul si une seule période ou même cours.
Les périodes sont triées par `numero`.

## Création
### `POST /classes/apercu` et `POST /classes` — admin, directeur
```json
{
  "annee_scolaire_id": 1, "jour_semaine": 3, "heure_debut": "14:00", "heure_fin": "17:00", "lieu": "Salle A",
  "periodes": [
    {"periode_id": 1, "cours_id": 5, "date_premiere_session": "2026-10-07"},
    {"periode_id": 2, "cours_id": 6, "date_premiere_session": "2027-02-17"}
  ]
}
```
- `periodes` : 1 ou 2 éléments, `periode_id` distincts et appartenant à l'année. La période peut être 1, 2 ou les deux (démarrage en P2 seule autorisé).
- Validation 422 : jour de la date ≠ `jour_semaine` (le plan recale sur le jour demandé comme aujourd'hui → pas d'erreur, `recale: true`) ; date hors bornes de la période (`periodes.N.date_premiere_session`) ; P2 qui démarre ≤ dernière séance de P1 quand les deux sont fournies.
- **Plus aucun blocage sur la fin de période** : avertissement.
- `apercu` → `{"data": {"periodes": [{"periode_id":1,"numero":1,"date_premiere_session":"…","recale":false,"seances":[{"seance_numero":1,"date":"…","hors_periode":false}],"dates_sautees":[…],"avertissements":["La séance 14 dépasse la fin de la période 1 (…)"],"blocage":null|{"message":"…","periode_numero":2}}]}}`. `blocage` n'est renseigné que pour les cas bloquants (P2 avant fin de P1).
- `POST` → 201, `Classe` complète (28 ou 14 sessions). Transactionnel.

## Périodes d'une classe
| Route | Rôle | Description |
|---|---|---|
| `POST /classes/{classe}/periodes/apercu` | admin, directeur | `{periode_id, cours_id, date_premiere_session}` → plan de la période (même forme que ci-dessus, un élément) |
| `POST /classes/{classe}/periodes` | admin, directeur | Ajoute la période (P2 ou P1 manquante) : génère 14 sessions, propage les professeurs actifs aux séances à venir. 201 → `Classe` |
| `PUT /classes/{classe}/periodes/{classePeriode}` | admin, directeur | `{cours_id}` : change le cours. 409 si ≥ 1 timesheet sur les sessions de la période (`{"message": "…", "nb_saisies": n}`). 200 → `Classe` |
| `DELETE /classes/{classe}/periodes/{classePeriode}` | admin, directeur | Suppression physique si aucune heure encodée **et** la classe a une autre période ; 409 sinon avec message guidant vers l'annulation |
| `POST /classes/{classe}/periodes/{classePeriode}/annuler` | admin, directeur | `{motif}` (obligatoire) : séances à venir annulées (celles sans heures), période `annulee`. 200 → `Classe` |

Codes : 403 non autorisé, 404, 409 règle de cycle de vie, 422 validation.

## Filtres `GET /classes`
`annee_scolaire_id`, `jour_semaine`, `statut`, `cours_id` (P1 **ou** P2), `periode_id` (classes ayant cette période). Tri : année, jour, heure, id.

## Sessions
- `CourseSessionResource` : ajoute `periode_numero`, `classe_periode_id`, `hors_periode` (bool : date > `periodes.date_fin`), `libelle_complet` (« P1 · Séance 3 » / « P1 · Séance 3 bis »), et `classe.cours` / `classe.cours_id` sont remplacés par `cours` de la période de la session (`{"id","titre"}`) ; `classe.periode_id` → `periode_id` de la période.
- `GET /classes/{classe}/sessions` : trié par période puis séance ; filtre `periode_numero`.
- `PUT /sessions/{id}` et `POST /classes/{classe}/sessions/bis` : **plus de 422 après la fin de période**. `bis` prend `periode_numero` (ou `classe_periode_id`) en plus de `seance_numero` (défaut : 1). La réponse contient `avertissements: ["Cette date est après la fin de la période 1"]` si hors période. Dépassement de 14 : même règle de confirmation 409 qu'avant (compté par période).

## Traçabilité du changement de cours (ajout validé le 2026-10-03)
- Table `classe_periode_cours_historique` : `id`, `classe_periode_id` (FK, cascade), `ancien_cours_id`, `nouveau_cours_id` (FK cours, restrict), `user_id` (FK users, null on delete), `created_at`. Une ligne par `PUT /classes/{classe}/periodes/{classePeriode}` qui change réellement le cours (rien si le cours est identique), écrite dans la même transaction.
- `GET /classes/{classe}/periodes/{classePeriode}/historique-cours` — admin, directeur → `{"data":[{"id":1,"ancien_cours":{"id":5,"titre":"Scratch"},"nouveau_cours":{"id":6,"titre":"Python"},"par":{"id":2,"name":"…"},"date":"2026-10-03T10:12:00+02:00"}]}` trié du plus récent au plus ancien.
- `periodes[]` de `Classe` expose `nb_changements_cours` (entier).

## Précisions d'implémentation (backend livré)
- `POST /classes/apercu` et `/classes/{classe}/periodes/apercu` : une date hors des bornes de la période renvoie 422 (`periodes.N.date_premiere_session`), même en aperçu ; seule la règle P2 ≤ fin de P1 sort dans `blocage` (message : « La période 2 démarre avant la fin de la période 1 (dernière séance le jj/mm/aaaa). »). Un `POST` bloqué renvoie 422 avec ce message (clé d'erreur `periodes.N.date_premiere_session`, ou `date_premiere_session` pour l'ajout).
- `periodes[]` ajoute `nb_changements_cours`. `prochaine_session` ajoute `periode_numero` et son `libelle` est « P1 · Séance 3 ». `alerte_periode_2.jours_restants` peut être négatif (P1 déjà terminée).
- `bis` : sans `periode_numero` ni `classe_periode_id`, la période visée est l'unique période de la classe, sinon P1. Dépassement de 14 compté par période : 409 « Cette période passera à N sessions » (`nb_sessions`).
- `CourseSessionResource` : `avertissements` toujours présent (liste vide hors période) ; `classe` ne contient plus que `id` et `jour_semaine` ; `cours` et `periode_id` sont à la racine de la session.
- Assignations professeur (`POST …/professeurs`) : `recapitulatif.par_periode = [{periode_numero, sessions_assignees}]`. `ProfesseurClasseResource.classe` expose `titre` et `periodes[{id, periode_id, numero, cours_id, cours, statut}]` (plus de `cours_id`/`cours`).
- Portail / timesheets : sessions avec `periode_numero`, `libelle_complet`, `classe_libelle` = cours de la période ; `GET /cours/{cours}/liens` → `classes[].seance_courante` = prochaine séance des périodes de CE cours (`periode_numero`, `libelle`).
- `GET /classes/{classe}/sessions` : tri période → séance → bis (filtre `periode_numero`) ; `GET /sessions` accepte aussi `periode_numero`.
- Annulation de période : annule les séances à venir sans heures encodées (les autres restent) ; 409 si la période est déjà annulée. Changement de cours sur période annulée : autorisé.
- Rollback de la migration : `classes` reçoit la période de plus petit numéro ; la seconde période d'une classe est perdue.

