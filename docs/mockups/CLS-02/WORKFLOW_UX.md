# CLS-02 — Classe sur deux périodes : workflow UX et mock-ups (à valider avant développement)

| | |
|---|---|
| **Auteur** | UX Expert (sub-agent) |
| **Base** | `docs/requirements/CLS-01-modele-classes.md` et `docs/mockups/CLS-01/WORKFLOW_UX.md` (règles conservées : 14 séances par période, calendrier FWB, dates sautées listées, numéro de séance fixe 1..14, bis, aperçu avant création ; **la borne de fin de période devient un avertissement, plus un blocage**, état propre sans migration) |
| **Mock-ups** | `index.html`, `01-creation-classe.html`, `02-detail-classe-2-periodes.html`, `03-liste-classes.html`, `04-portail-et-liens.html` (HTML statiques, sélecteur d'états en haut, même CSS que CLS-01). Dates illustratives. |
| **Statut** | **Révision 2** — intègre les réponses du directeur aux questions de la révision 1 (démarrage en P2, même cours, correction rétroactive du cours, alerte de planification, séances hors période non bloquantes). **Aucune zone de validation n'est remplie.** |

## 1. Décisions du directeur

1. Cours **différent par période** (catalogue), mais **le même cours est autorisé** en P1 et P2 (liens par séance partagés dans ce cas).
2. **Même groupe, jour, horaire, lieu et professeurs** pour toutes les périodes (assignations au niveau de la classe).
3. **Chaque période est optionnelle à la création, au moins une est requise** : une classe peut **démarrer seulement en P2** ; la période manquante (P2 ou P1) s'ajoute plus tard depuis la fiche classe.
4. **Une ligne par classe** dans la liste.
5. **Changement de cours d'une période possible après son démarrage**, y compris sur les séances passées (correction rétroactive). **Seule condition bloquante : aucune heure encodée (timesheet) sur cette période.**
6. **Alerte de planification de la P2** (ex. P1 à moins de 4 semaines de sa fin, sans P2) : liste, fiche classe, tableau de bord directeur.
7. **Des séances peuvent avoir lieu après la fin de la période** (rattrapage, retard) : on **avertit sans bloquer** à la génération, au déplacement et à l'ajout d'un bis ; les séances sont marquées « hors période ».

## 2. Principes UX

- **Une classe = une entité = une ligne partout.** Les périodes sont des *sections* de la classe, jamais des classes séparées.
- **Un libellé de séance unique : « P1 · Séance 3 »** (pastille bleue P1, violette P2), complété du cours quand la place le permet (« P1 · Séance 3 · Scratch »). Il remplace « Séance 3 » partout où une classe a plusieurs périodes ; pour une classe sans P2, le préfixe reste affiché (cohérence, une seule règle).
- **Ce qui est commun se saisit une fois** (jour, créneau, lieu, profs) ; **ce qui est propre à la période** (cours, date de démarrage, fin, séances) est dans un bloc coloré par période.
- **Aucune action nouvelle côté professeur** : la timesheet reste indépendante, l'encodage garde le même nombre de clics.

## 3. Workflow par écran

### 3.1 Création (mock-up 01) — un écran, deux blocs période
Ordre : **1 · Groupe et créneau** (année, lieu, jour, horaires, **cases « Période 1 » / « Période 2 » à ouvrir maintenant**, au moins une) → bloc **Période 1** (cours + date de démarrage) → bloc **Période 2** (cours + date) → **Aperçu** → **Professeurs**.
- Une période décochée devient une ligne grisée « n'est pas ouverte : vous pourrez l'ajouter plus tard ». Pas de sélecteur supplémentaire : les cases suffisent pour « démarrer en P2 seulement ».
- Date P2 **pré-proposée** : 1er jour de classe après la dernière séance P1 (ou, P2 seule, 1er jour de classe de la période 2).
- **Aperçu en direct 14 + 14 dates**, avec dates sautées FWB par période. Bouton : « Créer la classe (28 séances) » / « (14 séances) ».
- **Fin de période dépassée = avertissement jaune non bloquant** : « La 14e séance tomberait le 10/02/2027, après la fin de la période 1 (05/02/2027). Elle sera marquée « hors période ». » Badge « hors période » sur la date concernée dans l'aperçu ; bouton « Créer quand même ».
- **Blocage rouge** uniquement pour le chevauchement P1/P2 (voir §4).
- Confirmation : « Classe « Scratch → Python — mercredi 14h–17h » créée. P1 : … · P2 : … ». Titre dérivé : « Cours P1 → Cours P2 », cours seul si identique ou si une seule période.

### 3.2 Fiche classe (mock-up 02) — 28 séances groupées
- En-tête : titre dérivé, créneau, lieu, profs (communs), carte Avancement (« P1 Scratch 5/14 · P2 Python 0/14 · démarre le … »).
- **Un bloc par période** (bande colorée, cours, dates, fin de période, dates sautées, actions de période : « Changer le cours », « Liens du cours », « ＋ Séance / bis », « Supprimer / annuler la période »), puis le tableau des 14 séances. Les séances sont **jamais mélangées** : P1 puis P2.
- **Période absente** : carte pointillée + bouton « ＋ Ajouter la période 2 » (ou « 1 » pour une classe démarrée en P2) → modale (cours, date, aperçu 14 séances, profs hérités, conflits). Aucune re-saisie du créneau. Si la P2 est absente et que la P1 finit dans moins de 4 semaines : **bandeau jaune « ⏰ Période 2 à planifier »** avec bouton d'action.
- **Séances hors période** : dans le bloc de leur période, la séance garde son numéro (« P1 · Séance 14 ») et porte le badge jaune **« Hors période · rattrapage »** ; l'en-tête du bloc affiche « 1 séance hors période ».
- Ajuster une séance (déplacer, annuler, bis) : comportement CLS-01 ; une date après la fin de la période donne un **avertissement jaune non bloquant**, une date qui chevauche l'autre période est refusée.

### 3.3 Liste et filtres (mock-up 03)
- **Une ligne par classe**, deux colonnes « Période 1 · cours » / « Période 2 · cours » (« P2 à planifier » en gris si absente), colonnes « Séances P1 » / « Séances P2 ».
- Filtre **Période** : défaut = **période en cours** selon le calendrier (P1 maintenant). Il ne cache pas la classe, il **met en avant la période** (ne montre que ses colonnes). Choix « P2 » : n'affiche que les classes ayant une P2. « Toutes » : les deux colonnes.
- Filtre **Cours** : cherche dans P1 **et** P2 (« Python » trouve « Scratch → Python »).
- **Alerte de planification** : bandeau jaune en tête (« ⏰ 1 classe à planifier ») et badge dans la colonne Alerte (« P2 à planifier · P1 finit dans 3 sem. »). Autres badges : « Démarre en P2 · P1 à ajouter », « 1 séance P1 hors période ».
- Une classe qui démarre en P2 est masquée par le filtre « P1 » (et inversement pour une classe sans P2 avec le filtre « P2 »).

### 3.4 Portail professeur (mock-up 04)
- Sections inchangées : **À encoder → Prochaine session → Mes classes**. Chaque session porte « P1 · Séance 5 ».
- Carte classe : une carte, **deux lignes de période** avec barre de progression (P1 5/14, P2 « démarre le 17/02/2027 » estompée). Quand la P2 est en cours, la P1 passe en « terminée (14/14) · voir ».
- Prof ajouté à une classe : voit les deux périodes (assignation de classe). Séance hors période : badge jaune et date réelle (« rattrapage le 10/02/2027 »).
- **Tableau de bord directeur** (accueil, mock-up 04) : deux cartes, « Périodes 2 à planifier » et « Séances hors période », avec lien vers la fiche.

### 3.5 Fiche professeur › Classes (mock-up 04)
Une ligne par classe, colonne « Périodes » (« P1 Scratch · P2 Python »). Assignation « valide depuis le … » inchangée.

### 3.6 Timesheets, liens, calendrier (mock-up 04)
- **Timesheets** : le sélecteur de session affiche « P1 · Séance 5 — Scratch — mer. 18/11/2026 ». La date reste le repère principal. Aucun changement du flux d'encodage.
- **Liens par séance** : rattachés au couple **(cours, n° de séance)**. P1 et P2 ayant des cours différents, « Séance 3 » de Scratch et « Séance 3 » de Python sont des données distinctes ; l'étiquette « P1 » ou « P2 » indique dans quelle période on se trouve. Si le **même cours** est utilisé en P1 et P2 (autorisé), ils **partagent** le même jeu de liens (affiché « Séance 3 — utilisée en P1 et P2 »).
- **Calendrier** : pastille de séance « P1 · Séance 3 · Scratch » (bleu) / « P2 · Séance 3 · Python » (violet).

## 4. Cas limites et règle UX proposée

**Seuls cas bloquants** : (a) chevauchement des périodes, (b) P2 avant le début de P1, (c) date hors de l'année scolaire (pas de calendrier FWB pour la générer), (d) jour de la date différent du jour de la classe, (e) changement de cours avec heures encodées. Tout le reste avertit.

| Cas | Règle proposée | Message / comportement |
|---|---|---|
| **P2 démarre avant le début de P1** | Bloquant | « La période 2 ne peut pas démarrer avant la période 1. » |
| **Chevauchement** : 1re séance P2 ≤ dernière séance P1 (même créneau) | **Bloquant** : deux séances au même créneau seraient incohérentes. Règle : P2 démarre **après la dernière séance de la P1, rattrapages compris** | « Chevauchement : la période 1 a encore des séances jusqu'au 03/02/2027. La période 2 doit démarrer après (au plus tôt le 10/02/2027). » Même règle pour un bis/déplacement P1 qui atteindrait la 1re séance P2 (et inversement en P2 seule + ajout P1). |
| **Fin de période dépassée** (14e séance, bis, déplacement) | **Avertissement jaune non bloquant** ; séance marquée « Hors période · rattrapage » | « Après la fin de la période 1 (05/02/2027) : non bloquant. » Bouton « Créer quand même » / « Déplacer (hors période) ». |
| **Date P2 avant le début officiel de la période 2** (mais après P1) | Avertissement | « Avant le début de la période 2 (08/02/2027). » |
| **P2 hors année scolaire** | Bloquant | « Cette date n'est pas dans l'année 2026-2027. » |
| **Classe démarrée en P2** | Autorisé ; P1 ajoutable ensuite | Bouton « Ajouter la période 1 » ; la P1 (14 séances) doit se terminer avant la 1re séance P2 (sinon chevauchement bloquant). |
| **Aucune période cochée / date vide d'une période cochée** | Bloquant sur le formulaire | « Ouvrez au moins une période. » / « Indiquez la date de démarrage ou décochez la période. » |
| **Jour de la date ≠ jour de la classe** | Bloquant (règle CLS-01) | « Le 08/10/2026 est un jeudi. Choisissez un mercredi. » |
| **Changer le cours d'une période** (démarrée ou non, séances passées incluses) | Autorisé **sauf si une heure est encodée** sur cette période | Modale : conséquences (séances passées et à venir prennent le nouveau cours, liens remplacés, autre période inchangée). Si bloqué : « Des heures sont déjà encodées sur la période 1 (4 séances concernées). Le cours ne peut plus être changé. » |
| **Même cours en P1 et P2** | Autorisé | Liens partagés ; titre « Python » seul. |
| **Supprimer / annuler une période** | Suppression physique si aucune heure encodée ; sinon annulation des séances à venir (motif obligatoire), passées conservées | Modale à deux cas. Une classe ne peut pas perdre sa dernière période : alors « Archiver la classe ». |
| **Alerte P2 à planifier** | Affichée si P2 absente et fin de P1 < 4 semaines (aussi si P1 absente et P2 démarrée : « P1 à ajouter » reste informatif, sans alerte) | Liste, fiche classe, tableau de bord directeur. Disparaît dès que la P2 existe. |
| **Archiver la classe** | Archive toutes les périodes (CLS-01) | « Archiver la classe (P1 et P2) ». |
| **Prof ajouté/retiré/remplacé** | Niveau classe ; propagation aux séances **à venir des deux périodes**, hors période comprises | Récapitulatif « n séances P1, n séances P2 ». |
| **Calendrier scolaire modifié après coup** | Alerte visuelle sur la séance (CLS-01) | Libellé « P2 · Séance 4 ». |

## 5. États (textes à reprendre)

- Création, chargement/erreur/vide : identiques à CLS-01. Succès : voir 3.1.
- Détail, P2 ajoutée : « Période 2 ajoutée : Python, 14 séances du 17/02/2027 au 02/06/2027. Les professeurs de la classe y sont assignés. »
- Liste, vide : texte CLS-01 ajusté « … les 14 séances de la période 1 seront générées ; vous pouvez ajouter la période 2 tout de suite ou plus tard. »
- Portail, vide / chargement / erreur : inchangés.

## 6. Accessibilité et responsive

- La période est toujours identifiée par **texte** (« P1 »/« P2 ») en plus de la couleur (contraste ≥ 4.5:1).
- Le bloc P2 est un groupe de champs avec une case à cocher nommée ; le tableau de séances a un en-tête par période (`<h2>` du bloc).
- Mobile : blocs empilés, aperçu en une colonne, portail 375 px, boutons 48 px pour l'encodage.

## 7. Impacts techniques à connaître (information pour la suite, pas de décision UX)

- Une classe doit porter deux périodes avec chacune cours, `periode_id`, date de démarrage et 14 séances ; le numéro de séance 1..14 reste **par période** (clé « période + numéro »).
- Les liens par séance doivent être résolus par (cours de la période, numéro) ; le front affiche la période.
- Les filtres, la fiche prof et le portail consomment la liste de périodes de la classe ; le backend reste source de vérité des règles (dates, bornes).

## 8. Questions ouvertes pour le directeur

Les 5 questions de la révision 1 sont **tranchées** (voir §1). Restent :

1. **Alerte de planification : seuil.** *Reco :* 4 semaines avant la fin de la P1 (réglage fixe), identique pour la liste, la fiche et le tableau de bord.
2. **« Hors période » : durée maximale tolérée ?** *Reco :* aucune limite (simple avertissement) ; seule la 1re séance de l'autre période borne le rattrapage (chevauchement).
3. **Changement de cours rétroactif et liens/heures** : *Reco :* conserver les liens par séance propres au nouveau cours ; tracer le changement dans l'historique de la classe (qui, quand, ancien → nouveau cours).
