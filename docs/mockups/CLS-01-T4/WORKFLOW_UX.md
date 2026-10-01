# CLS-01 / T4 — Workflow UX : liens du cours (généraux et par séance), historique, restauration

| | |
|---|---|
| **Auteur** | UX Expert (sub-agent) |
| **Source de vérité métier** | `docs/requirements/CLS-01-modele-classes.md` : RG-6, RG-10, RG-12, AC-4, AC-5, AC-6, AC-16, AC-17, AC-19, AC-23, Q5, Q10, Q17 ; décisions de la direction rappelées en §0 |
| **Mock-ups** | `index.html` (sommaire) + `01` à `04` dans ce dossier ; HTML statiques autonomes, sélecteur d'états (Chargement / Vide / Erreur / Données / Confirmation) et de rôle en haut de chaque page |
| **Base reprise** | `docs/mockups/CLS-01/07-liens-cours-historique.html` (validé au niveau T1) : onglets « Séance 1 … 14 », portée à la création, bandeau de portée, panneau d'historique. T4 l'**étend** (écarts en §9) |
| **Statut** | **Proposition à valider avant tout développement.** Aucune case de validation n'est remplie (§10) |
| **Périmètre** | Aucun code applicatif modifié. Seuls les fichiers de `docs/mockups/CLS-01-T4/` sont créés |

> Données fictives : cours **« Scratch Junior »**, classes **mercredi 14h–17h** et **samedi 9h–12h**, 2026-2027 ; professeurs Alice Dupont, Bob Leroy, Carol Simon ; directeur Marc Delvaux ; date simulée **jeudi 03/12/2026**. Séances du mercredi : 4 = 18/11, 5 = 25/11 (Carol remplace Alice), 6 = 02/12 **annulée**, **6 bis** = 16/12 (15ᵉ session de la classe), 7 = 06/01/2027. Liens fictifs sous `ecole.example`.

## 0. Principes (décisions de la direction, déjà actées)

1. Les liens sont définis **au niveau du cours** (catalogue) et **partagés par toutes les classes** de ce cours (RG-12, RG-6). Jamais de lien « propre à une classe ».
2. Un lien est **général** (toutes les séances) ou rattaché à **une séance** (n° 1 à 14). Le numéro de séance est **fixe** : une session annulée garde son numéro, un **bis** affiche les mêmes liens que sa séance, quelle que soit la position chronologique.
3. **Qui modifie** : admin, directeur, et tout professeur ayant **au moins une assignation active** sur une classe du cours. Les professeurs « adaptent » les liens pour tous.
4. **Historique** (RG-10) : toute création, modification, archivage ou réordonnancement produit une **version** (qui, quand, avant/après), conservée **6 mois** ; tous les professeurs du cours et le staff peuvent **restaurer** ; une suppression est un **archivage annulable** ; **restaurer crée une nouvelle version** (l'historique n'est jamais réécrit). Objectif central : **éviter toute erreur opérationnelle**.
5. Aucune notification sur les changements. Aucune migration de données de CLS-01.

---

## 1. Cartographie « aujourd'hui → demain »

> Établie d'après `App.jsx`, `PortalLayout.jsx`, `MesCoursPage.jsx`, `CoursAdminPage.jsx`, `ClasseLiensManager.jsx`, `SharePage.jsx`, `ShareCodeController.php`, `ClasseLienController.php`, `ClasseLienPolicy.php`, `CoursRessourcePolicy.php`, `Professeur::canAccessCours` et la migration `classe_liens`. On **étend** les écrans existants ; aucun parcours parallèle.

### 1.1 Point de vigilance : deux notions proches (`cours_ressources` et `classe_liens`)

