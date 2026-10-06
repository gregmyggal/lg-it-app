# PROF-02 — Archiver ou supprimer un professeur (avec forçage) — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Workflow : sub-agent UX Expert (`docs/mockups/PROF-02/WORKFLOW_UX.md`). Maquettes : `docs/mockups/PROF-02/index.html`.

| | |
|---|---|
| **ID / Titre** | PROF-02 — Archiver ou supprimer un professeur, nettoyage des séances à venir |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — maquettes validées le 2026-10-06 ☑ En dev ☑ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Date / Version** | 2026-10-06 · v1.0 |
| **Liens** | PROF-01, CLS-08 (pattern de forçage), `ProfesseurCompteService`, `ClasseProfesseurAssignmentService`, `SessionReplacementService`, `CompteProfesseurSection.jsx`, `AdminProfesseurDetail.jsx` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** `DELETE /professeurs/{id}` renvoie 409 dès qu'il existe une heure, une assignation ou une ligne de séance ; le bouton « 🗑️ Supprimer » (window.confirm) est donc inutilisable. « Désactiver » termine les assignations de classe mais ne retire que les lignes `origine=classe` : ajouts ponctuels, remplacements assurés et séances où il est remplacé restent à son nom.
- **Douleur :** professeurs créés par erreur impossibles à supprimer ; séances futures « fantômes » assignées à un professeur parti.
- **Déclencheur (verbatim directeur) :** *« Ajouter une fonctionnalité pour supprimer un professeur, avec possibilité de forcer si des heures ont déjà été encodées, et archiver. En cas de suppression et/ou archive d'un professeur toutes les assignations futures qui sont à son nom doivent être nettoyées, les séances restent valables mais plus assignées à ce professeur. »*

## 2. 🔴 Objectifs

| Objectif | Indicateur | Cible |
|---|---|---|
| Archiver un départ | séances à venir encore à son nom après archivage | 0 (hors séances avec heure encodée) |
| Supprimer, même avec heures | possible depuis la fiche | 100 % des profs sans fiche générée |
| Forçage réfléchi | motif + nom recopié + case « irréversible » | obligatoire |

**Hors périmètre :** notifications, restauration d'un professeur supprimé, suppression en lot depuis la liste.

## 3. 🔴 Permissions

| Action | Admin | Staff | Professeur |
|---|---|---|---|
| Archiver / réactiver | ✓ | ✓ | ✗ 403 |
| Supprimer (simple ou forcé) | ✓ | ✓ | ✗ 403 |
| Forcer avec heures sur une fiche générée | ✓ | ✗ (409) | ✗ 403 |
| Supprimer son propre compte / un compte non `professeur` | ✗ 422 | ✗ 422 | — |

## 4. 🔴 Glossaire

| Terme affiché | Définition | Technique |
|---|---|---|
| Archivé | ne se connecte plus, plus assigné au futur, historique conservé | `professeurs.statut = inactif` (libellé seul change) |
| Séance à venir | date ≥ aujourd'hui (Europe/Brussels), statut `planifiee` | `course_sessions` |
| Libérer | retirer toutes ses lignes `session_professors` des séances à venir | `libererSeancesFutures()` |
| Remplaçant à trouver | remplacé sans remplaçant | `remplace = true`, `remplace_par_professeur_id = NULL` |
| Fiche générée | heure reprise dans une fiche de défraiement PDF | `timesheets.statut_validation = genere` |

## 5. 🔴 Parcours et critères d'acceptation

Parcours détaillés : `WORKFLOW_UX.md` §4–§5 et maquettes écrans 1–10.

