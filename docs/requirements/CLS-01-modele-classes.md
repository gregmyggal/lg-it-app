# CLS-01 — Catalogue, classes, sessions et assignation des professeurs

| | |
|---|---|
| **Statut** | ☑ Questions métier tranchées · mock-ups validés (2026-09-30) · ****mock-ups à réaligner** sur la règle « bis / numéro de séance fixe » avant dev |
| **Remplace** | Spec « Gestion professeurs v2 » (assignation à des cours), types de cours |
| **ADR** | `docs/adr/0001-modele-cours-classe-session.md` |
| **Workflow UX (sub-agent UX Expert)** | ☑ `docs/mockups/CLS-01/WORKFLOW_UX.md` |
| **Mock-ups validés** | ☑ 2026-09-30, par le directeur (« les mock-ups sont tops »), **sous réserve** : liens **par séance** + liens généraux (mock-up 07 en cours de mise à jour) |

## 1. Contexte et problème
L'application lie les professeurs à des types de cours puis à des cours du catalogue. Or l'école organise un même cours **plusieurs fois par semaine** avec des professeurs différents (React mercredi après-midi ≠ React samedi). Impossible aujourd'hui d'avoir des sessions, des heures et des droits par classe.

## 2. Objectifs et indicateurs
| Objectif | Indicateur | Cible |
|---|---|---|
| Créer l'organisation d'une année scolaire sans ressaisie | Temps pour créer une classe complète (14 sessions) | ≤ 1 min, 1 formulaire |
| Assigner les profs par classe | Clics pour assigner | ≤ 3 |
| Timesheet fiable | Saisies rattachées à la bonne session/prof | 100 % |
| Ressources à jour | Liens modifiables par les profs concernés sans passer par l'admin | oui |

**Hors périmètre :** gestion des élèves/inscriptions, facturation, stages/formations/anniversaires.

## 3. Rôles et permissions
| Ressource / Action | Admin | Staff (directeur) | Professeur | Élève (code) |
|---|---|---|---|---|
| Catalogue de cours | C L M S | C L M | L | L (publié) |
| Année scolaire | C L M S | C L M | L | — |
| Classe (+ génération des 14 sessions) | C L M S | C L M | L (les siennes) | — |
| Assignation prof ↔ classe | C L M S | C L M S | L (les siennes + co-profs) | — |
| Session (statut, annulation) | C L M S | C L M | L ; marquer sa présence | — |
| Timesheet | C L M S V | L V | C L M (**ses propres** brouillons) | — |
| Liens du cours | C L M S | C L M S | **C L M S si au moins une classe active de ce cours** | L (actifs) |

## 4. Glossaire
Voir `DEVELOPMENT_STANDARDS.md` §2.0 (Cours, Année scolaire, Classe, Session, Assignation, Timesheet, Liens de cours). *Type de cours* : **supprimé**.

## 5. Parcours et critères d'acceptation

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | MoSCoW |
|---|---|---|---|---|
| US-1 | Directeur | créer une année scolaire avec ses 2 périodes | structurer l'année | Must |
| US-2 | Directeur | organiser un cours du catalogue en classe (jour, créneau, lieu, période) | planifier ses 14 séances | Must |
| US-3 | Directeur | assigner un ou plusieurs professeurs à une classe | qu'ils voient et encodent leurs séances | Must |
| US-4 | Professeur | voir mes classes et mes sessions | préparer et encoder mes heures | Must |
| US-5 | Professeur | encoder mes heures pour une session | être payé correctement, indépendamment de mon co-prof | Must |
| US-6 | Professeur | adapter les liens du cours, **séance par séance**, et disposer de **liens généraux** valables pour toutes les séances | garder les ressources à jour et pertinentes à chaque séance, pour toutes les classes | Must |
| US-7 | Directeur | remplacer un professeur en cours d'année (date de fin + nouveau prof) | gérer absences/départs | Should |
| US-9 | Directeur | rattacher des professeurs **depuis la classe**, et une classe **depuis la fiche professeur** | travailler dans le sens le plus naturel | Must |
| US-10 | Directeur | qu'ajouter un professeur à une classe l'assigne **automatiquement à toutes ses sessions** (et inversement) | ne pas assigner 14 séances à la main | Must |
| US-11 | Directeur | remplacer un professeur sur **une session précise** | gérer une absence ponctuelle | Must |
| US-12 | Directeur | ajuster les dates des séances (déplacer, annuler, ajouter) par rapport au calendrier scolaire | coller à la réalité | Must |
| US-13 | Professeur / Directeur | voir l'historique des modifications des liens d'un cours et **annuler** une modification | corriger une erreur opérationnelle | Must |
| US-8 | Directeur | dupliquer les classes d'une année sur la suivante | éviter la ressaisie | Could |

