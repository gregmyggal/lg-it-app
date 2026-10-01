# CLS-01 / T5 — Inventaire du « type de cours » (lecture seule du code)

> Statut : **à valider** (aucune case de validation remplie). Audience : architecte et développeurs.
> Méthode : recherche de `typeCours`, `TypeCours`, `types_cours`, `types-cours`, `type_cours`, `canAccessCoursByType`, « Types de cours », « Catégories », `CheckboxGroup`, « type de cours » dans `frontend/src`, `backend/` (app, routes, database, tests) et `docs/`.
> À ne **pas** confondre : `types_formation` / « Types de formation » (autre notion, **conservée**), `type_contrat` du professeur, `type` d'un code de partage.

## 1. Constats clés (à lire en premier)

| # | Constat | Conséquence |
|---|---|---|
| C1 | **Usage fonctionnel caché : la section « Catégories » de `SharePage.jsx` est mutualisée** entre cours **et formations**. `ShareCodeController` renvoie `types` = `typesCours` pour un Cours **et** `typesFormation` pour une Formation ; la page affiche la même section. | Retirer `types` pour les cours **sans** casser la page élève d'une **formation** (les types de formation restent affichés). Ne pas supprimer la section côté front. |
| C2 | `Professeur::canAccessCoursByType()` n'a **aucun appelant** (grep : définition seule). Le droit d'accès repose déjà sur `canAccessCours()` (assignations de classe actives). | Suppression sans risque (code mort). « Sécurité en double » inexistante en pratique. |
| C3 | `Professeur::typesCours()` est le seul pont `professeur_type_cours` ; plus aucune Policy/scope ne l'utilise (`CoursIsolationTest` confirme : « plus de logique par type de cours »). | Aucun droit d'accès ne dépend des types. |
| C4 | Le **seeder** crée `TypeCours` « Scratch Junior » / « Python Ado » (même nom que les cours) et les rattache aux profs Alice/Bob et aux cours (5 `attach`). | À retirer du seeder ; les assignations de **classes** (T2) restent la source de vérité des démonstrations. |
| C5 | `CoursController` et `ProfesseurController` font `with('typesCours')` / `load('typesCours')` et valident `types_cours.*` (`exists:types_cours,id`). Une requête front ancienne envoyant `types_cours` serait aujourd'hui acceptée. | Après T5 : la clé est simplement **ignorée** (Laravel ignore les champs non validés) — pas d'erreur 422 pour un ancien client. |
| C6 | `SimpleLookupAdmin` est **réutilisé** par `TypesFormationAdminPage`. | À **conserver** ; seul `TypesCoursAdminPage` disparaît. Commentaire d'en-tête à ajuster. |
| C7 | `CheckboxGroup.jsx` : encore importé par `CoursAdminPage` **et** `FormationsAdminPage` (formations ↔ types de formation). | **Conserver** ; ajuster seulement le commentaire (« types_cours/ »). |
| C8 | « Catégories » dans `utils/SchemaRegistry.js` (`section_pourqui.items`) = **faux positif** : libellé du champ « Pour qui ? » du contenu d'un cours (ex. Débutants). | Hors périmètre, à ne pas toucher. |
| C9 | Aucun export/import, filtre de liste, ni affichage de type dans `AdminProfesseurDetail.jsx` (la fiche n'affiche que `type_contrat`). La fiche professeur n'a donc **rien à retirer** ; seuls la **carte de liste** et le **formulaire** affichent les types. | Périmètre UI plus restreint que prévu. |
| C10 | Aucun test PHP ne référence `TypeCours` / `types_cours` (seul un commentaire de `CoursIsolationTest`). | Pas de test à réécrire ; ajouter des tests de non-régression (route 404, payload sans `types_cours`, partage cours sans `types`). |

## 2. Frontend

| Fichier | Ce que voit l'utilisateur / ce que fait le code | Impact de la suppression |
|---|---|---|
| `layouts/PortalLayout.jsx` l.87-89 | Menu admin/directeur : lien « Types de cours » → `/admin/types-cours` (entre « Anniversaires » et « Types de formation »). | Retirer le `NavLink`. « Types de formation » reste. |
| `App.jsx` l.260-266 (+ import `TypesCoursAdminPage`) | Route protégée `admin/types-cours` (rôles STAFF). | Retirer route + import. Voir décision D4 (redirection vs page « introuvable »). |
| `pages/admin/TypesCoursAdminPage.jsx` | Écran CRUD « Types de cours » (via `SimpleLookupAdmin`, endpoint `types-cours`). | Supprimer le fichier. |
| `components/SimpleLookupAdmin.jsx` l.22 | CRUD générique {nom, slug}, commentaire cite `types-cours`. | **Conserver** (types de formation). Commentaire à corriger. |
| `components/CheckboxGroup.jsx` l.1 | Cases à cocher N-N ; commentaire cite `types_cours`. | **Conserver** (utilisé par `FormationsAdminPage`). Commentaire à corriger ; import à retirer de `CoursAdminPage`. |
| `pages/admin/CoursAdminPage.jsx` | État `types_cours: []` (l.15), chargement `GET /types-cours` (l.32), pré-remplissage `c.types_cours.map` (l.50), champ de formulaire « Types de cours » (l.235-240), **badges bleus** de types sur chaque carte (l.324-328). | Retirer état, appel API, champ et badges ; plus d'appel réseau au chargement. Le badge de statut reste seul sur la ligne de badges. |
| `pages/admin/ProfesseursAdminPage.jsx` | Sous-titre « Gérez les professeurs et leurs types de cours » (l.179) ; état `types_cours` (l.29, 42), `GET /types-cours` (l.59), pré-remplissage (l.86), bloc « Types de cours (n) : » + badges sur la carte (l.276-290), champ « Types de cours enseignés » dans **deux** modales (création l.406, édition l.521). | Retirer tout ; nouveau sous-titre « Gérez les professeurs et leurs classes ». Import `AdminCheckboxGroup` à retirer s'il n'est plus utilisé dans ce fichier. |
| `components/AdminProfesseurDetail.jsx` | Aucune référence au type de cours (seulement `type_contrat`). | Aucun changement (C9). La section « Classes » T2 reste. |
| `pages/SharePage.jsx` l.85, 175-186 | Page élève : section « Catégories » (badges) alimentée par `types`. | Pour un **cours** : plus de section. Pour une **formation** : inchangé (C1). |
| `styles/SharePage.css` (classes `.types-section`, `.types-list`, `.type-badge`) | Style de cette section. | Conservées (formations). |
| `pages/admin/CoursEditContentPage*` et pages de contenu | Aucune occurrence trouvée. | Rien. |
| `utils/SchemaRegistry.js` l.39 | « Catégories » = champ « Pour qui ? » (C8). | Rien. |
| `frontend/*.md` (BONUS_REFACTOR_SUMMARY, ADMIN_UX_*, PHASE3/4, SESSION_SUMMARY, FINAL_DELIVERABLES) | Documentation historique citant les types de cours. | Archive : ne pas réécrire ; éventuellement bandeau « obsolète ». |

## 3. Backend

| Fichier | Ce que fait le code | Impact |
|---|---|---|
| `Models/TypeCours.php` | Modèle table `types_cours`, relations `cours()` / `professeurs()`. | Supprimer. |
| `Http/Controllers/TypeCoursController.php` | CRUD `/api/types-cours` (index, show, store, update, destroy) avec `TypeCoursPolicy`. | Supprimer. |
| `Policies/TypeCoursPolicy.php` | Autorisations staff. | Supprimer (+ enregistrement éventuel dans `AuthServiceProvider`, à vérifier). |
| `routes/api.php` l.30, 66-72 | `use TypeCoursController` + 5 routes ; commentaire sur le binding `$typeCours` (cite aussi `types-formation`). | Retirer l'import et les 5 routes. Voir D4 / Q1 pour l'ancienne URL API (404 vs 410). Adapter le commentaire (types-formation reste). |
| `Models/Cours.php` l.42 | Relation `typesCours()` (pivot `cours_type_cours`). | Retirer. |
| `Models/Professeur.php` l.70-73, 112-127 | `typesCours()` (pivot `professeur_type_cours`) + `canAccessCoursByType()` (**mort**, C2). | Retirer les deux ; supprimer aussi le commentaire « LEGACY… sécurité en double ». |
| `CoursController.php` l.20, 42, 53, 55, 66-70, 95-96 | `with/load('typesCours')`, `sync`, validation `types_cours`. | Retirer ; la réponse JSON n'a plus la clé `types_cours`. |
| `ProfesseurController.php` l.17, 27, 48-49, 61, 65, 70, 87-97 | Idem pour les professeurs (`array_diff_key` sur `types_cours`). | Retirer ; simplifier `array_diff_key`. |
| `ShareCodeController.php` l.50 | `$data['types'] = $shareable->typesCours` pour un Cours. | Retirer cette ligne **uniquement** ; la branche Formation (`typesFormation`) reste (C1). |
| Migrations `2026_07_20_125311_create_types_cours_table`, `…125312_create_professeur_type_cours_table`, `…125314_create_cours_type_cours_table` | Création des 3 tables. | Ne **pas** éditer les anciennes migrations ; ajouter une migration `drop` (pivots d'abord, puis `types_cours`) — voir Q2. `down()` à fournir. |
| `database/seeders/DatabaseSeeder.php` l.7, 35-36, 52, 68, 75, 82 | Crée 2 types, les rattache aux profs et cours. | Retirer ; vérifier que les assignations de classes de démonstration suffisent. |
| `tests/Feature/CoursIsolationTest.php` l.13 | Commentaire seul. | Rien (déjà aligné). |
| `tests/` autres | Aucune référence. | Ajouter : `GET /api/types-cours` → 404 ; `POST /api/cours` et `/api/professeurs` avec `types_cours` ne plantent pas ; partage d'un cours sans clé `types`, partage d'une formation avec `types`. |
| `Support/ContentDefaults` | Aucune occurrence. | Rien. |

## 4. Documentation

| Fichier | Mention | Action proposée |
|---|---|---|
| `docs/requirements/CLS-01-modele-classes.md` (l.36, 67 AC-8, 129, 147, 160) | Définition de T5, AC-8. | Source de vérité ; mise à jour du statut T5 après livraison. |
| `docs/adr/0001-modele-cours-classe-session.md` (l.4, 14, 32) | Suppression actée, alternative « filtre » écartée. | Aucun changement. |
| `docs/DEVELOPMENT_STANDARDS.md` (l.61, 72, 100, 348, 364) | Glossaire et règles : type de cours supprimé. | Déjà alignés ; ajuster « LEGACY » restant si présent. |
| `docs/requirements/CLS-01-T1/T2/T3/T4-contrat.md`, `docs/mockups/CLS-01/*` | Mentions « restent (T5) ». | Historique, pas de modification. |
| Création du contrat `CLS-01-T5-contrat.md` | N'existe pas. | À rédiger par l'architecte après validation. |

## 5. Synthèse d'impact

- **À supprimer** : 1 page, 1 route front, 1 lien de menu, 1 controller, 1 policy, 1 modèle, 5 routes API, 2 relations Eloquent, 1 méthode morte, 3 tables.
- **À modifier** : `CoursAdminPage`, `ProfesseursAdminPage` (liste + 2 modales), `SharePage` (non, voir C1 : inchangée côté code, le backend cesse d'envoyer `types` pour un cours), `CoursController`, `ProfesseurController`, `ShareCodeController`, seeder, commentaires `SimpleLookupAdmin`/`CheckboxGroup`.
- **À conserver** : types de formation (page, routes, tables, section « Catégories » des formations), `SimpleLookupAdmin`, `CheckboxGroup`, `type_contrat`.
- **Pas de migration de données** (décision CLS-01) : les liaisons existantes sont perdues avec les tables ; aucune règle d'accès n'en dépendait (C2, C3).
