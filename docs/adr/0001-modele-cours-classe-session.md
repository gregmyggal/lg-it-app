# ADR-0001 — Modèle Cours → Classe → Session, suppression du type de cours

**Date :** 2026-09-30 · **Statut :** Proposé (à valider par l'architecte) · **Décideur métier :** direction de l'école

## Contexte
Le modèle actuel confond le **catalogue** (`cours`) et l'**organisation** d'un cours. Les professeurs sont liés à des *types de cours* (`professeur_type_cours`) puis, en Sprint 2, à des `cours` via `professeur_cours`, ce qui ne permet pas d'exprimer « le React du mercredi après-midi » et « le React du samedi » avec des professeurs différents. Les sessions (`course_sessions`) et récurrences (`course_recurrences`) pointent sur `cours`.

Réalité métier confirmée : catalogue de cours ; un cours peut être organisé plusieurs fois par semaine ; une année scolaire = 2 cours à la suite ; un cours = 14 sessions, 1 séance par semaine ; profs associés à une **classe** d'une année ; timesheet toujours individuelle ; liens du cours adaptables par tous les profs ayant une classe de ce cours.

## Décision
1. Introduire **`annees_scolaires`** et **`classes`** (cours × année scolaire × période 1|2 × jour × créneau × lieu).
2. **`course_sessions.classe_id`** remplace `cours_id` ; une classe génère **14 sessions hebdomadaires** (service unique, idempotent, calendrier scolaire respecté). `course_recurrences` disparaît (règle fixe : 14 sessions hebdomadaires, ajustables).
3. **`professeur_classe`** (`professeur_id`, `classe_id`, `role`, `date_debut`, `date_fin`, `UNIQUE(professeur_id, classe_id)`) remplace `professeur_cours` et `professeur_type_cours`.
4. **Suppression du type de cours** : `types_cours`, `professeur_type_cours`, `cours_type_cours`, `TypeCoursController/Policy/Model`, routes `/types-cours`, page admin et menu.
5. **Timesheet** : reste `professeur_id` + `session_id` ; une par professeur et par session, jamais partagée ; contrainte `UNIQUE(professeur_id, session_id, date)` à préciser au Canvas.
6. **Liens** : `classe_liens` reste rattaché au **cours** (parent polymorphe `cours`). Autorisation d'écriture : admin/staff ou professeur ayant une classe active de ce cours (Policy basée sur `professeur_classe`).
7. **`session_professors` conservée** : elle matérialise les professeurs de **chaque session**. Elle est alimentée par **propagation** depuis `professeur_classe` (`origine = classe`) et porte les **remplacements ponctuels** (`origine = remplacement`, protégés de toute re-propagation). L'assignation est bidirectionnelle (depuis la classe ou le professeur) via un **service unique** `ClasseProfesseurAssignmentService`, transactionnel et idempotent.
8. **Calendrier scolaire** (`calendrier_scolaire`, initialisé depuis la **FWB**, complétable à la main, champ `source`) : la génération des 14 sessions saute vacances/fériés ; chaque session reste ajustable (déplacer/annuler/ajouter).
9. **Historique des liens** : `classe_liens` en soft delete + table append-only `classe_liens_historique` (avant/après JSON, utilisateur) ; restauration = nouvelle version ; **rétention 6 mois** (purge planifiée) ; restauration ouverte à tous les professeurs du cours.
10. Le **remplacement ponctuel** n'est soumis à aucune contrainte liée aux timesheets (elles restent indépendantes et inchangées). Le rôle du professeur est **indicatif** (aucun effet sur les tarifs ni les timesheets).
11. **Sessions** : `seance_numero` (1..14) **fixe** ; une session annulée le conserve et sa remplaçante est un **bis** (`bis_rang`, `remplace_session_id`), `UNIQUE(classe_id, seance_numero, bis_rang)` ; le nombre de sessions peut dépasser 14, jamais le nombre de séances ; création/déplacement bornés par `periodes.date_fin` (table `periodes`). La propagation des professeurs vise les **sessions à venir**.
12. **Liens par séance** : `classe_liens.seance_numero` (NULL = lien général, sinon 1..14) au niveau du **cours** ; une session (et son bis) affiche les liens généraux + ceux de son `seance_numero`, indépendamment de sa position chronologique.

## Conséquences
- (+) Le modèle correspond au vocabulaire de l'école ; isolation des données par classe, simple à raisonner ; plus de règle « 14 sessions » dans le front.
- (+) Suppression d'une notion (types) qui ne servait plus à rien.
- (−) **Pas de migration de données** (décision direction) : l'ancien modèle Sprint 2 est abandonné et ses tables supprimées ; catalogue, professeurs, liens, tarifs et timesheets sont conservés. Rupture de contrats API (`/cours/{cours}/professeurs`, `/cours/{cours}/recurrences`, `/sessions?cours_id`), refonte des écrans Sprint 2 (calendrier, sessions, assignation).
- Réalisée en **tranches verticales** (voir Canvas CLS-01 §10), API compatibles pendant la transition, dans la même PR que les écrans correspondants.

## Alternatives écartées
- Garder `professeur_cours` et ajouter jour/créneau au pivot : mélange catalogue et organisation, pas de sessions par classe.
- Garder le type de cours comme filtre : demandé supprimé par la direction.