| | **Ressources de cours** (`cours_ressources`) | **Liens de classe** (`classe_liens`) |
|---|---|---|
| Champs | titre, url, `type_ressource` (vidéo / outil / document / jeu), ordre | titre, url, description, `theme` (défaut « outil »), `seance` (**texte libre**), ordre, `actif`, `pinned` ; parent **polymorphe** (cours, stages, formations, anniversaires) |
| Écrans qui l'utilisent | Admin : panneau « Ressources pédagogiques » de `CoursAdminPage` (ajout, suppression). **Professeur : `MesCoursPage` « Ressources de mes cours »** (ajout seulement). **Élève : `SharePage`** (section « Ressources », via `ShareCodeController`) | Admin seulement : `ClasseLiensManager` (titre + url, suppression ; aucun champ séance/thème/épinglage dans l'écran), sous le panneau ressources de `CoursAdminPage`, et pages stages/formations/anniversaires. **Aucun écran professeur, rien côté élève** |
| API | `GET/POST /cours/{id}/ressources`, `PUT/DELETE /ressources/{id}` | `GET/POST /{parent}/{id}/liens`, `PUT/DELETE /liens/{id}` |
| Droits | `CoursRessourcePolicy` : staff, ou professeur lié au cours (`canAccessCours`) | `ClasseLienPolicy` : idem, `cours` seulement pour un professeur |
| Historique, séance, archivage | non | non (le texte `seance` n'est pas un numéro ; suppression physique) |

**Constat.** En pratique, ce sont les **ressources** que professeurs et élèves voient aujourd'hui ; les **liens de classe** sont un reliquat du plugin WordPress (`lgit_lien`) que seul l'admin peut atteindre. Les deux se ressemblent mais **aucun ne porte** séance numérique, historique ni archivage. Un utilisateur qui ouvre le panneau admin voit deux listes de « liens » à la suite : confusion garantie.

**Proposition UX (à valider, Q-T4-1).** **Un seul écran « Liens du cours »**, un seul vocabulaire. Le **modèle `classe_liens`** devient la source de vérité (le requirements l'impose déjà : `classe_liens_historique`, RG-12), restreint au parent **cours** pour T4. Le champ `type_ressource` devient un « Type » facultatif (Document, Vidéo, Outil, Jeu) repris du `theme`. L'écran **Ressources** (professeur, admin, élève) est **retiré** au profit de cet écran ; stages/formations/anniversaires gardent `ClasseLiensManager` en l'état (hors T4).
**Conséquence à décider** : sans migration (§0.5), les ressources existantes disparaîtraient côté élève. Proposition : bandeau « *N anciennes ressources n'ont pas encore été reprises* » avec l'action **« Reprendre comme liens généraux »** (action humaine, versionnée, pas une migration silencieuse) ; voir Q-T4-1.

### 1.2 Où sont les liens, par rôle

| Rôle | Aujourd'hui | Demain (T4) |
|---|---|---|
| **Admin / Directeur** | Liste des cours → « 📚 Ressources » : panneau ressources + `ClasseLiensManager` empilés (`/admin/cours`) | Liste des cours : bouton **« Liens (8) »** → écran **Liens du cours** `/admin/cours/:id/liens` ; **« Historique »** `/admin/cours/:id/liens/historique`. Le panneau empilé disparaît. Dans le détail d'une classe (`ClasseDetailPage`), la colonne « Liens » de chaque session : « 3 généraux + 2 de la séance 6 » avec accès direct |
| **Professeur** | Menu « Ressources de mes cours » (`/mes-ressources`) : cartes de cours avec ressources, ajout seul, aucun changement ni suppression | Menu renommé **« Liens de mes cours »** (même route `/mes-ressources`, redirections conservées) : cartes de **ses** cours (ceux d'une assignation active) avec « 3 généraux · 4 de séance » → **écran Liens du cours** `/mes-ressources/:coursId` ; historique `/mes-ressources/:coursId/historique` |
| **Professeur, depuis une session** | Aucun lien affiché sur les sessions | `Mes classes › Scratch Junior mercredi › sessions` (`MesClasseSessionsPage`) : chaque session a **« Liens : 3 généraux + 2 de la séance 6 »**, déplié en un tap ; bouton « Gérer les liens du cours » |
| **Élève** | `/share/:code` : section « Ressources » (liste plate) | Même page : « **Pour tout le cours** » + « **Par séance** » (accordéon des séances qui ont des liens), lecture seule, sans auteur ni historique |

### 1.3 Menus et routes proposés

| Élément | Changement |
|---|---|
| Menu professeur | « Ressources de mes cours » → **« Liens de mes cours »** (même route) ; `MesClassesPage` : phrase « Besoin des ressources d'un cours ? » → « Besoin des liens d'un cours ? Liens de mes cours » |
| Menu staff | Pas d'entrée nouvelle : accès par **Cours du catalogue › Liens** (réutilise `CoursAdminPage` ; `CoursEditContentPage` reste pour le contenu pédagogique) |
| Écran partagé | **Un seul composant** `Liens du cours` utilisé par `/mes-ressources/:coursId` (rôle professeur) et `/admin/cours/:id/liens` (staff) : seuls le fil d'Ariane et le menu changent. `ClasseLiensManager` est étendu/remplacé pour le parent `cours` |
| Sessions | `MesClasseSessionsPage` et `ClasseDetailPage` : ajout du résumé « N généraux + M de la séance n » (lecture, lien « Ouvrir ») |

### 1.4 Contrat d'API attendu (indicatif, pour l'architecte)

- `GET /cours/{id}/liens` → liens actifs (généraux + séances), triés (portée, `ordre`) + `peut_modifier` (booléen) + `anciennes_ressources` (nombre).
- `POST /cours/{id}/liens`, `PUT /liens/{id}` (avec `version` ou `updated_at` attendu → **409** en cas de conflit), `DELETE /liens/{id}` (= archivage), `PUT /cours/{id}/liens/ordre` (réordonnancement d'une portée).
- `GET /cours/{id}/liens/historique?portee=&auteur=&action=` (6 mois) ; `POST /liens-versions/{id}/restaurer` (**410** si purgée, **409** si le lien a changé depuis et que la confirmation explicite n'est pas passée) ; `POST /liens-versions/{id}/annuler-suppression`.
- Accès : **403** pour un professeur sans assignation active (AC-5, AC-6). Aujourd'hui `Professeur::canAccessCours` teste la **relation pivot cours/professeur**, pas l'assignation **active** d'une classe : écart à corriger (§9).
- `GET /share/{code}` : ajouter `liens` (généraux + par séance) à la place de `ressources`.

---

## 2. Parcours (nombre de clics à partir du menu)

> Convention : un **clic** = un clic ou un tap ; la saisie de texte n'est pas comptée. « Menu » = « Liens de mes cours » (professeur) ou « Cours du catalogue › Liens » (staff). Un professeur qui n'a qu'**un seul cours** y arrive directement (la liste de cours est sautée).

### 2.1 Professeur (Alice, classe active de Scratch Junior) — mock-ups 01, 02

| Parcours | Étapes | Clics |
|---|---|---|
| **Ajouter un lien général** | Menu › (cours) › « Ajouter un lien général » › saisir titre + adresse › « Ajouter le lien » | **3** (4 si plusieurs cours) |
| **Ajouter un lien à la séance 6** | Menu › onglet « Séance 6 » › « Ajouter un lien à cette séance » (portée **pré-remplie**) › « Ajouter le lien » | **4** |
| **Modifier un lien** | Menu › (onglet) › « Modifier » › corriger › « Enregistrer les modifications » | **3 à 4** |
| **Changer la portée** d'un lien (séance 8 → 9) | « Modifier » › choisir la séance › « Enregistrer » ; journalisé « Changement de portée » | **3 à 4** |
| **Réordonner** | **1 clic par cran** (↑ / ↓) ou **1 glisser-déposer** ; clavier : Alt+↑ / Alt+↓ sur la poignée ; toast « Ordre modifié… » avec **Annuler** | **1** |
| **Archiver un lien** (supprimer) | « Archiver » › confirmation qui énonce la conséquence › « Archiver le lien » | **2** |
| **Annuler tout de suite** | Toast « Lien … archivé. » › **Annuler** (10 secondes ; la même action existe dans l'historique) | **1** |
| **Voir l'historique** | « Historique » (en-tête de l'écran) | **1** |
| **Restaurer une version** | « Historique » › « Restaurer cette version » › confirmation (avant/après, conséquence) › « Restaurer cette version » | **3** |
| **Annuler une suppression ancienne** | « Historique » › filtre Action = Archivage (facultatif) › « Annuler la suppression » › confirmer | **3** (4 avec filtre) |
| **Ouvrir un lien** | Clic sur l'adresse du lien (nouvel onglet, `rel="noopener"`) | **1** |

### 2.2 Directeur / Admin — mock-up 01 (rôle « Directeur »), 02

- **Mêmes actions** que le professeur, **sans condition d'assignation**, avec en plus : accès à **tous** les cours depuis « Cours du catalogue », colonne « Liens (n) » dans la liste des cours, et **vue d'ensemble** : l'écran montre les compteurs par séance (onglets) et les séances **sans lien** ; la présence de séances vides est donc repérable d'un coup d'œil (1 clic par onglet).
- Dans le détail d'une classe : « 3 généraux + 2 de la séance 6 » par session, avec lien « Ouvrir les liens du cours » (**2 clics** : classe › lien).
- L'historique du directeur affiche l'auteur avec la mention « (direction) » ; il peut restaurer toute version de moins de 6 mois (**3 clics**).

### 2.3 Professeur depuis une session — mock-up 03 (mobile 375 px)

| Besoin | Étapes | Clics |
|---|---|---|
| Voir les liens d'une séance | Mes classes › classe › session › « Liens : 3 généraux + 2 de la séance 6 » (dépliage) | **3** (la prochaine session est **dépliée par défaut**) |
| Ouvrir un lien (accès direct) | Un tap sur la ligne (cible ≥ 48 px, icône ↗, nouvel onglet) | **+1** |
| Corriger un lien trouvé périmé en classe | « Gérer les liens du cours » (1) › Modifier (2) › Enregistrer (3) | **3** |
| Séance sans lien | Message « Aucun lien pour la séance 7. Seuls les liens généraux s'affichent. » + « Ajouter un lien à cette séance » | **1** vers l'écran 01 |

### 2.4 Élève — mock-up 04

Ouvre `/share/:code` (code donné par le professeur) : **0 clic** pour voir les liens généraux ; **1 tap** pour déplier une séance. Lecture seule : ni bouton de modification, ni nom d'auteur, ni historique (Q-T4-6, Q-T4-7).

### 2.5 Cas limites

| Cas | Comportement proposé |
|---|---|
| **Professeur sans classe active du cours** (AC-5) | L'écran n'est pas dans son menu. Accès direct à l'URL → écran « Vous n'enseignez pas ce cours » (403 expliqué) avec « Voir les liens en lecture seule » (lecture autorisée : Q-T4-9). Aucun bouton d'édition ; l'API renvoie **403** à toute écriture |
| **Assignation terminée** (AC-6) | Lecture seule + bandeau « Votre assignation à Scratch Junior s'est terminée le 31/01/2027… ». Historique consultable, **sans** « Restaurer » (texte explicatif sous chaque version) |
| **Plusieurs classes, une seule active** | Droit conservé tant qu'**une** assignation est active (R-T4-2) |
| **Séance annulée** | Elle **garde son numéro et ses liens** (carte « Annulée » dépliable, liens ouvrables) ; la session bis de la même séance affiche les **mêmes** liens |
| **Session bis / 15ᵉ session de la classe** | Libellé « Séance 6 bis · 15ᵉ session de la classe » ; liens = ceux de la séance 6 ; **aucune « séance 15 »** n'est créée |
| **Séance sans lien** | État vide « Aucun lien pour la séance 3 » + explication + « Ajouter un lien à cette séance » |
| **Lien d'une séance > 14 / inexistante** | Impossible à créer (liste 1 à 14). Si une donnée existe : onglet **« Hors programme »**, jamais affichée aux classes, déplaçable ou archivable (Q-T4-4) |
| **Deux professeurs modifient le même lien** | Voir D8 : le second reçoit **409** et choisit explicitement la version à garder |
| **Version purgée entre-temps** | « Cette version a plus de 6 mois… ne peut plus être restaurée » (410) |
| **Lien supprimé puis séance retirée** | Restauration en « Hors programme » avec explication (Q-T4-5) |

---

## 3. Décisions UX justifiées

### D1 — Un seul écran « Liens du cours » (fusion ressources / liens de classe)
- **Décision** : un écran, un vocabulaire (« Lien »). Source de vérité = `classe_liens` (parent cours). Les écrans « Ressources » (professeur, admin, élève) disparaissent ; voir §1.1 et Q-T4-1 pour la reprise de l'existant.
- **Pourquoi** : l'utilisateur ne doit pas deviner dans quelle liste ajouter un lien ; seul `classe_liens` peut porter séance numérique, historique et archivage (RG-10/12).
- **Écarté** : garder `cours_ressources` et lui ajouter séance + historique (deux modèles pour un même concept, `classe_liens` resterait orphelin et le requirements nomme `classe_liens_historique`) ; deux onglets « Ressources » / « Liens de classe » (reproduit la confusion actuelle).

### D2 — Organisation : « Liens généraux » toujours visibles + onglets « Séance 1 … 14 »
- **Décision** (reprend D6 de CLS-01) : bloc **Liens généraux** en haut ; en dessous, **onglets** Séance 1 à 14, chacun avec son **compteur** (gris à 0, bleu sinon). Défilement horizontal des onglets sur mobile (portail : accordéon par session, voir D3). Navigation clavier de la liste d'onglets (`role="tablist"`).
- **Pourquoi** : les liens généraux concernent toutes les séances (toujours sous les yeux) ; 14 séances × quelques liens tiennent mal dans une page unique ; le compteur montre d'un coup d'œil les séances **sans lien**.
- **Écarté** : accordéon de 14 sections (page très longue, repère perdu) ; un tableau unique avec colonne « Portée » (illisible, pas de repère de séance courante, réordonnancement ambigu).

### D3 — Séance courante et aperçu « ce que verra la session »
- **Décision** : « **Séance courante de** [classe] » (liste déroulante, défaut = classe du professeur la plus proche) marque l'onglet par **●** et ouvre cet onglet par défaut. Chaque onglet rappelle : « Une session de la séance 6 (ou sa session bis, même annulée) affichera : 3 liens généraux + 2 de la séance 6 ». Séance courante = **prochaine session non terminée** de la classe (ici : 6 bis le 16/12 pour le mercredi, 6 le 05/12 pour le samedi).
- **Pourquoi** : le professeur modifie « pour la prochaine fois » ; l'aperçu l'empêche de se demander où le lien apparaîtra.
- Côté **session** (mock-up 03) : « Liens : 3 généraux + 2 de la séance 6 » déplié pour la prochaine session, replié ailleurs ; **sans** sélecteur de classe (la classe est celle de la page).

### D4 — La portée est toujours visible, avant et après l'action
- **Décision** : bandeau permanent « **Ces liens sont visibles par toutes les classes de ce cours** (mercredi 14h–17h, samedi 9h–12h) **et par les élèves** via le code de partage. Chaque modification est enregistrée à votre nom et peut être annulée pendant 6 mois. » ; dans la modale de création, un second rappel **dynamique** sous le choix de portée (« Visible sur la séance 6 et ses sessions bis, dans les 2 classes… ») ; les **toasts** répètent « Visible par toutes les classes du cours ». Chaque ligne porte un badge de portée (Général / Séance n).
- **Pourquoi** : l'erreur la plus probable est de croire qu'on modifie « sa » classe.

### D5 — Protection contre l'erreur
1. **Archiver, jamais « supprimer »** : le mot du bouton est « Archiver » ; la confirmation énonce la conséquence (« Le lien disparaît pour les 2 classes du cours et pour les élèves ; il reste dans l'historique 6 mois et pourra être restauré »). Bouton principal rouge « Archiver le lien », secondaire « Conserver le lien » (focus initial sur la sortie sûre : Échap ferme).
2. **Annulation immédiate** : tout changement (ajout, modification, archivage, ordre) affiche un toast **« … Annuler »** (10 s, `aria-live="polite"`) ; l'annulation est elle-même une version.
3. **Pas de confirmation sur les actions réversibles à faible risque** (réordonnancement, modification) : le toast « Annuler » suffit ; la confirmation est réservée à l'archivage et à la restauration (qui change ce que voient les élèves).
4. **Validation** : titre obligatoire ; adresse commençant par http(s):// (422 sinon, message sous le champ) ; doublon d'adresse dans la même portée = avertissement non bloquant (Q-T4-11).
- **Écarté** : double confirmation « tapez SUPPRIMER » (disproportionnée, l'archivage est annulable) ; suppression définitive proposée à l'utilisateur (aucun besoin métier : la purge à 6 mois suffit).

### D6 — Panneau d'historique (mock-up 02)
- **Décision** : écran dédié (route partageable, retour « ← Retour aux liens ») ; sur grand écran il peut s'ouvrir en panneau latéral depuis le bouton « Historique ». Chronologie inverse ; chaque version : **date/heure, auteur, badge d'action** (Création, Modification, Archivage, Réordonnancement, Changement de portée, Restauration), **titre du lien, badge de portée**, **encadré Avant → Après** (barré rouge / souligné vert), ligne « Modifié depuis par … » si un autre changement a suivi, bouton **Restaurer cette version** / **Annuler la suppression** / **Restaurer cet ordre**.
- **Filtres** : Portée (Toutes, Général, Séance 1…14), Auteur, Action ; « Réinitialiser les filtres » ; compteur « 5 versions affichées sur 8 » (`aria-live`).
- **Avant/après** : on montre **uniquement les champs modifiés** (adresse, titre, description, type, portée) ; pas de bloc technique JSON.
- **Réordonnancement** (Q-T4-8) : une version par **lot de déplacements d'une même portée** dans un intervalle court (5 min, même auteur), libellée « Liens généraux : ordre modifié », avec la liste ordonnée avant/après (les titres déplacés sont mis en évidence), jamais un diff champ par champ.
- **Limite de 6 mois** : bandeau « Les plus anciennes versions expirent à partir du 06/12/2026 » ; badge « Expire dans 3 jours » sur les versions concernées ; après purge, message « Cette version a plus de 6 mois : elle a été supprimée et ne peut plus être restaurée » (410).
- **Restaurer** : confirmation montrant l'**état qui sera rétabli** (même encadré avant/après) + conséquence (« changera pour les 2 classes… ; une nouvelle version « Restauration » est ajoutée ») ; la restauration apparaît aussitôt en tête sous forme d’une entrée « Restauration ».
- **Écarté** : historique replié dans la modale d'édition d'un lien (on ne retrouve pas un lien archivé) ; restauration sans confirmation (change ce que voient les élèves).

### D7 — Restauration quand un autre professeur a modifié depuis (Q-T4-3)
Avertissement **dans la confirmation** (« Attention : modifié depuis par Alice Dupont (05/10/2026). Restaurer remplacera la version actuelle ; elle restera dans l'historique ») ; **pas de blocage** (l'historique n'est jamais perdu et la restauration est annulable).

### D8 — Conflits d'édition (deux professeurs sur le même lien)
- **Décision** : verrouillage **optimiste**. À l'enregistrement, si le lien a changé depuis l'ouverture de la modale, le serveur répond **409**. Le second professeur voit : « **Ce lien vient d'être modifié par Bob Leroy** — rien n'a été écrasé » avec **deux versions côte à côte** (Bob enregistrée / la sienne non enregistrée) et le choix **« Reprendre la version de Bob »** (secondaire) ou **« Enregistrer ma version »** (principal, crée une version : celle de Bob reste restaurable).
- **Écarté** : dernier qui écrit gagne sans avertissement (c'est exactement l'erreur opérationnelle à éviter) ; verrou bloquant « lien en cours de modification » (verrous orphelins, pas de temps réel disponible) ; fusion champ par champ automatique (sémantique floue pour une adresse).

### D9 — Réordonnancement : glisser-déposer + alternative clavier
**Poignée ⠿** (glisser-déposer à la souris/au toucher) **et** boutons **↑ / ↓** explicites sur chaque ligne (toujours présents : seul moyen fiable au clavier, sur mobile et pour les lecteurs d'écran), + Alt+↑ / Alt+↓ sur la poignée. Chaque déplacement est annoncé : « « Quiz » déplacé en position 1 sur 3 » (`aria-live`). Le déplacement reste **dans une portée** (on change de portée par « Modifier »).

### D10 — Professeur sans droit : lecture seule expliquée plutôt que cachée
Les actions d'édition sont **masquées** (jamais grisées sans explication) et **remplacées par un bandeau** qui dit pourquoi et à qui s'adresser (direction). La lecture reste possible : un professeur a besoin des liens d'un cours même sans l'enseigner (Q-T4-9).

### D11 — Portail professeur mobile (375 px, priorité)
Une colonne ; session = carte avec bouton pleine largeur « Liens : 3 généraux + 2 de la séance 6 » (48 px), déplié sur place ; lien = ligne de 48 px minimum avec icône de type, titre, adresse courte, ↗ ; édition par « Gérer les liens du cours » (écran 01 en une colonne : l'édition fine se fait de préférence sur ordinateur, mais ajout / modification / archivage restent possibles au tap).

---

## 4. Règles proposées (à valider)

| # | Règle |
|---|---|
| **R-T4-1** | Les liens appartiennent au **cours** ; une classe n'a **aucun lien propre**. Toute modification est visible par **toutes les classes** du cours et par les élèves (bandeau + rappel dans la modale + toasts). |
| **R-T4-2** | **Peut modifier / restaurer** : admin, directeur, ou professeur avec **au moins une assignation active** à une classe du cours (RG-6). Sinon **403** (AC-5, AC-6) ; la lecture reste possible. |
| **R-T4-3** | Un lien a : **titre** (obligatoire, 255), **adresse** (obligatoire, http/https), **description** (facultative, 255), **type** (Document / Vidéo / Outil / Jeu, facultatif), **portée** (Général ou séance 1 à 14), **ordre** dans sa portée. Les champs `pinned` et `actif` ne sont pas exposés (Q-T4-2). |
| **R-T4-4** | Une session (et son **bis**, même **annulée**) affiche : liens généraux + liens de **son numéro de séance** (fixe). Un lien ne dépend jamais de la date ni de la position chronologique. |
| **R-T4-5** | Création, modification, **changement de portée**, archivage, réordonnancement et restauration produisent **chacun une version** (auteur, date, avant/après). Un lot de déplacements (même auteur, même portée, < 5 min) = **une** version. |
| **R-T4-6** | « Supprimer » = **archiver** (soft delete). Un lien archivé n'est vu **nulle part** (ni classes, ni élèves) mais est restaurable 6 mois. Aucune suppression physique par l'utilisateur. |
| **R-T4-7** | **Restaurer** (version, ordre ou suppression) = confirmation obligatoire + **nouvelle version** « Restauration » ; l'historique n'est jamais réécrit ; la restauration est annulable (toast, ou nouvelle restauration). |
| **R-T4-8** | Versions **conservées 6 mois** puis purgées (tâche quotidienne). Une version purgée n'est plus proposée ; tenter de la restaurer → **410** expliqué. |
| **R-T4-9** | **Conflit** : modification basée sur une version dépassée → **409** + choix explicite (reprendre / enregistrer la sienne). Jamais d'écrasement silencieux. |
| **R-T4-10** | Le réordonnancement ne change l'ordre que **dans une portée**. Nouveau lien = **en dernière position** de sa portée. |
| **R-T4-11** | Les **élèves** (code de partage) voient les liens généraux et par séance, **lecture seule**, sans auteur, historique ni lien archivé. |
| **R-T4-12** | Aucune notification de changement (décision direction). La seule trace visible est l'historique et la mention « Modifié par … · date » sur chaque ligne (côté professeurs et staff). |

---

## 5. Les 4 états par écran (textes exacts)

Légende : **Ch.** chargement · **Vide** · **Err.** erreur · **Succ.** succès/confirmation (toast `role="status"` `aria-live="polite"`).

### 01 — Liens du cours (professeur, directeur)
| État | Texte |
|---|---|
| Ch. | squelette de 4 lignes ; lecteur d'écran : « Chargement des liens du cours… » |
| Vide (cours sans lien) | Titre « **Aucun lien pour Scratch Junior** » ; « Ajoutez un premier lien général (valable pour toutes les séances) ou un lien propre à une séance. Il sera visible par les deux classes du cours et par les élèves. » ; bouton « Ajouter le premier lien » |
| Vide (séance) | « **Aucun lien pour la séance 3** » ; « Seuls les 3 liens généraux s'affichent pour cette séance. Un lien ajouté ici sera visible par toutes les classes du cours. » ; « Ajouter un lien à cette séance » |
| Vide (généraux) | « Aucun lien général » ; « Un lien général s'affiche sur toutes les séances de toutes les classes. » |
| Err. (`role="alert"`) | « **Impossible de charger les liens du cours Scratch Junior.** Vérifiez votre connexion puis réessayez. Vos liens n'ont pas été modifiés. » + « Réessayer » |
| Succ. | « Lien « Exercices semaine 6 — boucles » ajouté. Visible par toutes les classes du cours. » · « Lien modifié : « … ». Visible par toutes les classes du cours. » · « Lien « … » archivé. Il reste restaurable pendant 6 mois. » + **Annuler** · « Ordre modifié : « … » est en position 2. » + **Annuler** · après annulation : « Action annulée. Une nouvelle version a été ajoutée à l'historique. » |
| Erreurs de formulaire (422) | « Le titre est obligatoire. » · « Adresse invalide : elle doit commencer par https:// ou http://. » |
| 403 écriture | « Vous n'avez pas le droit de modifier les liens de ce cours : il faut enseigner au moins une classe active de Scratch Junior. » |
| 409 conflit | Titre « Ce lien vient d'être modifié par Bob Leroy » ; « Bob Leroy a enregistré une modification à 10:12, pendant que vous éditiez « … ». Rien n'a été écrasé : choisissez la version à conserver. » ; boutons « Reprendre la version de Bob » / « Enregistrer ma version » ; pied : « Dans les deux cas, une nouvelle version est ajoutée à l'historique : la version de Bob reste restaurable. » |
| Lien déjà archivé par un autre (404/410 à l'enregistrement) | « Ce lien a été archivé par Alice Dupont. Vous pouvez le restaurer depuis l'historique. » + « Ouvrir l'historique » |
| Lecture seule | « **Lecture seule :** votre assignation à Scratch Junior s'est terminée le 31/01/2027. Vous pouvez consulter les liens, mais plus les modifier ni restaurer de version. Contactez la direction pour être assigné à une nouvelle classe. » · « **Non autorisé** » : « Vous n'enseignez pas ce cours » ; « Seuls les professeurs ayant une classe active de Scratch Junior, la direction et l'administration peuvent modifier ses liens… » |
| Confirmation d'archivage | Titre « Archiver ce lien ? » ; « Le lien « X » (séance 6) va être archivé. » ; « Ce que cela change : le lien disparaît pour les 2 classes du cours et pour les élèves ; il reste dans l'historique pendant 6 mois… » ; boutons « Conserver le lien » / « **Archiver le lien** » |

### 02 — Historique des liens
| État | Texte |
|---|---|
| Ch. | « Chargement de l'historique… » |
| Vide | « **Aucune modification dans les 6 derniers mois** » ; « Les créations, modifications, archivages, changements de portée, réordonnancements et restaurations de liens apparaîtront ici. » |
| Vide (filtres) | « Aucune version ne correspond à ces filtres » ; « Modifiez ou réinitialisez les filtres pour revoir toutes les versions des 6 derniers mois. » |
| Err. | « **Impossible de charger l'historique.** Vos liens n'ont pas été modifiés. Réessayez dans un instant. » |
| Succ. | « Version restaurée : « … ». Visible par toutes les classes du cours. » + **Annuler** · « Lien « … » restauré. » + **Annuler** · « Restauration annulée. Une nouvelle version a été ajoutée à l'historique. » |
| Confirmation | « Restaurer cette version ? » / « Annuler la suppression ? » ; « **Conséquence :** le lien « … » changera pour les 2 classes du cours (mercredi, samedi) et pour les élèves ; une nouvelle version « Restauration » est ajoutée à l'historique ; aucune version existante n'est effacée. » |
| Avertissement | « **Attention :** Modifié depuis par Alice Dupont (05/10/2026). Restaurer remplacera la version actuelle ; elle restera dans l'historique. » |
| 403 | « **Accès refusé (403).** L'historique est réservé aux professeurs ayant une classe active de Scratch Junior et à la direction. … » |
| Consultation seule | « Consultation seule : votre assignation à Scratch Junior est terminée. Vous voyez l'historique, mais ne pouvez plus restaurer de version. » |
| 410 | « Cette version a plus de 6 mois : elle a été supprimée et ne peut plus être restaurée. » (toast : « Cette version n'existe plus : l'historique est conservé 6 mois. ») |

### 03 — Portail professeur : session et liens (mobile)
| État | Texte |
|---|---|
| Ch. | squelette de 2 cartes ; « Chargement des sessions et des liens… » |
| Vide | par session : « **Aucun lien pour ce cours.** Ajoutez un premier lien général ou de séance depuis « Gérer les liens du cours ». » |
| Err. | « **Impossible de charger les liens.** Les sessions s'affichent, mais pas leurs ressources. Réessayez. » + « Réessayer » |
| Succ. | « Lien ajouté. Il apparaît sur la séance 6 de toutes les classes du cours. » |
| Séance sans lien | « **Aucun lien pour la séance 7.** Seuls les liens généraux s'affichent. » + « Ajouter un lien à cette séance » (professeur uniquement) |

### 04 — Page élève (code de partage)
| État | Texte |
|---|---|
| Ch. | « Chargement des liens du cours… » |
| Vide | « **Pas encore de lien pour ce cours** » ; « Ton professeur n'a pas encore ajouté de ressource. Reviens un peu plus tard. » |
| Err. | « **Code d'accès invalide ou expiré.** Vérifie le code d'accès donné par ton professeur, puis réessaie. » · « **Ce code d'accès a atteint sa limite d'utilisation.** Demande un nouveau code à ton professeur. » (textes déjà présents dans `SharePage`, tutoiement conservé) |
| Succ. | « Adresse copiée dans le presse-papiers. » (si l'action « Copier » est retenue ; sinon page sans état de succès) |

---

## 6. Accessibilité et mobile

- **Clavier** : tout est atteignable au clavier ; onglets de séances en `role="tablist"` (focus replacé sur l'onglet actif) ; modales avec piège de focus, Échap, retour du focus à l'élément déclencheur (déjà dans la maquette 07) ; réordonnancement au clavier par ↑/↓ visibles (et Alt+↑/↓ sur la poignée).
- **Lecteurs d'écran** : toasts `role="status"` `aria-live="polite"` ; erreurs `role="alert"` ; confirmations `role="alertdialog"` ; zone vive cachée qui annonce « « X » déplacé en position 2 sur 3 » ; compteur de l'historique annoncé ; chaque bouton répété porte le titre du lien (« Monter « Quiz de la séance 6 » », « Ouvrir « … » dans un nouvel onglet »).
- **Couleurs** : jamais seules (badge texte « Général », « Séance 6 », « Annulée », « Archivage » ; avant/après = barré + souligné, pas seulement rouge/vert). Contrastes ≥ 4,5:1 (design system `AdminDesignSystem`). `prefers-reduced-motion` respecté pour les squelettes.
- **Mobile 375 px** (portail professeur prioritaire) : une colonne, cibles ≥ 44–48 px, onglets de séances défilables, boutons principaux pleine largeur ; pas de glisser-déposer obligatoire (boutons ↑/↓) ; toasts pleine largeur en bas, au-dessus de la barre d'actions.
- **Liens externes** : `target="_blank"` + `rel="noopener noreferrer"`, annoncés « dans un nouvel onglet » ; l'adresse complète reste visible (anti-hameçonnage dans un contexte élèves).

---

## 7. Questions ouvertes (chacune avec ma recommandation par défaut)

| # | Question | Recommandation par défaut |
|---|---|---|
| **Q-T4-1** | **Fusion ressources / liens** : un seul écran ? lequel garder ? que faire des `cours_ressources` existantes (visibles aujourd'hui des élèves) sans migration ? | **Un seul écran « Liens du cours »**, source `classe_liens` (cours). Écrans « Ressources » retirés. Reprise **manuelle et versionnée** : bandeau « N anciennes ressources n'ont pas encore été reprises » + « Reprendre comme liens généraux » ; en attendant, `SharePage` continue d'afficher les anciennes ressources sous « Anciennes ressources ». *À trancher : accepter ce bandeau transitoire, ou une commande de copie unique (contraire à « pas de migration »).* |
| **Q-T4-2** | `theme` et `pinned` : conservés ? | `theme` → **« Type »** (Document / Vidéo / Outil / Jeu, facultatif, icône), aligné sur `type_ressource`. **`pinned` retiré de l'UX** (l'ordre suffit : « le plus important en premier ») ; `actif` non exposé (l'archivage le remplace). Colonnes non supprimées en T4. |
| **Q-T4-3** | **Restauration quand un autre professeur a modifié depuis** ? | **Autorisée avec avertissement** dans la confirmation (D7) ; la version actuelle reste dans l'historique ; pas de blocage. |
| **Q-T4-4** | Liens de séance **au-delà de la séance 14** ? | **Impossible à créer** (choix 1 à 14). Si une donnée existe : onglet **« Hors programme »** (jamais affiché aux classes), déplaçable ou archivable. |
| **Q-T4-5** | **Restauration d'un lien dont la séance n'existe plus** (ex. séance 15) ? | Restauration **acceptée** ; le lien apparaît en « Hors programme » avec le message « La séance 15 n'existe plus : ce lien n'est affiché à aucune classe. Choisissez une séance de 1 à 14. » (modale de choix proposée directement après la restauration). |
| **Q-T4-6** | **Visibilité élève de l'historique** ? | **Non** : historique réservé au staff et aux professeurs du cours (R-T4-2). L'élève ne voit que l'état courant. |
| **Q-T4-7** | **Mention de l'auteur dans la vue élève** ? | **Non** (donnée interne, inutile pour l'élève, sensible pour les professeurs). Les professeurs et le staff voient « Modifié par … · date ». |
| **Q-T4-8** | **Avant/après d'un simple réordonnancement** ? | Une version par **lot** (même auteur, même portée, < 5 min) ; affichage de la **liste ordonnée avant / après** de la portée avec le(s) titre(s) déplacé(s) mis en évidence ; pas de diff champ par champ. « Restaurer cet ordre » rétablit la liste « avant ». |
| **Q-T4-9** | **Professeur sans classe du cours** : lecture ? | **Lecture seule autorisée** des liens (utile pour un remplaçant ou une préparation) ; écriture **403** ; historique **refusé** (403) tant qu'il n'a pas d'assignation active ; assignation terminée : lecture + historique consultable, sans restauration. |
| **Q-T4-10** | **Séance courante côté élève** (le code de partage est au niveau cours, pas au niveau classe) ? | **Aucune mise en avant** : « Pour tout le cours » puis séances à liens, la séance courante n’est pas connue (plusieurs classes) : séances repliées, avec compteur. *Mock-up : séance 6 dépliée à titre d'exemple.* |
| **Q-T4-11** | **Doublons** d'adresse dans une même portée ? | **Avertissement non bloquant** (« Cette adresse existe déjà dans la séance 6 »), pas d'interdiction. |
| **Q-T4-12** | **Stages, formations, anniversaires** (parents polymorphes de `classe_liens`) ? | **Hors T4** : `ClasseLiensManager` inchangé pour eux ; à planifier séparément. |
| **Q-T4-13** | `ClasseSetting` (`seance_active`, `access_code`, « portail /classe » du plugin WordPress) : conservé ? | Hors périmètre UX T4, à ne pas toucher ; la « séance active » libre devient inutile pour les liens (séance courante calculée). À décider avec l'architecte. |
| **Q-T4-14** | **Quota** de liens (anti-abus) ? | Pas de limite en T4 ; avertissement visuel au-delà de 20 liens par portée. |

---

## 8. Matrice AC → mock-up

| AC / règle | Comportement vérifié | Mock-up et élément |
|---|---|---|
| **AC-4** (professeur du cours ajoute un lien) | Visible par tous les professeurs/élèves du cours, journalisé | 01 (modale « Ajouter un lien », toast « Visible par toutes les classes du cours »), 04, 02 (version « Création ») |
| **AC-5** (professeur sans classe : 403) | Pas d'édition, explication, lecture seule | 01 (rôle « Professeur sans classe du cours »), 02 (rôle : 403) |
| **AC-6** (assignation terminée : 403 en écriture) | Lecture seule, historique sans restauration | 01 (rôle « Assignation terminée »), 02, 03 (rôle) |
| **AC-16** (historique : qui, quand, avant/après, restaurer, restauration = nouvelle version) | Panneau, filtres, confirmation, entrée « Restauration » en tête | 02 |
| **AC-17** (lien supprimé par erreur, annulable) | « Annuler la suppression » restaure ordre et attributs | 01 (toast Annuler), 02 (Archivage › « Annuler la suppression ») |
| **AC-19** (purge à 6 mois) | Bandeau d'expiration, badge « Expire dans 3 jours », bouton de scénario « Version purgée entre-temps » (410) ; restaurable par tout professeur du cours | 02 |
| **AC-23** (séance 3 / 3 bis : généraux + séance ; création des deux types ; visible par toutes les classes) | Aperçu « 3 généraux + 2 de la séance 6 », bis, annulée, séance vide, 15ᵉ session | 01 (onglets), 03 (séance 6 annulée, 6 bis, séance 7 sans lien) |
| **RG-6** (droit d'éditer) | Matrice des rôles | 01, 02, 03 |
| **RG-10** (versions 6 mois, archivage) | Actions journalisées, restauration confirmée | 01, 02 |
| **RG-12** (liens par séance, numéro fixe) | Onglets 1–14, portée visible, bis | 01, 03, 04 |
| **Q5, Q10, Q17** | Historique versionné, 6 mois, par séance | 01, 02 |
| Critères **proposés** à ajouter au §5.2 de CLS-01 après validation | **AC-28** deux professeurs modifient le même lien : le second reçoit 409 et choisit ; **AC-29** restaurer une version crée une version « Restauration » annulable ; **AC-30** un lien rattaché à une séance inexistante n'est affiché à aucune classe ; **AC-31** l'élève ne voit ni historique, ni auteur, ni lien archivé | 01, 02, 04 |

---

## 9. Écarts et points d'attention

**Écarts avec `docs/mockups/CLS-01/07-liens-cours-historique.html` (validé T1)**
1. 07 prenait « React » ; T4 utilise **Scratch Junior** et deux classes (mercredi/samedi).
2. Ajout d'un **champ Type** (ex-`theme`/`type_ressource`) et d'une **description**, absents de 07 (Q-T4-2).
3. Ajout de l'**archivage** (mot « Archiver » au lieu de « Supprimer »), de l'**annulation immédiate par toast** et d'une confirmation qui énonce la conséquence.
4. Ajout du **conflit d'édition 409** (D8), de la restauration **avec avertissement** (D7) et de la version **purgée** (410).
5. Historique : **écran/route dédié** (`…/historique`) plutôt que seul tiroir ; **filtres Auteur et Action** en plus de la portée ; regroupement du **réordonnancement** (Q-T4-8).
6. **Onglet « Hors programme »** pour les liens de séance inexistante (07 : simple note).
7. **Mobile / portail** : 07 n'avait pas de vue session ; ajout de 03 (liens dépliables par session) et de 04 (élève).
8. 07 laissait la portée « sans classe » comme refus plein écran uniquement ; T4 ajoute la **lecture seule** pour un professeur sans classe (Q-T4-9).

**Points d'attention techniques (à transmettre à l'architecte)**
1. `Professeur::canAccessCours` (pivot professeur/cours) **ne correspond pas** à « assignation **active** sur une classe du cours » (RG-6, AC-5, AC-6) : `ClasseLienPolicy` et `CoursRessourcePolicy` doivent s'appuyer sur `professeur_classes` actives.
2. `classe_liens.seance` est un **texte libre** ; RG-12 impose un **numéro 1 à 14** (`seance_numero`) et l'index `(parent, seance_numero, ordre)`.
3. `ClasseLienController` : aucune validation du droit sur le **parent à l'écriture d'ordre**, `destroy` fait une **suppression physique** (à remplacer par archivage), `update` sans contrôle de version (409). `store` ne positionne pas `ordre`.
4. `ClasseLiensManager` n'expose aujourd'hui ni séance, ni thème, ni description, ni modification : l'écran est à **réécrire** pour le parent cours (les autres parents gardent l'ancien composant).
5. `ShareCodeController` n'expose que `ressources` : le payload doit fournir `liens` (généraux + par séance, sans auteur).
6. Les dates, noms et URL des mock-ups sont fictifs ; la date simulée est le 03/12/2026.

---

## 10. Validation

| Point | Statut |
|---|---|
| Workflow et mock-ups T4 | ☐ À valider |
| Réponses aux questions Q-T4-1 à Q-T4-14 | ☐ À valider |
| Règles R-T4-1 à R-T4-12 | ☐ À valider |
| Critères proposés AC-28 à AC-31 | ☐ À valider |
