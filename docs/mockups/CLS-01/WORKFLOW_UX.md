# CLS-01 — Workflow UX et mock-ups (à valider avant tout développement)

| | |
|---|---|
| **Auteur** | UX Expert (sub-agent) |
| **Source de vérité métier** | `docs/requirements/CLS-01-modele-classes.md` (RG-1..RG-11, AC-1..AC-19) |
| **Mock-ups** | `index.html` (sommaire) + `01` à `08` dans ce dossier — HTML statiques autonomes, sélecteur d'états en haut de chaque page |
| **Statut** | **Révision 3** — intègre les décisions du directeur : liens par séance, **numéros de séance fixes 1..14 sans renumérotation**, rattrapage par **« bis »**, propagation aux sessions à venir, plafond de sessions dépassable, borne de fin de période, **remplacement d'un professeur sans contrainte de timesheet** (CLS-01 RG-2/8/9/12, AC-12, AC-20 à AC-25, Q12 à Q18). **Aucune zone de validation n'est remplie** (voir §9 et la case UX du §13 de CLS-01) |

> Données fictives : année 2026-2027, période 1, date simulée **jeudi 19/11/2026**. Les dates de vacances FWB sont **indicatives** (à remplacer par le fichier officiel). Cours « React » : classes mercredi 14h–17h (Alice principale, Bob co-enseignant, Carol remplaçante sur la séance 5) et samedi 9h–12h (Bob principal). Période 1 : jusqu'au **05/02/2027** (date illustrative). Dans la classe du mercredi, la **séance 12** (20/01/2027) est **annulée** ; elle garde son numéro et une **séance 12 bis** (04/02/2027) peut la remplacer.

---

## 1. Cartographie « aujourd'hui → demain »

> Le mapping des écrans existants est établi d'après les routes (`App.jsx`), le menu (`PortalLayout.jsx`) et le nom/rôle des composants. L'architecte/dev front doit le confirmer fichier par fichier avant découpage des tranches.

### 1.1 Menu (PortalLayout)

| Aujourd'hui | Demain | Rôles | Route proposée |
|---|---|---|---|
| « Mes cours » | **« Mes classes »** (renommé) ; `/mes-cours` redirige vers `/mes-classes` | tous les rôles qui enseignent | `/mes-classes` |
| « Timesheets » | inchangé ; accessible aussi en direct depuis « Mes classes » (pré-rempli par session) | tous | `/timesheets?session={id}` |
| Groupe « Gestion Opérationnelle » (Timesheets, Professeurs & Tarifs) | inchangé | admin, directeur | — |
| *(absent)* | **Nouveau groupe « Scolarité »** : **Classes**, **Calendrier** (existant, route `admin/calendar`, ajouté au menu), **Calendrier scolaire** | admin, directeur | `/admin/classes`, `/admin/calendar`, `/admin/calendrier-scolaire` |
| Groupe « Contenu » : Cours, Stages, Formations, Anniversaires, **Types de cours**, Types de formation | même groupe **sans « Types de cours »** (AC-8) | admin, directeur | — |

