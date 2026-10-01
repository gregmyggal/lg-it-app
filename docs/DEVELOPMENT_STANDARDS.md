# Standards de développement — Logiscool Pays Vert

**Version :** 1.0 — 2026-09-30
**Statut :** À valider par l'équipe (voir §12)
**Périmètre :** toute évolution — nouvelle feature, changement, correctif, migration.

> **Principe directeur — la « Definition of Actionable »**
> Un changement n'est *terminé* que lorsqu'un utilisateur réel (directeur, staff, professeur) peut l'utiliser
> de bout en bout : **donnée en base → API → écran → retour d'information → test**.
> Un endpoint sans écran, un écran sans permission, ou une colonne sans migration ni seeder = **non livré**.

---

## 0. Comment ces standards ont été construits (co-création)

Chaque rôle a apporté ses exigences ; les sections suivantes les fusionnent. Les points de friction réellement
observés dans le dépôt sont cités pour que les règles soient justifiées, pas théoriques.

| Rôle | Ce qu'il exige | Constat dans le code qui motive la règle |
|---|---|---|
| **Analyste fonctionnel** | Aucun développement sans Canvas de requirements validé ; critères d'acceptation testables ; glossaire métier unique | Les specs vivent dans des notes/mémoires et des `SPRINT_2_*.md` dispersés ; questions ouvertes non tranchées (« timesheet commune ou par-prof ? », « qui édite ClasseLien ? ») |
| **UX/UI expert** | Parcours par rôle, 4 états d'écran (chargement/vide/erreur/succès), messages en français métier, design system unique | `docs/AUDIT_UIUX.md` : badges de statut `valide` vs `confirmé/généré` désynchronisés, bouton Lissage absent du tableau |
| **Architecte** | Une seule source de vérité par règle métier (le backend) ; contrat d'API stable ; autorisation côté serveur ; décisions tracées (ADR) | Duplication des enums de statut dans le front ; `professor_principal_id` (EN) vs `professeur_id` (FR) ; `typesCours` vs `professeur_cours` en cohabitation |
| **Dev back-end** | Validation en FormRequest, logique dans des Services, Policies partout, migrations réversibles, tests | `CourseSessionController` : validation + règles métier + création dans le contrôleur, `TODO` non résolu (timesheets liées), tests quasi absents (`ShareCode` seulement) |
| **Dev front-end** | Un seul client HTTP, composants du design system, aucun statut/rôle codé en dur | `useAdminCRUD` utilise `axios` brut (contourne le token du `client.js`) ; `console.log` de debug dans `client.js` ; styles inline vs `AdminDesignSystem.js` |

---

## 1. Cycle de livraison d'une évolution

```
Besoin ─▶ Canvas (analyste) ─▶ Workflow UX (sub-agent UX Expert) ─▶ Mock-ups ─▶ VALIDATION ─▶ Conception (archi) ─▶ Dev vertical slice ─▶ Revue ─▶ Recette ─▶ Livraison
             docs/requirements/    trajets actuels de l'app          docs/mockups/   utilisateur      ADR si besoin       DB→API→UI→Tests      DoD      Analyste+UX
```

1. **Canvas obligatoire** (`docs/REQUIREMENTS_CANVAS.md` → copie dans `docs/requirements/<ID>-<slug>.md`).
2. **Workflow UX et mock-ups (obligatoire avant tout code de feature)** :
   - Un **sub-agent UX Expert** est consulté pour définir le workflow le plus efficace et pertinent, **intégré aux trajets existants** de l'application (menus, pages, modales déjà en place — pas de parcours parallèle).
   - Des **mock-ups** (par parcours et par rôle, 4 états inclus) sont produits (`docs/mockups/<ID>/`) et **soumis à validation** ; **aucun développement avant validation explicite**.
   - Toute divergence en cours de dev par rapport au mock-up validé = retour en validation.
