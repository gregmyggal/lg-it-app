# CLS-02 — Classe sur deux périodes (14 + 14 séances)

| | |
|---|---|
| **Statut** | ☑ Validé (DoR) — en dev |
| **Étend** | `CLS-01-modele-classes.md` (la classe n'est plus liée à une seule période) |
| **Workflow UX (sub-agent UX Expert)** | ☑ `docs/mockups/CLS-02/WORKFLOW_UX.md` |
| **Mock-ups validés** | ☑ `docs/mockups/CLS-02/` — validés par le directeur le 2026-10-03 |
| **Contrat API** | `docs/API_CLS02.md` |

## 1. Contexte et problème
L'ouverture d'une classe se fait sur une **année scolaire de deux périodes**. Aujourd'hui une classe n'est liée qu'à une période (14 séances) : il faut créer deux classes distinctes pour un même groupe d'élèves, qui ne sont pas reliées entre elles. Le directeur veut **une seule classe** portant les deux périodes, chacune avec sa date de démarrage et ses 14 séances.

## 2. Objectifs
| Objectif | Indicateur | Cible |
|---|---|---|
| Ouvrir une année complète en un formulaire | Temps de création d'une classe 2 périodes | ≤ 1 min, 1 écran |
| Ne plus dupliquer groupe/créneau/profs | Classes à créer pour un groupe sur l'année | 1 |
| Anticiper la période 2 | Classes dont la P2 est planifiée avant la fin de la P1 | alerte à J-28 |

**Hors périmètre :** duplication année N → N+1, élèves/inscriptions, facturation.

## 3. Rôles et permissions
Inchangés par rapport à CLS-01 (création / ajout / modification / suppression de période : admin et directeur ; lecture professeur : ses classes).

## 4. Glossaire
| Terme | Définition | Nom technique |
|---|---|---|
| Classe | Groupe + créneau hebdomadaire sur une année scolaire, portant 1 ou 2 périodes | `classes` |
| Période de classe | Une période (1 ou 2) d'une classe : un cours, une date de démarrage, 14 séances | `classe_periodes` |
| Séance « P1 · Séance 3 » | Séance 1..14 d'une période de classe (numéro fixe par période) | `classe_periode_cours_historique` | table | classe_periode_id, ancien_cours_id, nouveau_cours_id, user_id, created_at |
| `course_sessions.classe_periode_id` + `seance_numero` |
| Hors période | Séance datée après la fin de la période définie (rattrapage, retard) | `hors_periode` (calculé) |

## 5. Parcours et critères d'acceptation

### 5.1 Décisions du directeur
- Cours **différent par période** ; le **même cours** reste autorisé en P1 et P2 (liens partagés). Un cours peut être donné en P1 ou en P2.
- Même groupe, jour, horaire, lieu et professeurs pour les deux périodes (assignations au niveau de la classe, valables pour les 28 séances).
- **P2 optionnelle** à la création ; ajout ultérieur depuis la fiche classe.
- Une classe peut **démarrer en P2 seule** ; la P1 peut être ajoutée plus tard.
- **Une ligne par classe** dans la liste.
- Le **cours d'une période peut être changé après son démarrage**, y compris rétroactivement (correction), **tant qu'aucune heure n'est encodée** sur cette période.
- **Alerte** de planification de la P2.
- Des séances peuvent être données **après la fin de la période** (rattrapage, retard) : plus de blocage (remplace RG « pas de session après fin de période » / AC-22 de CLS-01), mais un **avertissement**.

### 5.2 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… |
|---|---|---|---|
| AC-1 | un cours C1 et un cours C2, une année avec P1 et P2 | le directeur crée une classe mercredi 14h–17h avec P1 (C1, 07/10/2026) et P2 (C2, 17/02/2027) | 1 classe, 2 périodes de classe, 28 sessions (14 par période, numéros 1..14 chacune) ; l'aperçu liste les dates sautées de chaque période |
| AC-2 | la même saisie sans P2 | création | 1 classe, 1 période de classe, 14 sessions ; la P2 reste ajoutable |
| AC-3 | une classe sans P1 | création avec seulement la P2 | classe créée avec P2 seule (14 sessions) |
| AC-4 | une classe avec P1 seule | `POST /classes/{id}/periodes` (P2, cours, date) | 14 sessions P2 créées, professeurs actifs propagés ; P1 inchangée |
| AC-5 | une classe avec P1 | ajout d'une P2 dont la date est ≤ dernière séance de P1 (ou d'une P1 dont la dernière séance dépasse le début de la P2) | refus 422 « avant la fin de la période 1 » |
| AC-6 | une date de démarrage hors de la période choisie ou dont le jour ≠ jour de la classe | création / ajout | 422 |
| AC-7 | la 14e séance tombe après la fin de la période | création / ajout | **pas de blocage** : création OK, avertissement dans l'aperçu et la réponse ; la séance porte `hors_periode = true` |
| AC-8 | une séance de P1 | déplacement ou ajout d'un bis après la fin de P1 | accepté, `hors_periode = true` + avertissement (jamais d'erreur) |
| AC-9 | une période sans aucune heure encodée | changement de cours | accepté (séances passées incluses) ; les liens affichés sont ceux du nouveau cours |
| AC-10 | une période ayant au moins une heure encodée | changement de cours | 409 avec nombre de saisies |
| AC-9b | un changement de cours accepté | consultation de l'historique de la période | une ligne (ancien → nouveau cours, auteur, date) ; rien n'est écrit si le cours est identique |
| AC-11 | une période sans heure encodée | suppression | période et sessions supprimées ; si c'est la seule période de la classe → 409 (supprimer la classe) |
| AC-12 | une période avec heures encodées | « supprimer » | 409 : utiliser l'annulation (motif obligatoire) → séances à venir annulées, statut période `annulee` |
| AC-13 | une classe avec P1 seule dont la dernière séance P1 est dans ≤ 28 jours (ou passée) | consultation de la liste / fiche | `alerte_periode_2` renseignée (« P2 à planifier ») ; absente si P2 existe ou si l'année n'a pas de période 2 |
| AC-14 | une classe à deux périodes | un professeur est ajouté à la classe | assigné aux séances à venir des deux périodes ; récap par période |
| AC-15 | un cours en P1 et P2 | un lien « Séance 3 » est créé | partagé entre P1 et P2 (même cours) ; avec deux cours différents, jeux de liens distincts |
| AC-16 | liste des classes | filtres `cours_id` / `periode_id` | `cours_id` cherche dans P1 **et** P2 ; `periode_id` renvoie les classes ayant cette période |
| AC-17 | une classe à deux périodes | portail professeur, timesheets, calendrier | libellé « P1 · Séance 5 » (+ cours) ; flux d'encodage inchangé |

