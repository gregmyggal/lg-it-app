# CLS-07 — Dupliquer une classe sur un autre jour et/ou un autre horaire — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. **Brouillon — en attente de validation des mock-ups** (arbitrages du directeur du 2026-10-04 intégrés, v0.2 ; aucun code écrit). Workflow et mock-ups : `docs/mockups/CLS-07/README.md`.

| | |
|---|---|
| **ID / Titre** | CLS-07 — Dupliquer une classe existante sur un autre jour et/ou un autre horaire, avec validation des dates et forçage |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — mock-ups validés par le directeur le 2026-10-05 ☑ En dev (backend + frontend livrés, vérifiés dans le navigateur) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* |
| **Date / Version** | 2026-10-04 · v0.2 (arbitrages directeur : démarrage dans la **même semaine que la source, même passé**, avec avertissement non bloquant ; **aucun professeur repris** ; congés forcés de la source signalés sans pré-cocher ; autre année = dates vidées ; doublon = avertissement, à valider avec les maquettes) |
| **Liens** | `CLS-02-classe-deux-periodes.md` (création 1 ou 2 périodes, RG-3), `CLS-05-forcer-seances-sur-congés.md` v0.2 (forçage date par date), `CLS-06-replanifier-seances-suivantes.md` (séances déplacées), `CLS-04-ajout-professeur-session.md`, `API_T2_PROFESSEURS_CLASSES.md` (propagation des assignations), `ClasseCreatePage.jsx`, `ClasseApercu.jsx`, `ClasseDetailPage.jsx`, `ClasseSessionGenerator.php`, `docs/mockups/CLS-07/` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** pour ouvrir un second groupe d'un cours qui existe déjà (ex. Scratch le mercredi est complet, on ouvre le même cours le jeudi), le directeur passe par « Nouvelle classe » et ressaisit tout : année, lieu, horaire, cours de chaque période, dates de démarrage. Il compare de tête le nouveau planning avec celui de la classe d'origine, et il ne sait pas quelles dates de la nouvelle classe tombent sur un congé tant qu'il n'a pas calculé lui-même le jour équivalent.
- **Douleur :** 10 à 15 champs ressaisis, erreurs de cours ou de lieu, date de démarrage à recalculer pour le nouveau jour (un congé du mercredi n'est pas forcément un congé du lundi).
- **Déclencheur (verbatim directeur) :** *« Nouvelle fonctionnalité, dupliquer une classe sur un autre jour et/ou un autre horaire. Crée un workflow permettant de valider les dates et forcer si nécessaire à partir de la duplication d'une classe existante. »*

---

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif métier | Indicateur mesurable | Cible | Comment mesurer |
|---|---|---|---|
| Créer la copie sans ressaisie | clics depuis la fiche de la classe source | ≤ 4 (Dupliquer → jour → [lire l'aperçu] → Créer) | recette chronométrée |
| Valider les dates avant de créer | séances de la copie comparées à la source avant création | 100 % (numéro par numéro, dates sautées, dates forcées, dates passées) | recette visuelle |
| Forcer un congé si nécessaire | forçage dans l'aperçu de la copie | identique à CLS-05 (1 clic par date) | recette |
| Éviter les doublons | doublon probable affiché avant création | 100 % | test back AC-9 |

**Hors périmètre (v1) :**
- Reproduire le planning réel de la source (séances déplacées, annulées, bis, dates forcées, décalages CLS-06) : la copie est **régénérée** (RG-4).
- **Reprendre les professeurs** (arbitrage 2) : la copie est créée **sans professeur**. Ni assignations de classe, ni remplacements ponctuels, ni ajouts sur séance (CLS-04). Les heures encodées et l'historique des changements de cours ne sont pas copiés non plus.
- Dupliquer vers une **autre année scolaire avec transposition des dates** (arbitrage 4 : l'année reste modifiable, sans transposition ni comparaison).
- Dupliquer plusieurs classes en une fois (ex. toute une année).
- Détection des conflits de **salle** (même lieu, même créneau) : seul le doublon de cours est signalé.
- Lier durablement la copie à la source (aucune synchronisation : modifier la source ne modifie pas la copie).

---

## 3. 🔴 Acteurs, rôles et permissions

**Personas :** Directeur/Staff et Admin, sur poste, depuis la fiche d'une classe.

| Action | Admin | Staff (directeur) | Professeur | Élève |
|---|---|---|---|---|
| Voir le bouton « Dupliquer la classe » | ✓ | ✓ | ✗ (page staff, inaccessible) | — |
| Obtenir la proposition de duplication (pré-remplissage) | ✓ | ✓ | ✗ (403) | — |
| Aperçu de la copie (dates, comparaison, doublons) | ✓ | ✓ | ✗ (403) | — |
| Créer la copie | ✓ | ✓ | ✗ (403) | — |

→ **Policy :** `view` sur la classe source **et** `create` sur `Classe` (déjà réservée au staff). Sans jeton : 401. Aucune nouvelle permission.

---

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Technique |
|---|---|---|
| Classe source | Classe existante à partir de laquelle on duplique | `source_classe_id` |
| Copie | Nouvelle classe créée par duplication ; classe ordinaire, indépendante de la source, sans professeur | `classes` (nouvelle ligne), `classes.source_classe_id` (proposé, traçabilité) |
| Dupliquer la classe | Ouvrir l'écran « Nouvelle classe » pré-rempli depuis la source | route `/admin/classes/nouvelle?source={id}` |
| Date transposée | Date de démarrage d'une période de la copie : nouveau jour, dans la même semaine que la date de démarrage de la source, **même si elle est passée** (RG-3) | `date_premiere_session` proposée |
| Séance à date passée | Séance de la copie dont la date est antérieure à aujourd'hui (Europe/Brussels) | `seances_passees` de l'aperçu |
| Comparaison source → copie | Mise en regard, numéro par numéro, de la date de la séance dans la source et de sa date dans la copie | `periodes[].comparaison[]` de l'aperçu |
| Doublon probable | Classe non archivée de la même année, au même jour, dont l'horaire chevauche, et qui a un cours en commun avec la copie | `doublons[]` de l'aperçu |
| Date forcée / sautée | Comme CLS-05 v0.2 : forçage **date par date** dans l'aperçu, non persisté | `periodes[].dates_forcees[]` |

---

## 5. 🔴 Parcours utilisateurs et user stories

### 5.1 User stories

| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | Directeur | dupliquer une classe depuis sa fiche en un clic | ne rien ressaisir | Must |
| US-2 | Directeur | changer le jour et/ou l'horaire et voir les dates de démarrage suivre | ne pas recalculer les dates moi-même | Must |
| US-3 | Directeur | voir le planning de la copie à côté de celui de la source, séance par séance | valider les dates avant de créer | Must |
| US-4 | Directeur | forcer une date sautée de la copie (congé) | garder une séance pendant un congé, comme à la création | Must |
| US-5 | Directeur | être prévenu si la copie démarre dans le passé, et pouvoir choisir un autre début | décider en connaissance de cause | Must |
| US-6 | Directeur | être prévenu si la copie fait doublon avec une classe existante | éviter de créer deux fois le même groupe | Should |
| US-7 | Directeur | aller assigner les professeurs juste après la création | terminer la mise en place de la classe | Should |

### 5.2 Parcours nominal (directeur, « j'ouvre le même cours le jeudi »)

**Jeu de données (tous les exemples) — à vérifier sur la base de recette :**
- Année **2026-2027**, calendrier FWB importé (`backend/database/data/calendrier_fwb_2026-2027.json`). Bornes supposées (proposition FWB) : **P1 du 01/09/2026 au 19/02/2027**, **P2 du 08/03/2027 au 02/07/2027**. Aujourd'hui : **04/10/2026**.
- Congés utiles : automne 19/10–01/11, Commémoration des défunts 02/11, Armistice 11/11, hiver 21/12–03/01, détente 22/02–07/03, Lundi de Pâques 29/03, printemps 26/04–09/05, Lundi de Pentecôte 17/05.
- Mercredis sautés : 21/10, 28/10, 11/11, 23/12, 30/12, 24/02, 03/03, 28/04, 05/05. Jeudis sautés : 22/10, 29/10, 24/12, 31/12, 25/02, 04/03, 29/04, 06/05. Lundis sautés : 19/10, 26/10, 02/11, 21/12, 28/12, 22/02, 01/03, 29/03, 26/04, 03/05, 17/05.
- **Classe source S** : « Scratch — mercredi 14:00–15:30 », lieu « Salle A ». **P1** cours Scratch, démarrage **14/10/2026** : P1 · 1 à 14 = 14/10, 04/11, 18/11, 25/11, 02/12, 09/12, 16/12, 06/01, 13/01, 20/01, 27/01, 03/02, 10/02, 17/02 (sautées : 21/10, 28/10, 11/11, 23/12, 30/12). **P2** cours Python, démarrage **10/03/2027** : P2 · 1 à 14 = 10/03, 17/03, 24/03, 31/03, 07/04, 14/04, 21/04, 12/05, 19/05, 26/05, 02/06, 09/06, 16/06, 23/06 (sautées : 28/04, 05/05).
- Professeurs de S : Alice Prof (`principal`), Bob Prof (`co_enseignant`) — **non repris**.
- Ajustements de S : P1 · 2 déplacée du 04/11 au jeudi 05/11 ; P1 · 6 (09/12) annulée, avec P1 · 6 bis le samedi 12/12.
- **Classe source S2** : identique à S, mais P1 démarrée le **mercredi 16/09/2026** (P1 · 1 à 3 déjà passées).

**Étapes**
1. Fiche de S › en-tête › **« Dupliquer la classe »** (NOUVEAU, à côté de « Voir dans le calendrier »).
2. L'écran **« Nouvelle classe »** s'ouvre en mode duplication (`/admin/classes/nouvelle?source={S}`), titre « Dupliquer une classe », bandeau « Copie de Scratch — mercredi 14:00–15:30… Les professeurs ne sont pas repris. ». Tout est pré-rempli (RG-2) : année, jour (mercredi), lieu, horaire, P1 (Scratch, 14/10/2026), P2 (Python, 10/03/2027). Le focus est placé sur **Jour**.
3. L'aperçu affiche immédiatement l'avertissement **doublon** (même cours, même jour, même horaire que S) : c'est le signal qu'il reste à changer le jour ou l'horaire.
4. Le directeur choisit **jeudi** (et garde ou change l'horaire). Les dates de démarrage se transposent (RG-3) : P1 → **15/10/2026**, P2 → **11/03/2027** ; aide sous chaque date : « Même semaine que la classe source (mercredi 14/10). »
5. L'aperçu se recalcule : pour chaque période, tableau **N° · Source · Copie · Écart**, dates sautées avec case **Forcer** (CLS-05 v0.2), ligne de synthèse « Copie : 14 séances du 15/10 au 11/02 (4 dates sautées) · Source : du 14/10 au 17/02 ». Les écarts notables sont signalés (« 1 semaine plus tôt : l'Armistice ne tombe pas un jeudi »). L'avertissement doublon disparaît.
6. (Optionnel) Le directeur coche **Forcer** sur une date sautée ; l'aperçu se met à jour.
7. **« Créer la copie (28 séances) »** → bandeau de succès (§8) : « Assigner des professeurs » (fiche de la nouvelle classe), « Ouvrir la nouvelle classe », « Revenir à la classe source ».

**Variantes / erreurs**
- **Seul l'horaire change** (mercredi 16:00–17:30) : dates identiques à la source, écart « même jour » partout, pas de doublon (horaires disjoints).
- **Source déjà démarrée** (S2 → jeudi) : P1 pré-remplie au **17/09/2026**, dans le passé. Avertissement non bloquant : « La copie démarre dans le passé : 3 séances seront créées à des dates déjà passées. Vous pouvez choisir une date de démarrage plus tardive. » Le directeur peut garder la date ou en saisir une autre ; aucun blocage (RG-3b).
- **Date transposée hors des bornes de la période** : premier jour de classe dans les bornes (RG-3c).
- **Source à une seule période** ou P2 annulée : seule la période active est reprise ; « Ajouter la période 2 maintenant » reste disponible, sans transposition ni comparaison (RG-7).
- **Changement d'année** : dates de démarrage vidées, comparaison retirée (RG-8).
- **Source introuvable / supprimée** : erreur + « Créer une classe vide ».
- **Erreur réseau à la création** : bandeau rouge existant, saisie conservée, rien n'est créé.
- **Détour par la gestion des années** (lien « Modifier les dates ») : brouillon conservé, retour en mode duplication (`?source={S}&restaurer=1`).

### 5.3 Critères d'acceptation

Jeu de données : celui du §5.2.

**Pré-remplissage et transposition**

| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| **AC-1** | Fiche de S, utilisateur staff ou admin | affichage | bouton « Dupliquer la classe » dans l'en-tête ; il mène à `/admin/classes/nouvelle?source={S}` ; aussi affiché pour une classe archivée | Front / Recette |
| **AC-2** | S | `GET /classes/{S}/duplication` (sans `jour_semaine`) | 200 ; année 2026-2027, jour 3, 14:00–15:30, lieu « Salle A », périodes `[{P1, Scratch, 2026-10-14}, {P2, Python, 2027-03-10}]` ; **aucun professeur** dans la réponse ; aucune écriture | Feature |
| **AC-3** | S | `GET /classes/{S}/duplication?jour_semaine=4` (jeudi) | P1 `2026-10-15`, P2 `2027-03-11`, `raison = meme_semaine` | Feature |
| **AC-4** | AC-3 | aperçu `POST /classes/apercu` avec `source_classe_id = S`, jeudi 14:00–15:30 | P1 · 1 à 14 = 15/10, 05/11, 12/11, 19/11, 26/11, 03/12, 10/12, 17/12, 07/01, 14/01, 21/01, 28/01, 04/02, 11/02 ; `dates_sautees` P1 = 22/10, 29/10, 24/12, 31/12 ; P2 · 1 à 14 = 11/03, 18/03, 25/03, 01/04, 08/04, 15/04, 22/04, 13/05, 20/05, 27/05, 03/06, 10/06, 17/06, 24/06 ; `dates_sautees` P2 = 29/04, 06/05 ; `seances_passees = 0` | Feature |
| **AC-5** | S, nouveau jour **lundi** | proposition puis aperçu | P1 démarre le 12/10 ; P1 · 1 à 14 = 12/10, 09/11, 16/11, 23/11, 30/11, 07/12, 14/12, 04/01, 11/01, 18/01, 25/01, 01/02, 08/02, 15/02 ; sautées 19/10, 26/10, 02/11, 21/12, 28/12 | Feature |
| **AC-6** | S2 (P1 démarrée le 16/09/2026) ; aujourd'hui 04/10/2026 | proposition `jour_semaine=4` | P1 proposée au **17/09/2026** (même semaine, passée), `raison = meme_semaine` ; aperçu P1 = 17/09, 24/09, 01/10, 08/10, 15/10, 05/11, 12/11, 19/11, 26/11, 03/12, 10/12, 17/12, 07/01, 14/01 ; `seances_passees = 3` (17/09, 24/09, 01/10) ; avertissement non bloquant `code = demarrage_passe` ; bouton de création **actif** | Feature + Front |
| **AC-6b** | AC-6 | le directeur saisit le **08/10/2026** comme démarrage P1 | la date n'est plus re-proposée au changement de jour ; aperçu P1 = 08/10, 15/10, 05/11, 12/11, 19/11, 26/11, 03/12, 10/12, 17/12, 07/01, 14/01, 21/01, 28/01, 04/02 ; `seances_passees = 0`, avertissement retiré | Feature + Front |
| **AC-6c** | AC-6, date conservée | `POST /classes` | 201 ; 28 séances `planifiee` dont 3 à des dates passées (comportement de création inchangé, aucun blocage) | Feature |
| **AC-7** | Année de test dont la P2 débute le mardi 09/03/2027 ; S démarre la P2 le mercredi 10/03 | proposition `jour_semaine=1` (lundi) | lundi de la même semaine (08/03) hors bornes → P2 proposée au **15/03/2027**, `raison = borne_periode` | Feature |
| **AC-8** | S | proposition et aperçu avec le même jour (mercredi) et **16:00–17:30** | dates identiques à la source (écart nul partout) ; `doublons = []` | Feature |

**Comparaison, forçage, doublon**

| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| **AC-9** | Saisie inchangée (mercredi 14:00–15:30, mêmes cours) | aperçu | `doublons = [{classe_id: S, libelle: "Scratch — mercredi 14:00–15:30", cours_communs: ["Scratch", "Python"]}]` ; avertissement **non bloquant** ; bouton « Créer quand même (28 séances) » | Feature + Front |
| **AC-10** | AC-4 | `POST /classes` `{…, source_classe_id: S}` | 201 ; nouvelle classe jeudi, 28 séances `planifiee` ; **aucune** assignation `professeur_classe` ni ligne `session_professors` ; `source_classe_id = S` ; **S inchangée** (séances, professeurs) | Feature |
| **AC-11** | S a P1 · 2 déplacée au 05/11 et P1 · 6 annulée + P1 · 6 bis | AC-10 | la copie a 14 séances de base par période, aucune annulée, aucun bis ; dans la comparaison, les lignes source P1 · 2 et P1 · 6 portent « déplacée (était 04/11) » / « annulée · bis le 12/12 (non copiés) » | Feature + Front |
| **AC-12** | Source S3 (mercredi) créée en forçant l'Armistice : P1 · 3 le 11/11 | aperçu d'une copie le **mercredi 16:00–17:30** | ligne source P1 · 3 « 11/11 · pendant un congé (Armistice) » ; dans la copie le 11/11 est **sauté**, case Forcer **non cochée**, mention « forcée dans la source » à côté de la case ; copie P1 · 3 = 18/11 | Feature + Front |
| **AC-13** | AC-4 | aperçu avec `dates_forcees` P1 = `['2026-10-22']` | P1 · 2 = 22/10 (forcée) ; P1 · 3 à 14 = 05/11, 12/11, 19/11, 26/11, 03/12, 10/12, 17/12, 07/01, 14/01, 21/01, 28/01, 04/02 ; écart P1 · 14 « 2 semaines plus tôt » | Feature |
| **AC-14** | Source S4 avec la P1 seule | proposition puis création | seule la P1 est pré-remplie ; « Ajouter la période 2 maintenant » décoché ; si on la coche, date à saisir, pas de colonne Source pour la P2 | Feature + Front |
| **AC-15** | Source S5 dont la P2 est `annulee` | proposition | P2 non proposée ; message « La période 2 de la classe source est annulée : elle n'est pas reprise. » | Feature + Front |
| **AC-16** | Formulaire de duplication | l'utilisateur change l'année | dates de démarrage vidées, comparaison retirée, bandeau « Les dates de la source ne sont pas transposées sur une autre année » ; cours, horaire, lieu conservés | Front |
| **AC-17** | Copie jeudi dont la P2 saisie démarre avant la dernière séance de la P1 de la copie | aperçu / création | blocage existant CLS-02 RG-3 (422) ; aucun changement | Feature (existant) |

**Transverses**

| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| **AC-18** | Professeur connecté | `GET /classes/{S}/duplication`, `POST /classes/apercu` ou `POST /classes` avec `source_classe_id` | 403 ; 401 sans jeton | Feature |
| **AC-19** | Source supprimée | `GET /classes/{id}/duplication` | 404 ; écran « Cette classe n'existe plus » + « Créer une classe vide » | Feature + Front |
| **AC-20** | AC-10 réussi | — | bandeau succès : « Classe « Scratch — jeudi 14:00–15:30 » créée à partir de « Scratch — mercredi 14:00–15:30 ». P1 : 14 séances du 15/10 au 11/02 (4 dates sautées) · P2 : 14 séances du 11/03 au 24/06 (2 dates sautées). Aucun professeur n'est encore assigné. » ; actions « Assigner des professeurs » (→ fiche de la nouvelle classe, section Professeurs), « Ouvrir la nouvelle classe », « Revenir à la classe source » | Front / Recette |
| **AC-20b** | AC-6c réussi | — | le bandeau ajoute « dont 3 à des dates déjà passées » au résumé de la P1 | Front |
| **AC-21** | Nouvelle classe créée par duplication | fiche de la copie | sous-titre « Dupliquée de Scratch — mercredi 14:00–15:30 » (lien vers la source si elle existe encore) | Front |

---

## 6. 🔴 Règles métier et cycle de vie

- **RG-1 — Même écran que la création.** La duplication n'est pas un écran parallèle : c'est « Nouvelle classe » pré-rempli. Toutes les règles de création (CLS-02 RG-1 à RG-4, CLS-05) s'appliquent sans exception ; le serveur revalide tout à la création.
- **RG-2 — Ce qui est copié.** Année, jour, horaire, lieu ; pour chaque période **non annulée** de la source : la période, le cours et la date de démarrage (transposée, RG-3). Tout est modifiable avant création.
- **RG-2bis — Ce qui n'est pas copié.** **Professeurs** (assignations de classe, remplacements, ajouts sur séance — arbitrage 2), séances déplacées, annulées, bis, dates forcées, décalages CLS-06, heures encodées, statut, historique des cours. Les **liens** n'ont rien à copier : ils sont partagés par cours (CLS-02 RG-9), la copie les affiche d'office.
- **RG-3 — Transposition des dates de démarrage (arbitrage 1).** Pour chaque période : date = jour choisi dans la **même semaine civile** (lundi → dimanche) que `classe_periodes.date_premiere_session` de la source. Recalculée à chaque changement de jour tant que l'utilisateur n'a pas saisi la date lui-même. Le champ reste modifiable.
  - **RG-3b — Date passée : avertissement, jamais de blocage.** Si la date pré-remplie (ou saisie) donne des séances antérieures à aujourd'hui (Europe/Brussels), l'aperçu porte l'avertissement non bloquant « La copie démarre dans le passé : N séances seront créées à des dates déjà passées. Vous pouvez choisir une date de démarrage plus tardive. » (N = total des deux périodes). La création reste possible.
  - **RG-3c — Bornes :** si la date obtenue sort des bornes de la période, on prend le premier jour de classe dans les bornes.
  - Une date transposée qui tombe sur un congé n'est pas déplacée : le générateur saute la date (dates sautées, case Forcer).
- **RG-4 — Planning régénéré.** La copie est générée comme une création : 14 séances par période, une par semaine, congés sautés, forçage date par date (CLS-05 RG-1). Le planning réel de la source n'est jamais reproduit.
- **RG-5 — Comparaison source → copie.** Pour chaque période copiée, l'aperçu associe la séance n° *n* de la copie à la séance de base n° *n* de la source (date **actuelle** de la source) et donne l'écart (« même jour », « même semaine », « N semaines plus tôt / plus tard »). Signalements côté source : « déplacée », « annulée », « bis (non copié) », « pendant un congé ». Signalement côté copie : « passée » pour une date antérieure à aujourd'hui.
- **RG-5bis — Congés forcés dans la source (arbitrage 3).** Une date sautée de la copie qui correspond (même date) à une séance tenue pendant un congé dans la source est **sautée par défaut** ; sa case Forcer porte la mention « forcée dans la source » et **n'est pas cochée**.
- **RG-6 — Professeurs (arbitrage 2).** La copie est créée **sans professeur**. Le message de succès propose « Assigner des professeurs » (fiche de la nouvelle classe). L'assignation suit ensuite les règles T2 inchangées (voir N-1 pour les séances passées).
- **RG-7 — Périodes de la source.** Seules les périodes non annulées sont reprises. Source à une seule période : la copie a une seule période ; l'autre peut être ajoutée comme à la création, sans transposition ni comparaison.
- **RG-8 — Année scolaire (arbitrage 4).** La copie est pré-remplie dans l'année de la source et l'année reste modifiable. Si l'année de la source est archivée, ou si l'utilisateur choisit une autre année, les dates de démarrage sont **vidées** et la comparaison avec la source est retirée.
- **RG-9 — Doublon probable : avertissement (à valider avec les maquettes).** Classe **non archivée** de la même année, même jour, horaire qui **chevauche**, ayant au moins un cours en commun avec la copie → avertissement non bloquant, lien vers la classe, bouton « Créer quand même (N séances) ».
- **RG-10 — Indépendance.** La copie est une classe ordinaire : aucune synchronisation avec la source. `classes.source_classe_id` (nullable, `ON DELETE SET NULL`) sert uniquement à la traçabilité (AC-21).
- **RG-11 — Atomicité.** Classe, périodes et séances : tout ou rien (transaction de création existante).
- **RG-12 — Permissions.** Admin et staff. Professeur : 403.

**Cycle de vie :** aucun nouveau statut. La copie naît `active`, ses séances `planifiee` (y compris celles à date passée).

---

## 7. 🔴 Données et migration

| Entité / champ | Type | Obligatoire | Contraintes | Exemple |
|---|---|---|---|---|
| `classes.source_classe_id` *(proposé)* | FK `classes.id` | non | nullable, `ON DELETE SET NULL` | 42 |

- **Nouvelles tables :** aucune. **Nouvelle colonne :** `classes.source_classe_id` (à confirmer par l'architecte ; sans elle, AC-21 tombe).
- **Données existantes impactées :** aucune ; la source n'est jamais modifiée.
- **Backfill :** aucun (null pour les classes existantes).
- **Seeders de test :** S (mercredi, P1 + P2, Alice et Bob assignés, P1 · 2 déplacée, P1 · 6 annulée + bis) ; S2 (démarrée le 16/09) ; S3 (Armistice forcé) ; S4 (P1 seule) ; S5 (P2 annulée).

---

## 8. 🔴 Exigences UX/UI

> Workflow : **réutiliser l'écran « Nouvelle classe »** en mode duplication, ouvert depuis un bouton « Dupliquer la classe » de la fiche classe. Mock-ups : `docs/mockups/CLS-07/README.md` · Validé par : *à remplir* ☐

**Pourquoi l'écran de création plutôt qu'un écran dédié**
- Le directeur connaît déjà cet écran : mêmes champs, même aperçu, mêmes cases Forcer (CLS-05), mêmes blocages (CLS-02 RG-3).
- Une seule logique de génération et de validation (backend source de vérité) : aucune règle dupliquée, aucun risque de divergence entre « créer » et « dupliquer ».
- Le formulaire accepte déjà des pré-remplissages (`annee_scolaire_id`, `cours_id`) et un brouillon restauré (`?restaurer=1`) : `?source={id}` en est l'extension naturelle.
- Ajouts limités : bandeau source, colonnes « Source » et « Écart » dans l'aperçu, avertissements « démarrage passé » et « doublon ».

**Écrans concernés**

| Écran | Contenu clé | Actions primaires | Composants réutilisés |
|---|---|---|---|
| Fiche classe (`ClasseDetailPage`) | bouton d'en-tête « Dupliquer la classe » | ouvrir la duplication | `LinkButton` |
| Nouvelle classe, mode duplication (`ClasseCreatePage`, `?source=`) | titre « Dupliquer une classe », bandeau source (professeurs non repris), formulaire pré-rempli, aide de transposition sous chaque date, aperçu avec colonnes Source + Écart + cases Forcer, avertissements démarrage passé et doublon | « Créer la copie (N séances) » / « Créer quand même (N séances) » ; « Annuler » (retour à la source) | `AdminPageHeader`, `Section`, `Banner`, `ClasseApercu` (+ colonnes), `LoadingBlock`, `ErrorBlock`, `EmptyBlock` |
| Liste des classes | inchangée en v1 (bouton par ligne : voir Q-bis) | — | — |

**Les 4 états**

| Écran | Chargement | Vide | Erreur | Succès |
|---|---|---|---|---|
| Duplication | « Préparation de la copie de Scratch — mercredi 14:00–15:30… » (`LoadingBlock`) ; aperçu : squelette « Calcul des dates… » | source sans période active : « Rien à dupliquer : toutes les périodes de cette classe sont annulées. » + « Créer une classe vide » | 404 « Cette classe n'existe plus » ; 403 ; réseau « Réessayer » ; 422 à la création (bandeau existant, saisie conservée) | bandeau succès (AC-20) + toast ; « Assigner des professeurs » / « Ouvrir la nouvelle classe » / « Revenir à la classe source » |

- **Textes exacts :** voir mock-ups.
- **Formulaire :** ordre de tabulation inchangé ; le focus initial est sur **Jour**. Aide sous la date : « Même semaine que la classe source (mercredi 14/10). » ; si passée : « Date passée : voir l'avertissement de l'aperçu. »
- **Appareils :** desktop prioritaire ; < 600 px : colonne Écart masquée, Source sous la date de la copie.
- **Accessibilité :** colonnes avec `<th scope="col">` ; écart et « passée » en texte ; avertissements doublon et démarrage passé en `role="status"`.
- **Charge cognitive :** 4 clics ; tout pré-rempli ; le doublon visible dès l'ouverture indique ce qu'il reste à changer.

---

## 9. Exigences non fonctionnelles

| Domaine | Exigence |
|---|---|
| Sécurité | policies existantes ; aucun tarif exposé dans la proposition ni l'aperçu |
| Performance | aperçu < 400 ms (≤ 28 séances, comparaison et doublons compris) ; debounce existant (400 ms) |
| Traçabilité | `source_classe_id` ; pas d'historique dédié |
| Compatibilité | FR, fuseau Europe/Brussels (« aujourd'hui » de RG-3b) |

---

## 10. Impact technique (proposition, à confirmer par l'architecte)

| Couche | Changements prévus | Taille |
|---|---|---|
| DB | `classes.source_classe_id` nullable | S |
| Back | service de proposition (RG-2, RG-3, RG-7) ; aperçu étendu : `comparaison`, `seances_passees`, `doublons` ; création : `source_classe_id` | M |
| API | `GET /classes/{id}/duplication?jour_semaine=&heure_debut=&heure_fin=` → `{source {id, libelle}, annee_scolaire_id, jour_semaine, heure_debut, heure_fin, lieu, periodes[{periode_id, numero, cours_id, date_premiere_session, raison: meme_semaine|borne_periode}], periodes_non_reprises[{numero, motif}]}` ; `POST /classes/apercu` et `POST /classes` acceptent `source_classe_id?` ; l'aperçu renvoie en plus `periodes[].comparaison[{seance_numero, date_source, etat_source: normale|deplacee|annulee|pendant_conge, bis_source?, date_copie, passee, ecart_jours}]`, `periodes[].dates_sautees[].forcee_dans_source`, `seances_passees`, `avertissements[{code: demarrage_passe|doublon, message}]`, `doublons[]` ; 403/404/422 | S |
| Front | `ClasseDetailPage` : bouton ; `ClasseCreatePage` : mode `?source=`, re-proposition des dates au changement de jour (sauf date saisie à la main), avertissements, brouillon incluant `source`, bandeau succès avec « Assigner des professeurs » ; `ClasseApercu` : colonnes Source/Écart, « passée », mention « forcée dans la source » ; hooks via `api/client.js` | M |
| Tests | AC-1 à AC-21 (Feature sur `lgit_test`, front/recette) | M |

- **Tranche unique** (DB → API → UI → tests).
- **Retour arrière :** sans `source`, l'écran de création est inchangé.

---

## 11. Plan de recette

| # | Rôle | Scénario | Résultat attendu | AC |
|---|---|---|---|---|
| R1 | Directeur | Fiche S › Dupliquer › jeudi › Créer | 28 séances (AC-4), aucun professeur, bandeau AC-20 ; « Assigner des professeurs » ouvre la fiche | AC-1/3/4/10/20 |
| R2 | Directeur | Idem, forcer le 22/10 | P1 · 2 le 22/10, fin P1 le 04/02 | AC-13 |
| R3 | Directeur | Dupliquer sans rien changer | avertissement doublon, « Créer quand même » | AC-9 |
| R4 | Directeur | Dupliquer S2 (démarrée) sur jeudi | P1 au 17/09, avertissement « 3 séances … déjà passées », création possible ; puis saisir 08/10 → avertissement retiré | AC-6/6b/6c |
| R5 | Directeur | Comparer avec S (déplacée, annulée + bis) | lignes source signalées, copie standard | AC-11 |
| R6 | Directeur | Copie de S3 le mercredi 16:00 | 11/11 sauté, « forcée dans la source », case non cochée | AC-12 |
| R7 | Professeur | appel API direct | 403 | AC-18 |

---

## 12. Questions ouvertes

### 12.1 Arbitrages du directeur (tranchés le 2026-10-04)

| # | Question | Décision |
|---|---|---|
| A1 | Date de démarrage quand la source a déjà démarré | **Même semaine que la source, même passée** ; champ modifiable ; avertissement non bloquant avec le nombre de séances passées (RG-3, RG-3b) |
| A2 | Reprise des professeurs | **Aucun professeur repris** ; « Assigner des professeurs » dans le message de succès (RG-6) |
| A3 | Séances de la source tenues pendant un congé | **Date équivalente sautée par défaut**, mention « forcée dans la source », case non cochée (RG-5bis) |
| A4 | Autre année scolaire | **Année modifiable** ; si elle change, dates vidées et comparaison retirée (RG-8) |

### 12.2 Points encore ouverts

| # | Question | Recommandation UX | Propriétaire | Avant |
|---|---|---|---|---|
| Q3 | Doublon probable : avertir ou refuser ? (non arbitré) | **Avertissement non bloquant**, bouton « Créer quand même » (RG-9) — **à valider avec les maquettes** | Directeur | DoR |
| N-1 | Copie démarrée dans le passé puis professeurs assignés depuis la fiche : la propagation T2 n'assigne que les **séances à venir** ; les séances passées de la copie restent **sans professeur** (encodage d'heures impossible sans ajout séance par séance via CLS-04) | Étendre la propagation, pour cette classe, aux **séances passées sans aucun professeur ni heure encodée** ; à défaut, le signaler dans le message de succès (« 3 séances passées : ajoutez le professeur séance par séance ») | Directeur + Architecte | DoR |
| Q-bis | Bouton « Dupliquer » aussi sur chaque ligne de la liste des classes ? | **Non en v1** : la fiche est le point d'entrée naturel | Analyste | Dev |
| Q-ter | Colonne `classes.source_classe_id` (mention « Dupliquée de… ») ? | **Oui** (coût faible) | Architecte | Dev |

*Q3 et N-1 bloquent le passage en « Validé (DoR) ».*

---

## 13. Validation (Definition of Ready)

| Rôle | Nom | Date | ☐ Validé | Réserves |
|---|---|---|---|---|
| Analyste fonctionnel | | | ☐ | |
| UX/UI | sub-agent UX Expert | 2026-10-04 | ☐ | mock-ups v0.2 à valider |
| Architecte | | | ☐ | N-1, Q-ter |
| Dev back | | | ☐ | |
| Dev front | | | ☐ | |
| Directeur / Sponsor métier | | 2026-10-04 | ☐ | A1–A4 tranchés ; Q3 et N-1 à valider avec les mock-ups |

**DoR :** ☐ Workflow UX + mock-ups validés ☑ §1–7 complets ☑ Matrice des permissions ☑ Carte des statuts (aucun nouveau) ☑ 4 états ☑ AC testables ☑ Impact données ☐ Questions bloquantes résolues

---

## Décisions finales et contrat d'API implémenté (backend, 2026-10-05)

- **Q3 doublon :** avertissement non bloquant + « Créer quand même » (validé).
- **N-1 assignation étendue — portée : TOUTES les classes** (arbitrage du directeur). Sans date de début explicite, une assignation commence à la première séance passée **orpheline** de la classe (aucun professeur, aucune heure encodée, non annulée) et s'y propage, en plus des séances à venir. Avec une date de début explicite, la règle T2 est inchangée. Les séances passées qui ont un professeur ou des heures ne sont jamais touchées. Le test T2 « ne propage qu'aux sessions à venir » est remplacé par « propage aussi aux séances passées orphelines ».
- **Q-ter :** colonne `classes.source_classe_id` créée (nullable, `ON DELETE SET NULL`) ; `ClasseResource.source = {id, libelle} | null`.

**API**
- `GET /api/classes/{id}/duplication?jour_semaine=&heure_debut=&heure_fin=` → `data = { source {id, libelle}, annee_scolaire_id (null si année archivée), annee_source_archivee, jour_semaine, heure_debut, heure_fin, lieu, periodes [{periode_id, numero, cours_id, cours, date_premiere_session (null si année archivée), raison: meme_semaine|borne_periode}], periodes_non_reprises [{numero, motif}] }` ; aucun professeur ; 403 professeur, 404 source inexistante.
- `POST /api/classes/apercu` avec `source_classe_id` → l'aperçu habituel + `periodes[].comparaison [{seance_numero, date_source, etat_source: normale|deplacee|annulee|pendant_conge|null, date_source_prevue, conge_source, bis_source, date_copie, passee, ecart_jours}]` (seulement si la copie est dans l'année de la source), `periodes[].dates_sautees[].forcee_dans_source`, `seances_passees`, `avertissements [{code: demarrage_passe|doublon, message}]`, `doublons [{classe_id, libelle, cours_communs}]`. Sans `source_classe_id`, l'aperçu est inchangé.
- `POST /api/classes` accepte `source_classe_id` (enregistré, aucune autre différence).
- « Déplacée » = date actuelle de la source différente de sa date de génération standard (y compris après un décalage CLS-06).
- Service : `backend/app/Services/ClasseDuplicationService.php` ; tests : `backend/tests/Feature/ClasseDuplicationApiTest.php` (12 tests) et `ClasseProfesseurAssignmentServiceTest` (3 tests N-1).
- **Ajustements en implémentation (2026-10-05) :**
  - La transposition part de la date **actuelle de la séance 1** de la période source (non annulée), et non de `date_premiere_session`, qui n'est pas mise à jour quand la séance 1 est déplacée (constat en recette : sinon la copie démarrait une semaine avant la vraie première séance). Repli : `date_premiere_session`.
  - Libellés de classe côté serveur au format du front (« mercredi 14h–15h30 »).
  - Textes d'assignation mis à jour (N-1) : « N sessions seront assignées », « passées non modifiées (elles ont déjà un professeur ou des heures) ».
  - Front : `ClasseCreatePage` (mode `?source=`), `ClasseApercu` (colonnes Source / Écart, « passée », « forcée dans la source »), `ClasseDetailPage` (bouton, « Dupliquée de… », ancre `#professeurs`). Écart : la P2 n'est pas repliée par défaut dans l'aperçu.