3. **Definition of Ready (DoR)** — on ne code pas tant que :
   - [ ] Workflow UX défini par le sub-agent UX Expert et mock-ups **validés** (lien dans le Canvas §8)
   - [ ] Canvas rempli, sections 1–9 sans « TBD » bloquant
   - [ ] Questions ouvertes = tranchées **ou** explicitement hors périmètre
   - [ ] Critères d'acceptation en Given/When/Then, testables
   - [ ] Matrice rôle × action remplie (qui voit / crée / modifie / supprime / valide)
   - [ ] Maquette ou description d'écran validée par l'UX pour chaque parcours
   - [ ] Impact données identifié (migration, backfill, seeders, rétrocompatibilité)
4. **Découpage en tranche verticale** : chaque PR/story livre *toutes* les couches nécessaires à un usage réel
   (voir §9). Interdit : « sprint API » puis « sprint UI » qui laisse l'API sans usage.
5. **Definition of Done (DoD)** : §11.

---

## 2. Vocabulaire et nommage (langue)

### 2.0 Modèle du domaine (référence — voir `docs/adr/0001-modele-cours-classe-session.md`)

| Terme métier | Définition | Table |
|---|---|---|
| **Cours** | Entrée du **catalogue** de l'école (ex. « React »). Porte le contenu pédagogique et les liens de référence. Pas de type de cours. | `cours` |
| **Année scolaire** | Période d'organisation. Contient **2 cours à la suite** (période 1 puis période 2). | `annees_scolaires` |
| **Classe** | **Organisation d'un cours** pour une année scolaire, une période et un créneau hebdomadaire (ex. « React — mercredi après-midi — 2026-2027, P1 »). 1 séance par semaine. | `classes` |
| **Session** | Une **séance datée** d'une classe. **14 sessions par classe.** | `course_sessions` (→ `classe_id`) |
| **Assignation** | Lien **professeur ↔ classe** (1..n profs, rôle indicatif), **propagé automatiquement** aux sessions à venir de la classe, depuis la classe ou depuis le professeur. | `professeur_classe` |
| **Timesheet** | Heures d'**un** professeur, **toujours indépendante** (jamais partagée, même en co-enseignement). | `timesheets` (→ `professeur_id`, `session_id`) |
| **Liens de cours** | Ressources du cours, **généraux** ou **par séance**, adaptables par **tous les professeurs ayant une classe de ce cours** ; chaque changement est versionné et annulable. | `classe_liens` + `classe_liens_historique` |
| **Calendrier scolaire** | Vacances/fériés/fermetures d'une année ; la génération des sessions le respecte. | `calendrier_scolaire` |
| **Remplacement** | Substitution d'un professeur sur **une** session ; jamais écrasée par la propagation. | `session_professors` (`origine = remplacement`) |

Un même cours du catalogue peut être organisé **plusieurs fois par semaine** (plusieurs classes : mercredi PM, samedi…), chacune avec ses propres professeurs.
La notion de **type de cours est supprimée** (`types_cours`, `professeur_type_cours`, `cours_type_cours`).

| Élément | Règle | Exemple |
|---|---|---|
| Domaine métier | **Français**, celui de l'analyste, défini dans le glossaire du Canvas | `professeur`, `cours`, `statut_validation` |
| Tables / colonnes | snake_case, **français**, pluriel pour tables | `professeur_cours`, `date_debut`, `heure_fin` |
| Clés étrangères | `<entité_singulier>_id`, **même nom partout** | `professeur_id` — jamais `professor_id` |
| Code technique (classes utilitaires, méthodes génériques) | Anglais | `RecurrenceGenerationService` |
| Valeurs d'enum stockées | snake_case ASCII, sans accent ni espace | `co_enseignant`, `remplacant`, `confirme` |
| Libellés affichés | Français avec accents, **uniquement** dans une table de libellés front | `co_enseignant` → « Co-enseignant » |
| Routes API | kebab-case, ressources au pluriel, verbes d'action en sous-ressource POST | `POST /sessions/{session}/cancel` |
| Réponses JSON | snake_case (identique aux colonnes) | `date_debut` |

