# CLS-01 / T5 — Workflow UX : suppression du « type de cours »

> Statut : **proposition à valider** (aucune case de validation remplie). Aucun développement avant validation.
> Références : `docs/requirements/CLS-01-modele-classes.md` (AC-8), `docs/adr/0001-modele-cours-classe-session.md` §4, `docs/DEVELOPMENT_STANDARDS.md` §5-6, `INVENTAIRE.md` (ce dossier).
> Maquettes : `index.html`, `01-menu-admin.html`, `02-cours-admin.html`, `03-professeur-formulaire.html`, `04-partage-eleve.html`.

## 1. Principe

Décision de la direction : « L'école a un catalogue de **cours** organisé en **classes** (plusieurs fois par semaine). » Les professeurs sont rattachés aux **classes** (T2), l'accès aux cours et aux liens repose sur ces assignations actives (T2/T4). Le type de cours n'a plus aucun rôle : on le retire **partout**, sans le remplacer, et sans migration de données.

Vocabulaire des écrans « après » : **Cours**, **Classe**, **Séance**. Les **types de formation** (autre notion) restent tels quels.

## 2. Cartographie « aujourd'hui → demain »

### 2.1 Admin / directeur

| Écran | Aujourd'hui | Demain | Clics |
|---|---|---|---|
| Menu latéral | « … Anniversaires, **Types de cours**, Types de formation » | « … Anniversaires, Types de formation » | Inchangé (1 clic de moins à parcourir : un lien en moins) |
| `/admin/types-cours` | CRUD des types (nom, slug) | Supprimé | — (une étape de configuration en moins) |
| Cours : carte | Titre, slug, badge de statut **+ badges bleus de types**, 4 actions | Titre, slug, badge de statut, 4 actions | Inchangé |
| Cours : formulaire créer/éditer | Titre, slug, statut, …, **« Types de cours »** (cases à cocher) | Même formulaire sans ce champ ; il se termine sur « Statut » | Création : 1 champ de moins à regarder, 0 clic obligatoire en moins (champ optionnel aujourd'hui) |
| Professeurs : en-tête | « Gérez les professeurs et **leurs types de cours** » | « Gérez les professeurs et **leurs classes** » | — |
| Professeurs : carte | Nom, statut, contrat, téléphone, entrée, **bloc « Types de cours (n) »** | Idem sans le bloc ; carte plus compacte | Inchangé |
| Professeurs : formulaires création et édition | …, type de contrat, **« Types de cours enseignés »** | Idem sans ce champ. Les professeurs sont rattachés à des **classes** depuis la fiche (T2) | Inchangé |
| Fiche professeur | Aucun type affiché ; section « Classes » (T2) | Identique | Inchangé |

### 2.2 Professeur
Aucun écran professeur n'affichait de type de cours. « Mes classes », séances, liens et heures sont **inchangés**. Le professeur ne voit jamais de différence (accès déjà basé sur les assignations actives).

### 2.3 Élève (code de partage)

| Page | Aujourd'hui | Demain |
|---|---|---|
| Partage d'un **cours** | Titre, description, liens, **section « Catégories »** (badges = types de cours), bouton plateforme | Même page **sans** « Catégories » ; la page se termine sur les liens puis le bouton (pas de vide) |
| Partage d'une **formation** | Section « Catégories » = **types de formation** | **Inchangé** (types de formation conservés, voir D3) |
| Partage d'un stage | Dates de session | Inchangé |

## 3. Ce qui disparaît / change / reste

- **Disparaît** : entrée de menu, page CRUD, champ « Types de cours » (cours), champ « Types de cours enseignés » (professeur, 2 modales), badges de types (carte cours), bloc « Types de cours (n) » (carte professeur), « Catégories » (page élève d'un cours).
- **Change** : sous-titre de la page Professeurs ; réponses API (`types_cours` n'existe plus) ; aucune autre apparence.
- **Reste** : types de formation (menu, page, « Catégories » des formations), `type_contrat`, assignations de classes, liens, séances, heures.

## 4. Messages de transition

- **Pas d'avertissement dans l'application** (ni bandeau, ni toast) : le type de cours n'avait aucun effet fonctionnel visible ; un message ajouterait du bruit. Une **note de version** (hors appli) suffit : « Les types de cours sont supprimés. Les professeurs sont rattachés aux classes. »
- **Anciennes URL** `/admin/types-cours` (favori, historique) : l'écran « Page introuvable » existant s'affiche, avec son lien de retour (voir D4 / Q1). Texte proposé s'il faut enrichir : « Cette page n'existe plus. Les types de cours ont été supprimés : les professeurs sont désormais rattachés aux classes. » + bouton « Retour au tableau de bord ».
- **Favori ouvert sur la page élève** : le code de partage et l'URL ne changent pas ; la page s'affiche simplement sans « Catégories ».

## 5. Cas limites

| Cas | Comportement |
|---|---|
| Un cours avait 2 types | Plus de badge ; carte avec le seul badge de statut. Aucune mention d'une perte de donnée. |
| Un professeur avait des types mais aucune classe | Carte sans bloc supplémentaire ; l'accès aux cours dépend uniquement des classes (déjà le cas). Le message existant de la fiche « Aucune classe » (T2) guide l'admin. |
| Page élève d'un cours sans lien ni ressource | État vide existant (T4) : « Pas encore de lien pour ce cours ». Jamais de page vide du fait de la suppression des catégories. |
| Formulaire sans le dernier champ | Le bouton « Enregistrer » reste directement sous les champs ; aucun espace réservé. |
| Ancien client envoyant `types_cours` | Champ ignoré par le serveur, pas d'erreur (R-T5-6). |
| Admin avec l'onglet « Types de cours » ouvert pendant le déploiement | Les appels échouent en 404 : message d'erreur générique existant (« Impossible de charger… », bouton « Réessayer »). |

## 6. Textes FR des états des écrans modifiés

**Cours (liste)** — Chargement : « Chargement en cours... » · Vide : « Aucun cours pour le moment » / « Créez votre premier cours en utilisant le formulaire ci-dessous » + bouton « Nouveau cours » · Erreur : « Impossible de charger les cours. » + « Réessayer » · Données : cartes. Formulaire : titre « Nouveau cours » / « Modifier le cours », boutons « Enregistrer » / « Annuler », erreur de champ « Le titre est obligatoire. »

**Professeurs (liste)** — Chargement : « Chargement… » · Vide : « Aucun professeur. » / « Créez-en un pour commencer. » + « Nouveau professeur » · Erreur : « Impossible de charger les professeurs. » + « Réessayer » · Données : cartes. Formulaire : « Nouveau professeur » / « Modifier le professeur ». Aide ajoutée sous le formulaire de **création** : « Les classes se gèrent depuis la fiche du professeur, une fois créé. »

**Menu** — pas d'état.

**Partage élève (cours)** — Chargement : « Chargement du cours… » · Vide : « Pas encore de lien pour ce cours » · Erreur : « Code d'accès invalide ou expiré. » + « Vérifie le code d'accès donné par ton professeur, puis réessaie. » · Données : sans « Catégories ».

## 7. Accessibilité

- Menu : le lien retiré ne laisse aucun élément vide dans la liste ; ordre de tabulation inchangé.
- Formulaires : l'ordre des champs reste logique ; aucun `aria-describedby` orphelin après suppression du champ.
- Cartes : statut transmis par le **texte** du badge (pas seulement la couleur) ; contraste conservé.
- Page élève : titres hiérarchisés (h1 cours, h2 sections) sans saut ; états Chargement/Erreur annoncés (`role="status"` / `role="alert"`).
- Navigation clavier et focus visibles inchangés ; cibles ≥ 44 px sur mobile (page élève).

## 8. Décisions

| # | Décision | Alternatives écartées |
|---|---|---|
| D1 | Retirer le type de cours **sans le remplacer** (aucun nouveau champ, aucune nouvelle étiquette). | Remplacer par un « niveau » ou « public » libre : nouvelle notion non demandée, risque de refaire un type de cours déguisé. Le contenu « Pour qui ? » existe déjà dans les contenus de cours. |
| D2 | Côté élève d'un **cours**, ne **rien afficher** à la place de « Catégories ». | Remplacer par le nom des classes / jours : expose des données d'organisation non voulues pour les élèves. Remplacer par le « Pour qui ? » : déjà présent dans le contenu. |
| D3 | Conserver la section « Catégories » pour les **formations** (types de formation). | Retirer partout : casserait un usage fonctionnel hors périmètre. Renommer en « Types de formation » : hors T5, à décider séparément. |
| D4 | Anciennes URL : **pas de redirection**, page « introuvable » existante (route retirée). | Rediriger vers `/admin/cours` : laisse croire que la page existe encore ; redirection permanente inutile pour un écran réservé au personnel. |
| D5 | Aucun message de transition dans l'application ; note de version hors appli. | Bandeau temporaire : bruit pour une notion sans effet visible. |
| D6 | Sous-titre Professeurs : « Gérez les professeurs et leurs classes ». | « Gérez les professeurs » seul : plus pauvre. |
| D7 | Page « Types de formation » et son menu inchangés. | Fusionner / renommer : hors périmètre. |

## 9. Règles proposées

- **R-T5-1** Aucun écran ni libellé ne mentionne « type de cours » (hors historique technique et documentation d'archive).
- **R-T5-2** L'accès des professeurs aux cours repose **uniquement** sur les assignations actives de classe.
- **R-T5-3** Le partage d'un cours n'expose plus `types` ; celui d'une formation expose toujours `types` (types de formation).
- **R-T5-4** Les types de formation et leurs écrans ne sont pas modifiés.
- **R-T5-5** Aucune migration de données : les liaisons professeur↔type et cours↔type sont supprimées avec les tables.
- **R-T5-6** Les requêtes contenant encore `types_cours` ne provoquent pas d'erreur (champ ignoré).
- **R-T5-7** Les routes `/api/types-cours*` et `/admin/types-cours` n'existent plus.
- **R-T5-8** Données de démonstration : plus de types ; profs et cours de démo reliés par des classes.

## 10. Questions ouvertes (recommandation par défaut en gras)

1. Ancienne URL `/admin/types-cours` et `/api/types-cours` : redirection, 404 ou 410 ? **404 / page « introuvable » existante (D4).**
2. Tables `types_cours`, `professeur_type_cours`, `cours_type_cours` : suppression physique immédiate ou conservation temporaire ? **Suppression immédiate par une migration `drop` réversible (`down()` recrée les tables vides) ; sauvegarde avant déploiement. Aucune donnée n'est utilisée.**
3. Remplacer « Catégories » côté élève d'un cours ? **Non, ne rien afficher (D2).**
4. `canAccessCoursByType` (« sécurité en double ») ? **Supprimer : code mort, aucun appelant ; l'accès repose sur `canAccessCours`.**
5. Données de démonstration (seeder) ? **Retirer types et rattachements ; garder cours « Scratch Junior », « Python Ado » et profs Alice, Bob avec leurs classes.**
6. Export / import éventuels ? **Aucun trouvé dans le code ; rien à faire. À confirmer avec la direction qu'aucun export manuel (tableur) n'utilise ces types.**
7. Renommer « Catégories » en « Types de formation » sur la page élève des formations ? **Non (hors T5), à traiter à part si souhaité.**
8. Faut-il une note de version aux professeurs ? **Non pour les professeurs (aucun impact visible) ; oui pour l'admin/direction.**

## 11. Matrice AC → maquette

| AC | Énoncé | Maquette | Élément vérifiable |
|---|---|---|---|
| AC-8 | Le catalogue : plus aucune référence aux types de cours | `01-menu-admin.html` | Menu sans « Types de cours » |
| AC-8 | idem | `02-cours-admin.html` | Cartes sans badges ; formulaire sans champ |
| AC-8 | idem | `03-professeur-formulaire.html` | Carte et formulaires sans types ; section « Classes » conservée |
| AC-8 | idem | `04-partage-eleve.html` | Page cours sans « Catégories » ; page formation inchangée |
| AC-8 | idem | `INVENTAIRE.md` | Aucun usage fonctionnel caché restant (C1-C10) |

## 12. Écarts et points d'attention

- La spécification parle de retirer « Catégories » de la page élève : en réalité cette section sert aussi aux **formations** (C1) ; elle n'est retirée que pour les cours.
- `AdminProfesseurDetail.jsx` n'affichait pas de types : aucune modification de la fiche, contrairement à l'hypothèse du brief (C9).
- `canAccessCoursByType` est du code mort, la « sécurité en double » n'existe pas (C2).
- « Catégories » dans `SchemaRegistry.js` = champ « Pour qui ? » (faux positif, C8).
- Les maquettes utilisent des données fictives ; les éléments « Avant » sont fournis pour comparaison uniquement.
