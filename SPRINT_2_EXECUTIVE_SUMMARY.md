# Sprint 2: Résumé Exécutif (One-Pager)

**Dates:** 6 semaines | Sept 29 - Nov 10, 2026  
**Équipe:** 3 devs (Backend × 2, Frontend × 1.5, QA × 0.5)  
**Effort:** 206 story points | ~34 points/semaine

---

## 🎯 Objectif

Transformer le système de gestion de cours en ajoutant les **sessions concrètes** (occurrences planifiées) avec calendrier interactif, récurrence automatique et gestion d'agenda.

### Avant (Sprint 1)
```
Cours → Professeurs assignés
```

### Après (Sprint 2)
```
Cours → Récurrence → Sessions (dates/heures/lieux) → Professeurs/Présence
      ↓
    Calendrier interactif mois/semaine/agenda
```

---

## 📦 Livrables Majeurs

### Base de Données
- **3 nouvelles tables:** `course_sessions`, `course_recurrences`, `session_professors`
- **Relations:** Modèles Eloquent avec scopes et requêtes optimisées
- **Seeders:** Données de test pour développement

### Backend API (15+ endpoints)
| Catégorie | Endpoints | Impact |
|-----------|-----------|--------|
| **Sessions CRUD** | GET/POST/PUT/DELETE/PATCH sessions | Gestion complète |
| **Récurrence** | Génération automatique (async job) | 50+ sessions en 2s |
| **Calendrier** | Month/Year/Agenda views | Navigation fluide |
| **Override Prof** | Changer principal, ajouter assistant | Remplaçants gérés |

### Frontend Components (8+ components)
- `<CalendarView />` - Mois/semaine/agenda avec drag-drop
- `<SessionDetailModal />` - Create/edit/delete sessions
- `<RecurrenceForm />` - Générer sessions automatiques
- `<ProfessorCalendarView />` - Vue personnalisée prof
- Pages: Calendar, RecurrencesAdmin, CoursDetail

### Tests
- **32 unit tests** (modèles)
- **25 API tests** (endpoints)
- **20+ component tests** (React Jest)
- **8+ E2E tests** (workflows complets)

---

## 📊 Phases (3 × 2 semaines)

```
Phase 2A (Sem 1-2): Fondations [47 pts]
├─ Migrations + Modèles + CRUD API + Calendrier basique
└─ Livrables: API fonctionnelle, CalendarView read-only

Phase 2B (Sem 3-4): Récurrence + Workflows [91 pts]
├─ Génération sessions (async job)
├─ Override professeur, présence/absence
├─ Drag-drop, filtres, 3 modes de vue
└─ Livrables: Calendrier complet interactif, workflows

Phase 2C (Sem 5-6): Polish + Performance [68 pts]
├─ Export PDF, notifications email, audit logs
├─ Caching, optimisation DB, dark mode
├─ Documentation API, tests load
└─ Livrables: Production-ready, docs complètes
```

---

## 💼 Valeur Métier

| Cas d'Usage | Avant | Après | Gain |
|-------------|-------|-------|------|
| **Créer 45 sessions** | 45 clics manuels (45 min) | 1 récurrence (2 min) | **95% temps** ✅ |
| **Voir agenda du mois** | Requête DB + liste → paginer | Calendrier visuel interactif | **UX** ✅ |
| **Gérer remplaçant** | Éditer + notification manuelle | Modal override + notif auto | **Efficacité** ✅ |
| **Enregistrer présences** | Feuille + saisie manuelle | Modal intégré | **Traçabilité** ✅ |

**Résultat:** Directeurs gèrent leur agenda complet; Professeurs voient leurs sessions; Système réduit charge admin de ~60%.

---

## 🔧 Stack & Dépendances

| Layer | Tech | Raison |
|-------|------|--------|
| **Backend** | Laravel 11 + Eloquent | Migrations, Jobs async, Tests natifs |
| **Frontend** | React 18 + React Query | Fetch déclaratif, caching, offline support |
| **DB** | PostgreSQL 15 | Enums, JSON, indexes (sprint 1 setup) |
| **Queue** | Redis (async job) | Génération sessions sans bloquer API |
| **Tests** | PHPUnit + Jest + Cypress | Couverture complète backend/frontend/E2E |
| **Deploy** | Docker Compose | Local dev, staging (production: futur) |

**Aucune dépendance externe nouvelle.** Utiliser libs éprouvées (react-big-calendar).

---

## ⚠️ Risques & Mitigations

| Risk | Probab. | Impact | Mitigation |
|------|---------|--------|-----------|
| Génération sessions lente | Moyen | Haut | Job async + batching (50 sessions/batch) |
| Drag-drop buggé | Moyen | Moyen | Lib react-big-calendar + 15 tests |
| N+1 queries | Haute | Moyen | Eager loading + Laravel Debugbar |
| Tests E2E flaky | Moyen | Bas | Selectors robustes + retry logic |