Par rôle : **Professeur** = Mes classes + Timesheets (2 entrées, rien d'autre). **Directeur/Staff et Admin** = idem + 3 groupes. Les actions d'admin pur (ex. importer le calendrier FWB) sont visibles mais désactivées/expliquées pour le directeur (mock-up 05).

### 1.2 Écrans existants

| Écran / composant existant | Ce qui change | Mock-up |
|---|---|---|
| `AdminCoursSessionsPage` (`admin/cours/:coursId/sessions`) et modales `SessionAssignmentModal`, `CoursAssignmentModal` | **Remplacés** par la page de détail d'une classe (sessions + professeurs + modales de propagation). L'ancienne route redirige vers la liste des classes filtrée par cours. | 03 |
| *(nouveau)* liste + création de classe | Nouvelles pages `/admin/classes`, `/admin/classes/nouvelle`, `/admin/classes/:id` | 01, 02, 03 |
| `AdminCalendarPage` / `CalendarView` / `SessionCard` | **Étendus** : filtres année / classe / professeur, vacances et fermetures affichées, sessions rattachées à `classe_id`. | 08 |
| `AdminProfesseurDetail` (section « Cours assignés », `ProfesseurCoursCard`) | Section remplacée par **« Classes »** (assignation dans le sens professeur → classe). Sections Informations et Tarifs inchangées. | 04 |
| `ProfesseursAdminPage` (liste) | Inchangée, hors retrait des références aux types de cours | — |
| `CoursAdminPage` + `admin/cours/:id/contenu` (contenu, `ClasseLiensManager`) | Retrait de tout « type de cours ». `ClasseLiensManager` est **refondu** : plus une liste unique mais « Liens généraux » + « Liens par séance (1..N) », portée au choix, historique. | 07 |
| `MesCoursPage` | **Renommée et refondue** en « Mes classes » (mobile-first) | 06 |
| `TimesheetsPage` | Inchangée dans son principe ; accepte un paramètre `session` pour s'ouvrir **pré-remplie** ; saisie indépendante par professeur | 06 (bottom sheet illustrative) |
| `admin/types-cours` | **Supprimée** (route, menu, page) | — |
| *(nouveau)* calendrier scolaire | Page `/admin/calendrier-scolaire` (+ création d'année scolaire en modale) | 05 |
| *(nouveau)* liens du cours côté professeur | Page `/cours/:coursId/liens` accessible depuis « Mes classes » ; visible pour tout professeur du cours, éditable selon assignation active | 07 |

---

## 2. Parcours par rôle et objectifs mesurables

Convention : « clic » = clic ou tap sur un bouton/lien ; les champs à remplir (listes, dates) ne sont pas comptés. Le chemin nominal est celui des mock-ups.

### 2.1 Directeur / Staff

| Parcours | Chemin nominal | Clics | Objectif |
|---|---|---|---|
| **Créer une classe complète** (avec professeur, 14 sessions) | Menu Classes → « Nouvelle classe » → remplir le formulaire unique (cours, jour, horaire, date de 1re session, professeur) → « Créer la classe et générer 14 sessions » | **3** | **≤ 1 min**, 1 formulaire (AC-1, AC-10) |
| **Assigner un professeur depuis la classe** | Page de la classe → « Ajouter un professeur » → choisir professeur/rôle → « Assigner … à N sessions » | **3** (à partir de la page de la classe) ; 5 depuis le menu | **≤ 3 clics** (AC-12) |
| **Assigner depuis la fiche professeur** | Fiche professeur → « Ajouter une classe » → choisir la classe → « Assigner … à N sessions » | **3** | ≤ 3 clics (AC-13) |
| **Remplacer un professeur sur une session** | Page de la classe → « Remplacer un professeur » (ligne de la session) → choisir le remplaçant → « Remplacer X par Y » | **3** | ≤ 3 clics (AC-14) |
| **Déplacer / annuler une session** | « Ajuster » → choisir l'action (+ date ou motif) → bouton de confirmation | **3** | ≤ 3 clics (AC-11, AC-20, AC-22) |
| **Remplacer une session annulée (créer un bis)** | Page de la classe → « Remplacer cette session » → choisir la date (dans la période) → « Créer la session de rattrapage » | **3** | ≤ 3 clics (AC-24) |
| **Ajouter une fermeture d'école** | Calendrier scolaire → « Ajouter une date » → remplir → « Ajouter la date » | **3** | Impact affiché avant validation |
| **Retirer un professeur** | Page de la classe → « Retirer » → « Retirer X de N sessions » | **2 à 3** | Récapitulatif (AC-15) |

### 2.2 Professeur

| Parcours | Chemin nominal | Clics | Objectif |
|---|---|---|---|
| **Encoder mes heures** | Arrivée sur « Mes classes » → « Encoder mes heures » (sur la session à encoder) → « Soumettre les heures » | **2** (3 depuis le menu) | **≤ 3 clics** (US-5, AC-3) |
| **Voir ma prochaine session** | Arrivée sur « Mes classes » | **0** | Toujours en haut de page |
| **Corriger un lien du cours** | Mes classes → « Liens du cours » → « Modifier » → « Enregistrer le lien » | **3** | AC-4 |
| **Ajouter un lien à une séance** | Mes classes → « Voir les liens » (session) → « Ajouter un lien à cette séance » (portée pré-remplie) → « Enregistrer le lien » | **3** | AC-23 |
| **Annuler une erreur sur un lien** | Liens du cours → « Historique » → « Restaurer cette version » → « Restaurer la version » | **3** | AC-16, AC-17 |

Le professeur n'a accès qu'à **ses** classes : il ne voit ni les autres classes, ni les autres professeurs hors co-professeurs de ses classes, ni les boutons d'administration (masqués, pas grisés, car aucune action n'est possible ; voir §7).

### 2.3 Admin

Mêmes parcours que le directeur + : import du calendrier FWB (bouton visible, réservé), archivage d'une classe, suppression refusée expliquée (409). Aucun écran supplémentaire.

---

## 3. Décisions UX justifiées

### D1 — Création de classe : un formulaire, aperçu en direct des 14 dates
- **Décision** : formulaire unique à gauche, **aperçu à droite** qui se recalcule (14 lignes ; dates sautées barrées avec motif « Sautée · Congé d'automne (FWB) »), puis **récapitulatif avant génération** (14 sessions, dates de début/fin, dates sautées, professeurs concernés). Les professeurs sont assignables dans ce même formulaire (facultatif) pour tenir le budget d'une minute.
- **Pourquoi** : le directeur voit le résultat avant de le créer (pas de mauvaise surprise), sans étape intermédiaire.
- **Écarté** : assistant en 3 étapes (plus de clics, contraire à « ≤ 1 min ») ; génération directe sans aperçu (l'impact du calendrier scolaire resterait invisible).
- Un jour saisi incohérent avec la date (ex. jeudi vs mercredi) est bloqué **avant** validation avec un message expliquant quoi corriger.

### D2 — Assignation bidirectionnelle : propagation aux sessions à venir
- **Décision** : mêmes composants et mêmes textes dans les deux sens ; la modale affiche, **avant** confirmation, une carte « Récapitulatif de propagation » qui se met à jour : « **Carol Simon sera assignée à 8 sessions à venir** (séances 6 à 14, hors séance 12 annulée) », « **Les 5 sessions passées ne sont pas modifiées** », sessions annulées ignorées, conflits. Pour une classe **pas encore commencée**, le nombre est 14 (mock-up 02 : « Alice sera assignée aux 14 sessions »). Le bouton de confirmation **répète la conséquence** (« Assigner Carol à 8 sessions à venir »).
- **Pas d'option « inclure les sessions passées »** (retirée suite à Q12 / RG-8 / AC-12). La date de validité proposée est la prochaine session ; le texte d'aide rappelle que les sessions passées ne sont jamais assignées.
- **Conflit d'horaire (RG-4)** : encart rouge, texte explicatif, bouton de confirmation **désactivé** avec le motif lisible (`aria-describedby`), pas de bouton mort silencieux.
- **Écarté** : glisser-déposer (inaccessible au clavier, mobile) ; confirmation sans chiffres (« Confirmer ? ») ; assignation session par session.

### D3 — Remplacement ponctuel sur une session
- **Décision** : bouton « Remplacer un professeur » sur chaque ligne de session, **passée ou à venir** (aucune contrainte liée aux timesheets, RG-9 / Q18 / AC-25) ; la modale nomme la séance (« séance 6, mercredi 25/11/2026 »), le remplacé, le remplaçant et énonce la conséquence : « Carol encode ses propres heures : sa timesheet est indépendante ; les timesheets déjà encodées (celles d'Alice comprises) ne sont pas modifiées ; les autres sessions d'Alice ne changent pas ». La session affiche « ~~Alice~~ → Carol (remplaçante) » ; elle est listée sous « Remplacements ponctuels » pour qu'une re-propagation ne l'écrase pas visiblement. Le bouton n'est jamais désactivé pour cause de timesheet existante.
- **Écarté** : remplacer via « retirer + ajouter » (perd la traçabilité, viole RG-9).

### D4 — Ajustement d'une session : numéros de séance fixes, bis, plafond, borne de période, alerte calendrier
- **Ajuster** : bouton « Ajuster » → une modale à trois choix (Déplacer / Annuler avec motif obligatoire / **Ajouter un bis d'une séance**), avec dates libres proposées (hors vacances/fermetures, **dans la période**) et conséquence énoncée. Sessions **passées ou avec heures** : bouton « 🔒 Ajuster » désactivé avec infobulle et légende (le déplacement/l'annulation d'une session passée reste interdit ; le remplacement d'un professeur, lui, reste possible, voir D3).
- **Numéros de séance fixes (RG-2, AC-20)** : chaque session porte un numéro de **séance 1..14 fixe**, jamais recalculé (aucune renumérotation après annulation, déplacement ou ajout). Une session annulée **garde son numéro** et s'affiche « **Séance 12 — Annulée** » avec la date barrée et le motif visible. Déplacer une séance change sa date, pas son numéro (« Séance 9 déplacée au 04/02/2027. Elle garde son numéro de séance et ses assignations. »).
- **Bis (RG-2, RG-9, AC-21, AC-24)** : « Remplacer cette session » (sur une session annulée) crée une **« Séance 12 bis »** : même numéro de séance que l'annulée, autre date de la période, professeurs propagés (RG-8). La session annulée **reste visible** avec son motif et le lien « Bis : Séance 12 bis · jeu. 04/02/2027 » ; le bis affiche « remplace la séance 12 du 20/01/2027 » et les **mêmes liens** que la séance 12. **Bis du bis** : si la « Séance 12 bis » est à son tour annulée, « Remplacer cette session » crée une « **Séance 12 bis 2** » (puis « bis 3 »…), toujours rattachée à la séance 12 ; ce cas est décrit ici mais **non mocké**.
- **Ajout libre interdit** : une session ajoutée est **toujours** rattachée à l'une des 14 séances. La modale d'ajout de 03 propose « **Ajouter un bis de la séance** [1..14] » + date ; il n'existe plus de « ajouter une session » sans séance.
- **Plafond de sessions dépassable (RG-2, AC-21)** : le total de sessions peut dépasser 14 (jamais le nombre de séances). Créer un bis au-delà demande une confirmation explicite : « **Cette classe passera à 15 sessions.** » + case « Je confirme le dépassement de 14 sessions » (le bouton de création reste désactivé tant qu'elle n'est pas cochée).
- **Borne de fin de période (RG-2, AC-22)** : créer ou déplacer une session après la fin de la période est **refusé (bloquant)** : « **Cette date est après la fin de la période 1** (05/02/2027). » Le bouton de confirmation est désactivé tant que la date est invalide ; s'il n'existe aucune date libre dans la période, la création du bis est bloquée par cette borne. Dans la création (02), si la séance 14 dépasse la fin de période, l'aperçu la marque « ⛔ Après la fin de la période 1 », un encart d'erreur propose la correction (« première session au plus tard le mercredi 07/10/2026 ») et le bouton de création est désactivé.
- **Alerte calendrier ajouté après coup** : quand une entrée est ajoutée au calendrier scolaire **après** la création d'une classe, (a) la modale d'ajout d'entrée affiche l'**impact** avant enregistrement, (b) la classe affiche un bandeau orange + ligne surlignée « ⚠ Date en conflit », (c) le calendrier scolaire affiche le même avertissement. **Pas de déplacement automatique** ; trois actions : Déplacer / Annuler / Maintenir la date.
- **Écarté** : renumérotation des séances (casserait les liens de séance et la lisibilité pour les professeurs) ; ajout libre sans séance ; déplacement automatique (Q9 : pas de notification) ; simple avertissement pour une date hors période (remplacé par un refus bloquant).

### D5 — Calendrier scolaire
- **Décision** : page unique avec (1) **frise de l'année** (mois × jours, lettres V/F/E en plus de la couleur), (2) **tableau des dates** avec type, libellé, **source (badge FWB / École)**, impact sur les classes, Modifier / Supprimer. Ajout/modification en modale avec impact. Modifier une entrée FWB la marque « FWB · modifiée » ; un import ne l'écrase pas (RG-11).
- **Création d'année scolaire** : modale (libellé, 2 périodes avec règle « période 2 après période 1 », case « Importer le calendrier FWB ») — *ajout par rapport à la liste de §8 pour couvrir US-1/AC-18*.
- **Écarté** : calendrier mensuel seul (mauvaise vue d'ensemble d'une année) ; liste seule (pas de vue « où sont les trous »).

### D6 — Liens du cours : généraux + par séance, historique, Annuler/Restaurer
- **Décision (correction du directeur, RG-12 / AC-23)** : la page n'est plus une liste unique. Deux zones : **« Liens généraux »** (valables pour toutes les séances) et **« Liens par séance »** avec une barre d'onglets « Séance 1 … Séance 14 » (chaque onglet porte son nombre de liens ; **● marque la séance courante** de la classe choisie dans « Séance courante de… »). L'onglet courant est ouvert par défaut.
- **Création/modification** : la modale demande la **portée** : « Général » ou « Séance » + numéro (pré-rempli avec l'onglet actif ; « Ajouter un lien à cette séance » pré-sélectionne la séance). Le changement de portée est possible et journalisé.
- **Portée et classes** : bandeau permanent « **Ces liens sont visibles par toutes les classes de ce cours** … **et par les élèves** » ; un lien de séance est visible par toutes les classes du cours, sur la séance de ce numéro et sur ses bis. Dans 03 (colonne « Liens ») et 06 (carte de session), une session affiche « 3 généraux + N de la séance n » (ex. « 3 généraux + 2 de la séance 6 ») avec accès direct.
- **Séance sans lien (état vide)** : « **Aucun lien pour la séance 3** » + explication (seuls les liens généraux s'affichent) + action **« Ajouter un lien à cette séance »**. Une note rappelle qu'un lien rattaché à une séance inexistante (ex. 15) reste enregistré mais n'est affiché nulle part.
- **Numéro de séance fixe** : le lien est rattaché à un **numéro de séance 1..14**, quels que soient la date, la position chronologique ou le statut de la session (annulée, déplacée). Une **séance et son bis affichent les mêmes liens** ; l'onglet « Séance n » l'indique : « S'applique aussi aux sessions bis de la séance n ».
- **Historique** (panneau latéral) : inchangé dans son principe (qui, quand, avant/après, type) avec **la portée affichée dans chaque version** (« Portée : Général » / « Portée : Séance 6 »), un filtre par portée, un type d'action « Changement de portée », « Restaurer cette version » / « Annuler la suppression » (la confirmation rappelle la portée d'origine) ; conservé 6 mois.
- **États spéciaux** : *lecture seule* (AC-6) et *non autorisé* (AC-5) conservés ; suppression = confirmation énonçant la portée (« Ce lien de la séance 6 disparaîtra pour toutes les classes de React… »).
- **Écarté** : un seul tableau avec colonne « Portée » (illisible dès 14 séances, pas de repère de la séance courante) ; liens saisis dans chaque classe (contraire à RG-12 : définis au niveau du cours) ; rattachement à une date (casserait avec le décalage des séances).

### D7 — Portail « Mes classes » mobile-first
- **Décision** : une colonne 375 px ; ordre : **À encoder** (bouton pleine largeur 48 px « Encoder mes heures »), **Prochaine session**, **Mes classes** (rôle indicatif, co-professeurs, « Liens du cours », sessions annulées signalées sans numéro), classes terminées repliées. Co-enseignement : « Vos heures sont indépendantes de celles d'Alice ». Liens : la session à venir affiche « 3 généraux + 2 de la séance 6 ». Remplacement : côté Alice « Remplacée par Carol — vous n'encodez pas cette session » ; côté Carol « Remplaçante » avec son bouton d'encodage.
- **Timesheet** : le bouton ouvre l'écran Timesheets existant **pré-rempli** (session, horaire du créneau) : 1 tap + « Soumettre les heures ».
- **Écarté** : liste de toutes les sessions en page d'accueil (bruit sur mobile) ; encodage directement dans la carte (perte des verrous et statuts existants de la Timesheet).

---

## 4. Les 4 états par écran (textes exacts)

Légende : **Ch.** chargement · **Vide** · **Err.** erreur · **Succ.** succès/confirmation (toast `role="status"` `aria-live="polite"`).

### 01 — Liste des classes
| État | Texte |
|---|---|
| Ch. | squelette de tableau ; lecteur d'écran : « Chargement des classes… » |
| Vide | Titre « Aucune classe pour 2026-2027 » ; « L'année démarre sur un état propre : aucune classe n'a été créée. Choisissez un cours du catalogue, un jour et un créneau : les 14 sessions seront générées en respectant le calendrier scolaire. » ; boutons « ＋ Créer la première classe », « Voir le calendrier scolaire » |
| Err. | « Impossible de charger les classes de 2026-2027. Vérifiez votre connexion puis réessayez. » — bouton « Réessayer » |
| Succ. | « Classe « React — mercredi 14h–17h » créée : 14 sessions générées du 07/10/2026 au 03/02/2027 (4 dates sautées). » |
| Alerte | « 1 classe demande votre attention : « Python — jeudi 17h–20h » n'a aucun professeur. » |

### 02 — Création de classe
| État | Texte |
|---|---|
| Ch. | squelette du formulaire ; « Chargement du formulaire… » |
| Vide (calendrier vide) | « Le calendrier scolaire 2026-2027 est vide. Sans lui, les sessions ne pourront pas sauter les vacances ni les jours fériés. Importez d'abord le calendrier FWB. » — « Configurer le calendrier scolaire » ; bouton de création désactivé ; « Aperçu indisponible » |
| Err. | Champ : « La date du 08/10/2026 est un jeudi. Choisissez un mercredi, ou changez le jour de la classe. » ; bandeau : « La classe n'a pas pu être créée. Aucune session n'a été générée (l'opération a été annulée en entier). Corrigez la date de première session puis réessayez. » |
| Succ. | « Classe « React — mercredi 14h–17h » créée : 14 sessions générées du 07/10/2026 au 03/02/2027. Alice Dupont est assignée aux 14 sessions. » — « Ouvrir la classe », « Créer une autre classe » |
| Blocage période (état « Blocage fin de période ») | Champ : « Cette date est après la fin de la période 1 : la 14e session tomberait le 10/02/2027, or la période 1 se termine le 05/02/2027. » ; encart : « Création bloquée : la 14e session dépasse la période » + « Pour tenir dans la période : première session au plus tard le mercredi 07/10/2026, ou choisissez la période 2. » ; ligne d'aperçu « ⛔ Après la fin de la période 1 (05/02/2027) » ; bouton de création désactivé |
| Boutons | « Annuler », « Créer la classe et générer 14 sessions » |

### 03 — Détail d'une classe
| État | Texte |
|---|---|
| Ch. | squelettes ; « Chargement de la classe… » |
| Vide (brouillon) | « Cette classe est un brouillon : aucune session, aucun professeur » ; « Générez les 14 sessions à partir de la première date pour activer la classe. Vous pourrez ensuite assigner les professeurs. » — « Générer les 14 sessions », « Ajouter un professeur » |
| Err. | « Impossible de charger cette classe. Vérifiez votre connexion puis réessayez. » — « Réessayer » |
| Succ. | « Carol Simon est assignée à 8 sessions à venir de « React — mercredi 14h–17h » (5 passées inchangées). » ; « Bob Leroy est retiré de 8 sessions futures ; 5 sessions conservées. » ; « Séance 6 : Carol Simon remplace Alice Dupont. Les timesheets existantes sont inchangées. » ; « Séance 9 déplacée au 04/02/2027. Elle garde son numéro de séance et ses assignations. » ; « Séance 9 annulée (motif : …). Elle garde son numéro et reste visible avec son motif. » ; « Séance 12 bis créée le 04/02/2027, Alice et Bob assignés. La séance 12 du 20/01 reste visible avec son motif. » |
| Bloquant | « Cette date est après la fin de la période 1 (05/02/2027). Choisissez une date jusqu'au 05/02/2027. » (déplacement, ajout, rattrapage ; bouton désactivé) |
| Plafond | « Cette classe passera à 15 sessions. » + « Je confirme le dépassement de 14 sessions » (création d'un bis ; le bouton reste désactivé tant que la case n'est pas cochée) |
| Session annulée | « Séance 12 — Annulée » (numéro conservé) ; « Motif : Salle indisponible » ; « À remplacer » puis « Remplacée » ; « Bis : Séance 12 bis · jeu. 04/02/2027 » ; le bis : « Séance 12 bis — remplace la séance 12 du 20/01/2027 » |
| Refus 409 | « Impossible de supprimer cette classe » — « Cette classe contient 5 sessions avec des heures encodées (7 timesheets). Une classe avec des heures ne peut jamais être supprimée. » + alternatives « Archiver la classe » / « Annuler des sessions à venir » |
| Boutons | « Ajouter un professeur », « Assigner Carol à 8 sessions à venir », « Retirer Bob de 8 sessions », « Conserver Bob », « Remplacer Alice par Carol », « Déplacer la séance », « Annuler la séance 9 », « Créer le bis », « Remplacer cette session », « Créer la séance 12 bis », « Archiver la classe » |

### 04 — Fiche professeur, section Classes
| État | Texte |
|---|---|
| Ch. | squelette ; « Chargement des classes d'Alice… » |
| Vide | « Alice n'est assignée à aucune classe en 2026-2027 » ; « Ajoutez une classe pour qu'Alice voie ses sessions dans « Mes classes » et puisse encoder ses heures. » — « Ajouter une classe » |
| Err. | « Impossible de charger les classes d'Alice Dupont. Réessayez dans un instant. » |
| Succ. | « Alice Dupont est assignée à « React — samedi 9h–12h » : 9 sessions à venir concernées (5 passées inchangées). » |
| Conflit | « Conflit d'horaire — assignation impossible : Alice est déjà assignée à « React — mercredi 14h–17h ». Chevauchement le mercredi de 15h à 17h, sur 9 sessions à venir. Choisissez une autre classe, ou réglez les dates de validité pour éviter le chevauchement. » (bouton désactivé, `aria-describedby`) |

### 05 — Calendrier scolaire
| État | Texte |
|---|---|
| Ch. | « Chargement du calendrier scolaire… » |
| Vide | « Le calendrier scolaire 2026-2027 est vide » ; « Importez le calendrier officiel de la FWB : vous pourrez ensuite y ajouter les fermetures propres à l'école. Sans calendrier, la création d'une classe ne saute aucune vacance. » — « Importer le calendrier FWB 2026-2027 » (admin), « Ajouter une date à la main » ; pour le directeur : « Réservé aux administrateurs. Vous êtes directeur : demandez l'import à un administrateur, ou ajoutez vos dates à la main. » |
| Err. | « Impossible de charger le calendrier scolaire 2026-2027. Réessayez. » |
| Succ. | « Date « Journée pédagogique » (16/12/2026) ajoutée. 1 session à vérifier : React mercredi, séance 9. » ; « Vacances de Noël supprimées du calendrier 2026-2027. » ; « Année 2027-2028 créée avec ses 2 périodes. Calendrier FWB importé : 9 entrées. » |
| Confirmation de suppression | « Supprimer « Vacances de Noël » (FWB) ? » — « Conserver la date » / « Supprimer les vacances de Noël » |
| Lecture seule | « Lecture seule. Le calendrier scolaire est géré par la direction. Contactez-la pour signaler une erreur. » |

### 06 — Portail Mes classes
| État | Texte |
|---|---|
| Ch. | « Chargement de vos classes… » |
| Vide | « Vous n'avez pas encore de classe » ; « Dès que la direction vous assignera à une classe, elle apparaîtra ici avec ses sessions. Une question ? Contactez la direction. » |
| Err. | « Impossible de charger vos classes. Vérifiez votre connexion. » — « Réessayer » |
| Succ. | « Heures de la séance 5 soumises (3 h). Elles sont visibles par le directeur. » |
| Session annulée | « Séance 12 — Annulée » + « Salle indisponible » ; « La direction programmera un bis (« Séance 12 bis ») ; le numéro de séance ne change jamais. » |
| Remplacement | « Remplacée par Carol. Carol a sa propre saisie ; vos heures déjà encodées ne changent pas. » |
| Liens | « Liens de cette session : 3 généraux + 2 de la séance 6 » — « Voir les liens » |
| Bouton | « Encoder mes heures », « Soumettre mes heures » (brouillon), « Soumettre les heures », « Enregistrer le brouillon » |

### 07 — Liens du cours (généraux + par séance)
| État | Texte |
|---|---|
| Ch. | « Chargement des liens… » |
| Vide (séance sans lien) | Titre « Aucun lien pour la séance 3 » ; « Seuls les liens généraux (3) s'affichent pour cette séance. Ajoutez la ressource propre à cette séance : elle sera visible par toutes les classes du cours. » — « Ajouter un lien à cette séance » (les liens généraux restent affichés) |
| Err. | « Impossible de charger les liens du cours React. Réessayez. » |
| Succ. | « Lien enregistré (portée : séance 6). Visible par toutes les classes de React ; modification ajoutée à l'historique. » ; « Lien « Exercices — les hooks » (séance 6) supprimé pour toutes les classes de React. Annulable depuis l'historique. » ; « Version restaurée : « Ancien wiki du cours » (Général) est de nouveau visible. Entrée ajoutée à l'historique. » |
| Suppression | « Supprimer le lien « … » ? Ce lien de la séance 6 disparaîtra pour toutes les classes de React (mercredi et samedi) et pour les élèves. Il reste archivé : vous pourrez l'annuler depuis l'historique pendant 6 mois. » — « Conserver le lien » / « Supprimer le lien pour tout le cours » |
| Portée (modale) | « Portée du lien » : « Général (toutes les séances) » / « Séance » + n° ; « Visible par toutes les classes de ce cours (mercredi et samedi) et par les élèves. Un lien de séance s'affiche sur la séance de ce numéro et sur ses sessions bis, dans chaque classe. » ; onglet : « S'applique aussi aux sessions bis de la séance n » |
| Lecture seule (AC-6) | « Lecture seule : votre assignation à React s'est terminée le 31/01/2027. Vous pouvez consulter les liens mais plus les modifier. » |
| Non autorisé (AC-5) | « Vous n'avez pas de classe de ce cours » ; « Seuls les professeurs qui enseignent React peuvent modifier ses liens. Vous pouvez demander à la direction de vous assigner à une classe. » |
| Historique (panneau) | Chaque version : « Portée : Général » ou « Portée : Séance n » ; Vide : « Aucune modification dans les 6 derniers mois » ; Erreur : « Impossible de charger l'historique. » ; Ch. : « Chargement de l'historique… » |

### 08 — Calendrier filtré
| État | Texte |
|---|---|
| Ch. | « Chargement du calendrier… » |
| Vide | « Aucune session pour ces filtres en novembre 2026 » ; « Modifiez les filtres ou créez une classe pour générer des sessions. » |
| Err. | « Impossible de charger le calendrier. Réessayez. » |

---

## 5. Accessibilité et responsive

- **Clavier** : tout est atteignable au clavier ; les actions de ligne sont des boutons visibles (pas de menu « … » caché) ; réordonnancement des liens par boutons « ↑ ↓ » (le glisser-déposer n'est qu'un bonus).
- **Modales** : `role="dialog"`, `aria-modal`, titre `aria-labelledby`, focus placé dans la modale à l'ouverture, **focus piégé**, `Escape` ferme et rend le focus à l'élément déclencheur (implémenté dans les mock-ups).
- **aria-live** : toasts `role="status"` ; récapitulatifs de propagation et conflits en `aria-live="polite"` (les changements de sélection sont annoncés) ; erreurs bloquantes `role="alert"`.
- **Couleur jamais seule** : statuts en texte + couleur (« Active », « Brouillon », « ⚠ Date en conflit ») ; frise du calendrier avec lettres V/F/E ; badges FWB/École en texte.
- **Contraste AA** : couleurs du design system utilisées avec textes foncés sur fonds clairs (badges `-l`/`-d`) ; boutons primaires blanc sur `#2563eb` (≈ 5,2:1). *À contrôler à l'implémentation avec un outil.* Focus visible 3 px.
- **Formulaires** : `label` associé à chaque champ, erreur à côté du champ + bandeau.
- **`prefers-reduced-motion`** : squelettes non animés.
- **Responsive** : admin desktop-first, lisible en tablette (tableaux défilables horizontalement, grilles passant à 1 colonne sous 860 px). **Portail professeur : 375 px prioritaire**, boutons d'action de 48 px, une colonne. La liste des sessions du professeur reste en cartes (pas de tableau).

---

## 6. Règles de cohérence appliquées

- Vocabulaire du glossaire : Cours, Année scolaire, Classe, Session, Assignation, Remplacement, Calendrier scolaire ; **aucun « type de cours »** ; rôle « principal / co-enseignant / remplaçant » toujours accompagné de « indicatif » et **sans mention de rémunération** (seule mention : « sans effet sur la rémunération », pour lever le doute).
- Pattern des pages : `AdminPageLayout` (titre, actions primaires en haut à droite, filtres, contenu), `DetailPageSection` pour les fiches, `AdminModal` pour les modales, `AdminButton` (variants primary/secondary/danger).
- Couleurs et typographie issues de `AdminDesignSystem.js` ; sidebar `#0b3550` et police Manrope repris de `index.css`. Les hex sont en dur dans les mock-ups uniquement (fichiers autonomes) ; **en dev, uniquement via les tokens** (DEVELOPMENT_STANDARDS §5.5).
- Libellés de rôle et de statut : **table de libellés front unique** (`co_enseignant` → « Co-enseignant/Co-enseignante » selon le genre du professeur ou forme neutre à trancher, voir Q11).

---

## 7. Actions non autorisées : masquées ou désactivées avec explication

| Situation | Traitement |
|---|---|
| Professeur : actions d'administration (classes, assignations, calendrier) | **Absentes** du menu et des pages (aucune action possible) |
| Professeur : liens d'un cours sans classe | Page « Vous n'avez pas de classe de ce cours » (pas de bouton mort) |
| Professeur : assignation terminée | Lecture seule + bandeau explicatif ; actions d'édition retirées |
| Directeur : import FWB | Bouton remplacé par le texte « Réservé aux administrateurs… » |
| Session passée / avec heures | Bouton « 🔒 Ajuster » désactivé + infobulle + légende |
| Conflit d'horaire | Bouton de confirmation désactivé, motif affiché juste au-dessus (`aria-describedby`) |
| Suppression d'une classe avec heures | Le bouton reste actif ; le clic ouvre l'explication 409 et propose l'archivage |

---

## 8. Matrice AC → mock-up et manques

| AC | Couvert par | Remarque |
|---|---|---|
| AC-1 | 02 (+ 01 toast) | ✔ |
| AC-2 | 06 (vue par professeur), 01 | ✔ partiel : l'isolation est **surtout back** (403) ; l'UI montre uniquement les classes/sessions du professeur |
| AC-3 | 03 (colonne « Heures encodées » par professeur), 06 (co-enseignement, « heures indépendantes ») | ⚠ **Manque** : écran Timesheets modifié (session pré-remplie) non mocké en pleine page ; seule une bottom sheet illustrative est montrée |
| AC-4 | 07 (bandeau de portée, journalisation dans l'historique) | ✔ |
| AC-5 | 07 (état « Non autorisé ») | ✔ côté UI ; le 403 est back |
| AC-6 | 07 (état « Lecture seule »), 06 (classes terminées : heures consultables) | ✔ |
| AC-7 | 03 (modale de refus 409 + archivage) | ✔ ; refus de suppression d'une **session** couvert par le verrou « 🔒 » |
| AC-8 | Menu de tous les mock-ups + §1 | ✔ (retrait de « Types de cours ») |
| AC-9 | 01 (état Vide : « aucune classe… état propre ») | ✔ partiel (migration = back) |
| AC-10 | 02 (aperçu avec dates sautées), 03 (dates sautées listées) | ✔ |
| AC-11 | 03 (modale « Ajuster » + toast) | ✔ ; « les professeurs voient la nouvelle date » : pas de notification (Q9), visible dans « Mes classes » |
| AC-12 | 03 (modale Ajouter un professeur : « 8 sessions à venir, 5 passées non modifiées »), 02 (classe neuve : 14 sessions) | ✔ |
| AC-13 | 04 (modale Ajouter une classe : « 9 sessions à venir ») | ✔ |
| AC-14 | 03 (remplacement), 06 (Alice/Carol) | ✔ |
| AC-15 | 03 (modale Retirer, 9 futures / 5 conservées) | ✔ |
| AC-16 | 07 (panneau d'historique avec portée, Restaurer) | ✔ |
| AC-17 | 07 (« Annuler la suppression ») | ✔ |
| AC-18 | 05 (FWB pré-rempli, badges FWB/École, ajout/modif/suppression, création d'année) | ✔ |
| AC-19 | 07 (mention « conservé 6 mois », expiration des plus anciennes) | ✔ partiel (purge = back) |
| AC-20 | 03 (« Séance 12 — Annulée » avec numéro et motif conservés, aucune renumérotation), 06 (séance annulée), 07 (liens = numéro de séance fixe) | ✔ |
| AC-21 | 03 (modale « Ajouter un bis de la séance [1..14] », « Séance 12 bis », confirmation « Cette classe passera à 15 sessions ») | ✔ |
| AC-22 | 02 (état « Blocage fin de période »), 03 (déplacement/ajout/rattrapage : « Cette date est après la fin de la période 1 ») | ✔ |
| AC-23 | 07 (généraux + séances, séance courante, portée au choix), 03 (colonne Liens), 06 (liens de la session) | ✔ |
| AC-24 | 03 (« Remplacer cette session » → « Séance 12 bis », lien depuis la session annulée, « remplace la séance 12 du 20/01 ») | ✔ |
| AC-25 | 03 (« Remplacer un professeur » disponible sur sessions passées et à venir, sans message ni blocage lié aux timesheets ; timesheets existantes inchangées), 06 (Alice/Carol) | ✔ |

**Manques signalés**
1. **Écran Timesheets** (pré-remplissage par session) : pas de mock-up pleine page ; à décrire dans la story T3.
2. **Liste complète des sessions côté professeur** (« Toutes les sessions ») : même composant que 03 en lecture seule, non mocké.
3. **Duplication d'année (US-8, Could)** : hors mock-ups.
4. **Modale « Créer une année scolaire »** : ajoutée dans 05 (pas prévue en §8) pour couvrir US-1 / AC-18.
5. **Écran 08 (calendrier filtré)** : ajouté pour couvrir §8.3 (absent de la liste de fichiers demandée).
6. **Page contenu de cours (`admin/cours/:id/contenu`)** : seul le principe (historique en panneau) est décrit, pas de mock dédié.

---

## 9. Questions ouvertes pour validation

Sont **tranchées** et retirées : sessions à assigner = sessions à venir (Q12), numéros de séance fixes sans renumérotation (Q13), plafond de sessions dépassable (Q14), borne de fin de période (Q15), rattrapage par bis (Q16), liens par numéro de séance (Q17, RG-12), aucune date libre = création bloquée par la borne de période, remplacement d'un professeur **sans contrainte de timesheet** (Q18, AC-25). Les questions ci-dessous restent des **propositions non tranchées** ; recommandation par défaut entre parenthèses.

1. **Qui met à jour le fichier FWB chaque année ?** *Reco :* un **administrateur** dépose le fichier versionné `calendrier_fwb_AAAA-AAAA.json` dès la publication du calendrier officiel, puis clique « Importer le calendrier FWB » (idempotent) ; le **directeur** complète ensuite les fermetures « École ». Rappel annuel (mai–juin).
2. **Session tombant sur une date de calendrier ajoutée après coup ?** *Reco :* **alerte visuelle** (bandeau + ligne surlignée + avertissement dans la modale d'ajout et dans le calendrier), **aucun déplacement automatique**, trois actions : Déplacer / Annuler / Maintenir la date.
3. **Bouton d'import FWB dans l'UI ?** *Reco :* bouton réservé aux administrateurs appelant la même logique idempotente ; à défaut, message « Contactez l'équipe technique ».
4. **Suppression d'une entrée FWB** : *Reco :* conservée lors des imports suivants (entrée « masquée », pas recréée), comme les modifications manuelles.
5. **Duplication d'année (US-8)** : *Reco :* hors périmètre CLS-01, story dédiée ; aucun bouton dans les mock-ups.
6. **Genre des libellés de rôle** (« Principal » vs « Principale ») : *Reco :* libellé neutre « Principal·e » ou unique « Principal » ; les mock-ups accordent au genre à titre d'illustration.
7. **Accès du directeur au calendrier scolaire** : *Reco :* directeur = ajout/modification/suppression des entrées École et modification d'entrées FWB ; seuls les administrateurs importent.
8. **Redirections** : `/mes-cours` → `/mes-classes` et `admin/cours/:id/sessions` → liste des classes filtrée. *Reco :* oui.
9. **Élèves (code de partage)** : *Reco :* aucun changement visible à part les liens à jour (généraux + séance) ; pas de mock-up.

---

## 10. Écarts éventuels avec CLS-01

- §8 point 3 (« Calendrier existant filtré ») : mock-up 08 **ajouté** (non listé dans la demande initiale).
- US-1 : modale **Créer une année scolaire** ajoutée dans 05 (§8 ne prévoyait pas d'écran dédié).
- Menu : « Mes cours » renommé « Mes classes » et « Calendrier » ajouté au menu (route déjà existante).
- Fin de période : les mock-ups utilisent une date de fin de période 1 illustrative (05/02/2027) ; la valeur réelle vient de `periodes`.
- Plafond (AC-21) : la classe de démonstration compte 14 séances dont 1 annulée ; le bis fait passer à 15 sessions (confirmation montrée dans la modale).
- Retrait de l'assignation depuis la fiche professeur (`ProfesseurCoursCard`, `CoursAssignmentModal`) : hypothèse d'après leurs noms, à confirmer.
- Liens : l'écran 07 montre des onglets 1..14 (les séances sont bornées à 14) ; un lien sur une séance inexistante n'est affiché nulle part (RG-12).
