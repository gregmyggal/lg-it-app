# STG-01 — Stages planifiés sur une semaine (jusqu'à 5 demi-journées) — Canvas de requirements

> **v0.3 (2026-11-11) :** un stage se choisit par **thème** (fiche du catalogue « Stages », modèle `Stage`) et non par cours — voir §11.

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. **v0.2 — mock-ups validés par le directeur le 2026-11-11, backend + frontend livrés** (`docs/mockups/STG-01/README.md`). Recette navigateur à faire après `php artisan migrate`.

| | |
|---|---|
| **ID / Titre** | STG-01 — Créer un stage sur une semaine (1 à 5 séances d'une demi-journée) |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) ☑ En dev ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Date / Version** | 2026-11-11 · v0.2 |
| **Liens** | `CLS-01-modele-classes.md`, `CLS-02`, `CLS-07` (duplication), `TS-07`, `DEF-02` (défraiement), modèles `Classe`, `CourseSession`, `Stage` (fiche marketing, distincte) |

## 1. Contexte et problème
- **Situation actuelle :** on peut créer des classes (cours × année × période × jour, 14 séances). Un stage de vacances (une semaine, matin ou après-midi) n'entre pas dans ce modèle : pas de période, pas de 14 séances. Le modèle `Stage` existant n'est qu'une fiche marketing du site public (aucune séance, aucun professeur, aucune heure).
- **Déclencheur (verbatim directeur) :** *« Comme on peut créer des classes, je veux pouvoir créer des stages sur une semaine (avec moins de 5 jours possible), 5 séances d'une demi-journée. »*

## 2. Objectifs et indicateurs
| Objectif | Indicateur | Cible |
|---|---|---|
| Créer un stage sans bricolage | actions depuis la liste | ≤ 4 (Nouveau → cours + semaine → lire l'aperçu → Créer) |
| Gérer les semaines incomplètes | jours décochables | 1 à 5 séances |
| Heures et défraiement sans nouvel écran | séances de stage encodables comme celles d'une classe | 100 % |

**Hors périmètre v1 :** stage sur 2 semaines ; 2 demi-journées le même jour ; lien avec la fiche marketing (prix, description) ; détection de conflits de salle.

## 3. Rôles et permissions
Identiques à la classe : création/modification/archivage = admin et staff (policy `create` sur `Classe`) ; le professeur voit ses stages (assignation ou remplacement) via `scopeVisiblePour`, sans droit d'écriture sur le stage.

## 4. Glossaire
| Terme affiché | Définition | Technique (proposé) |
|---|---|---|
| Stage | Activité d'un cours sur une seule semaine, 1 à 5 demi-journées | `classes.type = 'stage'` |
| Fiche stage (site public) | Contenu marketing existant | modèle `Stage` (inchangé, menu renommé « Stages (site public) ») |
| Demi-journée | Matin ou après-midi, horaires par défaut modifiables | `heure_debut` / `heure_fin` de la séance |

## 5. User stories
| ID | En tant que… | Je veux… | Priorité |
|---|---|---|---|
| US-1 | Directeur | créer un stage en choisissant un cours et une semaine | Must |
| US-2 | Directeur | décocher des jours (semaine < 5 jours) et choisir matin/après-midi par jour | Must |
| US-3 | Directeur | voir les jours fériés/fermetures décochés automatiquement, avec possibilité de recocher | Must |
| US-4 | Directeur | assigner des professeurs au stage, avec propagation aux séances, et changer de professeur pour une séance | Must |
| US-5 | Professeur | voir mes stages dans « Mes classes » et « Aujourd'hui », encoder mes heures comme d'habitude | Must |
| US-6 | Directeur | dupliquer un stage vers une autre semaine | Should |

## 6. Règles de gestion
- RG-1 : un stage couvre une seule semaine (lundi → vendredi) ; 1 à 5 séances, une demi-journée par jour.
- RG-2 : `seance_numero` 1..5 dans l'ordre chronologique ; pas de période ni de plafond de 14.
- RG-3 : jours fériés et fermetures du calendrier scolaire décochés par défaut (avertissement non bloquant si recoché) ; les congés scolaires ne déclenchent aucune alerte (cas normal d'un stage).
- RG-4 : horaires par défaut Matin 9:00–12:00, Après-midi 13:30–16:30 (**à confirmer**), modifiables par stage et par jour.
- RG-5 : assignation, remplacement, annulation, déplacement, heures et défraiement : mêmes règles que les séances de classe.
- RG-6 : le cours détermine liens et heures défrayables.

## 7. Modélisation proposée (à confirmer avec l'architecte)
Étendre `classes` (`type`, `date_debut_stage`) plutôt qu'une table séparée : toutes les relations (`professeur_classe`, `session_professors`, `course_sessions`, timesheets, isolation RG-5) sont clés sur `classe_id`.
Points à lever avant le code : `cours_id` vit sur `classe_periodes` (`periode_id` obligatoire) → ligne `classe_periodes` à `periode_id` nullable **ou** `classes.cours_id` ; `course_sessions.classe_periode_id` nullable ; `jour_semaine`/`heure_*` de `classes` nullables pour un stage ; `annee_scolaire_id` = année contenant la date.

## 8. Critères d'acceptation (esquisse)
- AC-1 : créer un stage de 5 séances → 5 `course_sessions` datées, horaires corrects.
- AC-2 : stage avec 3 jours cochés → 3 séances numérotées 1..3.
- AC-3 : férié décoché par défaut ; recoché → avertissement non bloquant.
- AC-4 : aucun jour coché → 422.
- AC-5 : un professeur voit uniquement ses stages (isolation) ; 403 pour les écritures.
- AC-6 : séance de stage encodable dans les timesheets avec défraiement du cours.
- AC-7 : la liste « Classes & stages » filtre Tous / Classes / Stages ; les classes existantes sont inchangées (non-régression).

## 9. Questions ouvertes (recommandation par défaut)
1. Horaires exacts matin / après-midi ? *(9:00–12:00 / 13:30–16:30)*
2. Matin ET après-midi le même jour (10 séances) ? *(non)*
3. Stage sur 2 semaines ? *(non : deux stages / duplication)*
4. Fériés : décocher auto ou seulement avertir ? *(décocher + avertir)*
5. Rattacher à la fiche marketing (prix) ? *(non dans cette tranche)*
6. Même défraiement que les classes ? *(oui, via le cours)*

## 10. Livraison v0.2 (2026-11-11)

**API** (staff uniquement, 403 professeur) : `POST /api/classes/stage/apercu`, `POST /api/classes/stage` (`date_debut`, `cours_id`, `lieu`, `demi_journee_defaut`, `creneaux`, `jours[{date, demi_journee}]`) ; `GET /api/classes?type=stage|classe`. Le reste (assignation, remplacement, déplacer/annuler, heures) réutilise les routes des classes.
**Données** : migration `2026_11_11_100000_add_type_stage_to_classes` (`classes.type`, `classe_periodes.periode_id` nullable). Un stage = 1 `classe_periodes` sans période.
**UI** : `/admin/classes/nouveau-stage`, liste « Classes & stages » (filtre Type, badge Stage), fiche (bloc « Séances du stage »).
**Tests** : `ClasseStageApiTest` (10 tests) ; suite complète verte (654).

**Écarts / décisions à connaître**
- Un stage d'été tombe hors année scolaire : on rattache le stage à la dernière année commencée (sinon la première à venir) et on applique son calendrier.
- Pas de duplication de stage (écran 5 « optionnel » des mock-ups) : 422 explicite, bouton masqué.
- Horaires d'un stage réglés séance par séance : « Modifier créneau et lieu » masqué pour un stage (le lieu se change par séance).
- « Décaler les séances suivantes » indisponible pour un stage (422) ; déplacer une séance seule reste possible.
- Le filtre « Période » de la liste n'exclut jamais les stages.
- Menu « Contenu > Stages » (fiches site public) : renommage en « Stages (site public) » non fait.

## 11. Évolution v0.3 — thème de stage au lieu du cours (2026-11-11)

Demande du directeur : « il faut choisir le **thème d'un stage** et non d'un cours ». Le sélecteur « Cours » de l'écran de création devient « Thème du stage » et liste les fiches du catalogue `stages` (titre ; « (brouillon) » si non publiée).
- **Données** : migration `2026_11_11_300000_add_stage_id_to_classe_periodes` (`classe_periodes.stage_id` → `stages`, `cours_id` nullable). Un stage porte `stage_id` et pas de `cours_id`.
- **API** : `POST /classes/stage[/apercu]` prend `stage_id` (obligatoire à la création) au lieu de `cours_id`. Les réponses exposent `periodes[].stage_id` et `periodes[].cours = { id: null, titre: <thème>, stage_id }` : les écrans lisent `cours.titre` sans distinction.
- **Défraiement** : pas de cours → temps de préparation par défaut de l'année (`source: defaut`).
- **Pas de changement de thème** après création (422 sur « changer le cours » d'un stage) ; « Changer le cours » et « Liens du cours » masqués pour un stage.
- **Non fait** : gestion des liens par thème (`ClasseLien` sur `stages` existe déjà côté API, aucun écran dans cette tranche) ; réglage du temps de préparation par thème.