### 5.2 Critères d'acceptation (Given / When / Then)
| ID | Étant donné… | Quand… | Alors… |
|---|---|---|---|
| AC-1 | un cours du catalogue et une année scolaire | le directeur crée une classe (mercredi 14h-17h, début 2026-10-07, période 1) | 14 sessions hebdomadaires sont créées (mercredis consécutifs), un récapitulatif est affiché |
| AC-2 | deux classes du même cours (mercredi, samedi) | on assigne Alice au mercredi et Bob au samedi | Alice ne voit que les sessions du mercredi, Bob celles du samedi |
| AC-3 | une classe avec 2 professeurs | chacun encode ses heures pour la même session | 2 timesheets indépendantes existent ; modifier l'une n'affecte pas l'autre |
| AC-4 | un professeur ayant une classe active de « React » | il ajoute un lien au cours | le lien est visible par tous les profs/élèves de « React » et l'action est journalisée |
| AC-5 | un professeur sans classe de ce cours | il tente `PUT /classe-liens/{id}` | réponse `403` |
| AC-6 | un professeur dont l'assignation est terminée (`date_fin` passée) | il tente d'éditer les liens | `403` ; l'accès en lecture reste possible à ses timesheets historiques |
| AC-7 | une classe avec des timesheets sur ses sessions | on tente de supprimer la classe ou une session | refus `409` avec message expliquant qu'il faut annuler/archiver |
| AC-8 | le catalogue | on consulte les écrans/menus | plus aucune référence aux types de cours |
| AC-10 | une classe avec 14 sessions et un calendrier scolaire (base FWB) contenant des vacances | la classe est créée | les sessions sautent les jours de vacances/fériés du calendrier scolaire ; toujours 14 sessions ; les dates sautées sont listées dans l'aperçu |
| AC-11 | une classe planifiée | le directeur déplace, annule ou ajoute une session | la modification est enregistrée, les assignations sont conservées, les professeurs concernés voient la nouvelle date |
| AC-12 | une classe dont 5 sessions sont passées et 9 à venir | le directeur ajoute Alice à la classe | Alice est assignée aux **9 sessions à venir** (les passées ne sont pas modifiées) ; le récapitulatif indique ce nombre exact avant confirmation. Pour une classe pas encore commencée, ce sont les 14 sessions |
| AC-13 | la fiche d'Alice | le directeur lui ajoute la classe « React mercredi » | même résultat qu'AC-12 (propagation identique dans les deux sens) |
| AC-14 | une classe avec Alice | le directeur remplace Alice par Carol sur la session n°5 uniquement | la session 5 affiche Carol (remplaçante), les 13 autres restent à Alice ; la timesheet de la session 5 est celle de Carol |
| AC-25 | une session passée où Alice a déjà une timesheet | le directeur la remplace par Carol | le remplacement est accepté sans blocage ni avertissement bloquant ; la timesheet d'Alice reste intacte, Carol peut encoder la sienne |
| AC-15 | Alice retirée d'une classe à une date donnée | la modification est validée | les sessions **futures** perdent Alice ; celles passées ou ayant une timesheet la conservent |
| AC-20 | une classe de 14 sessions (séances 1..14) | la session n°5 est annulée | elle **garde le numéro de séance 5** (statut Annulée) ; les autres numéros **ne changent pas** ; les liens de la séance 5 restent ceux de la séance 5 |
| AC-21 | une séance annulée ou en cours | le directeur ajoute un **bis** à une autre date de la période | la session « Séance 5 bis » est créée avec les professeurs propagés ; le nombre de sessions peut dépasser 14 (confirmation « Cette classe passera à 15 sessions ») ; on ne peut pas créer une session sans la rattacher à une des 14 séances |
| AC-22 | une classe dont la période se termine le 30/01 | on tente de créer/déplacer une session au 06/02 | refus **bloquant** : « Cette date est après la fin de la période 1 » |
| AC-23 | un cours avec des liens généraux et des liens par séance | un professeur ouvre la « Séance 3 » (ou « Séance 3 bis ») d'une de ses classes | il voit les liens **généraux** + ceux de la **séance 3** ; il peut créer/modifier les deux types (RG-6/RG-10) ; un lien de séance est visible par toutes les classes du cours |
| AC-24 | une session annulée | le directeur la remplace | un **bis** (même n° de séance, autre date de la période) est créé, professeurs propagés (RG-8) ; la session annulée reste visible avec son motif et un lien vers son bis |
| AC-26 | une session passée, non annulée, où un professeur attendu n'a aucune saisie | le directeur ouvre la vue « par session » | la session est listée « sans heures » avec le(s) professeur(s) concerné(s) |
| AC-27 | un professeur remplacé sur une session | il tente de créer une saisie pour cette session | refus 403 ; ses saisies existantes restent visibles et gardent leur workflow ; le remplaçant peut créer les siennes |
| AC-28 | une saisie existe pour (professeur, session, type d'activité) | le même professeur en crée une autre identique | refus 422 (doublon) ; une saisie d'un autre type, ou d'un autre professeur, est acceptée |
| AC-29 | une session annulée ou qui n'a pas encore commencé | un professeur tente d'y rattacher des heures | refus 422 ; l'écran mensuel ne la propose pas |
| AC-30 | des heures sans cours ni session (ex. préparation) | le professeur les encode | elles sont acceptées (type, date, durée ; cours et commentaire facultatifs) et comptent dans le mois |
| AC-31 | un mois avec des brouillons (sessions préremplies + heures libres) | le professeur soumet le mois | toutes les saisies incluses passent en « Soumis » en une opération |
| AC-32 | des saisies soumises, dont certaines dépassent le plafond journalier | le directeur ou l'administrateur valide (en lot) avec lissage | les lissages demandés sont appliqués puis la validation faite en une transaction ; aucune saisie n'est exclue du lot |
| AC-33 | une session qui a des heures encodées | on tente de l'annuler ou de la déplacer | refus 409 expliqué (le remplacement de professeur reste possible) |
| AC-34 | un cours avec des liens généraux et des liens de séance | on liste ses liens, ou ceux d'une session (séance, bis, annulée) | une session affiche les liens **généraux + ceux de son numéro de séance (1 à 14)** ; un lien archivé n'apparaît nulle part |
| AC-35 | un lien | il est créé, modifié, change de portée, est archivé, réordonné ou restauré | **chaque action produit une version** (auteur, date, avant/après) ; un lot de déplacements (même auteur, même portée, < 5 min) = **une** version |
| AC-36 | deux professeurs modifient le même lien | le second enregistre à partir d'une version dépassée | **409** avec la version actuelle ; rien n'est écrasé en silence |
| AC-37 | une version de plus de 6 mois | la purge quotidienne s'exécute, ou on tente de la restaurer | elle n'est plus proposée ; la restaurer renvoie **410** expliqué |
| AC-38 | un élève avec un code de partage valide | il ouvre le cours | il voit les liens généraux et par séance, **sans auteur, historique ni lien archivé** |
| AC-39 | des anciennes ressources de cours existent | un professeur du cours ou le staff les reprend | elles deviennent des **liens généraux** (versionnés) et ne sont plus comptées comme « anciennes ressources » |
| AC-16 | un lien modifié par un professeur | n'importe quel utilisateur autorisé ouvre l'historique | il voit chaque version (qui, quand, avant/après) et peut **restaurer** une version ; la restauration crée elle-même une entrée d'historique |
| AC-18 | l'année scolaire est créée | on ouvre son calendrier | les vacances/fériés **FWB** sont pré-remplis ; le directeur peut ajouter, modifier ou supprimer des dates (fermetures propres à l'école) ; les entrées sont marquées « FWB » ou « École » |
| AC-19 | une version de lien de plus de 6 mois | la purge planifiée s'exécute | elle est supprimée ; les versions de moins de 6 mois restent restaurables par **tout professeur** ayant une classe du cours |
| AC-17 | un lien supprimé par erreur | on l'annule depuis l'historique | le lien est restauré avec son ordre et ses attributs |
| AC-9 | l'application est déployée avec le nouveau modèle | on consulte les classes/sessions | on part d'un état propre : aucune classe ni session héritée de l'ancien modèle ; les anciennes tables sont supprimées |

