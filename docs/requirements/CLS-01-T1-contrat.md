# CLS-01 · Tranche T1 — Contrat technique

**Périmètre T1** : années scolaires + périodes, calendrier scolaire (import FWB + édition manuelle), classes, génération des 14 sessions, ajustement des sessions (déplacer / annuler / bis), écrans admin. **Hors T1** : assignation des professeurs, remplacement, « Mes classes » (T2) ; timesheets (T3) ; liens par séance (T4) ; suppression des types de cours (T5).

Sources de vérité : `CLS-01-modele-classes.md` (règles RG, AC), `docs/adr/0001-…`, `docs/mockups/CLS-01/` (01, 02, 03 partiel, 05, 08), `docs/DEVELOPMENT_STANDARDS.md`.

## 1. Données (migrations réversibles, horodatage complet)

| Table | Colonnes | Contraintes |
|---|---|---|
| `annees_scolaires` | `id`, `libelle` (ex. « 2026-2027 »), `date_debut`, `date_fin`, `statut` (`brouillon`\|`active`\|`archivee`, défaut `active`) | `libelle` unique |
| `periodes` | `id`, `annee_scolaire_id` (FK cascade), `numero` (1\|2), `date_debut`, `date_fin` | `UNIQUE(annee_scolaire_id, numero)`, 2 périodes créées avec l'année |
| `calendrier_scolaire` | `id`, `annee_scolaire_id` (FK cascade), `date_debut`, `date_fin`, `type` (`vacances`\|`ferie`\|`fermeture`), `libelle`, `source` (`fwb`\|`ecole`), `masque` (bool, défaut false) | index `(annee_scolaire_id, date_debut)` |
| `classes` | `id`, `cours_id` (FK), `annee_scolaire_id` (FK), `periode_id` (FK), `jour_semaine` (1=lundi..7), `heure_debut`, `heure_fin`, `lieu` nullable, `date_premiere_session`, `statut` (`active`\|`terminee`\|`archivee`, défaut `active`) | index `(annee_scolaire_id, periode_id)`, `(cours_id)` ; FK **restrict** (pas de suppression physique en cascade) |
| `course_sessions` (**reconstruite**) | `id`, `classe_id` (FK restrict), `seance_numero` (1..14), `bis_rang` (0 = originale, 1 = bis…), `remplace_session_id` (FK self nullable), `date`, `heure_debut`, `heure_fin`, `lieu` nullable, `statut` (`planifiee`\|`en_cours`\|`terminee`\|`annulee`, défaut `planifiee`), `motif_annulation` nullable, `cancelled_at` nullable, timestamps | `UNIQUE(classe_id, seance_numero, bis_rang)` ; index `(classe_id, date)`, `(date, statut)` |

**Suppressions dans la même migration** (aucune donnée à migrer — décision direction) : `session_professors` (recréée en T2), `course_recurrences`. `session_calendar_views` : à conserver seulement si indépendante des sessions, sinon adapter/supprimer. `timesheets.course_session_id` : conserver la colonne (nullable), **ré-attacher la FK** à la nouvelle `course_sessions` (`nullOnDelete`). `professeur_cours` et son contrôleur **restent** jusqu'à T2.
Les `down()` recréent les structures supprimées (vides).

## 2. Règles (rappel opérationnel)

- **Génération** (service unique `ClasseSessionGenerator`, transactionnel, idempotent) : à partir de `date_premiere_session` (recalée sur `jour_semaine` si besoin) pas de +7 jours ; **saute** les dates couvertes par une entrée non `masque` du calendrier scolaire de l'année ; s'arrête à **14 séances** (`seance_numero` 1..14, `bis_rang` 0). Si la 14ᵉ tombe **après `periodes.date_fin`** → création **refusée (422)** avec message « La séance 14 dépasse la fin de la période N ».
- **Aperçu** sans persistance : liste des 14 dates + dates sautées (avec libellé du calendrier) + blocage éventuel.
- **Ajustement** : déplacer (date/heures) — jamais après `periodes.date_fin` (422), jamais une session passée (409) ; annuler avec `motif_annulation` obligatoire (la session **garde** son `seance_numero`) ; **bis** : `POST` avec `seance_numero`, `date` (dans la période) → crée `bis_rang = max+1`, `remplace_session_id` renseigné si la séance a une session annulée. Le nombre de sessions actives d'une classe peut dépasser 14 mais l'API exige `confirmer_depassement: true` sinon **409** « Cette classe passera à N sessions ».
- **Alerte calendrier** : une session dont la date est couverte par une entrée non masquée du calendrier (ajoutée après coup) expose `alerte_calendrier: { libelle, type }` (jamais de déplacement automatique).
- **Calendrier scolaire** : entrée `fwb` supprimée par le directeur → `masque = true` (reste masquée aux imports suivants, *proposition UX Q non encore tranchée : à isoler dans un seul endroit du code*) ; entrée `ecole` supprimée physiquement. Import FWB idempotent, n'écrase ni les entrées `ecole` ni les entrées modifiées à la main (marquer `modifie_manuellement` si nécessaire — ajouter la colonne).
- **Suppression** : année ou classe avec dépendances → 409 (archiver à la place).
- Valeurs d'enum stockées **sans accent** ; libellés affichés côté front.

## 3. API (préfixe `/api`, `auth:sanctum`, JSON snake_case, erreurs `{message, errors}` en français)