## 6. Règles métier
- **RG-1** Une classe porte 1 ou 2 périodes de classe, au plus une par période de l'année ; au moins une.
- **RG-2** Chaque période de classe a son cours, sa date de démarrage (jour = jour de la classe, dans les bornes de la période) et ses 14 séances (calendrier FWB / dates sautées). Numéro de séance 1..14 **par période**, jamais renuméroté ; bis rattaché à une séance de la même période.
- **RG-3** Une P2 doit démarrer **après la dernière séance planifiée de la P1** (créneau commun) ; sinon 422. Aucune création partielle (P1+P2 ensemble ou rien).
- **RG-4** Aucun blocage sur la fin de période : avertissement + marqueur `hors_periode`.
- **RG-5** Changement de cours : interdit (409) dès qu'une timesheet est rattachée à une session de la période.
- **RG-5b** Tout changement de cours est tracé (qui, quand, ancien → nouveau cours) et consultable dans la fiche de la période.
- **RG-6** Suppression d'une période : physique si aucune heure encodée et ≥ 2 périodes ; sinon annulation (motif).
- **RG-7** Assignations de professeurs au niveau de la classe ; propagation aux séances à venir de toutes les périodes.
- **RG-8** Alerte P2 : période 1 présente, période 2 absente, année avec P2 définie, classe active, dernière séance P1 active à ≤ 28 jours.
- **RG-9** Liens = (cours de la période, n° de séance) ; inchangé.

**Statuts de période de classe :** `active` → `annulee` (motif obligatoire, séances à venir annulées).

## 7. Données et migration
| Entité / champ | Type | Contraintes |
|---|---|---|
| `classe_periodes.id` | PK | |
| `classe_periodes.classe_id` | FK `classes` | cascade |
| `classe_periodes.periode_id` | FK `periodes` | restrict ; **unique (classe_id, periode_id)** |
| `classe_periodes.cours_id` | FK `cours` | restrict |
| `classe_periodes.date_premiere_session` | date | |
| `classe_periodes.statut` | string | `active` \| `annulee` ; `motif_annulation` nullable |
| `course_sessions.classe_periode_id` | FK | **unique (classe_periode_id, seance_numero, bis_rang)** ; `classe_id` conservé |
| `classes.cours_id`, `periode_id`, `date_premiere_session` | **supprimés** | déplacés vers `classe_periodes` |

- **Migration de données** : une `classe_periodes` créée par classe existante (cours/période/date copiés), puis `course_sessions.classe_periode_id` renseigné ; migration **réversible** (down recopie vers `classes`).
- Seeders et factories mis à jour.

## 8. UX / UI
Voir `docs/mockups/CLS-02/` (01 création, 02 fiche, 03 liste, 04 portail & liens). Composants réutilisés : `AdminPageLayout`, `AdminModal`, `AdminBadge`, `ClasseApercu`, `ClasseSessionsTable`.
4 états (chargement / vide / erreur / succès) repris de CLS-01 ; textes exacts dans `WORKFLOW_UX.md` §3 et §5.

## 9. Non fonctionnel
Création transactionnelle (28 sessions, < 1 s). Isolation professeur inchangée. Fuseau Europe/Brussels.

## 10. Impact technique
| Couche | Changements |
|---|---|
| DB | `classe_periodes`, `course_sessions.classe_periode_id`, retrait de 3 colonnes de `classes` |
| Back | modèle `ClassePeriode`, `ClasseSessionGenerator` (plan par période, avertissements), `CourseSessionService` (hors période), `ClassePeriodeService` (ajouter / changer cours / supprimer-annuler), `ClasseResource`, alerte, eager loads `classePeriode.cours`, filtres |
| API | `docs/API_CLS02.md` |
| Front | création 2 blocs + aperçu, fiche 2 blocs, liste, portail, fiche prof, timesheets, liens, calendrier (libellé « P1 · Séance n ») |
| Tests | feature back (génération, ajout P2, démarrage P2 seule, changement cours, suppression/annulation, alerte, hors période, filtres, isolation) ; lint + build front |

**Tranche verticale unique** (DB → API → UI → tests) : l'ancien modèle disparaît, il n'y a pas de cohabitation.

## 12. Questions ouvertes
Aucune bloquante (les 5 questions du workflow UX sont tranchées, voir §5.1).

## 13. Validation
Directeur / sponsor : ☑ 2026-10-03 (mock-ups et décisions §5.1). DoR : ☑
