# API EMP-01 — Employeur par mois (ASBL / L-IT Solutions)

Toutes les routes sont sous `auth:sanctum`. Format d'erreur : `{ "message", "errors": { champ: [..] } }`. Canvas : `docs/requirements/EMP-01-employeur-par-mois.md`.

**Résolution (RG-2)** : ligne explicite du mois, sinon valeur du dernier mois défini avant (`source: "herite"`), sinon entité `par_defaut` (`source: "defaut"`). Sources stockées : `explicite`, `migration`, `fige` (écrit à la génération du PDF ou à la signature).
**Verrou** : `type_verrou` = `pdf` (saisies « généré » : personne ne peut modifier, l'admin déverrouille d'abord) ou `signature` (mois signé : direction refusée ; admin autorisé avec motif, la signature devient « à re-signer »).

## Entités
| Méthode | Chemin | Rôle | Notes |
|---|---|---|---|
| GET | `/employeurs` | staff | Liste `data[]` : `id, code, nom, rpm, compte_bancaire` (IBAN formaté), `adresse, par_defaut, actif, couleur_badge, coordonnees_completes, mois_lies, can.update`. 403 professeur. |
| POST | `/employeurs` | admin | `code` (unique, `[a-z][a-z0-9_]*`), `nom`, `rpm` (`BE0123.456.789`), `compte_bancaire` (IBAN valide), `adresse`, `par_defaut?`, `actif?`, `couleur_badge?`. 201. `par_defaut: true` retire le défaut aux autres. |
| PUT | `/employeurs/{id}` | admin | Mêmes champs (sauf `code`), tous optionnels. 422 si on retire/désactive l'entité par défaut. Aucune suppression : on désactive. |

Le compte bancaire est chiffré au repos (`employeurs.compte_bancaire`, cast `encrypted`). Les coordonnées de L-IT Solutions sont à compléter (`LIT_NOM`, `LIT_RPM`, `LIT_BANQUE`, `LIT_ADRESSE` à l'amorçage, ou page admin).

## Employeur d'un animateur
| Méthode | Chemin | Rôle | Notes |
|---|---|---|---|
| GET | `/professeurs/{id}/employeurs-mois?annee=YYYY` | staff ; professeur (son id) | Staff : `mois[12]` = `{mois, employeur{id,code,nom,couleur_badge,actif,coordonnees_completes}, source, herite_de, version, verrouille, type_verrou, raison_verrou, modifiable}`. Professeur : `{mois, employeur:{nom}}` seulement. Autre professeur : 403. |
| PUT | `/professeurs/{id}/employeurs-mois/{annee}/{mois}` | staff | Corps `{employeur_id, version, motif?}` (`version` = celle lue, 0 si aucune ligne explicite). 200 `{data: <vue du mois>}` · 409 modification concurrente · 422 verrou / entité inactive / motif manquant / hors plage (2020 → +12 mois). Valeur identique : 200 sans écriture. |
| POST | `/employeurs-mois/lot` | staff | `{annee, mois, professeur_ids[≤200], employeur_id | reprendre_precedent: true, motif?}` → `{appliques[{professeur_id, professeur}], ignores[{professeur_id, professeur, raison}]}`. Motif exigé si une valeur explicite est remplacée, mois passé ou signé (422 sur `motif`). |
| GET | `/professeurs/{id}/employeurs-mois/historique?annee=` | staff | `data[]` : `{id, annee, mois, employeur_avant, employeur_apres, motif, auteur, created_at}`. |

Motif (RG-9) : obligatoire pour modifier une valeur explicite, pour un mois antérieur au mois courant et pour toute modification d'un mois signé.

## Champs ajoutés à des endpoints existants
- `GET /timesheets/mois-synthese` : `professeurs[].employeur` (vue ci-dessus, calculée en lot, sans N+1).
- `GET /professeurs/{id}/timesheets-mois` : `employeur` (vue du mois).
- `GET /timesheets/mon-mois` et `GET /timesheets/ma-confirmation` (professeur) : `employeur: {nom}`.
- Génération du PDF : l'employeur du mois est figé (`timesheet_pdfs.employeur_id` + `employeur_snapshot` chiffré) ; pied de page au nom de l'employeur ; génération refusée (422) si ses coordonnées sont incomplètes. Aperçu : employeur effectif, rien d'enregistré.
- Signature : `emetteur` et `employeur_id` dans le payload scellé ; vérification publique : `data.emetteur`.

## Reprise des données
Migration `2026_10_11_100000_create_employeurs_tables` : amorce ASBL (défaut) et L-IT Solutions, rattache à l'ASBL (`source: migration`, journal « Reprise de données ») chaque (professeur, mois) ayant timesheet, PDF ou signature, et l'ASBL + snapshot aux PDF existants. Idempotent (`EmployeurBackfillService`), `down()` supprime tables et colonnes.
