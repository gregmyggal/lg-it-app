# Sprint 2: Index & Navigation

Bienvenue! Ce fichier vous aide à naviguer les 4 documents du plan Sprint 2.

---

## 📄 Les 4 Piliers du Plan

### 1️⃣ **SPRINT_2_EXECUTIVE_SUMMARY.md** (One-Pager)
**Pour:** Managers, stakeholders, execs  
**Durée:** 5 min  
**Contient:**
- Vue d'ensemble objectif + phases
- Valeur métier (ROI: -60% charge admin)
- Risques & mitigations
- Timeline: 6 semaines, 3 équipes
- Q&A rapides
- **Lien:** Partagez ceci à la direction

➜ **Lire si:** Vous avez 5 minutes et voulez comprendre "pourquoi + combien"

---

### 2️⃣ **SPRINT_2_PLAN.md** (Architecture Complète)
**Pour:** Tech leads, architectes  
**Durée:** 30-45 min  
**Sections:**
1. Sommaire exécutif
2. Architecture BD (3 tables + relations Eloquent)
3. Backend API (15+ endpoints détaillés)
4. Frontend Components (8 components + pages)
5. Workflows (6 workflows complets)
6. Testing (unitaires + API + E2E)
7. Phases 2A/2B/2C (dépendances + livrables)
8. Ressources & timeline
9. Risks & mitigations
10. Futures améliortations (Sprint 3+)

**Highlights:**
- Schema exact des tables (DDL)
- Structure JSON de chaque endpoint (request/response)
- Props/state de chaque component
- 12 workflows user illustrés
- Mapping user stories → issues GitHub

➜ **Lire si:** Vous devez valider l'architecture avant dev

---

### 3️⃣ **SPRINT_2_USER_STORIES.md** (Pour Devs)
**Pour:** Développeurs backend/frontend  
**Durée:** 1-2h (par phase)  
**Contient:**
- 32 user stories complètes (2A: 8 stories | 2B: 12 stories | 2C: 12 stories)
- Pour chaque story:
  - User story (En tant que X, je veux Y, afin de Z)
  - Acceptance criteria (checklist)
  - Tasks techniques (détaillés)
  - Dépendances
  - Estimations (story points)
  - Tests requis
  - Notes

**Structure par phase:**
- **Phase 2A (47 pts):** Fondations (migrations, CRUD, calendrier basique)
- **Phase 2B (91 pts):** Workflows (récurrence, override, drag-drop)
- **Phase 2C (68 pts):** Polish (export, notifications, perf)

**Bonus:**
- Mapping stories → assignations
- Velocity estimée: 34 pts/semaine
- Template story/migration/test