## 6. Règles métier et statuts
- **RG-1** : une classe = 1 cours + 1 année scolaire + 1 période (1|2) + 1 jour + 1 créneau. Plusieurs classes du même cours par année/période sont permises.
- **RG-2** : une classe a, à la création, **14 sessions** hebdomadaires (jour fixe) générées à la création **en respectant le calendrier scolaire (base FWB, complétable à la main)** (les semaines de vacances/jours fériés sont sautés). Le directeur peut **ajuster** chaque session (déplacer, annuler avec motif, ajouter un bis). **Le nombre de sessions peut dépasser 14** (bis, confirmation explicite). **Aucune session ne peut être créée ni déplacée après la date de fin de la période** (bloquant). **Numéro de séance** : chaque session générée porte un `seance_numero` (1..14) **fixe** (position dans le cours). Une session annulée **conserve** son numéro de séance ; sa remplaçante est un **« bis »** (ex. « Séance 5 bis ») qui reprend le **même numéro**. Une session ajoutée doit donc **toujours se rattacher à l'une des 14 séances** (bis). Le nombre de sessions peut ainsi dépasser 14, pas le nombre de séances. Aucune renumérotation.
- **RG-3** : une année scolaire contient 2 périodes successives ; les dates de période 2 commencent après la fin de période 1.
- **RG-4** : professeurs par classe : 1..n, rôle `principal|co_enseignant|remplacant` (**indicatif, sans impact sur la rémunération**), dates de validité. Un professeur peut avoir plusieurs classes ; chevauchement de créneaux d'un même professeur → avertissement bloquant.
- **RG-8 (propagation)** : l'assignation est **bidirectionnelle** (depuis la classe ou depuis la fiche professeur) et produit le même résultat : ajouter un professeur à une classe l'assigne aux **sessions à venir** (non passées, non annulées ; pour une classe pas encore commencée : toutes) ; retirer/terminer une assignation retire le professeur des sessions **futures** sans timesheet ; les sessions passées ou avec timesheet sont conservées. Toute session créée/déplacée plus tard hérite des professeurs de la classe. Opération transactionnelle, idempotente, avec récapitulatif.
- **RG-9 (remplacement ponctuel)** : *(une session **annulée** peut aussi être remplacée par un **bis** : nouvelle session, même numéro de séance, autre date de la période — AC-24.)*  sur une session, le directeur peut remplacer un professeur par un autre (`session_professors` = surcharge par session). Le remplacement est possible **sur toute session, passée ou à venir, sans aucune contrainte liée aux timesheets**. Les timesheets existantes (du remplacé comme du remplaçant) **ne sont ni bloquées, ni supprimées, ni transférées** : chacune reste indépendante (RG-5) et la correction éventuelle se fait via leur propre workflow. La surcharge **n'est pas écrasée** par une re-propagation.
- **RG-11 (calendrier scolaire)** : chaque année scolaire est initialisée depuis le **calendrier officiel de la FWB** (fichier de données versionné, importé par une commande), puis **complété/modifié à la main** (fermetures de l'école, ajustements). Chaque entrée porte sa `source` (`fwb`|`ecole`) ; un import ultérieur n'écrase pas les entrées `ecole` ni les modifications manuelles.
- **RG-10 (historique des liens)** : toute création, modification, suppression ou réordonnancement d'un lien de cours produit une **version** (`classe_liens_historique` : lien, action, état avant/après JSON, utilisateur, date), **conservée 6 mois** (purge planifiée quotidienne des versions plus anciennes). Un lien supprimé est **archivé** (soft delete). **Restaurer** une version est possible pour admin/staff et professeurs du cours ; la restauration crée une nouvelle version (l'historique n'est jamais réécrit).
- **RG-5** : une timesheet appartient à un professeur et à une session ; jamais partagée ; le professeur n'encode que pour les sessions de ses classes.
- **RG-6** : droit d'éditer les liens = admin/staff ou professeur avec assignation active sur une classe du cours.
- **RG-12 (liens par séance)** : un lien de cours est soit **général** (toutes les séances), soit rattaché à **une séance** (n° 1..14, `seance_numero`). Les liens sont définis **au niveau du cours** (partagés par toutes ses classes). Une session affiche les liens généraux + ceux de **son numéro de séance** (fixe, identique pour une séance et son bis), **quelle que soit sa position chronologique ou son statut**.
- **RG-7** : suppression : classes/sessions avec timesheets → archivage/annulation, jamais de suppression physique.

**Statuts session** : `planifiee` → `en_cours` → `terminee` ; `planifiee|en_cours` → `annulee` (motif obligatoire). **Statut classe** : `brouillon` → `active` → `terminee`/`archivee`. **Timesheet** : inchangé (`brouillon` → `soumis` → `confirme` → `genere`) — *valeurs à normaliser sans accent au passage*.

## 7. Données et migration
| Table | Champs clés | Contraintes |
|---|---|---|
| `annees_scolaires` | `libelle`, `date_debut`, `date_fin`, `statut` | `libelle` unique |
| `classes` | `cours_id`, `annee_scolaire_id`, `periode` (1\|2), `jour_semaine`, `heure_debut`, `heure_fin`, `lieu`, `date_premiere_session`, `statut` | FK ; index `(annee_scolaire_id, periode)`, `(cours_id)` |
| `professeur_classe` | `professeur_id`, `classe_id`, `role`, `date_debut`, `date_fin` | `UNIQUE(professeur_id, classe_id)` |
| `course_sessions` | `classe_id` (remplace `cours_id`), `seance_numero` (1..14, fixe), `bis_rang` (0 = originale, 1 = bis, 2 = bis du bis…), `date`, heures, `statut`, `motif_annulation`, `remplace_session_id` (bis → session annulée) | `UNIQUE(classe_id, seance_numero, bis_rang)` |
| `calendrier_scolaire` | `annee_scolaire_id`, `date_debut`, `date_fin`, `type` (`vacances`\|`ferie`\|`fermeture`), `libelle`, `source` (`fwb`\|`ecole`) | index `(annee_scolaire_id, date_debut)` |
| `session_professors` (conservée) | `session_id`, `professeur_id`, `role`, `origine` (`classe`\|`remplacement`), `remplace_professeur_id` | `UNIQUE(session_id, professeur_id)` ; `origine=remplacement` protégée de la propagation |
| `classe_liens` | + `deleted_at` (soft delete), `updated_by`, `seance_numero` (NULL = lien **général**, sinon n° de séance 1..14 ; remplace le champ libre `seance`) | index `(parent_id, seance_numero)` |
| `periodes` | `annee_scolaire_id`, `numero` (1\|2), `date_debut`, `date_fin` | `UNIQUE(annee_scolaire_id, numero)` ; borne de création des sessions (RG-2) |
| `classe_liens_historique` | `classe_lien_id`, `parent_id` (cours), `action`, `avant` JSON, `apres` JSON, `user_id`, `created_at`, `restaure_depuis_id` | index `(parent_id, created_at)` ; append-only |
| `timesheets` | `professeur_id`, `session_id`, heures… | 1 par (prof, session) |
| **Supprimées** | `types_cours`, `professeur_type_cours`, `cours_type_cours`, `professeur_cours`, `course_recurrences` | après backfill |

- **Pas de migration de données** : l'ancien modèle (`professeur_cours`, `course_recurrences`, sessions et assignations de test du Sprint 2) est **abandonné** ; on démarre d'un état propre. Les migrations suppriment les anciennes tables ; `down()` recrée leur structure. *Les données de référence (catalogue `cours`, `professeurs`, `classe_liens`, tarifs, timesheets) sont conservées.* Timesheets existantes : `session_id` reste nullable et les anciennes saisies restent consultables.
- **Import FWB** : commande `artisan calendrier:import-fwb <année>` (idempotente) depuis `database/data/calendrier_fwb_AAAA-AAAA.json`.
- **Purge** : commande planifiée `liens:purge-historique` (> 6 mois).
- **Seeders/factories** : année scolaire, classes (mercredi/samedi d'un même cours), 2 profs indépendants, sessions.

## 8. Exigences UX/UI
> 🚦 Workflow défini par le sub-agent UX Expert, intégré aux trajets existants (menu admin Cours, Calendrier, Fiche professeur, Mes cours, Timesheets), + mock-ups validés **avant dev**.

**Écrans à concevoir (mock-ups à produire, chacun avec les 4 états)** :
1. Admin : sélecteur d'**année scolaire** + liste des classes (par cours / par jour) — création/édition de classe (génère les 14 sessions, aperçu des dates).
2. Admin : **assignation des professeurs**, **dans les deux sens** (depuis la classe et depuis la fiche professeur) — rôle, dates, détection de conflits, **récapitulatif de propagation** (« Alice sera assignée à 14 sessions ») avant confirmation.
2bis. Session : **remplacement ponctuel** d'un professeur, et **ajustement** (déplacer / annuler / ajouter) avec visualisation du **calendrier scolaire** (vacances/fériés).
2ter. Admin : gestion du **calendrier scolaire** de l'année.
3. Calendrier existant : filtré par classe/professeur/année.
4. Portail professeur « Mes classes » (mobile-first) → sessions → encodage d'heures (≤ 3 clics).
5. Édition des **liens du cours** par un professeur (indication « visible par toutes les classes de ce cours »), **panneau d'historique** (qui/quand/avant-après) avec **Annuler / Restaurer**, confirmation avant suppression.
6. Retrait des écrans/menus « Types de cours ».

## 9. Non fonctionnel
Isolation stricte par `professeur_classe` (tests 403) ; audit des modifications de liens et d'assignations ; génération de sessions transactionnelle et idempotente ; fuseau Europe/Brussels ; messages en français.

## 10. Impact technique et tranches verticales
| Tranche | Contenu livré (DB→API→UI→tests) |
|---|---|
| **T0** ✅ (2026-09-30) | Base de test MySQL Docker + garde-fou ; tests de caractérisation de l'existant |
| **T1** | Années scolaires + **périodes** + **calendrier scolaire (import FWB + édition manuelle)** + classes + génération des 14 sessions + ajustement des sessions (numéros de séance fixes, **bis** pour une session annulée, nombre de sessions dépassable, borne fin de période) + écran admin classes |
| **T2** ✅ (2026-10-01) | `professeur_classe` + assignation **bidirectionnelle avec propagation aux sessions** + **remplacement ponctuel** + Policies + `Mes classes` (prof) |
| **T3** ✅ (2026-10-01) | Rattachement timesheets ↔ session/prof indépendant ; encodage depuis « Mes classes » |
| **T4** ✅ (2026-10-01) | Liens du cours **généraux et par séance** édités par les profs de classe (Policy) + **historique versionné (6 mois) avec annulation/restauration par tous les professeurs du cours** |
| **T5** ✅ (2026-10-01) | Suppression du type de cours et des anciennes tables/routes/écrans ; nettoyage |

Risques : suppression des anciennes tables (vérifier qu'aucune donnée à conserver n'y est rattachée), rupture des routes `/cours/{cours}/professeurs|recurrences` (à faire évoluer avec les écrans dans la même PR), calendrier Sprint 2 à réancrer sur `classe_id`.

## 11. Plan de recette (à compléter après mock-ups)
R1 Directeur crée une classe → 14 sessions. R2 Deux classes React, deux profs → isolation. R3 Deux profs, même session → deux timesheets indépendantes. R4 Prof modifie un lien → visible partout, journalisé. R5 Prof hors cours → 403. R6 Calendrier FWB importé + fermeture école ajoutée → génération correcte. R7 Purge > 6 mois.

## 12. Questions ouvertes et décisions

**Tranchées (direction, 2026-09-30)**
| # | Décision |
|---|---|
| Q1 | Classes **indépendantes**. Un groupe d'élèves suit le cycle du cours A (période 1), un autre le cycle du cours B (période 2). Pas de lien « même groupe » entre classes. |
| Q2 | Les 14 séances suivent le **calendrier scolaire** ; ajustable au besoin (déplacer/annuler/ajouter). |
| Q3 | **Remplacement ponctuel** d'un professeur sur une session : requis. |
| Q4 | Le rôle « principal » est **indicatif**, **sans impact sur la rémunération**. |
| Q5 | Liens : **historique versionné avec annulation** (RG-10). |
| Q12 | Ajout d'un professeur à une classe : assigné aux **prochaines sessions** (RG-8). |
| Q13 | **Pas de renumérotation** : numéro de séance fixe (1..14) ; une session annulée le conserve, sa remplaçante est un **bis** (RG-2). *(Remplace la décision précédente de renumérotation chronologique.)* |
| Q14 | Le **nombre de sessions** peut dépasser 14 (bis), pas le nombre de séances. |
| Q15 | **Aucune session** créée après la fin de la période (bloquant). |
| Q16 | Une session annulée est remplacée par un **bis** de la même séance (AC-24). |
| Q23 | **T3 — mock-ups validés** (`docs/mockups/CLS-01-T3/`, 2026-10-01) avec les recommandations de l'UX : Q7 (correction d'une saisie soumise par erreur) **hors T3**, la direction est contactée ; Q13 **statuts normalisés sans accent** (`confirme`, `genere`) et `isLocked()` corrigé ; Q14 **annuler/déplacer une session ayant des heures est bloqué** ; Q16 la **feuille d'encodage** remplace l'ouverture de la page Timesheets du mock-up 06 ; Q11 le staff n'encode pas pour un professeur. |
| Q25 | **T5 — mock-ups et recommandations validés** (`docs/mockups/CLS-01-T5/`, 2026-10-01) : le type de cours est **retiré sans remplacement** (aucune « Catégories » sur un cours, aucun message de transition, **404** pour les anciennes URL front et API) ; les **types de formation sont conservés** (la page élève d'une formation garde ses catégories) ; migration `drop` **réversible** (pivots puis `types_cours`), sauvegarde avant déploiement, **aucune migration de données** ; `canAccessCoursByType` (code mort) supprimé ; seeder sans types ; aucun export ni import manuel n'utilise les types de cours (confirmé par la direction). |
| Q24 | **T4 — mock-ups validés** (`docs/mockups/CLS-01-T4/`, 2026-10-01) avec les recommandations de l'UX : **un seul écran « Liens du cours »** basé sur `classe_liens` (parent cours) ; `theme` devient un **Type** (Document, Vidéo, Outil, Jeu) ; `pinned` et `actif` **non exposés** ; **reprise manuelle et versionnée** des anciennes ressources (`cours_ressources`) en liens généraux ; séance **1 à 14** seulement, un lien hors programme va dans « Hors programme » ; l'**élève ne voit ni historique ni auteur** ; un **lot de déplacements = une version** ; un professeur **sans classe active** lit mais n'écrit pas (historique refusé tant qu'il n'a eu aucune assignation, consultable sans restauration si l'assignation est terminée) ; stages/formations/anniversaires **hors T4**. |
| Q19 | **Encodage libre** des heures, **pas forcément lié à un cours ni à une session** (ex. préparation faite à une autre date que le cours) : c'est une fonction à part entière, pas une exception (T3). |
| Q20 | **Encodage de toutes les heures en fin de mois** : le parcours principal est un écran mensuel « Encoder mon mois » (sessions du mois à encoder, préremplies, + heures libres), pas un encodage séance par séance au fil de l'eau. |
| Q21 | **Validation par l'administrateur ou le directeur**, avec **lissage possible** pendant la validation (le lissage ne bloque pas la validation en lot : il fait partie du flux). |
| Q22 | **Les montants en euros restent visibles pour le professeur** (ses propres heures/montants), pas masqués. |
| Q18 | **Remplacement d'un professeur sans contrainte liée aux timesheets** (session passée ou à venir ; timesheets existantes inchangées). |
| Q17 | Liens : **par séance (n° 1..14 fixe)** + **généraux** (RG-12). |
| Q7 | Assignation possible **depuis la classe et depuis le professeur**, avec **propagation** à toutes les sessions (RG-8). |

| Q6 | **Pas de migration** des classes/sessions existantes ; démarrage d'un état propre. |
| Q8 | Calendrier scolaire basé sur celui de la **FWB**, **complétable à la main**. |
| Q9 | **Aucune notification** sur les changements de session. |
| Q10 | Historique des liens conservé **6 mois** ; **tous les professeurs** (ayant une classe du cours) peuvent restaurer. |
| Q11 | Retrait d'un professeur : **sessions futures sans timesheet uniquement**. |

**À confirmer (propositions de l'UX Expert — WORKFLOW_UX.md §9, non encore tranchées)** :
- *(tranché)* Remplacement d'un professeur : aucune contrainte liée aux timesheets, y compris sur une session passée (Q18).
- Rattrapage impossible faute de date libre dans la période : la création est bloquée (RG-2) ; la direction doit alors prolonger la période ou ajuster le calendrier — *sans action supplémentaire prévue*.
- Fichier FWB : un administrateur l'importe (bouton réservé), le directeur complète les entrées « École » ; une entrée FWB supprimée reste masquée aux imports suivants ; date ajoutée après coup au calendrier = alerte + Déplacer/Annuler/Maintenir, sans déplacement automatique.
- Droits du directeur sur le calendrier scolaire ; genre des libellés de rôle ; redirections `/mes-cours` → `/mes-classes` ; duplication d'année (US-8, hors CLS-01).

## 13. Validation
☐ Analyste ☐ UX (workflow + mock-ups) ☐ Architecte ☐ Dev back ☐ Dev front ☐ Directeur
