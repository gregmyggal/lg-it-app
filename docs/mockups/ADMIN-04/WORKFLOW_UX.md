# ADMIN-04 — Workflow UX : filtres de la page professeurs alignés sur le staff

> Statut : **proposition à valider** (aucun développement avant validation des maquettes).
> Maquettes : `index.html`, `01` à `05`. Références : `docs/mockups/ADMIN-02/` et `ADMIN-03/` (mêmes pastilles Accès, même règle « à relancer »).
> Demande : « filtres sur la page des professeurs alignés avec ceux de la page de gestion du staff ».

## 1. Avis UX en bref

**Oui, il faut aligner**, en prenant la page staff comme référence (listes déroulantes étiquetées dans `FilterBar` / `FilterField`, déjà utilisées par l'écran Classes). Mais la page staff n'est pas exempte de défauts : l'aligner « telle quelle » propagerait ses problèmes. La cible est donc une **barre commune unique** (Recherche, Statut, Rôle ou Contrat, Accès, Réinitialiser), corrigée, utilisée par les deux pages.

## 2. Constats vérifiés (code actuel)

| Sujet | Staff (`StaffAdminPage.jsx`) | Professeurs (`AdminProfesseursPage.jsx`) |
|---|---|---|
| Composant | `FilterBar` + 3 `FilterField` (listes) | 2 rangées de `AdminButton` `aria-pressed` (3 + 5 boutons) |
| Statut | Tous (défaut), Actifs, Désactivés ; **serveur** (`?statut=`) | **Actifs (défaut)**, Désactivés, Tous ; serveur |
| Rôle / Accès | Rôle serveur ; Accès « À relancer » seulement, **client** | Accès 5 valeurs, **client** |
| Compteur | « N invitation(s) à relancer » (comptes actifs) | idem + badge d'en-tête « N professeurs » (liste chargée) |
| Recherche / Réinitialiser | Aucune / aucun | Aucune / aucun |
| Vide | Message dans le tableau si filtre Accès ; sinon « Aucun staff » | Deux messages distincts (liste vide / filtre Accès) |
| Affichage | Tableau | Cartes avec tarifs |

Problèmes constatés (au-delà des différences de style) :

1. **Staff : la barre disparaît.** Elle est rendue dans la branche « données non vides » : un filtre serveur qui ne renvoie rien (ex. « Désactivés » sans compte désactivé) affiche « Aucun staff » **sans barre** : impossible de revenir en arrière. Idem à chaque changement de filtre : `loading` remplace toute la zone (barre démontée, **focus clavier perdu**).
2. **Staff : libellés non associés.** `FilterField` utilise `htmlFor={id}` mais la page ne passe aucun `id` : le lecteur d'écran n'annonce pas « Statut ». La région `role="search"` n'a pas de nom (`label` absent).
3. **Professeurs : 8 boutons sur 2 rangées** sans recherche : sur plusieurs dizaines de professeurs, retrouver « Moreau » exige de parcourir des cartes de ~250 px de haut. Les 5 options d'Accès ne tiennent pas sur mobile sans retour à la ligne.
4. **Défauts contradictoires** (« Tous » vs « Actifs ») : un même libellé « Statut » ne donne pas la même liste d'une page à l'autre.
5. **Perte des filtres** : `Voir détails` quitte la page ; au retour, l'état React est perdu (staff et professeurs). L'écran Classes, lui, conserve ses filtres dans l'URL (`useSearchParams`).
6. Le badge d'en-tête « N professeurs » compte la liste chargée (avant le filtre Accès) : il contredit la liste affichée.

## 3. Conception cible

### 3.1 Composant : listes déroulantes (D1)

Barre unique `FilterBar` + `FilterField`, étendue de trois briques réutilisables dans `components/ui/Filters.jsx` : champ de recherche (`FilterSearch`), bouton « Réinitialiser » (`FilterReset`), compteur de résultats (`ResultCount`). Écran Classes concerné par la même évolution sans changement visuel.

| | Boutons-bascule | Listes déroulantes (retenu) |
|---|---|---|
| Découvrabilité | Toutes les valeurs visibles | Valeur courante visible, options au clic |
| Nombre d'options | Dégradé au-delà de ~4 (Accès : 5) | Indifférent |
| Mobile | Retour à la ligne, 2 rangées hautes | Sélecteur natif, 1 champ par filtre |
| Accessibilité | `aria-pressed` correct mais groupes sans étiquette de champ | Natif : label, clavier, lecteur d'écran |
| Cohérence | Propre à cette page | Staff, Classes, futures pages |

On **perd** la lecture d'un coup d'œil des valeurs et un clic de plus. Atténuation : l'état courant est toujours affiché dans le champ, les raccourcis les plus fréquents (« à relancer ») sont proposés sous la barre.
*Écartée* : boutons segmentés pour Statut sur les deux pages (3 valeurs seulement, mais hétérogène avec Rôle/Accès/Classes).

### 3.2 Filtres et ordre (identique sur les deux pages)

| # | Filtre | Staff | Professeurs | Où s'applique | Justification |
|---|---|---|---|---|---|
| 1 | **Rechercher** (nom, email) | oui | oui | client | Besoin n°1 sur une liste de dizaines de professeurs qui se renouvelle chaque année |
| 2 | **Statut** : Actifs / Désactivés / Tous | oui | oui | serveur (`?statut=`) | Existant |
| 3 | **Rôle** (staff) / **Contrat** (professeurs) | Admin, Directeur | Salarié, Freelance, Prestataire, Non renseigné | Rôle : serveur (existant) ; Contrat : client | `type_contrat` est déjà dans la réponse API ; utile pour la paie et les relances administratives |
| 4 | **Accès** | mêmes 5 valeurs | mêmes 5 valeurs | client sur `acces.statut` / `acces.a_relancer` | Existant ; on aligne le staff sur les 5 valeurs (« À relancer », « Invitation en attente », « Mot de passe provisoire », « Mot de passe défini », « Tous ») |
| 5 | **↺ Réinitialiser** | oui | oui | n/a | Voir §4 |

Le créneau 3 est le **seul écart de contenu** légitime (un staff a un rôle, un professeur un contrat).
Aucun changement backend requis : tout est faisable avec les champs actuels (`name`, `prenom`, `nom`, `email`, `user.email`, `type_contrat`, `acces`).
*Filtres non proposés (pas de besoin avéré)* : tarif, date d'entrée, nombre de classes (`classes_count` existe : à reconsidérer si le besoin « professeurs sans classe » apparaît).
*Ce qui exigerait un backend* : pagination (alors **tous** les filtres devraient passer côté serveur : `q`, `acces`, `type_contrat`) ; l'interface resterait identique.

### 3.3 Recherche sur les deux pages (D2)

- Oui sur les deux : un seul composant, alignement complet. Valeur très forte pour les professeurs, plus faible pour le staff (quelques comptes) mais coût nul et évite qu'un écran « ait » la recherche et pas l'autre.
- Comportement : insensible à la casse et aux accents ; plusieurs mots = tous doivent correspondre ; champs : prénom + nom (ou `name`), email de contact, email de connexion. Aucun appel serveur, pas de minimum de caractères.
- `type="search"` avec libellé visible « Rechercher » ; la touche Échap / la croix native efface.

### 3.4 Valeur par défaut du Statut et compteur « à relancer » (D4)

**Règle : Statut = « Actifs » par défaut sur les deux pages.** Les anciens professeurs désactivés s'accumulent chaque année et encombrent la liste de travail ; le staff désactivé est marginal mais jamais utile au quotidien. Le compteur « à relancer » ne concerne que des comptes actifs : défaut « Actifs » le rend exact et cohérent avec la liste.
Garde-fous pour ceux qui veulent voir les inactifs : le défaut est **visible** dans la liste « Statut » ; si une recherche ne trouve rien parmi les actifs, l'écran propose « Inclure les désactivés » (maquette 03). Impact : le staff passe de « Tous » à « Actifs » ; « Désactivés » et « Tous » restent à un clic.
*Alternative* : « Tous » partout (aucun changement de comportement pour le staff, mais liste professeurs encombrée et compteur trompeur).

Règle du compteur : « N invitations à relancer » compte les comptes **actifs de la liste chargée** (statut appliqué, Accès/Recherche/Contrat ignorés) ; il devient un raccourci « les afficher » qui règle Accès = « À relancer » et disparaît quand ce filtre est actif. En mode « Désactivés » il vaut 0 et n'est pas affiché.

## 4. Fonctionnement

- **Serveur vs client, invisible** : seul Statut (et Rôle côté staff) déclenche un appel ; les autres filtres sont instantanés. Pendant un rechargement : **la barre reste montée**, seule la liste affiche un squelette (`aria-busy`). Sur la page professeurs, seul le changement de Statut recharge les tarifs (déjà chargés par professeur ; ne pas les recharger pour un filtre client).
- **Compteur de résultats** sous la barre, `role="status"` : « 5 professeurs » (aucun filtre au-delà du défaut) ou « 2 professeurs sur 5 » (M = liste chargée). Singulier/pluriel corrects ; staff : « comptes ». Le badge d'en-tête « N professeurs » est supprimé (redondant, trompeur). Annonce différée (~0,5 s) pour la recherche au clavier.
- **Réinitialiser** : bouton dans la barre, désactivé quand tout est au défaut (Actifs, le reste « Tous », recherche vide). Remet les valeurs **par défaut**, pas « tout vide ».
- **Aucun résultat** : la barre reste visible ; titre « Aucun professeur ne correspond à ces filtres », rappel des filtres actifs, bouton principal **« Réinitialiser les filtres »** (maquette 03). Distinct du cas « Aucun professeur » (liste réellement vide, sans filtre actif) avec le bouton de création ; seul cas où la barre est masquée.
- **Persistance dans l'URL** (D5) : paramètres `q`, `statut`, `contrat`/`role`, `acces`, avec `replace` (pas d'empilement d'historique) ; valeurs par défaut omises sauf `statut=tous` (l'absence signifie « actifs »). Bénéfices : le retour depuis « Voir détails » ou le bouton Précédent conserve les filtres, lien partageable, même mécanisme que Classes. Alternative écartée : `sessionStorage` (invisible, non partageable, état fantôme au retour sur la page).
- **Valeurs inconnues** dans l'URL (ex. `acces=xyz`) : ignorées, défaut appliqué.

