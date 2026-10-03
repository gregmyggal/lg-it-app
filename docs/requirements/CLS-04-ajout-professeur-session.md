# CLS-04 — Ajouter un professeur sur une session (même passée) — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. **Brouillon proposé par le sub-agent UX Expert, à valider** (aucun code écrit). Workflow et mock-ups : `docs/mockups/CLS-04/`.

| | |
|---|---|
| **ID / Titre** | CLS-04 — Ajout ponctuel d'un professeur (co-enseignant) à une session |
| **Statut** | ☑ Brouillon ☐ En revue ☐ Validé (DoR) |
| **Analyste** | à nommer · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | à nommer |
| **Date / Version** | 2026-10-03 · v0.1 |
| **Liens** | `CLS-01-T2-contrat.md` (RG-9 remplacement), `docs/API_T2_PROFESSEURS_CLASSES.md`, `SessionReplacementService`, `RemplacerProfesseurModal.jsx`, `ClasseSessionsTable.jsx`, `docs/mockups/CLS-04/` |

---

## 1. Contexte et problème
- **Situation actuelle :** seul le **remplacement A→B** existe sur une session (RG-9). L'assignation à la classe propage aux sessions **à venir** uniquement.
- **Douleur :** quand un second professeur a réellement enseigné (ou enseignera) UNE séance en plus du titulaire (co-animation, renfort, visite), il faut le « remplacer » à quelqu'un (faux : le titulaire a bien enseigné) ou l'assigner à toute la classe (faux : propage à toutes les séances futures). Impossible de corriger une séance passée sans mentir sur qui était présent.
- **Déclencheur :** besoin directeur : « pouvoir ajouter un professeur sur une session, même passée ».

## 2. Objectifs et indicateurs
| Objectif métier | Indicateur | Cible | Mesure |
|---|---|---|---|
| Déclarer un co-enseignant ponctuel | clics depuis le tableau des sessions | ≤ 4 (bouton → professeur → Ajouter) | recette chronométrée |
| Ne rien casser | timesheets modifiées par l'ajout | 0 | test back AC-4 |

**Hors périmètre :** ajout à plusieurs sessions d'un coup (utiliser l'assignation de classe) ; encodage d'heures depuis la session (T3) ; changement de l'assignation de classe ; modification des timesheets ; rémunération (rôle indicatif seulement).

## 3. Acteurs et permissions
Persona : directeur/admin sur poste (page détail classe). Le professeur ajouté voit la session dans « Mes classes ».

| Action | Admin | Staff | Professeur |
|---|---|---|---|
| Ajouter un professeur à une session | oui | oui | — (403) |
| Retirer un ajout ponctuel | oui | oui | — (403) |
| Lister les professeurs d'une session | L | L | L (sessions où il intervient) |

→ Policy : réutiliser la capacité du remplacement (`can.remplacer`) ; **proposé** : `can.gerer_professeurs` (staff), vrai pour toute session non annulée.

## 4. Glossaire
| Terme affiché | Définition | Technique |
|---|---|---|
| Professeur ajouté / « ajout ponctuel » | professeur rattaché à UNE session, sans l'être à la classe, sans remplacer personne | `session_professors.origine = 'ajout'` |
| Co-enseignant | rôle par défaut de l'ajout (**indicatif**, aucun effet sur paie/timesheets) | `role = co_enseignant` |

## 5. Parcours et critères
### 5.1 User stories
| ID | En tant que | Je veux | Afin de | Prio |
|---|---|---|---|---|
| US-1 | directeur | ajouter un professeur à une session précise, passée ou future | refléter qui enseigne/a enseigné sans toucher au reste | Must |
| US-2 | directeur | retirer cet ajout en cas d'erreur | corriger sans passer par la base | Must |
| US-3 | professeur ajouté | voir la session dans Mes classes | savoir où et quand intervenir | Should |

