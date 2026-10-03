# API CLS-03 — Gestion des années scolaires et des périodes

Étend `API_T1_CLASSES.md` (section Années scolaires). Règles : `docs/requirements/CLS-03-gestion-annees-scolaires.md`. Routes sous `auth:sanctum`, admin et directeur (`isStaff`) ; professeur → 403.

## Ressource `AnneeScolaire` (liste, détail, création, modification)
Champs existants conservés (`id`, `libelle`, `date_debut`, `date_fin`, `statut` brouillon|active|archivee, `periodes[]`) plus :
```json
{ "classes_count": 14, "sessions_count": 412, "calendrier_count": 38, "en_cours": true,
  "updated_at": "2026-10-03T14:32:10+02:00",       // = `version` à renvoyer à la modification
  "updated_by": {"id": 2, "name": "Directrice"} ,   // null si inconnu
  "can": {"update": true, "delete": false, "archiver": true, "import_fwb": true,
          "raison_non_supprimable": "Contient 14 classes (412 séances) : archivez-la plutôt."} }
```
`en_cours` = la date du jour (Europe/Brussels) est comprise dans l'année. `classes_count`/`sessions_count` via withCount. `raison_non_supprimable` null si supprimable.

## `GET /annees-scolaires/proposition?libelle=2027-2028`
→ `{"data":{"libelle":"2027-2028","date_debut":"2027-09-01","date_fin":"2028-06-30","periodes":[{"numero":1,"date_debut":"…","date_fin":"…"},{"numero":2,…}],"source":"fwb"|"defaut","message":"Proposition par défaut : calendrier FWB non importé."|null}}`.
`libelle` optionnel (défaut : année suivant la dernière existante). Règle : FWB importé pour l'année (même libellé) → P1 = rentrée → vendredi précédant la coupure centrale (Carnaval), P2 = lundi de reprise → dernier jour de l'année scolaire ; sinon P1 01/09 → 31/01, P2 01/02 → 30/06.

## `POST /annees-scolaires`
Inchangé (payload de `API_T1_CLASSES.md`) + 422 : chevauchement avec une autre année (« Cette année chevauche 2026-2027 (qui se termine le 02/07/2027). »), libellé déjà utilisé, P2 ≤ fin de P1, fin ≤ début. Renseigne `updated_by`.

## `PUT /annees-scolaires/{annee}`
Corps : champs optionnels existants + **`version`** (le `updated_at` lu ; obligatoire dès que dates/périodes/libellé sont envoyés).
- **Plus aucun 422 sur les séances existantes.** Les séances dépassant la nouvelle fin ressortent simplement `hors_periode = true`.
- 409 `{"message":"Ces dates ont été modifiées par Sophie Martin à 14:32.","code":"modification_concurrente","modifie_par":{"id","name"},"modifie_a":"…","annee":{…ressource à jour…}}` si `version` ≠ `updated_at` courant.
- 409 `{"code":"annee_archivee","message":"Réactivez l'année pour modifier ses dates."}` si l'année est archivée (seul `statut` peut alors changer).
- 422 : mêmes règles de cohérence/chevauchement que la création. Avertissement trou > 6 semaines : champ `avertissements: ["44 jours sans période : …"]` dans la réponse 200 (et dans l'aperçu).
- Renseigne `updated_by` et rafraîchit `updated_at`.

## `POST /annees-scolaires/{annee}/apercu-impact` (lecture seule)
Corps : `{"periodes":[{"numero":1,"date_debut":"…","date_fin":"…"},{"numero":2,…}]}`. Réponse 200 (jamais d'écriture) :
```json
{ "data": {
  "bloquants": ["La période 2 doit commencer après la fin de la période 1 (18/02/2028). Au plus tôt le 19/02/2028."],
  "avertissements": [],
  "periodes": [{"numero":1,"avant":{"date_debut":"…","date_fin":"…"},"apres":{…},"evolution":"fin −14 j"}],
  "classes_touchees": 3,
  "seances_hors_periode_en_plus": 6,
  "seances_redevenant_dans_periode": 0,
  "classes_demarrant_avant_debut": 1,
  "classes_sans_p2": 2, "alertes_p2_creees": 2, "alertes_p2_supprimees": 0,
  "calendrier_hors_annee": 0,
  "par_classe": [{"classe_id":3,"titre":"React → Python Ado","creneau":"mercredi 14h–17h","periode_numero":1,"nb_seances":6,"dates":["2027-02-24"],"type":"hors_periode"|"demarre_avant_debut"|"alerte_p2"}]
} }
```
Les calculs réutilisent exactement les règles de `hors_periode` (date > fin de période) et `alerte_periode_2` (extraites dans un service commun appliqué aux dates hypothétiques). Si `bloquants` est non vide, les compteurs sont à 0 et `par_classe` vide.

## Archivage / réactivation
- `POST /annees-scolaires/{annee}/archiver` → 200 `AnneeScolaire` (statut `archivee`). N'affecte ni classes, ni séances, ni timesheets.
- `POST /annees-scolaires/{annee}/reactiver` → 200 (statut `active`). L'activation d'un brouillon passe par `PUT {statut:"active"}`.
- Année archivée : exclue de la création de classe (422 « Cette année scolaire est archivée »), des alertes `alerte_periode_2` et des propositions ; dates verrouillées.

## `DELETE /annees-scolaires/{annee}`
204 si sans classe ; 409 `{"message":"Contient 14 classes (412 séances) : archivez-la plutôt.","code":"annee_non_supprimable","classes_count":14,"sessions_count":412}`.

## Création de classe / ajout de période — date hors bornes
Pour `POST /classes`, `POST /classes/apercu`, `POST /classes/{classe}/periodes[/apercu]` : une `date_premiere_session` hors des bornes de la période renvoie 422 avec, en plus du format d'erreur habituel, `"code":"date_hors_bornes_periode"` et `"contexte":{"annee_id":1,"annee_libelle":"2026-2027","numero":2,"debut":"2027-02-22","fin":"2027-07-02"}` (pour le lien contextuel « Modifier les dates de la période 2 »).
