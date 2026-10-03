# CLS-03 — Gestion des années scolaires et des dates de période

| | |
|---|---|
| **Statut** | ☑ Validé (DoR) — en dev |
| **Étend** | `CLS-01` (années, périodes) et `CLS-02` (classe sur deux périodes, séances hors période) |
| **Workflow UX (sub-agent UX Expert)** | ☑ `docs/mockups/CLS-03/WORKFLOW_UX.md` |
| **Mock-ups validés** | ☑ `docs/mockups/CLS-03/` — validés par le directeur le 2026-10-03 (recommandations du §5 reprises) |
| **Contrat API** | `docs/API_CLS03.md` |

## 1. Contexte et problème
Les bornes des périodes (ex. P2 2026-2027 = 22/02/2027 → 02/07/2027) viennent du seed. Quand un utilisateur ajoute une période 2 à une classe, une date hors bornes est refusée sans qu'il sache pourquoi ni où changer ces dates. Il n'existe pas d'écran de gestion des années : seule une modale de création enfouie dans le calendrier scolaire.

## 2. Objectifs
| Objectif | Indicateur | Cible |
|---|---|---|
| Créer l'année suivante sans aide technique | Temps de création d'une année | ≤ 2 min, dates pré-remplies |
| Comprendre un refus de date | Clics du refus à la correction | ≤ 2 (lien contextuel, saisie conservée) |
| Modifier des dates en connaissance de cause | Impact chiffré avant enregistrement | 100 % des modifications |

**Hors périmètre :** duplication d'une année avec ses classes, plus de 2 périodes par année.