**Mitigation clé:** Tests complets en chaque phase avant merge.

---

## 📈 Métriques de Succès

**Technique:**
- ✓ 100% tests passent (32 unit + 25 API + 20 component + 8 E2E)
- ✓ API response < 200ms pour calendrier mois
- ✓ Génération 100 sessions < 5s
- ✓ 0 console errors en frontend

**Produit:**
- ✓ Directeur crée 50 sessions en 2 minutes (test utilisateur)
- ✓ Professeur marque présence < 10 secondes
- ✓ Calendrier drag-drop fonctionne sur mobile

**Équipe:**
- ✓ Velocity 34 points/semaine (objectif: 32-36)
- ✓ Code review turn-around < 24h
- ✓ 0 hotfixes post-demo

---

## 👥 Assignations Proposées

```
Backend Developer 1 (Greg Pierquin)
├─ Migrations + Modèles (2A-1)
├─ API CRUD Sessions (2A-2, 2A-3)
├─ Récurrence + Job Async (2B-1)
├─ Override Professeur (2B-2)
└─ Support code review

Backend Developer 2
├─ SessionProfessor CRUD (2B-3, 2B-4, 2B-5)
├─ Tests unitaires (2A-8, 2B-11)
└─ Intégration timesheets future (2C-12)

Frontend Developer 1
├─ CalendarView (2A-4)
├─ SessionDetailModal (2A-5)
├─ RecurrenceForm (2B-6)
├─ CalendarView complète (2B-7)
└─ RecurrencesAdminPage (2B-10)

Frontend Developer 2 (0.5 FTE)
├─ SessionCard (2A-6)
├─ CoursAdminPage intégration (2A-7)
├─ SessionList (2B-9)
├─ ProfessorCalendarView (2B-8)
└─ Responsive design (2C-10)

QA/DevOps (0.5 FTE)
├─ Setup CI/CD tests
├─ Tests E2E (2B-12)
├─ Performance testing (2C-9)
└─ Monitoring production
```

---

## 📅 Jalons

| Date | Jalon | Livrable |
|------|-------|----------|
| **J8 (Fri)** | Fin 2A | API CRUD + CalendarView read-only |
| **J16 (Fri)** | Fin 2B | Calendrier interactif + récurrence |
| **J24 (Fri)** | Fin 2C | Production-ready + docs |
| **J25 (Mon)** | Déploiement staging | UAT avec stakeholders |
| **J28 (Fri)** | Déploiement production | Go live |

---

## 🚀 Prochaines Étapes

1. **Valider ce plan** (aujourd'hui)
2. **Planifier sprint 2A** (demain)
   - Créer issues GitHub
   - Assigner devs
   - Setup branches
3. **Lancer 2A** (lundi)
   - Daily standups 10:00 AM
   - Demo jeudi avant sprint
4. **Sprint planning 2B** (jour 8)

---

## 📚 Documentation

Trois docs complémentaires disponibles:

| Doc | Usage | Pages |
|-----|-------|-------|
| **SPRINT_2_PLAN.md** | Planification détaillée | 25 |
| **SPRINT_2_USER_STORIES.md** | Assignations devs | 40 |
| **SPRINT_2_TECH_GUIDE.md** | Patterns & code | 20 |

**Format:** Markdown prêt pour partage équipe / import Jira.

---

## ❓ Q&A Rapides

**Q: Peut-on paralléliser 2A et 2B?**  
A: Partiellement. Front (2A-4/5/6) peut commencer avant backend terminé si API mockée. Gain: ~3-4 jours.

**Q: Et si la génération sessions est trop lente?**  
A: Batching (50 sessions/batch) + monitoring Redis queue + warning si > 10s. Fallback: générer au clique sans async.

**Q: Faut-il des migrations de production?**  
A: Oui. Prévoir downtime ~5 min pour 3 migrations (add tables). Script d'annulation prêt.

**Q: Qui fait la documentation API?**  
A: Backend dev 1 (2C-8, 2 jours). Utiliser Swagger/OpenAPI generator.

**Q: Est-on multi-tenant ou single-tenant?**  
A: Single tenant (école). Pas d'isolation par tenant dans Sprint 2. À revisiter Sprint 4.

---

## ✅ Approbations Requises

- [ ] Product Owner: Priorités & scope
- [ ] Tech Lead: Architecture + DB
- [ ] QA: Plan test
- [ ] DevOps: CI/CD + monitoring
- [ ] Stakeholders: Timeline & coûts

---

**Statut:** 🟢 Prêt pour sprint planning  
**Créé:** 29 sept 2026  
**Auteur:** Gregory Pierquin + Claude Haiku  
**Révision:** 1.0