## 5. Cohérence entre les deux pages

Mêmes libellés (« Actifs », « Désactivés », « Tous » ; « À relancer », « Invitation en attente », « Mot de passe provisoire », « Mot de passe défini »), mêmes composants, même ordre, mêmes messages et même compteur. Les constantes d'options (Statut, Accès) sont définies **une seule fois** (`utils/acces.js` / `utils/statuts.js`).
**Écarts restants légitimes** : colonne Rôle vs Contrat ; tableau (staff) vs liste de cartes avec tarifs et action « Renvoyer » (professeurs) ; unité du compteur (« comptes » / « professeurs »).

## 6. Mobile (D6)

≤ 600 px : la recherche reste toujours visible ; Statut, Contrat/Rôle et Accès sont derrière un bouton « Filtres (N) » (N = filtres différents du défaut), `aria-expanded` ; bouton ↺ à côté (libellé accessible « Réinitialiser les filtres »). Cibles de 44 px, sélecteurs natifs. Au-delà : barre complète (maquette 05). *Alternative* : tout empilé en 5 lignes (~350 px avant la première carte) : plus simple mais la liste sort de l'écran.

## 7. Accessibilité (checklist `DEVELOPMENT_STANDARDS.md`)

- Chaque champ a un `<label>` visible **associé** (`id` obligatoire sur `FilterField`, généré par défaut) ; `FilterBar` toujours nommée (`role="search"` + `aria-label`).
- Ordre de tabulation = ordre visuel : Rechercher, Statut, Rôle/Contrat, Accès, Réinitialiser, puis la liste.
- Le compteur est une région `role="status"` (annonce « 2 professeurs sur 5 ») ; l'état « aucun résultat » est annoncé de la même façon.
- Après « Réinitialiser » (barre ou état vide) : le focus va au champ **Rechercher** (le bouton peut être désactivé ensuite).
- La barre n'est jamais démontée : le focus reste sur la liste modifiée pendant le rechargement.
- Contrastes AA, focus visible (3 px), cibles 44 px, pastilles Accès avec icône + texte (jamais la couleur seule), pas d'animation avec `prefers-reduced-motion`.