| ID | Étant donné… | Quand… | Alors… |
|---|---|---|---|
| AC-1 | un prof actif avec classes, ajouts, remplacements à venir | archivage | statut `inactif`, assignations terminées, aucune ligne à son nom sur les séances à venir (hors heure encodée), séances toujours `planifiee` |
| AC-2 | il remplaçait A sur une séance à venir | archivage ou suppression | ligne B supprimée ; A reste `remplace=true`, `remplace_par=NULL` |
| AC-3 | il était remplacé par C sur une séance à venir | archivage ou suppression | sa ligne supprimée ; la ligne de C devient `origine=ajout` |
| AC-4 | un prof sans heure encodée | `DELETE` sans forçage | 204, séances à venir libérées, compte supprimé |
| AC-5 | un prof avec heures (aucune `genere`) | `DELETE` sans forçage | 409 `resume` (heures par statut, tarifs, séances passées, classes) + `forcable: true` |
| AC-6 | idem | `DELETE force=true` motif ≥ 10 + nom correct | 204, `Log::info` (auteur, compteurs, motif) |
| AC-7 | motif trop court ou nom erroné | `DELETE force=true` | 422 sur le champ, rien supprimé |
| AC-8 | au moins une heure `genere` | `DELETE force=true` par un directeur | 409 `forcable: false` ; par un admin : 204 (fiches, signatures et fichiers supprimés) |
| AC-9 | user lié non `professeur` ou soi-même | suppression | 422 / 403 |
| AC-10 | professeur ; anonyme | archiver / supprimer | 403 ; 401 |
| AC-11 | GET impact | archivage | compteurs par type, séances qui resteront sans professeur, dernier professeur, annulées exclues |

## 6. 🔴 Règles métier

- **RG-1** Une seule règle de nettoyage (archiver et supprimer), transactionnelle, recalculée côté serveur.
- **RG-2** Séances passées jamais modifiées à l'archivage.
- **RG-3** À l'archivage, une séance à venir avec heure encodée garde sa ligne.
- **RG-4** Nettoyage obligatoire à l'archivage (plus de case à cocher) — Q3.
- **RG-5** Forçage : motif ≥ 10 caractères, nom « Prénom Nom » recopié (casse, espaces, accents ignorés).

| Statut | Libellé | Badge | Transitions |
|---|---|---|---|
| `actif` | Actif | vert | → `inactif` (« Archiver… ») · → supprimé |
| `inactif` | Archivé | gris | → `actif` (« Réactiver ») · → supprimé |

## 7. 🔴 Données

Aucune migration (sauf Q4 = table de trace). Suppression = cascade FK existante (timesheets, tarifs, PDFs, signatures, audits, user).

## 10. Impact technique (prévisionnel)

- **Back :** `ProfesseurCompteService::libererSeancesFutures()`, `impact()` enrichi, `desactiver()` sans option, `supprimer($prof, $forcage, $auteur)` + `resumeHistorique()`, `DestroyProfesseurRequest`.
- **Front :** libellés Archiver/Archivé (fiche, liste, filtres, login), `ArchiverModal`, modales suppression A/B/C, « Zone sensible » dans `AdminProfesseurDetail.jsx`, affichage « remplaçant à trouver ».
- **Tests :** feature AC-1 à AC-11 via `scripts/test-backend.sh` ; `npm run lint && npm run build`.

## 11. Recette (2026-10-06, base `lgit_demo`)

| # | Scénario | Résultat |
|---|---|---|
| R1 | Directeur : fiche → Supprimer → « a un historique » → Archiver le professeur | ✅ impact (1 classe, 14 séances, 13 sans professeur, dernier professeur), archivage, badge « Archivé » |
| R2 | Directeur : professeur archivé avec heures → Supprimer quand même → motif + nom + case | ✅ bouton actif seulement si les 3 sont valides, retour liste + toast |
| R3 | Back : `ProfesseurArchivageSuppressionTest` (AC-1 à AC-11) + suite complète | ✅ 453 tests |

## 12. Questions ouvertes

| # | Question | Reco UX | Réponse |
|---|---|---|---|
| Q1 | Fusionner Désactiver et Archiver ? | Oui | **Oui** (2026-10-06) |
| Q2 | Bloquer la suppression si fiche générée ? | Oui | **Admin seulement** peut forcer (2026-10-06) |
| Q3 | Nettoyage obligatoire à l'archivage ? | Oui | **Oui** |
| Q4 | Trace d'une suppression forcée | Log applicatif v1 | **Log applicatif** |
| Q5 | Il remplaçait A : A « remplaçant à trouver » ou restauré ? | Remplaçant à trouver | **Remplaçant à trouver** (2026-10-06) ; un nouveau remplaçant peut être choisi |
| Q6 | Sans heure mais avec assignations : confirmation simple ? | Oui | **Oui** |
| Q7 | Notifier ? | Non | **Non** |
| Q8 | Qui peut forcer ? | Admin + directeur | **Admin + directeur** (sauf fiche générée : admin) |
