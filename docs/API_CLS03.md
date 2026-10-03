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

## Précisions d'implémentation (backend livré)
Écarts et choix par rapport au contrat ci-dessus :
- **Corps d'erreur** : tous les 409/422 métier suivent le format habituel `{message, errors, …extra}` ; `errors` est `{}` pour les 409. Le 422 « année archivée » à la création de classe / ajout de période porte aussi `code: "annee_archivee"` et la clé d'erreur `annee_scolaire_id` (création) ou `periode_id` (ajout) ; il s'applique aussi aux aperçus (`/classes/apercu`).
- **`version`** : le `updated_at` ISO 8601 (fuseau Europe/Brussels, précision d'une seconde) de la ressource ; la comparaison se fait à la seconde près et le backend garantit que `updated_at` avance d'au moins 1 s à chaque écriture. `version` absente avec libellé/dates/périodes → 422 (`errors.version`). Un `PUT {statut}` seul n'exige pas de version. L'archivage/réactivation met aussi à jour `updated_at` et `updated_by` (la version change donc).
- **Année archivée** : tout `PUT` contenant `libelle`, `date_debut`, `date_fin` ou `periodes` renvoie 409 `annee_archivee` *avant* la validation ; `PUT {statut:"active"}` reste possible. `POST …/archiver` et `…/reactiver` sont idempotents (200 même si le statut est déjà celui demandé).
- **L'année suit les périodes** : si le `PUT` envoie `periodes` sans `date_debut`/`date_fin`, l'année est recalée sur P1 début → P2 fin (RG-1) et aucun contrôle « période comprise dans l'année » n'est fait. Si les dates d'année sont envoyées, les périodes doivent y être comprises.
- **Clé des erreurs de cohérence** : fin ≤ début, P2 ≤ fin de P1 et chevauchement entre années sortent tous sous `errors.periodes` (le libellé en doublon sous `errors.libelle`, message existant « La valeur de libellé est déjà utilisée. »). Le chevauchement compare aussi les années archivées.
- **`avertissements`** : champ de premier niveau (à côté de `data`) des réponses 201 (création) et 200 (modification) ; toujours présent (tableau, éventuellement vide). Seuil : plus de 42 jours entre la fin de P1 et le début de P2 (« n jours sans période » = jours entièrement hors période).
- **`proposition`** : « calendrier FWB importé » = entrées FWB de type vacances en base pour une année existante portant ce libellé, **sinon** le fichier `database/data/calendrier_fwb_{libelle}.json` (une année qui n'existe pas encore n'a rien en base). Coupure centrale = entrée de vacances commençant entre le 1er février et le 31 mars. P1 début = toujours 1er septembre ; P2 fin = premier vendredi de juillet (FWB) ; P1 fin = vendredi précédant la coupure ; P2 début = lundi suivant la fin des vacances (ex. 2026-2027 : P1 01/09/2026 → 19/02/2027, P2 08/03/2027 → 02/07/2027). Sans libellé et sans année existante : année scolaire courante. `libelle` invalide (hors `AAAA-AAAA`) → 422.
- **`apercu-impact`** : accessible à qui peut modifier l'année (`can.update`) ; sur une année archivée, un bloquant « Réactivez l'année… » est renvoyé. `periodes[].evolution` = `début +N j · fin −N j` ou `inchangée`. `par_classe[].dates` liste toutes les dates concernées (`nb_seances = dates.length`) ; `demarre_avant_debut` : 1 date (la `date_premiere_session`) ; `alerte_p2` : `nb_seances 0`. `seances_*` comptent les séances non annulées. `classes_demarrant_avant_debut` ne compte que les classes qui ne l'étaient pas avant. `classes_touchees` = classes distinctes présentes dans `par_classe`. `calendrier_hors_annee` = entrées du calendrier entièrement hors de la nouvelle année.
- **Alertes P2 dans l'aperçu** : la règle CLS-02 `alerte_periode_2` dépend de la dernière séance de P1 et de la date du jour, **pas** des bornes de période ; elle est réutilisée telle quelle (`PeriodeRegles`), donc modifier la fin de P1 ne crée/supprime pas d'alerte réelle (`alertes_p2_creees`/`supprimees` valent 0 aujourd'hui). `classes_sans_p2` chiffre les classes candidates (active, P1 sans P2, année non archivée). Les champs sont conservés pour le jour où la règle dépendra des bornes.
- **Source de vérité** : `App\Services\PeriodeRegles` (`horsPeriode`, `sqlHorsPeriode`, `demarreAvantDebut`, `alertePeriode2`) est utilisé par `CourseSession::isHorsPeriode`, `Classe::chargerPeriodes`, le générateur de séances, `ClasseResource` et `AnneeImpactService`.
- **`can`** : `delete` = droit ET aucune classe ; `archiver` = droit staff (le front choisit archiver/réactiver selon `statut`). `sessions_count` compte toutes les séances (annulées incluses) ; `calendrier_count` les entrées non masquées. La liste et le détail sont tous deux enrichis (compteurs en `withCount`).
- **`date_hors_bornes_periode`** : `contexte` contient en plus `annee_libelle`. Le message reste celui de CLS-02 ; l'erreur de validation reste sur `periodes.N.date_premiere_session` (création) ou `date_premiere_session` (ajout).