## 8. Hors périmètre

Pagination et filtres serveur supplémentaires ; tri des colonnes/cartes ; filtre « sans classe » ; recherche globale inter-pages ; refonte de la carte professeur ; optimisation du chargement des tarifs (une requête par professeur à chaque rechargement : à traiter à part).

## 9. Décisions à valider (par priorité)

| # | Décision | Recommandation |
|---|---|---|
| **D1** | Unifier sur `FilterBar`/`FilterField` (listes) et abandonner les boutons-bascule | **Oui** |
| **D4** | Défaut Statut = « Actifs » sur les deux pages (staff : « Tous » → « Actifs »), avec « Inclure les désactivés » | **Oui** ; sinon « Tous » partout |
| **D2** | Ajouter la recherche nom/email aux **deux** pages (côté client) | **Oui** |
| **D5** | Persister les filtres dans l'URL (`replace`) | **Oui** |
| **D3** | Filtre **Contrat** sur les professeurs (côté client, créneau de « Rôle ») | **Oui**, priorité basse ; peut être retiré sans impact sur le reste |
| **D6** | Mobile : recherche visible + filtres repliables « Filtres (N) » | **Oui** ; sinon empilement simple |

Correctifs inclus dans la tranche (non négociables) : barre jamais démontée sur le staff, `id` des champs, `FilterBar` nommée, suppression du badge d'en-tête trompeur.