> ⚠️ **Dette à résorber (ne pas propager)** : `professor_principal_id`, `SessionProfessor`, `/professors`
> (Sprint 2) vs `professeur*` ailleurs ; valeurs `co-enseignant`, `remplaçant`, `confirmé` avec tiret/accent.
> Toute nouvelle feature utilise la convention ci-dessus ; la remise à niveau de l'existant passe par une story dédiée.

---

## 3. Architecture & responsabilités

### 3.1 Règles d'or (architecte)

1. **Le backend est la source de vérité** des règles métier, des transitions de statut, des permissions et des libellés d'état.
   Le front *affiche et guide* ; il ne *décide* jamais seul.
2. **Le front ne code pas de règle** qu'il peut recevoir de l'API : statuts autorisés, actions disponibles, rôles.
   → L'API expose des **capacités** (voir §4.5) que l'UI utilise pour afficher/masquer les boutons.
3. **Une règle = un endroit.** Toute règle apparaissant en deux endroits (validation dupliquée, enum dupliqué) est un défaut.
4. **Isolation par professeur** via Policies + scopes de requête (jamais un simple `if` dans le front). L'appartenance se détermine **uniquement** par `professeur_classe` (plus jamais par un type de cours).
5. **Toute décision structurante** (nouveau modèle de données, changement de contrat, nouvelle dépendance)
   fait l'objet d'un **ADR** court dans `docs/adr/NNNN-titre.md` (contexte, décision, conséquences).

### 3.2 Couches back-end (Laravel)

```
routes/api.php        → déclare, groupe par middleware (auth:sanctum), aucune logique
FormRequest           → validation + autorisation d'entrée (authorize() via Gate/Policy)
Controller (mince)    → orchestre : Gate → Service → Resource. ≤ ~15 lignes par action
Service (app/Services)→ règles métier, transactions, transitions de statut, calculs
Model                 → relations, casts, scopes, constantes d'enum. Pas de HTTP
Policy                → qui peut faire quoi (admin > staff > professeur propriétaire)
Resource (app/Http/Resources) → forme de la réponse JSON, incluant `can`/`allowed_transitions`
Job / Command         → traitements asynchrones ou planifiés (ex. génération de sessions)
```

### 3.3 Couches front-end (React)

```
pages/          → un écran = un parcours ; compose des composants, appelle des hooks
components/     → composants réutilisables du design system ; aucun appel API direct sauf composants « manager » nommés *Manager/*Modal
hooks/          → accès données (useAdminCRUD, useSessions…) ; jamais d'axios brut
api/client.js   → UNIQUE client HTTP (token, baseURL, gestion 401/422/500)
styles/         → AdminDesignSystem.js = tokens (couleurs, typo, espacements)
utils/          → constantes de libellés/statuts, formatage dates/montants
auth/           → contexte utilisateur, ProtectedRoute
```

---

## 4. Standards Back-end