| Méthode | Route | Rôle | Notes |
|---|---|---|---|
| GET/POST | `/annees-scolaires` | admin, staff | POST crée l'année + ses 2 périodes (`periodes: [{numero, date_debut, date_fin}]`) |
| GET/PUT/DELETE | `/annees-scolaires/{annee}` | admin, staff | inclut `periodes` |
| GET/POST | `/annees-scolaires/{annee}/calendrier` | admin, staff | filtres `type`, `source` ; masqués exclus sauf `?avec_masques=1` |
| PUT/DELETE | `/calendrier-scolaire/{entree}` | admin, staff | règle FWB/école ci-dessus |
| POST | `/annees-scolaires/{annee}/calendrier/import-fwb` | **admin** | idempotent ; réponse : créées / ignorées |
| GET/POST | `/classes` | admin, staff | filtres `annee_scolaire_id`, `periode_id`, `cours_id`, `jour_semaine`, `statut` ; pagination |
| POST | `/classes/apercu` | admin, staff | même payload que POST `/classes`, ne persiste rien |
| GET/PUT/DELETE | `/classes/{classe}` | admin, staff | PUT ne régénère pas les sessions passées ; changement de jour/créneau = action explicite (voir agent) |
| GET | `/classes/{classe}/sessions` | admin, staff | toutes les sessions (bis et annulées incluses) |
| GET | `/sessions` | admin, staff | filtres `classe_id`, `cours_id`, `date_from`, `date_to`, `statut` (base du calendrier) |
| PUT | `/sessions/{session}` | admin, staff | déplacer |
| POST | `/sessions/{session}/cancel` | admin, staff | `motif_annulation` requis |
| POST | `/classes/{classe}/sessions/bis` | admin, staff | `seance_numero`, `date`, heures optionnelles, `confirmer_depassement` |
| GET | `/calendar/month|week|year|agenda` | admin, staff | adaptés au nouveau modèle (jointure classe→cours) ; `professorCalendar` retiré jusqu'à T2 |

**Resources** : `AnneeScolaireResource`, `ClasseResource` (avec `cours`, `periode`, `nb_sessions`, `prochaine_session`), `CourseSessionResource` (avec `seance_numero`, `bis_rang`, `libelle` « Séance 5 » / « Séance 5 bis », `alerte_calendrier`, `can`: `{update, cancel, bis}`), `CalendrierScolaireResource`.
**Policies** (`Gate::authorize`) : `AnneeScolairePolicy`, `ClassePolicy`, `CourseSessionPolicy`, `CalendrierScolairePolicy` — en T1, `admin` et `staff` uniquement ; un professeur reçoit **403** (l'accès professeur arrive en T2 via `professeur_classe`). Import FWB : admin seul.
**Suppression des routes obsolètes** : `/cours/{cours}/recurrences*`, `/sessions/{session}/professors*`, `/cours/{cours}/sessions`, ancien `/sessions/*` (in-progress, complete), remplacés par ce qui précède.

## 4. Import FWB

Fichier `backend/database/data/calendrier_fwb_2026-2027.json` (vacances scolaires FWB **et** jours fériés belges) + commande `php artisan calendrier:import-fwb {annee_scolaire_id|libelle}` appelée aussi par l'endpoint. Les dates doivent venir de la source officielle (enseignement.be, calendrier scolaire FWB 2026-2027) ; le fichier porte `source_url`, `verifie` (false tant que la direction n'a pas confirmé) et `date_import`. **Ne jamais inventer de dates** : si une date est incertaine, la lister dans le compte rendu.

## 5. Seeders et factories

Remplacer `CourseRecurrenceSeeder`, `CourseSessionSeeder`, `SessionProfessorSeeder` par `AnneeScolaireSeeder` (2026-2027, 2 périodes cohérentes avec la FWB + import FWB), `ClasseSeeder` (cours « React » : classes mercredi 14h–17h et samedi 9h–12h en période 1, avec génération des sessions). Factories pour chaque nouveau modèle. `DatabaseSeeder` mis à jour.

## 6. Tests back (base `lgit_test`, `scripts/test-backend.sh`)

- **Service** : génération (14 séances, dates sautées vacances/fériés, recalage du jour, blocage fin de période), bis (rang, remplace_session_id, dépassement sans/avec confirmation), déplacement (bornes, session passée), annulation (garde le numéro), alerte calendrier, idempotence.
- **Endpoints** : 401 / 403 (professeur) / 422 / 409 / succès pour chaque route ; import FWB admin-only et idempotent ; suppression année/classe avec dépendances.
- **Migrations** : `migrate:fresh` puis `migrate:rollback` propres sur `lgit_test`.
- Les tests ne doivent **jamais** viser la base de dev (garde-fou T0). Ne **pas** lancer `php artisan migrate` sur la base de dev : c'est fait à part, après sauvegarde.

## 7. Front (étape suivante, après l'API)

Menu : groupe « Scolarité » (Classes, Calendrier, Calendrier scolaire) ; pages selon mock-ups 01, 02 (aperçu des 14 dates, blocage), 03 (sessions : déplacer/annuler/bis, alerte calendrier ; **sans** section professeurs, réservée T2), 05 (calendrier scolaire, import FWB admin), 08 (calendrier filtré) ; retrait des écrans/menus Sprint 2 obsolètes (sessions par cours, recurrences) ; 4 états partout ; un seul client HTTP `api/client.js`.
