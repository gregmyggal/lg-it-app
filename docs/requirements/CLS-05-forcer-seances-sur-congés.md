# CLS-05 — Forcer les séances sur les jours de congé/fériés — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. **Canvas proposé pour validation avant développement** (aucun code écrit). Workflow et mock-ups : `docs/mockups/CLS-05/`.

| | |
|---|---|
| **ID / Titre** | CLS-05 — Forcer les séances pendant les congés/fériés lors de la création d'une classe |
| **Statut** | ☑ Brouillon ☐ En revue ☐ Validé (DoR) ☐ En dev ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* |
| **Date / Version** | 2026-10-04 · v0.2 (révision : forçage date par date directement dans l'aperçu ; le panneau de synthèse v0.1 n'affichait aucune case tant que rien n'était forcé) |
| **Liens** | `CLS-02-classe-deux-periodes.md` (création classes), `ClasseSessionGenerator.php`, `ClasseCreatePage.jsx`, `ClasseApercu.jsx`, `docs/mockups/CLS-05/` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** lors de la création d'une classe, le système génère 14 séances en sautant automatiquement les dates couvertes par le calendrier scolaire (vacances, fériés, fermetures). Il est impossible de forcer une séance pendant un congé.
- **Douleur / besoin métier :** directeur/admin doit pouvoir forcer une séance un jour de congé/fériés (ex. : cours le jour de l'Ascension, ou rattrapage pendant les vacances). Actuellement, impossible — obligé de créer la classe partiellement puis ajouter les séances manuellement après.
- **Déclencheur :** demande directeur : *« Il doit être possible de faire une séance pendant les jours fériés lors de la création d'une classe »*.

---

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif métier | Indicateur mesurable | Cible | Comment mesurer |
|---|---|---|---|
| Forcer une ou plusieurs séances sur congé | clics depuis le formulaire de création | ≤ 5 clics (cocher les dates) | recette chronométrée |
| Clarté des impacts | dates sautées listées avant création | 100% des congés impactants affichés | vérification visuelle |
| Respect de la règle (1 séance/semaine, numérotation 1-14) | autres séances renumérées après forçage | séances renumérotées 1-14 | test backend AC-2 |

**Hors périmètre :**
- Forcer une séance en dehors des bornes de la période
- Modification des séances après création (ajouter/retirer un forçage)
- Forçage depuis l'interface de gestion d'une classe existante (toujours par ajout de sessions ponctuelles)

---

## 3. 🔴 Acteurs, rôles et permissions

**Personas :** Admin (poste), Directeur/Staff (poste) — tous deux gèrent les classes et leurs périodes.

**Matrice rôle × action**

| Action | Admin | Staff (directeur) | Professeur | Élève |
|---|---|---|---|---|
| Forcer séances sur congés lors de création | ✓ | ✓ (ses classes) | ✗ | ✗ |
| Voir les dates sautées dans l'aperçu | ✓ | ✓ | — | — |

→ **Policy :** réutiliser `can.manage` sur la classe (déjà en place pour staff/admin). Aucune nouvelle permission.

---

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Technique |
|---|---|---|
| Jour de congé / fériés | Entrée du calendrier scolaire (vacances, jour férié, fermeture) non masquée | `calendrier_scolaire.type IN (vacances, ferie, fermeture)` et `masque = false` |
| Forcer une séance | Maintenir la séance à une date précise couverte par le calendrier (congé, férié, fermeture) | `periodes.*.dates_forcees[]` (Y-m-d) dans le payload d'aperçu et de création ; `dates_forcees[]` pour l'ajout d'une période |
| Séance forcée | Séance du plan tombant sur un congé, maintenue à la demande | `seances[].forcee = true` + `seances[].conge {libelle, type}` dans l'aperçu (non persisté) |

---

## 5. 🔴 Parcours utilisateurs et user stories

### 5.1 User stories

| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | Directeur | voir les dates de congé/fériés impactant mes séances lors de la création | décider de les forcer ou les laisser sautées | Must |
| US-2 | Directeur | forcer une ou plusieurs dates de congé en cochant des checkboxes | créer les séances sur ces jours en une seule opération | Must |
| US-3 | Directeur | voir que les autres séances restent numérotées 1-14 et une par semaine | comprendre le plan final | Should |

### 5.2 Parcours nominal (Directeur créant une classe)

**Étape 1 :** Remplir le formulaire (année, jour, horaire, lieu, Période 1 : cours + date démarrage).

**Étape 2 :** Aperçu en direct affiche 14 séances + les dates sautées barré avec libellé (« ⏭ Vacances d'hiver »).

**Étape 3 (NOUVEAU, v0.2) :** dans le tableau de l'aperçu, chaque date sautée porte une case « Forcer » (1re colonne). Cochée, la ligne devient « P1 · N » en vert avec le badge « ✓ Forcée · libellé » et une case cochée pour annuler ; les séances suivantes sont renumérotées. *(Remplace le panneau synthèse v0.1 ci-dessous.)*
- Liste des congés (vacances, fériés, fermetures) qui décalent au moins 1 séance
- Pour chaque : type · dates · libellé · checkbox « Forcer ces dates »
- Exemple : « 🎄 Vacances d'hiver (22/01–26/01) — décale 1 séance · ☐ Forcer »

**Étape 4 :** Cochage des cases souhaités (exemple : cocher « Vacances d'hiver »). L'aperçu se met à jour : les séances forcées s'affichent en vert/normal au lieu de barré.

**Étape 5 :** Optionnel — si forçages cochés : modale de confirmation listant « Séances créées malgré congés » (affichage seulement, pas bloquant).

**Étape 6 :** Clic [Créer la classe] → transaction backend.

**Alternatives/erreurs :**
- Aucun congé impactant : panneau synthèse absent
- Forçage partiel (Vacances + 1 fériés seulement) : OK
- Erreur réseau : saisie conservée en brouillon
- Calendrier vide : aperçu indisponible (existant)

### 5.3 Critères d'acceptation

| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| **AC-1** | Classe en création, Période 1 sélectionnée, 2 congés impactent les séances (vacances, fériés) | aucun checkbox coché | Panneau synthèse affiche 2 entrées · aperçu montre dates sautées barré (⏭) · bouton Créer enabled | Feature + Recette |
| **AC-2** | Idem AC-1 | je coche « Vacances » (1 séance impactée) | Aperçu se met à jour · la séance du jour des vacances apparaît normal · numérotation 1-14 inchangée (car 1 seule séance décalée, elle reprend sa place) · autres séances inchangées | Feature |
| **AC-3** | Idem AC-1 | je coche les 2 (vacances + fériés = 2 séances impactées) | Aperçu : 2 séances normal · 12 autres inchangées · numérotation 1-14 · aucun saut | Feature |
| **AC-4** | Classe P1 + P2, P1 a vacances forcées, P2 n'a pas coché vacances | je soumets | P1 : 1 séance forcée · P2 : 1 séance sautée (normal) | Feature |
| **AC-5** | Idem AC-1, vacances forcées cochées | POST `/classes` avec `dates_forcees: ['2026-11-11']` | 201 · classe créée · 14 séances dont 1 le jour des vacances avec `statut = planifiee` (identique aux autres) | Feature |
| **AC-6** | Idem AC-5 | Vérifier les séances en DB (`course_sessions`) | Numérotation 1-14 · dates respectives · `date` = jour des vacances forcées · pas de colonne `force_conge` (c'est juste une sélection au moment de la création) | Feature |
| **AC-7** | Classe créée avec forçages | Directeur accède à `/admin/classes/{id}` | Tableau sessions affiche la séance forcée (identique aux autres, pas de badge spécial) | Recette |
| **AC-8** | Créateur non staff (ex. prof) | tente de remplir le formulaire de création | 403 / accès refusé (existant, inchangé) | Existing policy |

---

## 6. 🔴 Règles métier et cycle de vie

**Règles**

- **RG-1 :** Toute date du plan couverte par une entrée active (non masquée) du `calendrier_scolaire` est forçable individuellement (forçage **par date**, pas par entrée : forcer le 21/10 ne force pas le 28/10). Une date envoyée qui n'est pas un congé ou n'est pas dans le plan est ignorée.
- **RG-2 :** Le forçage = sélection explicite (checkbox) de congés à ignorer. Une fois cochée, la date est créée comme séance normale (pas de marquage spécial en BD).
- **RG-3 :** Les autres séances restent numérotées **1 à 14** par période. Après forçage de N congés, les N séances retournent à leur place hebdomadaire ; les 14–N autres séances ne changent pas de numéro.
- **RG-4 :** Chaque période (P1, P2) a ses propres cases dans sa colonne d'aperçu. Le forçage est indépendant (P1 peut forcer vacances d'hiver, P2 non).
- **RG-5 :** Les séances forcées sont créées avec `statut = planifiee`, identiques aux autres. Aucun historique/badge dans la BD ; l'intention était au moment de la création.
- **RG-6 :** Permissions : admin + directeur/staff pour forcer. Professeur : 403.

---

## 7. 🔴 Données et migration

**Nouvelles tables / colonnes :** Aucune. Le forçage est un **paramètre de création**, pas une colonne persistante.

**Données existantes impactées :**
- `course_sessions` : aucune modification de schéma. Les séances forcées sont indistinguables des autres (même `statut`, même structure).
- `calendrier_scolaire` : aucune modification.

**Backfill :** N/A.

**Seeders/factories :**
- Classe de test avec 1 congé forcé (pour AC-2)
- Classe de test avec 2 congés forcés (pour AC-3)
- Classe de test avec P1 forcée, P2 non (pour AC-4)

---

## 8. 🔴 Exigences UX/UI

**Workflow UX :** v0.2 — cases « Forcer » sur les dates sautées de l'aperçu (création de classe et modale « Ajouter la période N »). Le panneau synthèse v0.1 est abandonné : il ne pouvait rien afficher avant un premier forçage.

**Mock-ups :** `docs/mockups/CLS-05/` — 4 états :
1. Aucun congé impactant → panneau synthèse absent
2. Congés affichés, aucun coché → aperçu avec dates sautées barré
3. Congés partiellement cochés → aperçu mis à jour, séances forcées normal
4. Post-création : toast confirmation

**Mock-ups validé par :** *à remplir* · Date : *à remplir*

---

## 9. Décisions architecturales

| Décision | Justification | Alternative refusée |
|---|---|---|
| Forçage = paramètre créa, pas colonne BD | Aucun suivi ultérieur (pas de "retrait" du forçage après création) ; l'intention existe qu'au moment de créer | Colonne `force_conge_id` persistante → complexe, nécessite gestion de l'état après création |
| Panneau synthèse dans `ClasseApercu` | Intégration naturelle à l'aperçu, pas de modale supplémentaire | Modale de confirmation → lourd, perte de contexte du formulaire |
| Checkboxes séparés P1/P2 | Chaque période peut avoir ses propres congés impactants | Checkboxes partagés P1+P2 → confusion, risque de forçer l'un sans l'autre |
| Aucune numérotation spéciale en BD | Les séances forcées sont des séances normales → aucune distinction logicielle ultérieure | Colonne `forcee = true` → nécessiterait adaptation partout (aperçu, édition, timesheets) |

---

## 10. Questions ouvertes / TBD

- [ ] Mock-ups visuels à créer et valider (voir `docs/mockups/CLS-05/`)
- [ ] Message du toast de confirmation après création (par ex. : « Classe créée · ⚠️ 2 séances créées malgré congés »)
- [ ] Icône/couleur du panneau synthèse (⚠️ warning ? ℹ️ info ?)

---

## 11. Prochaines étapes

1. ✅ Canvas rempli et brouillon validé par Product Owner
2. 📋 Mock-ups visuels créés dans `docs/mockups/CLS-05/` → validation UX/UI
3. 🔒 Signature de la DoR (analyste + UX + architecte)
4. 💻 Implémentation :
   - **Backend** : `ClasseSessionGenerator::plan()` retourne les congés impactants ; payload `force_conge_ids[]` → création
   - **Frontend** : état `forçages` dans `ClasseCreatePage` ; panneau synthèse dans `ClasseApercu`
   - **Tests** : AC-1 à AC-8, seeders
5. ✅ Recette
6. 📦 Livraison
