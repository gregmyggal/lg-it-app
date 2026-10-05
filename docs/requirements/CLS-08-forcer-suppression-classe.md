# CLS-08 — Forcer la suppression d'une classe ayant un historique — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Workflow défini par le sub-agent UX Expert, maquette validée par le directeur le 2026-10-05.

| | |
|---|---|
| **ID / Titre** | CLS-08 — Forcer la suppression d'une classe même si elle a des séances passées |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — maquette validée le 2026-10-05 ☑ En dev ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Date / Version** | 2026-10-05 · v1.0 |
| **Liens** | `ClasseService::supprimer`, `ClasseController::destroy`, `ClasseDetailPage.jsx`, `TimesheetAudit` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** `DELETE /classes/{id}` refuse (409) dès qu'une séance est passée, commencée, terminée ou annulée, ou qu'une heure est encodée. Seul l'archivage est proposé.
- **Douleur :** une classe créée par erreur (ou en double) et découverte tardivement ne peut plus être supprimée ; elle encombre les listes et le calendrier même archivée.
- **Déclencheur (verbatim directeur) :** *« Donne à l'admin et au directeur la possibilité de forcer la suppression d'une classe même si il y a des séances dans le passé. »*

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif | Indicateur | Cible |
|---|---|---|
| Supprimer une classe avec historique | possible depuis la fiche classe | 100 % des classes sans heure « générée » |
| Ne jamais perdre d'heures dues | heures encodées conservées après suppression | 100 % (test back) |
| Rendre le forçage réfléchi | motif + nom de la classe + case « irréversible » | obligatoire |

**Hors périmètre :** notification des professeurs (arbitrage Q3 : non) ; suppression des heures encodées ; restauration d'une classe supprimée.

## 3. 🔴 Acteurs, rôles et permissions

| Action | Admin | Staff (directeur) | Professeur |
|---|---|---|---|
| Supprimer une classe sans historique | ✓ | ✓ | ✗ (403) |
| Voir le résumé de l'historique (409) | ✓ | ✓ | ✗ (403) |
| Forcer la suppression | ✓ | ✓ | ✗ (403) |

→ Policy `ClassePolicy::delete` inchangée (staff). Sans jeton : 401.

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Technique |
|---|---|---|
| Historique | séance passée, commencée, terminée ou annulée, ou heure encodée | `course_sessions.statut != planifiee` ou `date < aujourd'hui`, ou `timesheets.course_session_id` |
| Heures conservées | heures encodées sur la classe, gardées dans les feuilles des professeurs sans lien vers la séance | `timesheets.course_session_id = NULL` (FK `nullOnDelete`) |
| Fiche générée | heure déjà reprise dans une fiche de défraiement PDF | `timesheets.statut_validation = genere` |

## 5. 🔴 Parcours utilisateurs et user stories

### 5.1 User stories
- En tant que directeur, je veux supprimer définitivement une classe créée par erreur même si des séances sont passées, afin qu'elle n'apparaisse plus nulle part.
- En tant que directeur, je veux savoir ce que je vais perdre (séances, heures, professeurs) avant de forcer.

### 5.2 Parcours
1. Fiche classe → « Supprimer la classe » → modale de confirmation actuelle → « Supprimer la classe ».
2. Si la classe a un historique : modale « Cette classe a un historique » (résumé : séances passées / annulées / à venir, heures par statut, professeurs). CTA principal « Archiver la classe » ; lien discret « Supprimer quand même… ».
3. « Supprimer quand même… » → étape « Supprimer définitivement ? » : rappel irréversible, heures conservées, motif (≥ 10 caractères), saisie du nom de la classe, case « Je comprends que cette action est irréversible ». Bouton rouge actif seulement si les trois sont remplis.
4. Succès → retour à la liste des classes, toast « Classe supprimée — N heures conservées dans les feuilles des professeurs ».
5. Variante : heures sur une fiche générée → bandeau bloquant, pas de lien de forçage ; seul l'archivage est proposé.

### 5.3 Critères d'acceptation
- **AC-1** Given une classe sans historique, When DELETE sans forçage, Then 204 (inchangé).
- **AC-2** Given une classe avec séance passée, When DELETE sans forçage, Then 409 avec `resume` (compteurs séances, heures par statut, professeurs) et `forcable: true`.
- **AC-3** Given une classe avec séances passées et heures brouillon/soumises/confirmées, When DELETE `force=true` avec motif et nom corrects, Then 204, classe et séances supprimées, heures conservées avec `course_session_id = NULL`, une ligne `timesheet_audits` `classe_supprimee` par heure (motif, classe, n° et date de séance).
- **AC-4** Given forçage avec motif < 10 caractères ou nom erroné, Then 422 sur `motif` / `confirmation_nom`, rien n'est supprimé.
- **AC-5** Given une heure `genere`, When DELETE `force=true`, Then 409 et `forcable: false` ; rien n'est supprimé.
- **AC-6** Professeur : 403 ; anonyme : 401.

## 6. 🔴 Règles métier

- **RG-1** Le forçage ne supprime jamais d'heure encodée : elles restent dans les feuilles (paie inchangée).
- **RG-2** Heure au statut `genere` ⇒ forçage interdit (arbitrage Q1) : déverrouiller d'abord le mois.
- **RG-3** Motif obligatoire (≥ 10 caractères) et nom de la classe (titre des cours, ex. « Scratch » ou « Scratch → Python » ; casse et espaces ignorés, « -> » accepté pour « → ») recopiés et vérifiés côté serveur.
- **RG-4** Tout est recalculé côté serveur au moment du forçage, dans une transaction (audit → séances bis → séances → classe).
- **RG-5** Traçabilité : `TimesheetAudit` par heure détachée + `Log::info` (auteur, classe, compteurs, motif).

## 7. 🔴 Données et migration

Aucune migration : `timesheets.course_session_id` est déjà `nullOnDelete`, `timesheet_audits.action` est une chaîne (nouvelle valeur `classe_supprimee`).

## 8. Exigences UX/UI

Maquette validée (voir §5.2). Archiver reste l'action recommandée et focalisée. Libellé d'historique côté feuille : « Classe « X » supprimée : séance n° N du JJ/MM/AAAA détachée — motif ».

## 10. Impact technique

- Back : `DestroyClasseRequest` (force, motif, confirmation_nom), `ClasseService::supprimer($classe, $forcage, $auteur)` + `resumeHistorique()`, `TimesheetAudit::ACTION_CLASSE_SUPPRIMEE`.
- Front : `supprimerClasse(id, payload)`, modales « historique » et « forcer » dans `ClasseDetailPage.jsx`, libellé d'audit dans `DetailProfesseurMois.jsx`.
- Tests : `ClasseApiTest` (AC-1 à AC-6).

## 12. Questions ouvertes (tranchées le 2026-10-05)

1. Heures `genere` : **bloquer**.
2. Workflow (motif + nom + case) : **validé**, admin et directeur.
3. Notifier les professeurs : **non**.