### 5.2 Parcours nominal (directeur)
Classe › tableau des sessions › **Professeurs** sur la ligne › modale « Professeurs de la séance » (onglet **Ajouter** actif par défaut) › choisir le professeur (rôle « Co-enseignant » prérempli) › **Ajouter à cette session** › toast + colonne mise à jour (« Carol (ajouté) »). Alternatives : session passée (avertissement heures inchangées), conflit d'horaire (avertissement non bloquant), session annulée (action absente ; 409 si forcée), doublon (professeur absent de la liste ; 422 si forcé), erreur réseau (bandeau, saisie conservée).

### 5.3 Critères d'acceptation
| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| AC-1 | session future non annulée | je POST `professeur_id` | 201, ligne `origine=ajout`, `role=co_enseignant`, `remplace=false`, lignes existantes inchangées | Feature |
| AC-2 | session **passée** avec timesheet du titulaire | idem | 201 ; timesheets inchangées (compte et valeurs) ; assignation de classe inchangée | Feature |
| AC-3 | session annulée | idem | 409, rien créé | Feature |
| AC-4 | professeur déjà sur la session (classe, remplacement, ajout, **y compris remplacé**) | idem | 422 sur `professeur_id`, message distinct si remplacé | Feature |
| AC-5 | l'ajouté a une autre session au même horaire | idem | 201 + `avertissements[]` non vide | Feature |
| AC-6 | ligne `origine=ajout` | re-propagation de classe / `terminer` l'assignation du même prof | la ligne n'est ni écrasée ni retirée | Service |
| AC-7 | ligne `ajout` | DELETE | 200, ligne supprimée, timesheets intactes ; 409 si la ligne est `classe`/`remplacement` ou impliquée dans un remplacement en cours | Feature |
| AC-8 | professeur ajouté | `GET /mes-classes/...` | la session apparaît avec `ma_situation.type = 'ajoute'`, sans tarif | Feature |
| AC-9 | professeur non staff | POST/DELETE | 403 ; 401 sans jeton | Feature |
| AC-10 | modale ouverte | choix du professeur | liste = professeurs actifs non présents sur la session ; focus piégé, Échap ferme | Front |

## 6. Règles métier
- **RG-1** Ajout possible sur toute session **non annulée**, passée ou à venir ; **409** si annulée.
- **RG-2** Aucune contrainte liée aux timesheets : jamais lues, modifiées, bloquées ni transférées (comme RG-9). L'ajouté encode ses propres heures de façon indépendante.
- **RG-3** Le professeur ne doit avoir **aucune ligne** sur la session (classe, remplacement, ajout ; **y compris `remplace=true`**, pour ne pas contourner « annuler d'abord le remplacement ») → **422**. Professeur inactif/inexistant → 422.
- **RG-4** `role` : `co_enseignant` (défaut) | `principal` | `remplacant`, **indicatif** ; `remplacant` déconseillé en UI (réservé au remplacement) → l'UI n'expose que `co_enseignant` / `principal`.
- **RG-5** Conflit d'horaire (autre session non annulée, même jour, horaire chevauchant, `ClasseProfesseurAssignmentService::conflits`) = **avertissement non bloquant** dans `avertissements[]`, comme le remplaçant. L'ajout a lieu.
- **RG-6 Retrait : OUI, uniquement pour les lignes `origine = ajout`.** Justification : l'ajout est une action ponctuelle sujette à erreur (mauvais professeur, mauvaise séance) ; sans retrait, la seule issue serait la base de données. Pas de retrait pour `classe` (passe par la fin d'assignation) ni `remplacement` (passe par « Annuler le remplacement »). Refusé (409) si la ligne est remplacée (`remplace=true`) ou est le remplaçant d'une autre ligne : annuler d'abord le remplacement. **Pas de contrainte timesheet** (cohérent RG-2) : le retrait n'efface aucune heure ; l'UI le dit. *Alternative plus prudente (Q2) : bloquer le retrait si le professeur a une timesheet pour cette session.*
- **RG-7 Origine** : nouvelle valeur `ajout` de `session_professors.origine` (enum `classe|remplacement|ajout`).
- **RG-8 Re-propagation / terminaison de classe** : si une ligne existe pour (session, prof), rien n'est ajouté ni modifié (règle déjà en place) → une ligne `ajout` **conserve** son origine et son rôle même si le professeur est assigné ensuite à la classe. `terminer` l'assignation ne retire que les lignes `origine = classe` (**à vérifier/ajuster dans `ClasseProfesseurAssignmentService`** : une ligne `ajout` future ne doit pas disparaître).
- **RG-9 Remplacement d'un ajouté** : un ajouté peut être remplacé (A = ligne `ajout`, `remplace=true`) ; l'annulation du remplacement ne touche que la ligne du remplaçant et restaure A avec son origine `ajout`.
- **RG-10 Visibilité professeur** : une ligne `ajout` non remplacée rend la session visible au professeur (comme un remplaçant ponctuel) ; il n'obtient pas l'accès à la classe entière.

