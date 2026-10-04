# CLS-06 — Replanifier automatiquement les séances suivantes lors d'un déplacement — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. **Validé (DoR) le 2026-10-04 — backend implémenté** (arbitrages du directeur Q1–Q5 et N-1 à N-4 intégrés, v0.3) (aucun code écrit). Workflow et mock-ups : `docs/mockups/CLS-06/README.md`.

| | |
|---|---|
| **ID / Titre** | CLS-06 — Déplacer une séance et décaler les séances suivantes (une par semaine, numéros conservés, cascade vers la P2) |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — mock-ups validés par le directeur le 2026-10-04 ☑ En dev (backend + frontend livrés, vérifiés dans le navigateur) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* |
| **Date / Version** | 2026-10-04 · v0.3 (arbitrages directeur : calcul hebdo + Forcer, jour de la classe, **cascade vers P2** avec alignement de la date de démarrage P2, **arrêt sur séance verrouillée** avec **avertissement** (pas de refus) en cas de chevauchement, case cochée par défaut, fin d'année = avertissement) |
| **Liens** | `CourseSessionService::deplacer`, `ClasseSessionGenerator::plan` (RG génération, CLS-02 RG-3/RG-4), `CLS-05-forcer-seances-sur-congés.md` (forçage par date), `SessionAdjustModal.jsx`, `ClasseSessionsTable.jsx`, `ClasseDetailPage.jsx`, `docs/mockups/CLS-06/` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** la modale « Ajuster la séance » › *Déplacer à une autre date* déplace **une seule** séance (`PUT /sessions/{id}`). Elle garde son numéro et sa période ; une date après la fin de période donne l'avertissement non bloquant « hors période ». Les autres séances ne bougent pas.
- **Douleur :** quand une séance glisse d'une semaine (salle indisponible, absence, événement), le directeur doit ouvrir et déplacer **une à une** les 8 à 13 séances suivantes (et parfois celles de la période 2), recalculer lui-même les semaines et les congés. Sans cela, deux séances tombent la même semaine, ou l'ordre des numéros s'inverse (P1 · 5 après P1 · 6).
- **Déclencheur (verbatim directeur) :** *« Lorsqu'une classe existe, en cas d'ajustement d'une séance à une autre date, il faudrait la possibilité de replanifier automatiquement les autres séances avec une intervalle d'une séance par semaine et de conserver la numérotation. Si je déplace une séance d'une semaine, les autres séances devraient se décaler de manière alignée. »*

---

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif métier | Indicateur mesurable | Cible | Comment mesurer |
|---|---|---|---|
| Décaler toute la suite du planning en une opération | clics depuis le tableau des sessions | ≤ 4 (Ajuster → date → [case déjà cochée] → Déplacer N séances) | recette chronométrée |
| Voir l'impact avant d'agir | séances modifiées visibles avant confirmation | 100 % (avant → après, congés sautés, hors période, P2, séances verrouillées) | recette visuelle |
| Numérotation intacte | `seance_numero` / `bis_rang` modifiés | 0 | test back AC-3 |
| Ordre inversé signalé avant confirmation | chevauchements avec une séance verrouillée affichés en avertissement dans l'aperçu | 100 % | tests back AC-21 à AC-23, AC-25 |
| Cohérence avec la génération | dates recalculées = règle de génération (1/semaine, jour de la classe, congés sautés) | 100 % | test back AC-2 |

**Hors périmètre :**
- Décaler la P2 quand la P1 **ne déborde pas** sur elle (RG-6).
- Changer durablement le jour de la classe (mercredi → jeudi pour toutes les séances) : c'est une modification de la classe, pas d'une séance.
- Annuler/défaire la replanification (on refait un déplacement en sens inverse ; l'aperçu le rend sûr).
- Notification des professeurs ou des familles.
- Replanifier à partir d'un **bis**, d'une séance annulée ou passée.
- Modifier l'horaire ou le lieu des séances décalées (seule leur **date** change).
- Contrôles CLS-06 sur un **déplacement simple** (case décochée) : comportement actuel inchangé (arbitrage N-3).

---

## 3. 🔴 Acteurs, rôles et permissions

**Personas :** Directeur/Staff et Admin, sur poste, page détail d'une classe › tableau des sessions.

| Action | Admin | Staff (directeur) | Professeur | Élève |
|---|---|---|---|---|
| Voir l'aperçu « avant → après » d'un déplacement avec décalage | ✓ | ✓ | ✗ (403) | — |
| Déplacer une séance **et** décaler les suivantes (P1 et, en cascade, P2) | ✓ | ✓ | ✗ (403) | — |
| Voir les nouvelles dates (Mes classes) | ✓ | ✓ | L (ses sessions) | — |

→ **Policy :** réutiliser la capacité existante du déplacement (`session.can.update`, policy `update` de `CourseSession`). Aucune nouvelle permission. Sans jeton : 401.

---

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Technique |
|---|---|---|
| Séance déplacée | La séance sur laquelle on clique « Ajuster » ; prend la nouvelle date saisie | `course_sessions` ciblée par `PUT /sessions/{id}` |
| Séances suivantes | Séances de la **même période**, de numéro supérieur, non bis | même `classe_periode_id`, `seance_numero > n`, `bis_rang = 0` |
| Décaler les séances suivantes | Recalculer leurs dates à raison d'une par semaine, au jour habituel de la classe, à partir de la semaine qui suit la séance déplacée, en sautant les congés | option `decaler_suivantes = true` |
| Cascade vers la P2 | Recalcul des séances de la période 2 quand la nouvelle dernière séance de P1 tombe le jour ou après la 1re séance de P2 | bloc « Période 2 » de l'aperçu |
| Séance figée | Séance de la plage qui ne bouge pas et n'occupe pas de semaine dans le recalcul (annulée, bis) | affichée « inchangée » dans l'aperçu |
| Séance verrouillée | Séance de base de la plage qui a des **heures encodées** ou est **terminée** : elle ne bouge pas et **arrête** le décalage (elle et toutes les suivantes restent inchangées) | `etat = verrouillee`, `motif = heures_encodees | terminee` |
| Chevauchement | Une date recalculée (ou la date saisie) tombe le même jour ou après une séance verrouillée → **autorisé**, avertissement non bloquant (l'ordre des dates ne suit plus les numéros) | `avertissements[].code = ordre_inverse` |
| Date sautée / forcer | Comme CLS-05 : date de congé non masquée sautée ; « Forcer » la maintient | `dates_forcees[]` (Y-m-d), valables pour P1 et P2 |
| Aperçu | Calcul sans écriture du résultat (avant → après) | `POST /sessions/{id}/deplacement/apercu` (proposé) |

---

## 5. 🔴 Parcours utilisateurs et user stories

### 5.1 User stories

| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | Directeur | déplacer une séance et décaler automatiquement les suivantes de la période | ne pas ressaisir 8 à 13 dates | Must |
| US-2 | Directeur | voir avant de confirmer chaque séance « avant → après », les congés sautés, la fin de période et l'impact sur la P2 | décider en connaissance de cause | Must |
| US-3 | Directeur | que les numéros de séance restent identiques et être prévenu si l'ordre des dates ne les suit plus | garder le lien séance ↔ contenu pédagogique, liens et heures | Must |
| US-4 | Directeur | forcer une date de congé rencontrée pendant le recalcul | rattraper pendant un congé (comme CLS-05) | Should |
| US-5 | Directeur | pouvoir ne déplacer que la séance (comportement actuel) | gérer un report ponctuel sans toucher au reste | Must |
| US-6 | Directeur | avancer une séance et rapprocher les suivantes | récupérer une semaine libérée | Should |
| US-7 | Directeur | que la P2 se décale aussi si la P1 déborde dessus | garder une séance par semaine sur toute l'année | Must |
| US-8 | Directeur | que les séances déjà encodées ou terminées ne bougent jamais | ne pas fausser les heures déclarées | Must |

### 5.2 Parcours nominal (directeur, « je décale d'une semaine »)

Données d'exemple : classe du **mercredi**.
- **P1** du 01/09/2026 au 29/01/2027 ; P1 · 1 à 14 : 16/09, 23/09, 30/09, 07/10, 14/10, 04/11, 18/11, 25/11, 02/12, 09/12, 16/12, 06/01, 13/01, 20/01.
- **P2** du 01/02/2027 au 25/06/2027 ; P2 · 1 à 14 : 03/02, 10/02, 03/03, 10/03, 17/03, 24/03, 31/03, 07/04, 14/04, 21/04, 12/05, 19/05, 26/05, 02/06.
- Congés : Toussaint 19/10–30/10, Armistice 11/11, Noël 21/12–01/01, Détente 15/02–26/02, Printemps 26/04–07/05. Aujourd'hui : 04/10/2026.

1. Page de la classe › tableau des sessions › ligne **P1 · 4 (07/10)** › **Ajuster** (existant).
2. Mode **Déplacer à une autre date** (présélectionné, existant) › **Nouvelle date** = 14/10/2026.
3. **NOUVEAU** — sous la date : case **« Décaler aussi les séances suivantes (P1 · 5 à 14) »**, **cochée par défaut**, aide : « Une séance par semaine, le mercredi, en sautant les congés. Les numéros ne changent pas. »
4. **NOUVEAU** — l'aperçu « avant → après » se charge sous la case (debounce ~300 ms) : 10 séances décalées, dates sautées avec case **Forcer**, dernière séance « 27/01 (était 20/01) — dans la période », mention « La période 2 ne change pas ».
5. Le bloc **Conséquence** (existant) est réécrit : « La P1 · Séance 4 passe au 14/10 et **les 10 séances suivantes sont décalées**. Toutes gardent leur numéro. »
6. Bouton primaire : **« Déplacer 11 séances »**.
7. Toast succès + tableau rechargé. La modale se ferme.

**Variantes**
- **Décocher la case** → comportement actuel exact (une seule séance, bouton « Déplacer la séance »), aperçu masqué.
- **Même semaine, autre jour** (07/10 → jeudi 08/10) → « Aucune autre séance ne change de date » ; bouton « Déplacer la séance ».
- **Dernière séance de P1** (P1 · 14) → case libellée « Décaler aussi la période 2 si nécessaire » ; affichée uniquement si la nouvelle date atteint ou dépasse P2 · 1 ; sinon absente + « C'est la dernière séance de la période : aucune séance à décaler. »
- **Cascade vers P2** (P1 · 4 → 04/11) → P1 · 14 recalculée au 03/02 = date de P2 · 1 → toute la P2 est recalculée à partir de la semaine suivante ; bloc « Période 2 » séparé dans l'aperçu ; bouton « Déplacer 11 séances (P1) + 14 (P2) » (RG-6).
- **Arrêt sur séance verrouillée** (P1 · 11 avec heures encodées, P1 · 7 avancée au 12/11) → P1 · 8 à 10 décalées ; P1 · 11 à 14 inchangées, ligne « P1 · 11 — heures encodées : non décalée, les suivantes non plus » (RG-5).
- **Chevauchement avec une séance verrouillée** (P1 · 8 avec heures encodées, P1 · 4 → 14/10) → P1 · 7 tombera le 25/11 = date de P1 · 8 → **autorisé**, avertissement non bloquant dans l'aperçu, bouton actif ; forcer l'Armistice (11/11) supprime l'avertissement (RG-5bis).
- **Avancer** (P1 · 7 du 18/11 → jeudi 12/11) → séances suivantes rapprochées d'une semaine ; refus si la date n'est pas après la séance précédente (RG-8).
- **Annulées / bis dans la plage** → figées, signalées (RG-4), non bloquant.
- **Planning modifié entre l'aperçu et la confirmation** → 409, aperçu rechargé (RG-11).
- **Erreur réseau** → bandeau rouge, saisie conservée, bouton réactivé.

### 5.3 Critères d'acceptation

Jeu de données : celui du §5.2.

**Décalage dans la période**

| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| **AC-1** | P1 · 4 (07/10) planifiée, future | `PUT /sessions/{P1·4}` `{date: 2026-10-14}` **sans** `decaler_suivantes` | 200 ; seule P1 · 4 change ; comportement actuel inchangé (rétrocompatibilité) | Feature |
| **AC-2** | Idem | `PUT` `{date: 2026-10-14, decaler_suivantes: true}` | 200 ; P1 · 5 → 04/11, 6 → 18/11, 7 → 25/11, 8 → 02/12, 9 → 09/12, 10 → 16/12, 11 → 06/01, 12 → 13/01, 13 → 20/01, 14 → 27/01 ; P2 inchangée (27/01 < 03/02) ; réponse `decalees = {p1: 10, p2: 0}` | Feature |
| **AC-3** | Après AC-2 | lecture des sessions | `seance_numero`, `bis_rang`, `classe_periode_id`, `heure_debut`, `heure_fin`, `lieu`, `statut`, professeurs de séance (`session_professors`, y compris `remplacement`/`ajout`) **inchangés** ; P1 · 1 à 3 inchangées | Feature |
| **AC-4** | Idem AC-2 | `POST /sessions/{P1·4}/deplacement/apercu` même payload | 200 ; **aucune écriture** ; liste `avant → après` identique à AC-2 ; `dates_sautees` = 21/10, 28/10, 11/11, 23/12, 30/12 avec libellé/type ; bloc `periode_2 = null` | Feature |
| **AC-5** | Idem AC-2 + `dates_forcees: ['2026-11-11']` | `PUT` | P1 · 6 → 11/11 ; P1 · 7 à 14 à leurs dates d'origine ; une date forcée non couverte par un congé ou hors plan est ignorée (CLS-05 RG-1) | Feature |
| **AC-6** | P1 · 4 déplacée au **jeudi** 15/10 (jour de classe = mercredi) | `PUT … decaler_suivantes: true` | P1 · 5 → 04/11 (1er mercredi non congé de la semaine suivante) ; les suivantes restent le **mercredi** | Feature |
| **AC-7** | P1 · 4 déplacée au jeudi 08/10 (même semaine) | aperçu | toutes les suivantes `inchangee` ; `decalees = {p1: 0, p2: 0}` | Feature |
| **AC-9** | Idem AC-2, P1 fin le 22/01 au lieu du 29/01 | `PUT` | 200 ; P1 · 14 (27/01) marquée hors période ; `avertissements[]` non vide (non bloquant, CLS-02 RG-4) ; pas de cascade (27/01 < 03/02) | Feature |
| **AC-11** | P1 · 9 (02/12) annulée et son bis P1 · 9 bis le samedi 28/11 | `PUT` AC-2 | P1 · 9 et P1 · 9 bis **gardent leur date** ; P1 · 8 → 02/12, 10 → 09/12, 11 → 16/12, 12 → 06/01, 13 → 13/01, 14 → 20/01 ; `avertissements[]` : « P1 · 9 bis (28/11) est désormais avant P1 · 8 (02/12) » | Feature |
| **AC-12** | P1 · 7 (18/11) | `PUT {date: 2026-11-03, decaler_suivantes: true}` (avant P1 · 6 du 04/11) | **422** sur `date` : « La nouvelle date doit être après la séance précédente P1 · 6 (04/11). » (avec ou sans décalage) | Feature |
| **AC-13** | P1 · 7 (18/11) | `PUT {date: 2026-11-12, decaler_suivantes: true}` (avancer) | P1 · 8 → 18/11, 9 → 25/11, 10 → 02/12, 11 → 09/12, 12 → 16/12, 13 → 06/01, 14 → 13/01 | Feature |

**Cascade vers la P2 (arbitrage 3)**

| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| **AC-8** | P1 · 4 (07/10) ; P2 · 1 le 03/02 | `PUT {date: 2026-11-04, decaler_suivantes: true}` | P1 · 5 → 18/11, 6 → 25/11, 7 → 02/12, 8 → 09/12, 9 → 16/12, 10 → 06/01, 11 → 13/01, 12 → 20/01, 13 → 27/01, 14 → 03/02 (hors période, avertissement) ; 03/02 ≥ P2 · 1 → **cascade** : P2 · 1 → 10/02, 2 → 03/03, 3 → 10/03, 4 → 17/03, 5 → 24/03, 6 → 31/03, 7 → 07/04, 8 → 14/04, 9 → 21/04, 10 → 12/05, 11 → 19/05, 12 → 26/05, 13 → 02/06, 14 → 09/06 ; numéros P2 conservés ; `decalees = {p1: 10, p2: 14}` | Feature |
| **AC-18** | Idem AC-8 | aperçu | `periode_2` non nul : `declencheur` = « P1 · 14 le 03/02 ≥ P2 · 1 le 03/02 », lignes avant → après, `dates_sautees` P2 = 17/02, 24/02, 28/04, 05/05 ; aucune écriture | Feature |
| **AC-19** | Idem AC-8 + `dates_forcees: ['2027-02-17']` | `PUT` | P2 · 2 → 17/02, P2 · 3 à 14 retrouvent leur date d'origine (03/03 … 02/06) ; `dates_forcees` s'applique aux deux périodes | Feature |
| **AC-20** | P1 · 4 → 14/10 (dernière P1 au 27/01 < 03/02) | `PUT` | P2 **intacte** (aucune ligne P2 modifiée) | Feature |
| **AC-21** | Idem AC-8 ; P2 · 5 (17/03) a des heures encodées | aperçu puis `PUT` | 200 ; P1 comme AC-8 ; P2 · 1 → 10/02, 2 → 03/03, 3 → 10/03, 4 → 17/03 ; P2 · 5 à 14 **inchangées** ; `avertissements[]` (code `ordre_inverse`) : « La P2 · 4 tombera le 17/03, le même jour que la P2 · 5 (17/03, heures encodées) : l'ordre des séances ne suivra plus leur numéro. » ; `decalees = {p1: 10, p2: 4}` | Feature |
| **AC-22** | Idem AC-8 ; P1 · 12 (06/01) a des heures encodées | aperçu puis `PUT` | 200 ; P1 · 5 → 18/11 … 9 → 16/12, 10 → 06/01, 11 → 13/01 ; P1 · 12 à 14 inchangées ; avertissements pour P1 · 10 (même jour que P1 · 12) et P1 · 11 (après P1 · 12) ; **pas de cascade** (arrêt en P1, RG-6) ; P2 intacte | Feature |

**Arrêt sur séance verrouillée (arbitrage 4)**

| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| **AC-10** | P1 · 11 (16/12) a 1 saisie d'heures | `PUT {P1·7, date: 2026-11-12, decaler_suivantes: true}` | 200 ; P1 · 8 → 18/11, 9 → 25/11, 10 → 02/12 ; P1 · 11 à 14 **inchangées** ; aucune cascade P2 ; réponse `arret = {seance: 'P1 · 11', motif: 'heures_encodees'}`, `decalees = {p1: 3, p2: 0}` | Feature |
| **AC-10b** | P1 · 11 `statut = terminee` (sans heures) | idem | même résultat, `motif = terminee` | Feature |
| **AC-10c** | Idem AC-10 | aperçu | ligne P1 · 11 `etat = verrouillee`, `motif = heures_encodees`, libellé « non décalée, les suivantes non plus » ; P1 · 12 à 14 `inchangee` | Feature |
| **AC-23** | P1 · 8 (25/11) a des heures encodées | aperçu puis `PUT {P1·4, date: 2026-10-14, decaler_suivantes: true}` | 200 ; P1 · 4 → 14/10, 5 → 04/11, 6 → 18/11, 7 → 25/11 ; P1 · 8 à 14 inchangées ; avertissement « La P1 · 7 tombera le 25/11, le même jour que la P1 · 8 (25/11, heures encodées) : l'ordre des séances ne suivra plus leur numéro. » ; l'aperçu n'a **pas** de `blocage` | Feature |
| **AC-24** | Idem AC-23 + `dates_forcees: ['2026-11-11']` | aperçu puis `PUT` | 200 ; P1 · 5 → 04/11, 6 → 11/11, 7 → 18/11 ; P1 · 8 à 14 inchangées ; **aucun** avertissement `ordre_inverse` | Feature |
| **AC-25** | P1 · 8 (25/11) verrouillée | `PUT {P1·4, date: 2026-12-02, decaler_suivantes: true}` | 200 ; P1 · 4 → 02/12, 5 → 09/12, 6 → 16/12, 7 → 06/01 ; P1 · 8 à 14 inchangées ; un avertissement `ordre_inverse` par séance concernée (P1 · 4 à 7) | Feature |
| **AC-26** | Cascade AC-8 ; année scolaire se terminant le 04/06/2027 | aperçu puis `PUT` | 200 ; P2 · 14 (09/06) marquée hors période ; avertissement non bloquant « La P2 · 14 (09/06) tombe après la fin de l'année scolaire (04/06) » | Feature |
| **AC-27** | Cascade AC-8 | lecture de `classe_periodes` | `date_premiere_session` de la P2 = 10/02/2027 (alignée sur la nouvelle date de P2 · 1) ; P1 inchangée | Feature |
| **AC-28** | Case décochée | `PUT {P1·4, date: 2026-12-02}` (au-delà de P1 · 8 verrouillée) | comportement actuel inchangé (pas d'avertissement CLS-06) | Feature |

**Transverses**

| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| **AC-14** | Aperçu calculé, puis un autre utilisateur annule P1 · 10 | `PUT` avec l'`empreinte` de l'aperçu | **409** `code = planning_modifie` ; rien modifié | Feature |
| **AC-15** | Professeur | `POST …/apercu` ou `PUT … decaler_suivantes` | 403 ; 401 sans jeton | Feature |
| **AC-16** | Modale ouverte, mode Déplacer, séance non dernière | saisie d'une date | case **cochée par défaut** ; aperçu affiché ; bouton « Déplacer N séances » ou « Déplacer N séances (P1) + M (P2) » ; décocher → aperçu masqué, « Déplacer la séance » | Front / Recette |
| **AC-17** | Aperçu avec avertissement `ordre_inverse` | — | avertissement visible (`role="status"`, texte + icône ⚠) ; bouton primaire **actif** ; l'avertissement est repris en toast après succès | Front |

---

## 6. 🔴 Règles métier et cycle de vie

- **RG-1 — Option explicite, cochée par défaut.** Le décalage n'a lieu que si `decaler_suivantes = true`. L'interface coche la case par défaut (arbitrage 5). Sans l'option, `PUT /sessions/{id}` garde exactement son comportement actuel (rétrocompatible).
- **RG-2 — Mode de calcul (arbitrage 1).** Les séances sont **recalculées** comme à la génération : une par semaine civile (lundi → dimanche), au **jour habituel de la classe** (`classes.jour_semaine`), à partir de la semaine **qui suit** celle de la nouvelle date, en **sautant** chaque date couverte par une entrée non masquée du calendrier scolaire (sauf date forcée, RG-7).
- **RG-3 — Jour différent (arbitrage 2).** La séance déplacée prend la date saisie, quel que soit le jour. Les suivantes (P1 et P2) **restent au jour de la classe**. Un report dans la même semaine (mercredi → jeudi) ne décale rien.
- **RG-4 — Périmètre et séances figées.** Sont recalculées les séances de base (`bis_rang = 0`) de numéro supérieur, statut `planifiee`, de la même période (et de la P2 en cascade, RG-6). Les **annulées** et les **bis** de la plage sont **figés** : date inchangée, ils n'occupent pas de semaine. Si un bis se retrouve avant une séance de numéro inférieur, avertissement non bloquant.
- **RG-5 — Arrêt sur séance verrouillée (arbitrage 4).** Une séance de base de la plage est **verrouillée** si elle a des **heures encodées** ou est **terminée**. Le recalcul s'applique aux séances situées entre la séance déplacée et la **première** séance verrouillée ; la séance verrouillée **et toutes celles qui la suivent** dans la période restent inchangées. L'aperçu affiche la ligne verrouillée avec son motif (« heures encodées » / « terminée ») et « non décalée, les suivantes non plus ». La même règle s'applique aux séances de la P2 recalculées en cascade.
- **RG-5bis — Chevauchement : avertissement, jamais de refus (arbitrage N-1).** Si une date recalculée (ou la date saisie de la séance déplacée) tombe **le même jour ou après** une séance verrouillée qui arrête le décalage, le décalage est **autorisé**. L'aperçu et la réponse portent un avertissement non bloquant par séance concernée : « ⚠ La P{p} · {n} tombera le {date}, {le même jour que / après} la P{p} · {m} ({date}, {heures encodées / terminée}) : l'ordre des séances ne suivra plus leur numéro. » Le bouton reste actif ; forcer un congé peut supprimer l'avertissement.
- **RG-6 — Cascade vers la P2 (arbitrage 3).** Après recalcul de la P1, si la **nouvelle dernière séance active de P1** (bis compris) tombe **le même jour ou après** la 1re séance active de P2, toutes les séances de P2 sont recalculées à partir de la semaine qui suit cette dernière séance de P1 (RG-2, RG-3, RG-4, RG-5, RG-5bis, RG-7 s'appliquent). Numéros de P2 conservés. `classe_periodes.date_premiere_session` de la P2 est **alignée** sur la nouvelle date de P2 · 1 (arbitrage N-2). Si la P1 ne déborde pas, la P2 n'est **pas touchée**. Si la P1 est arrêtée par une séance verrouillée (RG-5), sa dernière séance ne bouge pas : **pas de cascade** (si une séance recalculée de P1 atteint P2 · 1 malgré l'arrêt, avertissement non bloquant « chevauche la période 2 »). Une classe a au plus deux périodes : pas de cascade au-delà.
- **RG-7 — Congés : sautés, forçables date par date.** Chaque date sautée (P1 et P2) porte une case **Forcer** (CLS-05 RG-1 : forçage **par date**, non persisté). Les dates forcées à la création ne sont pas reportées automatiquement.
- **RG-8 — Ordre avec la séance précédente.** La nouvelle date de la séance déplacée doit être **strictement après** la séance active précédente de la période (bis inclus) — **422** sinon, avec ou sans décalage.
- **RG-9 — Numérotation conservée.** Seule la colonne `date` change (et `hors_periode` si persistée). Jamais de renumérotation, de création ni de suppression de séance.
- **RG-10 — Fin de période et d'année.** Une séance recalculée après la fin de sa période (P1 ou P2) est marquée « Hors période · rattrapage » : **avertissement non bloquant** (CLS-02 RG-4). Une séance de P2 après la **fin de l'année scolaire** donne aussi un avertissement non bloquant, avec le même badge (arbitrage N-4).
- **RG-10bis — Déplacement simple.** Case décochée : comportement actuel strictement inchangé, aucune règle CLS-06 ne s'applique (arbitrage N-3).
- **RG-11 — Atomicité et concurrence.** Tout ou rien (transaction, verrou sur les séances des deux périodes). Le serveur recalcule à l'enregistrement ; si le résultat diffère de l'aperçu (`empreinte`), **409** `planning_modifie`.
- **RG-12 — Professeurs.** Les professeurs de séance (origines `classe`, `remplacement`, `ajout`) **suivent la ligne** : aucune re-propagation. Conflit d'horaire à la nouvelle date : avertissement non bloquant.
- **RG-13 — Alertes calendrier.** La séance déplacée peut tomber sur un congé (choix explicite) : l'alerte « Date en conflit » existante s'affiche. Les séances recalculées ne tombent sur un congé que si la date est forcée.
- **RG-14 — Permissions.** Admin et staff (policy `update` de la session). Professeur 403.

**Cycle de vie :** aucun nouveau statut. Les séances décalées restent `planifiee`.

---

## 7. 🔴 Données et migration

- **Nouvelles tables / colonnes :** aucune.
- **Données modifiées :** `course_sessions.date` des séances recalculées (P1 et, en cascade, P2) ; `hors_periode` si persistée. `classe_periodes.date_premiere_session` de la P2 en cascade : alignée sur la nouvelle date de P2 · 1 (RG-6).
- **Inchangées :** `seance_numero`, `bis_rang`, `remplace_session_id`, horaires, lieu, `session_professors`, `timesheets`.
- **Traçabilité :** pas d'historique dédié en v1.
- **Seeders de test :** classe du mercredi avec P1 + P2 et le calendrier du §5.2 ; variantes : P1 · 9 annulée + bis ; P1 · 8, P1 · 11, P1 · 12 ou P2 · 5 avec timesheet ; P1 · 11 terminée.

---

## 8. 🔴 Exigences UX/UI

> Workflow : **intégré à la modale existante « Ajuster la séance » › Déplacer** (aucun nouvel écran, aucun nouveau bouton dans le tableau). Mock-ups : `docs/mockups/CLS-06/README.md` · Validé par : *à remplir* ☐

| Écran | Contenu clé | Actions primaires | Composants réutilisés |
|---|---|---|---|
| Modale « Ajuster la séance » (mode Déplacer) | date + horaire (existant), **case « Décaler aussi les séances suivantes »** (cochée), **aperçu avant → après** (bloc P1, bloc P2 si cascade), lignes verrouillées, bloc Conséquence | « Déplacer N séances » / « Déplacer N séances (P1) + M (P2) » / « Déplacer la séance » ; « Fermer sans modifier » | `AdminModal`, `AdminCheckbox`, `Banner`, `StatutBadge`, rendu de l'aperçu de `ClasseApercu` (cases Forcer CLS-05) |
| Page détail classe › tableau des sessions | dates mises à jour | — | `ClasseSessionsTable` (inchangé) |

**Les 4 états (aperçu dans la modale)**

| Chargement | Rien à décaler | Erreur | Succès |
|---|---|---|---|
| Squelette « Calcul des nouvelles dates… » (`aria-busy`) ; bouton désactivé | « Aucune autre séance ne change de date. » / dernière séance de la période / 1re séance suivante verrouillée (« P1 · 5 verrouillée : aucune séance à décaler ») | 422 ordre (date avant la séance précédente) ; 409 planning modifié ; erreur réseau + « Réessayer » | Toast « … 10 séances suivantes décalées (P1) et 14 séances de la période 2 … Les numéros sont inchangés. » + avertissements (hors période, fin d'année, ordre inversé) ; tableau rechargé |

- **Accessibilité :** aperçu = `<table>` par période avec `<caption>` ; « avant → après », « verrouillée », « sautée » en texte ; résumé en `aria-live="polite"` ; blocages en `role="alert"`.
- **Appareils :** desktop prioritaire ; à < 600 px, liste « P1 · 5 : 14/10 → 04/11 ».
- **Charge cognitive :** 4 clics ; case pré-cochée ; résumé en une phrase par période au-dessus du détail (détail repliable au-delà de 6 lignes, bloc P2 replié par défaut avec son résumé visible).

---

## 9. Exigences non fonctionnelles

| Domaine | Exigence |
|---|---|
| Sécurité | policy existante ; aucune donnée sensible exposée dans l'aperçu |
| Performance | ≤ 28 séances recalculées ; aperçu < 300 ms ; debounce + annulation de la requête précédente |
| Traçabilité | v1 : aucune |
| Compatibilité | FR, fuseau Europe/Brussels |

---

## 10. Impact technique (proposition, à confirmer par l'architecte)

| Couche | Changements prévus | Taille |
|---|---|---|
| DB | aucun | — |
| Back | `CourseSessionService::deplacer` : options `decaler_suivantes`, `dates_forcees`, `empreinte` ; calcul pur « suite hebdomadaire à partir de la semaine N » partagé avec `ClasseSessionGenerator::plan` ; arrêt sur séance verrouillée + avertissements `ordre_inverse` ; cascade P2 + alignement `date_premiere_session` ; avertissement fin d'année ; RG-8 | M |
| API | `POST /sessions/{id}/deplacement/apercu` → `{ seance, periode_1: {lignes: [{id, libelle, date_avant, date_apres, etat: decalee|inchangee|figee|verrouillee, motif?, hors_periode}], dates_sautees, arret?}, periode_2: null | {declencheur, lignes, dates_sautees, arret?}, avertissements[], blocage?: {code, message, seance}, empreinte }` ; `PUT /sessions/{id}` accepte les mêmes options ; réponse + `decalees {p1, p2}`, `arret`, `avertissements[]` ; 403/409/422 | S |
| Front | `SessionAdjustModal` : case, aperçu à 1 ou 2 blocs, lignes verrouillées, libellé du bouton, message de succès ; hook `apercuDeplacement` via `api/client.js` | M |
| Tests | AC-1 à AC-28 (Feature, base `lgit_test`), AC-16/17 (front / recette) | M |

- **Tranche unique** (DB → API → UI → tests) : la valeur n'existe qu'avec l'aperçu.
- **Retour arrière :** option non envoyée = comportement actuel.

---

## 11. Plan de recette

| # | Rôle | Scénario | Résultat attendu | AC |
|---|---|---|---|---|
| R1 | Directeur | P1 · 4 07/10 → 14/10, case cochée, confirmer | 11 séances déplacées, P2 intacte | AC-2/3/16/20 |
| R2 | Directeur | Idem, forcer 11/11 | P1 · 6 le 11/11, P1 · 7 à 14 inchangées | AC-5 |
| R3 | Directeur | P1 · 4 → jeudi 08/10 | « Aucune autre séance ne change » | AC-7 |
| R4 | Directeur | P1 · 4 → 04/11 | bloc P2 dans l'aperçu ; bouton « Déplacer 11 séances (P1) + 14 (P2) » ; P2 · 1 le 10/02 | AC-8/18 |
| R5 | Directeur | P1 · 11 avec heures ; P1 · 7 → 12/11 | P1 · 8–10 décalées, P1 · 11–14 inchangées, ligne verrouillée lisible | AC-10/10c |
| R6 | Directeur | P1 · 8 avec heures ; P1 · 4 → 14/10 | avertissement « ordre » dans l'aperçu, bouton actif, enregistrement OK ; forcer 11/11 supprime l'avertissement | AC-23/24 |
| R7 | Directeur | P1 · 7 → 03/11 | erreur sous la date (ordre) | AC-12 |
| R8 | Professeur | appel API direct | 403 | AC-15 |

---

## 12. Questions ouvertes

### 12.1 Arbitrages du directeur (tranchés le 2026-10-04)

| # | Question | Décision |
|---|---|---|
| Q1 | Mode de calcul | Une séance par semaine, jour de la classe, congés sautés, cases « Forcer » par date (RG-2, RG-7) |
| Q2 | Séance déplacée sur un autre jour | Les suivantes restent au jour de la classe (RG-3) |
| Q3 | Case cochée par défaut | Oui (RG-1) |
| Q4 | Débordement P1 → P2 | **Cascade** : la P2 est recalculée à partir de la semaine qui suit la nouvelle dernière séance de P1 ; sinon intacte (RG-6) |
| Q5 | Séance suivante verrouillée | **Décalage jusqu'à elle** ; elle et les suivantes restent inchangées (RG-5) |
| N-1 | Date recalculée le jour ou après une séance verrouillée | **Autorisé** avec avertissement non bloquant ; pas de refus (RG-5bis) |
| N-2 | Date de démarrage de la P2 en cascade | **Alignée** sur la nouvelle date de P2 · 1 (RG-6) |
| N-3 | Déplacement simple au-delà d'une séance verrouillée | Comportement actuel inchangé, **hors périmètre** (RG-10bis) |
| N-4 | Séance de P2 après la fin de l'année scolaire | **Avertissement non bloquant**, comme « hors période » (RG-10) |

### 12.2 Points encore ouverts

| # | Question | Recommandation UX | Propriétaire | Avant |
|---|---|---|---|---|
| N-5 | Annulées et bis de la plage : figés + avertissement (RG-4) | Retenu sauf avis contraire | Analyste | Dev |

*Aucune question bloquante restante : le passage en « Validé (DoR) » dépend de la validation des mock-ups.*

---

## 13. Validation (Definition of Ready)

| Rôle | Nom | Date | ☐ Validé | Réserves |
|---|---|---|---|---|
| Analyste fonctionnel | | | ☐ | |
| UX/UI | sub-agent UX Expert | 2026-10-04 | ☐ | mock-ups v0.2 à valider |
| Architecte | | | ☐ | |
| Dev back | | | ☐ | |
| Dev front | | | ☐ | |
| Directeur / Sponsor métier | | 2026-10-04 | ☐ | Q1–Q5 et N-1 à N-4 tranchés ; mock-ups à valider |

**DoR :** ☐ Workflow UX + mock-ups validés ☑ §1–7 complets ☑ Matrice des permissions ☑ Carte des statuts (aucun nouveau) ☑ 4 états ☑ AC testables ☑ Impact données ☑ Questions bloquantes résolues

---

## Contrat d'API implémenté (backend, 2026-10-04)

Écart assumé avec la proposition §10 : les blocs de période sont une **liste** `periodes[]` (la séance déplacée peut être en P2), au lieu de `periode_1` / `periode_2`.

- `POST /api/sessions/{id}/deplacement/apercu` `{date, heure_debut?, heure_fin?, dates_forcees?[]}` → `data = { seance {id, libelle, date_avant, date_apres}, periodes: [{classe_periode_id, numero, cascade, declencheur, lignes: [{id, libelle, seance_numero, bis_rang, statut, date_avant, date_apres, etat: deplacee|decalee|inchangee|figee|verrouillee, motif?, hors_periode}], dates_sautees, arret: null|{seance, motif}, decalees}], decalees {p1, p2?}, avertissements: [{code: ordre_inverse|bis_avant|hors_periode|fin_annee|chevauche_periode|conflit_professeur, message}], empreinte }`.
- `PUT /api/sessions/{id}` + `decaler_suivantes: true`, `dates_forcees?[]`, `empreinte?` → `data` (séance) + `replanification {decalees, avertissements, periodes}` ; 409 `code = planning_modifie` si l'empreinte ne correspond plus ; 422 `date` (RG-8) ; 422 `decaler_suivantes` pour un bis.
- RG-8 (ordre avec la séance précédente) n'est appliquée qu'avec `decaler_suivantes` : le déplacement simple garde son comportement actuel (RG-10bis prime).
- Service : `backend/app/Services/SessionReplanificationService.php` ; tests : `backend/tests/Feature/SessionReplanificationApiTest.php` (17 tests).
- Front : `frontend/src/components/classes/SessionAdjustModal.jsx` (case, bouton, conséquence, message de succès, 409 → aperçu recalculé) et `frontend/src/components/classes/DeplacementApercu.jsx` (aperçu avant → après, cases Forcer, lignes verrouillées/figées, avertissements). Écart : le tableau d'aperçu défile au-delà de 320 px au lieu d'être repliable au-delà de 6 lignes.