➜ **Lire si:** Vous allez coder une story (c'est votre bible)

---

### 4️⃣ **SPRINT_2_TECH_GUIDE.md** (Code Patterns)
**Pour:** Développeurs juniors/confirmés  
**Durée:** 30 min (référence pendant dev)  
**Contient:**
- Setup rapide (git, composer, npm, docker)
- **Patterns Backend (Laravel):**
  - Structure dossiers
  - Model avec relations (4 exemples)
  - Controller CRUD complet
  - Migration avec indexes
  - Job async (récurrence)
  - Tests API (PHPUnit)
- **Patterns Frontend (React):**
  - Structure dossiers
  - Hook custom (React Query)
  - Component avec state/form
  - Tests component (Jest)
- **Workflows communs:**
  - Créer migration + modèle
  - Créer controller + tests
  - Créer component + tests
- Debugging tips (Tinker, React DevTools, etc.)
- Checklist phase 2A
- Ressources officielles

**Code Examples:**
- CourseSession model (20 lignes)
- CourseSessionController@store (40 lignes)
- SessionDetailModal component (80 lignes)
- Tests (30 lignes chaque)

➜ **Lire si:** Vous codez et vous voulez du copy-paste de patterns

---

## 🎯 Workflows par Rôle

### Backend Developer
```
1. Lis SPRINT_2_EXECUTIVE_SUMMARY (5 min)
   → Comprendre l'objectif global
   
2. Lis SPRINT_2_PLAN.md section 2 (Architecture BD) (10 min)
   → Valider les tables + relations
   
3. Lis SPRINT_2_PLAN.md section 3 (Backend API) (20 min)
   → Comprendre les endpoints
   
4. Lis SPRINT_2_TECH_GUIDE.md (30 min)
   → Patterns + exemples code
   
5. Lis SPRINT_2_USER_STORIES.md (1h)
   → Ta story assignée + dépendances
   
6. Code + Debug → Référence SPRINT_2_TECH_GUIDE.md
```

### Frontend Developer
```
1. Lis SPRINT_2_EXECUTIVE_SUMMARY (5 min)
   
2. Lis SPRINT_2_PLAN.md section 4 (Frontend Components) (15 min)
   → Props, state, features
   
3. Lis SPRINT_2_TECH_GUIDE.md (React patterns) (20 min)
   → Hooks, components, tests
   
4. Lis SPRINT_2_USER_STORIES.md (1h)
   → Ta/tes stories
   
5. Code + Debug → Référence SPRINT_2_TECH_GUIDE.md
```

### Tech Lead / Architect
```
1. Lis SPRINT_2_EXECUTIVE_SUMMARY (5 min)
   
2. Lis SPRINT_2_PLAN.md (45 min)
   → Valider toute architecture
   
3. Lis SPRINT_2_USER_STORIES.md (1h)
   → Valider estimations + assignations
   
4. Lis SPRINT_2_TECH_GUIDE.md (20 min)
   → Valider patterns proposés
   
5. Review PRs dans chaque phase
```

### Manager / Product Owner
```
1. Lis SPRINT_2_EXECUTIVE_SUMMARY (5 min)
   → Prendre decision go/no-go
   
2. Lis SPRINT_2_PLAN.md sections 7-8 (Phases + Risks) (10 min)
   → Comprendre timeline + risques
   
3. Attend sprint planning (jour 1)
```

### QA / Testeur
```
1. Lis SPRINT_2_EXECUTIVE_SUMMARY (5 min)
   
2. Lis SPRINT_2_PLAN.md section 6 (Testing) (15 min)
   → Stratégie de test complète
   
3. Lis SPRINT_2_USER_STORIES.md section "Tests Requis" (1h)
   → Chaque story a ses tests
   
4. Lis SPRINT_2_TECH_GUIDE.md (20 min)
   → Patterns test
   
5. Setup CI/CD + écris tests E2E
```

---

## 🔍 Naviguer par Sujet

### Je veux connaître...

**...la structure BD?**
→ SPRINT_2_PLAN.md § 1 (Architecture)

**...les endpoints API?**
→ SPRINT_2_PLAN.md § 2 (Backend API)
→ Endpoint exact? → SPRINT_2_USER_STORIES.md recherche story ID (ex: 2A-2)

**...les components React?**
→ SPRINT_2_PLAN.md § 3 (Frontend Components)
→ Component exact? → SPRINT_2_USER_STORIES.md recherche story ID

**...le workflow d'un use case?**
→ SPRINT_2_PLAN.md § 4 (Workflows & Features)

**...les phases et dépendances?**
→ SPRINT_2_PLAN.md § 6 (Phases)
→ SPRINT_2_USER_STORIES.md (dépendances à chaque story)

**...les risques?**
→ SPRINT_2_EXECUTIVE_SUMMARY.md (tableau)
→ SPRINT_2_PLAN.md § 8 (détaillé)

**...un exemple de code?**
→ SPRINT_2_TECH_GUIDE.md (patterns)

**...une story assignée?**
→ SPRINT_2_USER_STORIES.md + chercher par ID

**...le timeline?**
→ SPRINT_2_EXECUTIVE_SUMMARY.md (jalons)
→ SPRINT_2_PLAN.md § 8 (ressources)

---

## 📊 Stats du Plan

| Métrique | Valeur |
|----------|--------|
| Effort total | 206 story points |
| Durée | 6 semaines |
| Équipe | 3 devs (2 backend, 1.5 frontend, 0.5 QA) |
| Velocity estimée | 34 pts/semaine |
| User stories | 32 (dont 8 en 2A, 12 en 2B, 12 en 2C) |
| Endpoints API | 15+ |
| Components React | 8 |
| Pages | 3 nouvelles |
| Tables BD | 3 nouvelles |
| Tests requis | 32 unit + 25 API + 20 component + 8 E2E |
| Phases | 3 (2A/2B/2C) |
| Dépendances | ~15 |

---

## 🚀 Démarrage du Sprint

### Jour 1 (Lundi)
- [ ] Toute l'équipe lit EXECUTIVE_SUMMARY (5 min)
- [ ] Tech Lead présente PLAN (20 min)
- [ ] Sprint planning (2h)
  - Lire SPRINT_2_USER_STORIES.md ensemble
  - Assigner stories
  - Setup branches git
- [ ] Setup CI/CD pour tests
- [ ] Dev 1 lance 2A-1 (migrations)

### Jour 8 (Vendredi Phase 2A)
- [ ] Demo 2A (30 min)
  - Montrer calendrier read-only
  - Montrer CRUD API avec Postman
- [ ] Retrospective + notes
- [ ] Sprint planning 2B (30 min)

### Jour 16 (Vendredi Phase 2B)
- [ ] Demo 2B (45 min)
  - Montrer calendrier interactif (drag-drop)
  - Montrer récurrence + auto-génération
  - Montrer override professeur
- [ ] Retrospective

### Jour 24 (Vendredi Phase 2C)
- [ ] Demo 2C (60 min)
  - Montrer export PDF
  - Montrer notifications email
  - Montrer performance (cache, queries)
- [ ] Validation finale
- [ ] Merge vers production

### Jour 25+ (Staging + Production)
- [ ] UAT avec stakeholders
- [ ] Bugfixes si besoin
- [ ] Déploiement production

---

## 💾 Importer dans Jira/Azure Boards

Les stories SPRINT_2_USER_STORIES.md sont au format importable:

```bash
# Copier la section 2A User Stories
# Coller dans Jira "Create Issue Bulk"
# Format reconnu: titre + acceptance criteria
```

**Champs à remplir manuellement:**
- Assigné: frontend dev 1, backend dev 1, etc.
- Sprint: Sprint 2A
- Labels: Sprint2A, Backend/Frontend, P0/P1

---

## ❓ Besoin d'Aide?

**Si tu cherches...**

| Besoin | Référence | Pages |
|--------|-----------|-------|
| Objectif sprint | EXECUTIVE_SUMMARY | § 1 |
| Schema BD exact | PLAN | § 1 |
| Endpoint détaillé | USER_STORIES | $ 2A-2 |
| Component props | PLAN | § 3 |
| Code pattern | TECH_GUIDE | § 2 |
| Validation rules | USER_STORIES | story assignée |
| Cas d'erreur | TECH_GUIDE | Pattern controller |
| Tests | PLAN § 5 ou USER_STORIES |  |
| Timeline | EXECUTIVE_SUMMARY | § 3 |
| Assigné | USER_STORIES | § Assignations |

---

## 📝 Conventions

**Dans les documents:**
- **Gras** = terme important
- `code` = nom de classe/fonction
- `> Citation` = réponse à question
- [ ] Checkbox = task à faire
- ✅ Checkmark = complété

**Tous les chemins de fichier sont absolus:**
```
app/Models/CourseSession.php
tests/Feature/CourseSessionControllerTest.php
```

**Tous les endpoints sont relatifs à `/api`:**
```
GET /api/course-sessions
POST /api/course-sessions/{id}/professor
```

**Story IDs format:**
```
2A-1 = Phase 2A, Story 1
2B-7 = Phase 2B, Story 7
2C-12 = Phase 2C, Story 12
```

---

## 📞 Points de Contact

**Pour questions sur:**
- **Architecture BD** → Tech Lead
- **Backend API** → Backend Dev 1
- **Frontend** → Frontend Dev 1
- **Tests** → QA Lead
- **Timeline/Risques** → Product Owner
- **DevOps** → DevOps Engineer

---

## ✨ Bonus: Commandes Útiles

```bash
# Cloner le plan markdown
git clone repo && cd lg-it-app

# Lire les docs (en terminal)
less SPRINT_2_EXECUTIVE_SUMMARY.md  # ESC pour sortir
less SPRINT_2_PLAN.md

# Exporter en PDF (si pandoc installé)
pandoc SPRINT_2_PLAN.md -o SPRINT_2_PLAN.pdf

# Chercher une story
grep "2A-1:" SPRINT_2_USER_STORIES.md -A 20

# Rechercher un endpoint
grep "POST /api/course-sessions" SPRINT_2_PLAN.md -B 5 -A 20
```

---

## 📚 Ordre de Lecture Conseillé

### Option 1: Découverte Rapide (15 min)
1. EXECUTIVE_SUMMARY (5 min)
2. PLAN § 1 + § 6 (10 min)
3. TECH_GUIDE démarrage rapide (5 min)

### Option 2: Planification (2h)
1. EXECUTIVE_SUMMARY (5 min)
2. PLAN complète (45 min)
3. USER_STORIES (1h)
4. TECH_GUIDE patterns (15 min)

### Option 3: Implémentation (3+ jours)
1. TECH_GUIDE setup (30 min)
2. USER_STORIES assignée (1h)
3. CODE + DEBUG (code)
4. USER_STORIES tests requis (30 min)
5. TECH_GUIDE patterns (on demand)

---

## ✅ Checklist Avant Démarrage

- [ ] Toute l'équipe a lu EXECUTIVE_SUMMARY
- [ ] Tech Lead a validé PLAN architecture
- [ ] Backend devs ont lu USER_STORIES 2A
- [ ] Frontend devs ont lu USER_STORIES 2A
- [ ] QA a plan de test (PLAN § 5)
- [ ] CI/CD configuré pour tests
- [ ] Branches git créées (feature/sprint2a-*)
- [ ] Staging environment prêt
- [ ] Database backup en place
- [ ] Slack channel sprint2 créé

---

**Bon développement! 🚀**

Créé: 29 sept 2026  
Version: 1.0  
Prêt pour sprint planning