### 4.1 Base de données & migrations
- **Une migration par changement**, nom explicite `AAAA_MM_JJ_HHMMSS_verbe_objet.php` (horodatage complet — la migration `2026_09_29_create_professeur_cours_table.php` sans heure casse l'ordre).
- `down()` **obligatoire** et testé (`migrate:rollback` propre).
- **Ne jamais modifier une migration déjà exécutée** ailleurs que sur votre poste : créer une nouvelle migration.
- Contraintes en base : FK avec `onDelete` explicite, `UNIQUE` pour les invariants métier, `NOT NULL` par défaut, index sur les colonnes de filtre/tri.
- Enums : colonne `string` + contrainte applicative (constantes sur le modèle) — pas de `ENUM` SQL (évolutions douloureuses).
- **Backfill** de données existantes dans la migration ou une commande `artisan` idempotente, documentée dans le Canvas (§7).
- Suppression : préférer l'**archivage** (`date_fin`, `archived_at`, SoftDeletes) quand des données financières/heures y sont liées (timesheets, tarifs).

### 4.2 Modèles
- `$fillable` explicite, `casts()` pour dates/JSON/enums, relations typées.
- Constantes d'états sur le modèle : `Timesheet::STATUT_BROUILLON`, avec méthodes `isLocked()`, `canTransitionTo()`.
- Scopes nommés pour les filtres réutilisés (`scopeInDateRange`, `scopeForProfesseur`).
- Pas de requêtes dans les accesseurs (risque N+1).

### 4.3 Validation, contrôleurs, services
- Validation dans un **`FormRequest`** (`php artisan make:request`), messages d'erreur en **français** (`lang/fr/validation.php`).
- Les règles métier (doublons, chevauchements, transitions, calculs de montants) vivent dans un **Service**, dans une `DB::transaction` si plusieurs écritures.
- Contrôleur mince : `Gate::authorize(...)` → appel service → `Resource`.
- Pas de `TODO` métier dans le code livré : soit traité, soit une story/issue référencée (`// TODO(#123)`).
- **Attention précédence PHP** : `$a ?? null && $b ?? null` est faux. Écrire `isset($v['a'], $v['b'])` ou `!empty(...)`.
- Le contrôle des transitions est dans une table de transitions unique (modèle ou service) ; le contrôleur ne la redéfinit pas.

### 4.4 Autorisation & sécurité
- **Toute route back-office** est sous `auth:sanctum` **et** protégée par une Policy (`Gate::authorize`). Ne jamais se reposer sur `ProtectedRoute` du front.
- Hiérarchie : `admin` (tout) > `staff` (directeur : gestion et validation) > `professeur` (ses propres données).
- Toute liste renvoyée à un professeur est **filtrée côté requête** (scope), pas côté front.
- **Liens de cours (`classe_liens`)** : modifiables par `admin`/`staff` et par **tout professeur ayant au moins une classe active de ce cours** (`professeur_classe` valide à la date du jour). Les liens sont **partagés au niveau du cours** : toute modification est **versionnée** (`classe_liens_historique`, append-only : qui, quand, avant/après), les suppressions sont des **soft deletes**, et l'**annulation/restauration** crée une nouvelle version (l'historique n'est jamais réécrit).
- **Historique générique** : pour toute donnée partagée modifiable par plusieurs rôles, appliquer ce même schéma (versions append-only + restauration).
- Accès public (codes de partage) : lecture seule, `statut = publish`, aucune donnée personnelle ni tarifaire.
- Données sensibles (tarifs, montants) : un professeur voit **ses propres** tarifs et montants sur ses timesheets (décision direction, CLS-01 Q22) ; jamais ceux d'un autre professeur, ni dans les ressources de classes/sessions/calendrier (aucun tarif exposé là), ni dans les logs.
- Pas de secrets en dépôt : `.env.deploy.prod` ne doit **pas** être versionné (vérifier `.gitignore`).

### 4.5 Contrat d'API
- **Format de succès** : `Resource` Laravel (`data`, `meta` pour pagination).
- **Format d'erreur** unifié :
  ```json
  { "message": "Message lisible en français", "errors": { "champ": ["détail"] } }
  ```
  Codes : `401` non authentifié, `403` interdit (Policy), `404`, `409` conflit métier (doublon, verrou), `422` validation.
- **Pagination** : `?page=&per_page=` (max 100) sur toute liste susceptible de croître.
- **Filtres** : paramètres nommés comme les colonnes (`professeur_id`, `date_from`, `date_to`, `statut`).
- **Capacités exposées** — chaque Resource d'entité à cycle de vie renvoie :
  ```json
  { "statut": "soumis",
    "can": { "update": false, "submit": false, "validate": true },
    "allowed_transitions": ["valide", "refuse"] }
  ```
  → l'UI n'a plus à deviner ; elle rend exactement ce que le serveur autorise.
- **Rétrocompatibilité** : ajouter des champs = OK ; renommer/supprimer = versionner ou migrer front+back dans la **même PR**.
- Action métier = `POST /ressource/{id}/<verbe>` (déjà en usage : `cancel`, `submit`, `validate`).
- Documentation : chaque endpoint nouveau ou modifié est ajouté à `docs/API_*.md` (méthode, chemin, rôle, payload, réponses, erreurs) **dans la même PR**.

### 4.6 Performance & fiabilité
- Eager loading (`with`) systématique sur les listes ; vérifier l'absence de N+1 sur les vues calendrier.
- Traitements longs (génération de sessions récurrentes) : Job en file, idempotent, avec verrou contre le double-lancement.
- Dates : stockées en UTC/serveur `Europe/Brussels` de façon **cohérente et documentée** ; `date` sans heure pour les jours, `time` pour les heures ; jamais de chaîne libre.

### 4.7 Tests back-end (PHPUnit — obligatoires)
- **Base de test = base MySQL 8 dédiée, hébergée dans un container Docker** (même moteur que la prod ; pas de SQLite). Service `db_test` distinct de `db` dans `docker-compose.yml`, base `lgit_test`, identifiants propres, **`.env.testing`** + variables `DB_*` dans `phpunit.xml`.
  - Les tests utilisent `RefreshDatabase` (ou `DatabaseTransactions`) **uniquement** sur cette base.
  - **Garde-fou** : `TestCase::setUp()` échoue si `DB_DATABASE` ne se termine pas par `_test` — pour ne jamais effacer la base de dev.
  - Lancer : `scripts/test-backend.sh` (équivaut à `docker compose up -d db_test` puis `cd backend && php artisan test`). Garde-fou : `backend/tests/TestCase.php::assertSafeTestDatabase()` (testé par `TestDatabaseGuardTest`). Configuration : `backend/.env.testing`, `backend/phpunit.xml` (`force="true"`), service `db_test` (tmpfs, port 3309).
- Par endpoint : succès, validation (`422`), non-authentifié (`401`), rôle interdit (`403`), isolation (un prof ne voit pas les données d'un autre).
- Par service/règle métier : test unitaire avec cas limites (chevauchements, transitions invalides, dates de fin).
- Les Factories couvrent chaque modèle ; les Seeders de démo restent séparés des données de test.
- Commande de référence : `php artisan test` doit être **verte** avant toute PR ; `./vendor/bin/pint` pour le format.

---

## 5. Standards Front-end

### 5.1 Structure & composants
- Un composant = une responsabilité ; > ~250 lignes → découper.
- **Réutiliser avant de créer** : `AdminButton`, `AdminFormField`, `AdminModal`, `AdminPageLayout`, `DetailPageSection`, `SimpleLookupAdmin`, `ListItemEditor`. Un nouveau composant générique est proposé au design system, pas copié dans une page.
- Nommage : `PascalCase.jsx` composants, `useXxx.js` hooks, `camelCase` variables. Pages suffixées `Page`.
- Props documentées (JSDoc) ; pas de logique métier dans le JSX.

### 5.2 Accès aux données
- **Un seul client** : `import client from '../api/client'`. **Interdit** : `import axios from 'axios'` dans pages/hooks/composants (le token ne serait pas envoyé).
- Le client centralise : `baseURL`, token, **intercepteur de réponse** (401 → déconnexion + redirection ; 422 → erreurs par champ ; 5xx → message générique + identifiant d'erreur).
- **Pas de `console.log` de debug** en livraison (retirer celui de `client.js`).
- Chaque écran de données gère les **4 états** (§6.2) via un hook commun (`useAdminCRUD` corrigé pour utiliser `client`).

### 5.3 Statuts, rôles, libellés — zéro duplication de règle
- Un fichier `utils/statuts.js` (ou généré depuis l'API) contient **libellé + couleur** pour chaque valeur d'enum ; un statut inconnu affiche un **badge neutre + valeur brute** (jamais un badge vide — cf. audit `valide`/`confirmé`/`généré`).
- Affichage conditionnel des actions basé sur `can.*` / `allowed_transitions` de l'API, **pas** sur `if (statut === '...')`.
- `ProtectedRoute` = confort de navigation ; la sécurité réelle est côté serveur.

### 5.4 Formulaires
- Champs via `AdminFormField` ; label visible, aide contextuelle si règle métier non triviale.
- Validation client = **confort** (champs requis, formats) ; la validation serveur fait foi et ses erreurs `errors.champ` sont affichées **sous le champ concerné**.
- Bouton d'envoi désactivé + indicateur pendant l'appel (anti double-soumission). Confirmation explicite avant toute action destructive ou irréversible (soumission, validation, suppression), avec **conséquence énoncée** (« Les heures ne seront plus modifiables »).
- Conserver la saisie en cas d'erreur ; ne jamais vider le formulaire sur échec.

### 5.5 Style & accessibilité
- Couleurs, typographie, espacements **uniquement** via `styles/AdminDesignSystem.js` (pas de hex en dur dans les pages).
- Accessibilité minimale : navigation clavier, `label` associés, contraste AA, `aria-live` pour les toasts, modales avec focus piégé + `Escape`.
- Responsive : le portail **professeur** doit être utilisable sur mobile (encodage d'heures en déplacement) ; l'admin est desktop-first mais lisible en tablette.
- Lint : `npm run lint` (oxlint) sans erreur ; `npm run build` doit passer.

### 5.6 Tests front-end
- Introduire **Vitest + React Testing Library** (absents aujourd'hui) : tests pour les composants critiques (badge de statut, formulaires de saisie d'heures, modales de confirmation) et les hooks.
- Parcours critiques (login → encodage → soumission → validation) : test E2E (Playwright) au fur et à mesure ; en attendant, **script de recette manuelle** dans le Canvas (§9).

---

## 6. Standards UX/UI (issus de l'UX expert)

### 6.1 Parcours par rôle
Toute feature précise **le parcours de chacun des rôles concernés** :

| Rôle | Contexte d'usage | Priorités UX |
|---|---|---|
| **Professeur** | Rapide, parfois sur mobile, en fin de cours | Encodage en ≤ 3 clics, valeurs par défaut intelligentes (dernier cours, date du jour), statut de ses saisies toujours visible, verrous expliqués |
| **Directeur / Staff** | Bureau, traitement par lots en fin de mois | Vue synthétique par mois/professeur, filtres persistants, actions en masse, alertes (saisies manquantes), export |
| **Admin** | Paramétrage occasionnel | Cohérence, garde-fous, traçabilité des modifications |
| **Élève (code de partage)** | Non authentifié, mobile | Lecture seule, lisible, zéro jargon interne |

### 6.2 Les 4 états obligatoires de chaque écran
1. **Chargement** — squelette ou indicateur (pas d'écran blanc).
2. **Vide** — message expliquant *pourquoi* c'est vide + l'action pour y remédier (« Aucune session. Générer depuis une récurrence »).
3. **Erreur** — message métier en français + action (réessayer) ; pas de message technique brut.
4. **Succès / données** — + confirmation (toast) après chaque action, avec le résultat concret.

### 6.3 Règles de contenu
- Français simple, vocabulaire du glossaire, orthographe soignée (accents).
- Bouton = verbe d'action (« Soumettre les heures », pas « OK »).
- Une action d'état est toujours accompagnée de son **effet** (« → visible par le directeur »).
- Indiquer l'état du workflow par **texte + couleur** (jamais la couleur seule).

### 6.4 Cohérence
- Tout nouvel écran suit `AdminPageLayout` : titre, actions primaires en haut à droite, filtres, contenu, pagination.
- Même interaction = même composant (édition en modale ou en page : suivre le pattern déjà en place pour l'entité).
- Toute modification de workflow met à jour la **carte des statuts** (§ Canvas 6) — c'est le contrat commun UX/analyste/dev.

---

## 7. Alignement DB ⇄ API ⇄ UI (checklist « vertical slice »)

À cocher dans la PR pour chaque évolution :

| Couche | Contrôle | ✔ |
|---|---|---|
| **DB** | Migration réversible, index, contraintes, backfill, seeder/factory à jour | ☐ |
| **Modèle** | `fillable`, casts, relations, constantes d'états, scopes | ☐ |
| **Règles** | Service + table de transitions, cas limites testés | ☐ |
| **Autorisation** | Policy + test 403 par rôle + isolation prof | ☐ |
| **API** | FormRequest (FR), Resource avec `can`/`allowed_transitions`, route déclarée, erreurs uniformes | ☐ |
| **Doc API** | `docs/API_*.md` mis à jour | ☐ |
| **Front data** | Hook via `client`, gestion 401/422/5xx | ☐ |
| **Front UI** | 4 états, composants design system, libellés via table de statuts, `can.*` | ☐ |
| **Navigation** | Route + entrée de menu + `ProtectedRoute` par rôle | ☐ |
| **Tests** | Back (feature+unit) verts ; front (composants clés) ; recette Canvas §9 jouée | ☐ |
| **Données existantes** | Comportement vérifié sur données réelles/anciennes (statuts legacy, valeurs nulles) | ☐ |

**Règle de synchronisation des contrats** : si un champ, statut ou route change, la **même PR** modifie back, front,
seeders, docs et tests. Aucune PR « back seulement » qui casse l'écran.

---

## 8. Git, PR et revue

- Branches : `feature/<id>-<slug>`, `fix/<id>-<slug>`, `hotfix/<slug>`.
- Commits : Conventional Commits — `feat(sessions): …`, `fix(timesheets): …`, `docs(...)`, `refactor(...)`, `test(...)`, `chore(...)`; corps expliquant le *pourquoi*; référence de story (`Relates to: 2A-1`).
- **PR = une tranche verticale**, taille raisonnable (≲ 500 lignes utiles), description :
  Canvas lié · captures d'écran/GIF · checklist §7 · plan de test · risques/rollback.
- Revue : ≥ 1 dev **de l'autre couche** (back relit front et inversement) pour vérifier l'alignement des contrats ; l'architecte est requis si ADR, migration lourde ou changement de permission.
- Pas de merge avec tests rouges, lint en erreur, ou migration non réversible.

---

## 9. Environnements & déploiement

- Local : `docker compose up` / `START_LOCAL.sh`; variables dans `.env` (jamais commité).
- Avant déploiement : `php artisan test`, `npm run lint && npm run build`, migration testée **sur une copie des données de prod**.
- Migration destructive ou backfill : sauvegarde préalable + plan de retour arrière écrit dans le Canvas (§7).
- Déploiement OVH : suivre `docs/DEPLOIEMENT_OVH.md`; toute nouvelle variable d'env est ajoutée aux fichiers `*.example`.
- Journalisation : erreurs serveur loguées avec contexte (user, route) **sans données sensibles**.

---

## 10. Documentation vivante

| Quand | Quoi | Où |
|---|---|---|
| Nouvelle feature | Canvas rempli et validé | `docs/requirements/` |
| Décision structurante | ADR | `docs/adr/` |
| Endpoint | Doc API | `docs/API_*.md` |
| Workflow/statut | Carte des statuts à jour | Canvas §6 + `utils/statuts.js` |
| Livraison | Note de recette (ce qui a été testé, par qui) | PR |

Éviter les fichiers de « résumé de session » à la racine (`SESSION_SUMMARY.md`, `PHASE*_COMPLETE.md`…) : l'information durable va dans le Canvas / l'ADR / la doc API ; le reste est de l'historique git.

---

## 11. Definition of Done (DoD) — liste unique

- [ ] Canvas validé par l'analyste **et** l'UX ; critères d'acceptation tous vérifiés
- [ ] Tranche verticale complète (checklist §7)
- [ ] Tests back verts (`php artisan test`), tests front des composants clés, lint/build OK
- [ ] Permissions vérifiées **pour chaque rôle** (positif + négatif) et isolation professeur
- [ ] 4 états d'écran présents ; messages en français ; accessibilité de base
- [ ] Données existantes migrées/compatibles ; seeders/factories à jour
- [ ] Documentation (API, ADR, Canvas) à jour
- [ ] Recette faite par l'analyste/UX sur environnement de test avec des données réalistes
- [ ] Pas de `console.log`, `dd()`, `TODO` orphelin, secret ou donnée personnelle en dépôt

---

## 12. Mise en œuvre & décisions à valider

**Adoption immédiate** (nouvelles évolutions) : §1, §2 (nouveau code), §4.3–4.5, §5.2–5.4, §6, §7, §8, §11.

**Décisions actées (2026-09-30, direction)**
- Professeurs assignés à une **classe** (organisation d'un cours pour une année scolaire), jamais à un type de cours. **Type de cours supprimé.**
- Catalogue de cours → classes (plusieurs par semaine possible) → 14 sessions hebdomadaires par classe ; 2 cours à la suite par année scolaire.
- Une **timesheet indépendante par professeur**, même en co-enseignement.
- **Aucune migration** des classes/sessions existantes (état propre).
- Les 14 séances suivent le **calendrier scolaire (base FWB, complétable à la main)** et restent ajustables ; **remplacement ponctuel** d'un professeur possible sur une session ; rôle « principal » **sans impact sur la rémunération** ; remplacement ponctuel d'un professeur **sans contrainte liée aux timesheets**.
- Assignation **bidirectionnelle** (classe ⇄ professeur) avec **propagation aux sessions à venir** de la classe.
- Sessions : **numéro de séance fixe (1..14)** ; une session annulée le conserve et sa remplaçante est un **bis** ; le nombre de sessions peut dépasser 14 ; **aucune session après la fin de la période**.
- Liens du cours : **généraux** (toutes séances) et **par numéro de séance (1..14)**, au niveau du cours.
- **Mock-ups CLS-01 validés (2026-09-30)**, sous réserve de l'alignement des mock-ups sur la règle « bis / numéro de séance fixe ».
- Modification des liens = **historique versionné (6 mois) avec annulation/restauration** par tous les professeurs du cours.
- **Aucune notification** sur les changements de session.
- Liens du cours éditables par **tous** les professeurs ayant une classe de ce cours.
- Base de test : **MySQL dédiée dans un container Docker**.

**Chantiers de remise à niveau** (stories techniques, par ordre de valeur) :

1. **Refonte du modèle Cours/Classe/Session (sans migration de données)** (ADR-0001, Canvas CLS-01) : tables `annees_scolaires`, `classes`, `professeur_classe` ; re-rattacher `course_sessions` à `classe_id` ; remplacer `professeur_cours` et `course_recurrences` ; supprimer les types de cours (back, routes, seeders, `TypesCoursAdminPage`, `SimpleLookupAdmin`, menu).
2. Base de test MySQL Docker + garde-fou (`db_test`, `.env.testing`, `phpunit.xml`), puis tests d'isolation/permissions sur Timesheets, Sessions, Classes, Liens.
3. Brancher `useAdminCRUD` sur `api/client.js`, retirer le `console.log` du client — *quick win, bug de token potentiel*.
4. Centraliser statuts/libellés (`utils/statuts.js`) et corriger la désynchronisation timesheets (audit UI/UX).
5. `FormRequest` + Services sur `CourseSessionController` (corriger l'expression `??` et la parenthèse en trop dans `store`), résoudre le `TODO` timesheets liées avant `destroy`.
6. Ajouter `can`/`allowed_transitions` aux Resources Timesheet et Session.
7. Harmoniser le nommage (`professor_*` → `professeur_*`, valeurs d'enum sans accent) — à faire **dans** la refonte du point 1 pour ne migrer qu'une fois.
8. Vitest + RTL, puis Playwright sur le parcours d'encodage d'heures.
9. Vérifier que `.env.deploy.prod` n'est pas suivi par git.

**Points encore ouverts** : aucune question métier bloquante (voir Canvas CLS-01 §12, points « à confirmer »). Prochaine barrière : réalignement des mock-ups (bis, numéros fixes), puis T0.
