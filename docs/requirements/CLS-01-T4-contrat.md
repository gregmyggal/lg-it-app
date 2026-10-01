# CLS-01 · Tranche T4 — Contrat technique (liens du cours)

**Périmètre T4** : liens du **cours** généraux et par **séance (1 à 14)**, édités par admin, directeur et tout professeur ayant une **classe active** de ce cours ; **historique versionné** (6 mois), **restauration** par les mêmes personnes, archivage (jamais de suppression physique), conflit d'édition, reprise des anciennes ressources, affichage dans les sessions et pour l'élève. **Hors T4** : stages, formations, anniversaires (ils gardent l'ancien comportement), types de cours (T5).

Sources : `CLS-01-modele-classes.md` (RG-6, RG-10, RG-12, AC-4 à 6, AC-16/17/19/23, **AC-34 à 39**, Q24), `docs/mockups/CLS-01-T4/WORKFLOW_UX.md` (R-T4-1 à 12) et mock-ups validés 01 à 04.

## 1. Données (migrations réversibles)

| Table | Changement |
|---|---|
| `classe_liens` | + `seance_numero` tinyint unsigned nullable (**NULL = lien général**, 1..14 = séance ; > 14 = « hors programme » si donnée existante), + `version` unsigned int défaut 1 (concurrence optimiste, +1 à chaque changement), + `created_by`/`updated_by` FK `users` nullable, + `deleted_at` (**soft delete**), index `(parent_type, parent_id, seance_numero, ordre)`. `theme` = **Type** (`document`\|`video`\|`outil`\|`jeu`) ; `seance` (texte libre), `pinned`, `actif` conservés mais **non exposés**. |
| `classe_liens_historique` (**append-only**) | `id`, `classe_lien_id` (indexé, sans FK : la ligne survit), `parent_type`, `parent_id`, `action` (`creation`\|`modification`\|`portee`\|`archivage`\|`ordre`\|`restauration`), `avant` JSON null, `apres` JSON null, `user_id` FK nullOnDelete, `restaure_depuis_id` null, `created_at` ; index `(parent_type, parent_id, created_at)`. |
| `cours_ressources` | + `repris_at` timestamp nullable (reprise manuelle en lien général). |

**Purge quotidienne** (commande `liens:purge-historique`, planifiée) : supprime les versions de plus de **6 mois** et les liens archivés dont l'archivage a plus de 6 mois. Aucune migration de données.

## 2. Règles (R-T4-*)

- **R-T4-1/2 droits** : lecture des liens d'un cours : staff ou **tout professeur** (profil requis) ; **écriture et restauration** : staff ou professeur avec une **assignation active** sur une classe du cours (`Professeur::canAccessCours`) ; **historique** : staff, ou professeur ayant **eu** une assignation sur une classe du cours (restauration réservée à l'assignation active). Sinon 403.
- **R-T4-3** titre (requis, 255), `url` (requise, http/https), description (255, facultative), `type` (facultatif), `seance_numero` (null ou 1..14 à la création/modification).
- **R-T4-4** une session (et son bis, même annulée) affiche généraux + liens de son `seance_numero`. (Le calcul est fait par le front avec `GET /cours/{id}/liens`.)
- **R-T4-5** chaque création, modification, changement de portée, archivage, réordonnancement et restauration produit **une version** ; un lot de déplacements (même auteur, même portée, < 5 min) = **une** version (mise à jour de la dernière version `ordre`).
- **R-T4-6** « supprimer » = **archiver** ; un lien archivé n'est vu **nulle part** (ni classes, ni élèves).
- **R-T4-7** restaurer = **nouvelle version** `restauration` (`restaure_depuis_id`) ; jamais de réécriture de l'historique.
- **R-T4-8** versions conservées 6 mois ; restaurer une version de plus de 6 mois → **410** expliqué.
- **R-T4-9** `PUT` avec une `version` dépassée → **409** `{message, lien: <version actuelle>}`, rien n'est modifié.
- **R-T4-10** un nouveau lien (ou un lien qui change de portée) va **en dernière position** de sa portée ; le réordonnancement agit dans **une** portée.
- **R-T4-11** élève : liens généraux + par séance, lecture seule, **sans auteur, historique ni archivé**.
- **Restauration** : version de `modification`/`portee`/`ordre` → rétablit `avant` ; `archivage` → désarchive (annulation de suppression) ; `restauration` → annule cette restauration ; `creation` → **422** (on archive plutôt). Si le lien a changé **depuis** la version et que `confirmer` n'est pas vrai → **409** `modifie_depuis` (avertissement, pas de blocage une fois confirmé).
- **Reprise des anciennes ressources** : crée un **lien général** (type repris de `type_ressource`) par ressource non reprise, version `creation`, puis marque `repris_at` ; atomique.

## 3. API (préfixe `/api`, `auth:sanctum`, `{message, errors}`)

| Méthode | Route | Rôle | Notes |
|---|---|---|---|
| `GET` | `/cours/{cours}/liens` | staff, professeur | `{data:[lien…], peut_modifier, peut_voir_historique, anciennes_ressources}` ; lien = `{id, titre, url, description, type, seance_numero, hors_programme, ordre, version, modifie_par:{id,nom}, modifie_le, can}` ; triés (portée, ordre) ; archivés exclus |
| `POST` | `/cours/{cours}/liens` | écriture | 201 |
| `PUT` | `/liens/{lien}` | écriture | `version` **requise** ; 409 si dépassée ; routes legacy conservées pour les parents non-cours |
| `DELETE` | `/liens/{lien}` | écriture | **archive** ; `200 {historique_id}` (cours) ; parents non-cours : suppression physique inchangée (204) |
| `PUT` | `/cours/{cours}/liens/ordre` | écriture | `{seance_numero: null\|1..14, ids:[…]}` |
| `GET` | `/cours/{cours}/liens/historique?portee=&auteur=&action=&page=` | historique | 6 mois ; chaque version : `{id, lien_id, lien_titre, action, avant, apres, auteur, created_at, peut_restaurer, expiree}` |
| `POST` | `/liens-versions/{version}/restaurer` | restauration | `{confirmer?: bool}` ; 410 / 409 / 422 / 200 `{data: lien, version_id}` |
| `POST` | `/cours/{cours}/liens/reprendre-ressources` | écriture | `{creees}` |
| `GET` | `/share/{code}` | public | cours : `liens: {generaux:[…], par_seance:[{seance_numero, liens:[…]}]}` (sans auteur) ; `ressources` = anciennes ressources **non reprises** (affichage transitoire) |

## 4. Tests (base `lgit_test`)
Droits (professeur actif / terminé / sans classe / élève, 401/403), liste (généraux + séances, archivés exclus, hors programme), CRUD versionné (une version par action, avant/après), lot d'ordre (une version), conflit 409, archivage + annulation, restauration (modification, portée, ordre, suppression, restauration de restauration, 422 création, 409 modifié depuis, 410 > 6 mois), purge, reprise des ressources (atomique, idempotente), partage élève (sans auteur/archivés), non-régression des parents stages/formations/anniversaires.

## 5. Front (étape suivante)
Écran partagé **Liens du cours** (`/admin/cours/:id/liens`, `/mes-ressources/:coursId`), page **Historique**, liste « Liens de mes cours », résumé « N généraux + M de la séance n » dans les sessions (professeur et staff), page élève (`SharePage`), bouton « Liens » dans la liste des cours (retrait du panneau empilé).
