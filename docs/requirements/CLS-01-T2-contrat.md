# CLS-01 · Tranche T2 — Contrat technique

**Périmètre T2** : assignation des professeurs aux classes **dans les deux sens** (classe ⇄ professeur) avec **propagation aux sessions à venir**, **remplacement ponctuel** sur une session, accès **professeur** (« Mes classes », isolation), mise en conformité des droits d'accès au cours. **Hors T2** : encodage d'heures depuis une session (T3), liens par séance/historique (T4), suppression des types de cours (T5).

Sources : `CLS-01-modele-classes.md` (RG-4, RG-5, RG-8, RG-9, AC-2, AC-3, AC-12 à AC-15, AC-24, AC-25), `docs/adr/0001-…`, mock-ups validés `docs/mockups/CLS-01/` **03** (section professeurs, remplacement), **04** (fiche professeur › Classes), **06** (portail Mes classes), `docs/API_T1_CLASSES.md`, `CLS-01-T1-contrat.md`, `DEVELOPMENT_STANDARDS.md`.

## 1. Données (migrations réversibles)

| Table | Colonnes | Contraintes |
|---|---|---|
| `professeur_classe` (**nouvelle**) | `id`, `professeur_id` (FK cascade), `classe_id` (FK cascade), `role` (`principal`\|`co_enseignant`\|`remplacant`, défaut `co_enseignant`, **indicatif**), `date_debut` (défaut = aujourd'hui), `date_fin` nullable, timestamps | `UNIQUE(professeur_id, classe_id)` ; index `(classe_id)`, `(professeur_id, date_fin)` |
| `session_professors` (**recréée**) | `id`, `course_session_id` (FK cascade), `professeur_id` (FK), `role` (mêmes valeurs), `origine` (`classe`\|`remplacement`), `remplace` (bool, défaut false : le professeur a été remplacé sur cette session), `remplace_par_professeur_id` nullable (FK professeurs), timestamps | `UNIQUE(course_session_id, professeur_id)` ; index `(professeur_id)` |
| `professeur_cours` | **supprimée** (aucune donnée) ; `down()` la recrée vide | |

`professeur_type_cours` et `cours_type_cours` **restent** (T5). Aucune migration de données.

## 2. Règles opérationnelles

- **Assigner** (service unique `ClasseProfesseurAssignmentService`, transactionnel, idempotent ; **mêmes règles depuis la classe ou depuis le professeur**) : crée (ou réactive : `date_fin = null`) la ligne `professeur_classe`, puis **propage** aux sessions **à venir** de la classe — date ≥ aujourd'hui (Europe/Brussels) **et** statut `planifiee`/`en_cours`, annulées exclues — en créant des `session_professors` (`origine = classe`) si absents. Les sessions passées ne sont pas modifiées. Une classe pas encore commencée : toutes les sessions. Réponse : récapitulatif `{ sessions_assignees, sessions_passees_ignorees, sessions_deja_assignees }`.
- **Aperçu** (sans persistance) : nombre de sessions concernées (à venir / passées ignorées) et **conflits d'horaire** : sessions à venir où le professeur est déjà assigné (non remplacé) à une autre session **le même jour sur un horaire qui se chevauche**. **Conflit = blocage** (422, liste des conflits : date, classe, horaire), l'assignation n'a pas lieu (RG-4).
- **Modifier** une assignation : `role`, `date_debut`, `date_fin` (le rôle n'a **aucun effet** sur rémunération/timesheets). Un seul principal par classe n'est **pas** imposé.
- **Retirer / terminer** : `date_fin` (défaut aujourd'hui) ; retire le professeur des sessions **futures sans timesheet** (`timesheets.course_session_id` + `professeur_id`) ; conserve celles passées ou avec timesheet ; la ligne `professeur_classe` est conservée (historique). Réassigner plus tard = réactivation.
- **Sessions créées après coup** (bis, génération, déplacement ne change rien) : les professeurs **actifs** de la classe à la date de la session y sont propagés automatiquement (`ClasseSessionGenerator` / `CourseSessionService` appellent le service).
- **Remplacement ponctuel** (RG-9, AC-14, AC-25) : sur **une** session (passée ou à venir, **pas annulée**), `remplace` le professeur A par le professeur B : la ligne de A passe `remplace = true` (+ `remplace_par_professeur_id`), B reçoit une ligne `origine = remplacement`, `role = remplacant`. **Aucune contrainte liée aux timesheets** ; aucune timesheet n'est modifiée, bloquée ni transférée. **Annuler le remplacement** restaure A (supprime la ligne de B si elle n'a été créée que par ce remplacement). Une **re-propagation n'écrase jamais** un remplacement (idempotence : si une ligne existe pour (session, professeur), y compris `remplace = true`, on n'ajoute rien). B ne peut pas être déjà assigné non remplacé à la même session (422). Conflit d'horaire du remplaçant : **avertissement non bloquant** dans la réponse (`avertissements`).
- **Isolation professeur** (RG-5, AC-2) : un professeur ne voit que ses classes (au moins une ligne `professeur_classe`, y compris terminée) et ses sessions (ligne `session_professors` non `remplace`, ou session de sa classe pendant son assignation) ; toute liste (`/classes`, `/sessions`, `/calendar/*`) est **filtrée par scope de requête** pour un professeur (plus de 403 systématique) ; les actions d'écriture sur classes/sessions/calendrier restent staff uniquement. Aucune donnée tarifaire exposée.
- **Droit sur les cours** : `Professeur::canAccessCours(Cours)` devient : le professeur a une assignation **active** (`date_fin` null ou ≥ aujourd'hui) sur au moins une classe de ce cours (RG-6). `Professeur::cours()` est réécrit sur cette base (plus de pivot `professeur_cours`). `canAccessCoursByType` inchangée (T5). Les policies `CoursPolicy`, `ClasseLienPolicy`, `CoursRessourcePolicy` fonctionnent donc automatiquement avec le nouveau modèle.

## 3. API (préfixe `/api`, `auth:sanctum`, mêmes conventions que T1 : `{data}`, `{message, errors}`, `can`)

| Méthode | Route | Rôle | Notes |
|---|---|---|---|
| GET | `/classes/{classe}/professeurs` | staff ; professeur assigné | assignations (prof, rôle, dates, `actif`, `nb_sessions_assignees`) |
| POST | `/classes/{classe}/professeurs/apercu` | staff | `professeur_id`, `role?`, `date_debut?` → récapitulatif + conflits, **sans écrire** |
| POST | `/classes/{classe}/professeurs` | staff | assigne + propage ; 422 si conflit ; 201 + récapitulatif |
| PUT | `/classes/{classe}/professeurs/{professeur}` | staff | rôle / dates |
| DELETE | `/classes/{classe}/professeurs/{professeur}` | staff | terminer (`date_fin` optionnelle) + retrait des sessions futures sans timesheet ; réponse : récapitulatif |
| GET | `/professeurs/{professeur}/classes` | staff ; le professeur lui-même | classes assignées (actives et terminées), avec co-professeurs |
| POST | `/professeurs/{professeur}/classes/apercu` | staff | `classe_id`, … (même service) |
| POST | `/professeurs/{professeur}/classes` | staff | `classe_id`, `role?`, dates (**même service**, même résultat) |
| PUT / DELETE | `/professeurs/{professeur}/classes/{classe}` | staff | idem côté classe |
| GET | `/sessions/{session}/professeurs` | staff ; prof concerné | lignes `session_professors` (+ `remplace`, `remplace_par`) |
| POST | `/sessions/{session}/remplacer` | staff | `professeur_remplace_id`, `professeur_remplacant_id` ; `avertissements[]` |
| DELETE | `/sessions/{session}/remplacements/{professeur}` | staff | annule le remplacement du professeur A |
| GET | `/mes-classes` | professeur | classes du professeur connecté + prochaine session + co-professeurs ; `?inclure_terminees=1` |
| GET | `/mes-classes/{classe}/sessions` | professeur (sa classe) | sessions avec, pour chacune : numéro de séance/bis, statut, **ma situation** (`assignee`, `remplace_par`, `remplacant_de`) et co-professeurs |

Les Resources de T1 (`ClasseResource`, `CourseSessionResource`) exposent en plus `professeurs` (liste légère : id, nom, rôle, `remplace`) — **jamais** de tarif. **Suppression des routes** `/professeurs/{p}/cours*` et `/cours/{cours}/professeurs*` et de `ProfesseurCoursController`. Corriger au passage les deux anomalies T1 : `GET /classes/{id}/sessions` doit renvoyer `classe.cours` ; `can.bis` faux pour une session annulée.

## 4. Seeders et factories
`ProfesseurClasseSeeder` : dans la démo (classes React mercredi/samedi, ou cours existants), Alice principale du mercredi, Bob du samedi, Carol ou un 3ᵉ professeur en co-enseignement/remplaçant sur une session. `DatabaseSeeder` mis à jour. Factories `ProfesseurClasseFactory`, `SessionProfesseurFactory`.

## 5. Tests back (base `lgit_test`, `scripts/test-backend.sh`)
Service : propagation aux sessions à venir seulement (classe en cours / pas commencée), idempotence, conflit d'horaire bloquant, réactivation, terminaison (sessions futures sans timesheet retirées, passées/avec timesheet conservées), sessions créées après coup (bis) héritant des professeurs, **remplacement sans contrainte timesheet** (AC-25, session passée avec timesheet du remplacé), annulation du remplacement, re-propagation qui n'écrase pas un remplacement, remplacement refusé sur session annulée. Endpoints : 401/403/422/409/succès, **mêmes résultats depuis la classe et depuis le professeur** (AC-12/13), isolation (AC-2 : Alice ne voit pas le samedi de Bob, y compris via `/classes`, `/sessions`, `/calendar/*`), professeur sans classe → 403 sur les liens du cours, `canAccessCours` actif/terminé. Rollback des migrations T2.

## 6. Front (étape suivante)
Mock-up 03 : section « Professeurs » de la classe (liste, rôle indicatif, ajout avec **récapitulatif de propagation** « N sessions à venir, P passées non modifiées », conflits bloquants, terminer), colonne/profs par session et action **Remplacer un professeur** (actif aussi sur sessions passées) + annulation du remplacement ; mock-up 04 : fiche professeur › section « Classes » (ajout via la même modale de propagation) en **remplacement** de l'assignation de cours (`CoursAssignmentModal`, `ProfesseurCoursCard`) ; mock-up 06 : « Mes classes » (menu renommé, `/mes-cours` → `/mes-classes`), cas co-enseignement et remplacement ; conserver l'accès des professeurs aux liens/ressources de leurs cours (logique actuelle de `MesCoursPage`) ; bouton d'encodage d'heures différé à T3.