**Carte des statuts de ligne** : `classe` (retrait = fin d'assignation) · `remplacement` (retrait = annuler le remplacement) · `ajout` (retrait = DELETE direct). Badge UI « ajouté ».

## 7. Données
| Champ | Type | Contrainte |
|---|---|---|
| `session_professors.origine` | enum | + valeur `ajout` (migration réversible : `down` refuse ou convertit en `remplacement` s'il existe des `ajout`) |

Aucune nouvelle table ; aucune migration de données ; `UNIQUE(course_session_id, professeur_id)` garantit le doublon. RGPD/rétention : inchangés. Factory `SessionProfesseurFactory` : état `ajout()`. Seeder démo : Carol ajoutée sur une session passée du mercredi.

## 8. UX/UI
Workflow : `docs/mockups/CLS-04/WORKFLOW_UX.md` · Mock-ups : `docs/mockups/CLS-04/index.html` · Validé par : ☐
**Recommandation :** un **seul point d'entrée** « Professeurs » par ligne de session (remplace le bouton « Remplacer un professeur ») ouvrant la modale existante élargie à deux actions (Ajouter / Remplacer) + liste de la séance.
Textes clés : titre « Professeurs de la séance 5 — 18/03/2026 » · bouton « Ajouter à cette session » · session passée : « Cette séance est passée. Les heures déjà encodées ne changent pas ; <nom> pourra encoder les siennes. » · conflit : « <nom> est déjà assigné à une autre session ce jour-là (<classe>, 14:00–16:00). L'ajout reste possible. » · succès : « <nom> a été ajouté à la séance 5. Les autres sessions et les heures déjà encodées ne changent pas. »
4 états : chargement (squelette de la liste), vide (« Aucun professeur sur cette séance » + choix Ajouter), erreur (bandeau + saisie conservée), succès (toast, ligne visible).
Appareils : desktop prioritaire (staff). Accessibilité : modale `role=dialog`, focus, `aria-live` pour avertissements. Clics nominaux : 4.

## 9. Non fonctionnel
Sécurité : staff uniquement, pas de tarif exposé. Traçabilité : colonnes `created_at` + (proposé, Q4) `ajoute_par` pour audit. Performance : trivial. FR / Europe/Brussels.

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB | migration enum `origine` + `ajout` | S |
| Back | `SessionProfesseurService` (ou méthode `ajouter`/`retirerAjout` dans `SessionReplacementService`), `SessionProfesseur::ORIGINE_AJOUT`, FormRequest, `can.gerer_professeurs`, vérif terminaison de classe, scope visibilité prof | M |
| API | 2 routes (ci-dessous) | S |
| Front | `RemplacerProfesseurModal` → `ProfesseursSessionModal` (onglets), hook `ajouterProfesseurSession`/`retirerAjout`, `ClasseSessionsTable` (bouton + badge « ajouté ») | M |
| Tests | Feature AC-1→9, Service RG-8/9, front modale ; `scripts/test-backend.sh`, `npm run lint && npm run build` | M |
| Docs | `API_T2_PROFESSEURS_CLASSES.md` (+ section), pas d'ADR | S |

Tranche unique verticale (DB→API→UI→tests). Retour arrière : migration `down`.

### Contrat API proposé (préfixe `/api`, `auth:sanctum`, staff)
- `POST /sessions/{session}/professeurs` — `{ "professeur_id": 9, "role": "co_enseignant" }` (role optionnel) → **201** `{ "data": <CourseSessionResource avec professeurs>, "avertissements": [ {"date","classe","heure_debut","heure_fin","message"} ] }`. **409** session annulée ; **422** `{message, errors:{professeur_id:[…]}}` (déjà présent / remplacé / inactif / inexistant / `role` invalide) ; 403 ; 401.
- `DELETE /sessions/{session}/professeurs/{professeur}` — retire un ajout → **200** `{ data: <session> }` ; **404** pas de ligne ; **409** ligne non `ajout`, ou remplacée / remplaçante (message indiquant l'action à faire).
- `GET /sessions/{session}/professeurs` : inchangé, `origine` peut valoir `ajout`. `professeurs[]` des Resources : ajouter `origine`.
- `GET /mes-classes/{classe}/sessions` : `ma_situation.type` += `ajoute`.

## 11. Plan de recette
| # | Scénario | Attendu | AC |
|---|---|---|---|
| R1 | Directeur ajoute Carol à une séance future | Carol visible « (ajouté) » ; autres séances inchangées | AC-1 |
| R2 | Idem sur séance passée où Alice a encodé 2 h | avertissement affiché ; heures d'Alice = 2 h | AC-2 |
| R3 | Ajout d'un prof ayant un cours en même temps | avertissement jaune, ajout effectué | AC-5 |
| R4 | Retirer l'ajout de Carol | Carol disparaît, timesheets intactes | AC-7 |
| R5 | Carol se connecte | séance visible dans Mes classes | AC-8 |
Jeu de données : classes React mercredi/samedi, Alice/Bob/Carol, une séance passée avec timesheet.

## 12. Questions ouvertes
| # | Question | Avant |
|---|---|---|
| Q1 | Point d'entrée unique « Professeurs » (recommandé) ou second bouton « Ajouter un professeur » à côté de « Remplacer » ? | mock-ups |
| Q2 | Retrait d'un ajout : sans contrainte (proposé) ou bloqué si une timesheet existe pour ce professeur et cette session ? | dev |
| Q3 | Rôle exposé : « Co-enseignant » seul, ou choix Co-enseignant / Principal ? | dev |
| Q4 | Tracer qui a ajouté (`ajoute_par`) ? | dev |
| Q5 | Le professeur ajouté doit-il être prévenu (e-mail/notification) ? (proposé : non) | recette |

## 13. Validation (DoR)
| Rôle | Nom | Date | Validé |
|---|---|---|---|
| Analyste · UX · Architecte · Dev back · Dev front · Directeur | | | ☐ |

DoR : ☐ Mock-ups validés ☐ Questions bloquantes résolues (Q1, Q2)

## Décisions validées (2026-10-03)

1. **Un seul bouton « Professeurs »** par ligne de session (remplace « Remplacer un professeur ») ; modale : liste de la séance + onglets Ajouter / Remplacer.
2. **Retrait** d'un ajout autorisé **sauf si une timesheet existe déjà** pour ce professeur sur cette session (409). Cela remplace RG-6 « sans contrainte timesheet » pour le retrait ; l'**ajout** reste sans contrainte timesheet.
3. **Rôle par défaut : Principal** (modifiable dans le formulaire ; remplace « Co-enseignant »).
4. **Pas de traçabilité** supplémentaire (`ajoute_par` non créé) ; 5. **pas de notification** au professeur ajouté.
6. `origine` est une chaîne (pas un enum SQL) : **aucune migration** nécessaire.