## 3. Rôles et permissions
Admin et directeur : tous droits (liste, création, modification des dates, archivage, réactivation, suppression d'une année sans classe, import FWB). Professeur : aucun accès (403). 

## 4. Glossaire
| Terme | Définition | Technique |
|---|---|---|
| Année scolaire | Période de l'école portant 2 périodes ; s'étend du début de P1 à la fin de P2 | `annees_scolaires` |
| Période | P1 ou P2 d'une année, avec ses bornes (début, fin) | `periodes` |
| Impact | Effet d'un changement de dates sur les classes/séances existantes, calculé avant enregistrement | `POST …/apercu-impact` |

## 5. Décisions (recommandations du workflow UX validées)
1. Admin et directeur ont les mêmes droits, y compris supprimer une année vide.
2. **Chevauchement entre années refusé** (l'année suivante se prépare en brouillon).
3. Dates proposées : calendrier FWB de l'année s'il est importé (P1 : rentrée → vendredi précédant la coupure de Carnaval ; P2 : reprise → fin d'année), sinon 01/09 → 31/01 et 01/02 → 30/06.
4. Confirmation (case à cocher) dès qu'**une** séance ou classe est touchée ; sinon enregistrement direct.
5. **Plusieurs années actives** possibles (dates sans chevauchement) ; dates **verrouillées** une fois l'année archivée.

## 6. Règles métier
- **RG-1** Une année a toujours 2 périodes ; l'année va du début de P1 à la fin de P2.
- **RG-2** Blocages : fin ≤ début d'une période ; P2 ≤ fin de P1 ; chevauchement avec une autre année ; libellé déjà utilisé. Trou entre P1 et P2 > 6 semaines : avertissement seulement.
- **RG-3** **Aucun blocage sur les séances existantes** : l'ancien 422 « Des sessions dépassent la nouvelle fin de période » disparaît. Raccourcir = séances « hors période » (calculé, réversible) ; avancer le début = aucun effet ; retarder le début = classes dont la 1re séance de la période précède le nouveau début signalées ; modifier la fin de P1 = alertes « P2 à planifier » créées/supprimées chiffrées.
- **RG-4** L'aperçu d'impact est en lecture seule et utilise les **mêmes règles de calcul** que `hors_periode` et `alerte_periode_2` (source de vérité unique).
- **RG-5** Archiver une année : plus proposée à la création de classe ni dans les alertes P2, dates verrouillées ; n'affecte ni classes, ni séances, ni timesheets ni l'encodage des heures. Réactivable.
- **RG-6** Suppression : uniquement sans classe (409 sinon, avec nombres de classes/séances).
- **RG-7** Modification concurrente : verrou optimiste (`version` = `updated_at`), 409 avec auteur et date de la dernière modification.
- **RG-8** Une date de démarrage refusée car hors bornes de la période renvoie un code stable `date_hors_bornes_periode` + bornes, pour le lien contextuel.

## 5.2 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… |
|---|---|---|---|
| AC-1 | un libellé d'année suivant la dernière | `GET /annees-scolaires/proposition` | dates P1/P2 proposées + `source` fwb ou defaut |
| AC-2 | des dates cohérentes | création de l'année | 201 avec 2 périodes, `can`, `classes_count` = 0 |
| AC-3 | P2 ≤ fin de P1, fin ≤ début, libellé existant, chevauchement avec une autre année | création/modification | 422 avec message clair |
| AC-4 | des séances qui dépasseraient la nouvelle fin | modification | **200** (plus de 422) ; ces séances ressortent `hors_periode = true` |
| AC-5 | des dates modifiées | `apercu-impact` | compteurs et détail par classe identiques à ce que donne le calcul réel après enregistrement ; aucune écriture |
| AC-6 | deux utilisateurs modifient la même année | le 2e enregistre avec une `version` périmée | 409 `modification_concurrente` avec `modifie_par`/`modifie_a`, rien n'est écrit |
| AC-7 | une année archivée | modification de dates | 409 `annee_archivee` ; classes et séances existantes inchangées ; création de classe sur cette année refusée |
| AC-8 | archiver puis réactiver | actions | statut `archivee` puis `active` ; séances/timesheets intacts |
| AC-9 | une année avec classes | suppression | 409 avec « n classes (m séances) » ; sans classe → 204 |
| AC-10 | une date de démarrage hors bornes à la création/ajout de période | validation | 422 avec code `date_hors_bornes_periode` et `{annee_id, numero, debut, fin}` |
| AC-11 | un professeur | toute route années | 403 |
| AC-12 | la liste | `GET /annees-scolaires` | `classes_count`, `calendrier_count`, `updated_at`, `updated_by`, `en_cours`, `can.delete` + `raison_non_supprimable` |

## 7. Données
- `annees_scolaires.updated_by` (FK users, nullable, null on delete). Aucune autre table nouvelle.
- Seeders/factories : pas de changement de structure.

## 8. UX / UI
`docs/mockups/CLS-03/` (01 liste, 02 création, 03 modification avec impact, 04 liens contextuels). Menu **Scolarité › Années scolaires** (`/admin/annees-scolaires`, `/nouvelle`, `/:id/periodes`). Les 4 états et les textes exacts sont dans `WORKFLOW_UX.md` §3 et §6.

## 10. Impact technique
| Couche | Changements |
|---|---|
| DB | `annees_scolaires.updated_by` |
| Back | `AnneeScolaireService` (version optimiste, verrou archivée, plus de 422 séances, chevauchement entre années, archiver/réactiver), `AnneeImpactService` (aperçu), `AnneePropositionService`, règles de validation, code `date_hors_bornes_periode` |
| API | `docs/API_CLS03.md` |
| Front | `AnneesScolairesPage`, `AnneeCreatePage`, `AnneePeriodesPage`, menu, liens contextuels (création de classe, modale « Ajouter la période », fiche classe, calendrier scolaire), brouillon `sessionStorage` |
| Tests | feature back AC-1 à AC-12 ; lint + build front |

**Tranche verticale unique** (DB → API → UI → tests).

## 12. Questions ouvertes
Aucune.

## 13. Validation
Directeur : ☑ 2026-10-03. DoR : ☑
