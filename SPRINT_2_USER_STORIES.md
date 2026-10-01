# Sprint 2: User Stories Détaillées

Format: Chaque story est complète avec AC, tasks techniques, dépendances, et ressources estimées.

---

## Phase 2A: Fondations DB + API Basique + UI Simple

### 2A-1: Migrations & Modèles Eloquent

**ID:** 2A-1  
**Points:** 5  
**Assigné à:** Backend Dev 1  
**Dépendances:** Sprint 1 complété  
**Priorité:** P0 (bloquante)

**User Story:**
```
En tant que développeur backend,
Je veux créer les tables course_sessions, course_recurrences, et session_professors,
Afin que les sessions concrètes puissent être stockées et gérées.
```

**Acceptance Criteria:**
- [ ] Migration `create_course_sessions_table` crée la table avec colonnes correctes
- [ ] Migration `create_course_recurrences_table` crée la table avec règles de récurrence
- [ ] Migration `create_session_professors_table` crée la table de pivot
- [ ] Modèle `CourseSession` avec relations: `Cours`, `CourseRecurrence`, `SessionProfessor`, `Professeur`
- [ ] Modèle `CourseRecurrence` avec relations: `Cours`, `CourseSession`
- [ ] Modèle `SessionProfessor` avec relations: `CourseSession`, `Professeur`
- [ ] Relations bidirectionnelles dans `Cours` et `Professeur`
- [ ] Migrations up/down sont réversibles
- [ ] Tests unitaires pour relations passent (5+ tests)

**Tasks Techniques:**
1. [ ] Créer migration `2026_XX_XX_create_course_sessions_table.php` (2h)
   - Colonnes: id, cours_id, recurrence_id, date_debut, heure_debut, heure_fin, titre, lieu, description, statut, motif_annulation, professor_principal_id, nb_eleves_attendus, nb_eleves_presentes, created_at, updated_at, cancelled_at
   - Constraints: FK cours, FK recurrence (nullable), FK professor
   - Indexes: (cours_id, date_debut), (professor_principal_id, date_debut), (statut, date_debut), UNIQUE(cours_id, date_debut, heure_debut)

2. [ ] Créer migration `2026_XX_XX_create_course_recurrences_table.php` (1.5h)
   - Colonnes: id, cours_id, type (enum), jours_semaine, date_debut, date_fin, heure_debut, heure_fin, lieu_defaut, statut, created_at, updated_at
   - Constraints: FK cours
   - Indexes: (cours_id, statut), (date_debut, date_fin)

3. [ ] Créer migration `2026_XX_XX_create_session_professors_table.php` (1.5h)
   - Colonnes: id, course_session_id, professeur_id, role (enum), present, motif_absence, created_at, updated_at
   - Constraints: FK course_sessions, FK professeurs
   - Indexes: (course_session_id), (professeur_id, created_at), UNIQUE(course_session_id, professeur_id, role)

4. [ ] Créer modèle `CourseSession` avec relations (1h)
   - Methods: `belongsTo(Cours)`, `belongsTo(Professeur)`, `belongsTo(CourseRecurrence)`, `hasMany(SessionProfessor)`, `hasManyThrough(Professeur, SessionProfessor)`

5. [ ] Créer modèle `CourseRecurrence` avec relations (0.5h)
   - Methods: `belongsTo(Cours)`, `hasMany(CourseSession)`

6. [ ] Créer modèle `SessionProfessor` avec relations (0.5h)
   - Methods: `belongsTo(CourseSession)`, `belongsTo(Professeur)`

7. [ ] Mettre à jour modèle `Cours` avec relations (0.5h)
   - Ajouter: `hasMany(CourseSession)`, `hasMany(CourseRecurrence)`

8. [ ] Mettre à jour modèle `Professeur` avec relations (0.5h)
   - Ajouter: `hasMany(CourseSession)`, `hasMany(SessionProfessor)`, `hasManyThrough(CourseSession, SessionProfessor)`

9. [ ] Écrire tests unitaires (1.5h)
   - `CourseSessionTest`: relations, fillable, casts
   - `CourseRecurrenceTest`: relations, validations
   - `SessionProfessorTest`: relations, constraints

**Notes & Ressources:**
- Consulter commit `aa491d2` pour conventions de ce projet
- Utiliser `$casts` pour date/time si nécessaire
- Ajouter timestamps partout
- Soft-delete possible futur (non implémenté phase 2A)

**Estimé:** 8h
**Réel:** TBD

---

### 2A-2: API Sessions CRUD Complète

**ID:** 2A-2  
**Points:** 8  
**Assigné à:** Backend Dev 1  
**Dépendances:** 2A-1  
**Priorité:** P0

**User Story:**
```
En tant qu'administrateur,
Je veux accéder à une API REST complète pour gérer les sessions,
Afin que je puisse créer, lire, modifier, supprimer les sessions.
```

**Acceptance Criteria:**
- [ ] `GET /api/course-sessions` retourne liste paginée avec filtres
- [ ] `POST /api/course-sessions` crée une session avec validation complète
- [ ] `GET /api/course-sessions/{id}` retourne détails d'une session
- [ ] `PUT /api/course-sessions/{id}` modifie une session
- [ ] `DELETE /api/course-sessions/{id}` supprime une session
- [ ] `PATCH /api/course-sessions/{id}/statut` change le statut
- [ ] Toutes les validations retournent 422 avec messages d'erreur clairs
- [ ] Permissions contrôlées (Admin: full, Professeur: GET seul, Élève: 404)
- [ ] Tests API passent (15+ tests)

**Tasks Techniques:**
1. [ ] Créer `CourseSessionController@index` (2h)
   - Query params: cours_id, date_from, date_to, statut, professeur_id, lieu, sort_by, page, per_page
   - Retourner avec relations: cours, professeurs, professor_principal
   - Pagination: 25 par défaut
   - Tri: date_debut (défaut)
   - Tests: 3+ tests

2. [ ] Créer `CourseSessionController@store` (2h)
   - Validations: cours_id exists, date_debut >= today, heure_debut < heure_fin, no duplicate
   - Assignation professeur principal (optionnel, par défaut cours.professeur_principal)
   - Retourner session créée avec 201
   - Tests: 4+ tests (validation errors, success, permissions)

3. [ ] Créer `CourseSessionController@show` (0.5h)
   - Retourner avec relations complètes
   - Tests: 2+ tests

4. [ ] Créer `CourseSessionController@update` (1.5h)
   - Validations: pas de modification si completed ou cancelled
   - Champs modifiables: titre, lieu, description, heure_*, professeur_principal_id, nb_eleves_attendus
   - Tests: 4+ tests

5. [ ] Créer `CourseSessionController@destroy` (1h)
   - Rules: hard-delete si pas de timesheets, sinon soft-delete (futur)
   - Tests: 2+ tests

6. [ ] Créer `CourseSessionController@updateStatut` (1h)
   - PATCH endpoint pour changer statut
   - Transitions valides: scheduled→in_progress→completed, *→cancelled
   - Tests: 3+ tests

7. [ ] Mettre à jour routes `routes/api.php` (0.5h)
   - Ajouter resourceful routes pour CourseSession
   - Ajouter PATCH /statut route

**Validation Rules (Laravelish):**
```php
// Store/Update
$validated = $request->validate([
    'cours_id' => 'required|exists:cours,id',
    'date_debut' => 'required|date|after_or_equal:today',
    'heure_debut' => 'required|date_format:H:i',
    'heure_fin' => 'required|date_format:H:i|after:heure_debut',
    'titre' => 'nullable|string|max:255',
    'lieu' => 'nullable|string|max:255',
    'description' => 'nullable|string',
    'professor_principal_id' => 'nullable|exists:professeurs,id',
    'nb_eleves_attendus' => 'nullable|integer|min:0',
]);

// Unique: (cours_id, date_debut, heure_debut)
// Custom rule: date_debut + heure_debut ne doit pas dupliquer une autre session du même cours
```

**Tests Requis:**
- `tests/Feature/CourseSessionControllerTest.php`: 15+ tests
  - Index: list, filters (cours_id, statut, date_range), pagination
  - Store: success, validations, permissions
  - Show: exists, not_found
  - Update: success, validations, read-only statuts
  - Destroy: success, permissions
  - UpdateStatut: transitions valides, invalid transitions

**Notes:**
- Utiliser Policy pour permissions (`can update`, `can delete`)
- Utiliser Resource pour transformation JSON
- Eager loading: `with('cours', 'professeurs', 'professeurPrincipal')`

**Estimé:** 8h
**Réel:** TBD

---

### 2A-3: Calendrier API (Get Month)

**ID:** 2A-3  
**Points:** 5  
**Assigné à:** Backend Dev 1  
**Dépendances:** 2A-2  
**Priorité:** P0

**User Story:**
```
En tant qu'administrateur,
Je veux récupérer un calendrier du mois avec toutes les sessions,
Afin que je puisse afficher un vue mois dans l'interface.
```

**Acceptance Criteria:**
- [ ] `GET /api/calendar/month?year=2026&month=10` retourne structure jour+sessions
- [ ] Retour inclut tous les jours du mois (1-31) avec sessions du jour
- [ ] Sessions triées par heure_debut
- [ ] Filtres optionnels: professeur_id, cours_id, statut
- [ ] Response correctement formatée (JSON valide)
- [ ] Tests passent (5+ tests)

**Tasks Techniques:**
1. [ ] Créer `CalendarController@month` (2h)
   - Query params: year, month, professeur_id, cours_id, statut
   - Retourner structure: { year, month, days: [{date, day_of_week, sessions: [...]}] }
   - Sessions avec: id, cours_titre, heure, professeur, statut, lieu

2. [ ] Créer tests API (1h)
   - Test: year=2026, month=10
   - Test: filtres professeur_id
   - Test: filtres statut
   - Test: sessions triées
   - Test: jour sans sessions

**Response Format:**
```json
{
  "data": {
    "year": 2026,
    "month": 10,
    "days": [
      {
        "date": "2026-10-01",
        "day_of_week": 3,
        "sessions": [
          {
            "id": 1,
            "cours_id": 5,
            "cours_titre": "Java Avancé",
            "heure_debut": "09:30",
            "heure_fin": "11:30",
            "professeur_nom": "Dupont",
            "statut": "scheduled",
            "lieu": "Salle 201"
          }
        ]
      }
    ]
  }
}
```

**Estimé:** 3h
**Réel:** TBD

---

### 2A-4: CalendarView Component (React)

**ID:** 2A-4  
**Points:** 8  
**Assigné à:** Frontend Dev 1  
**Dépendances:** 2A-3  
**Priorité:** P0

**User Story:**
```
En tant qu'utilisateur,
Je veux voir un calendrier affichant les sessions du mois,
Afin que je puisse visualiser mon agenda.
```

**Acceptance Criteria:**
- [ ] Affiche grille 7 colonnes (lun-dim) × 5-6 lignes (semaines)
- [ ] Chaque jour affiche numéro + sessions du jour
- [ ] Navigation mois précédent/suivant fonctionne
- [ ] Sessions affichées compactement (heure + cours + prof)
- [ ] Click sur jour ouvre modal création
- [ ] Click sur session ouvre détail modal
- [ ] Loading state pendant fetch API
- [ ] Tests Jest passent (8+ tests)

**Tasks Techniques:**
1. [ ] Créer composant `<CalendarView />` (3h)
   - Props: view='month', year, month, coursId, professeurId, onSessionClick, onDateClick, loading
   - State: selectedMonth, selectedYear, sessions, loading
   - Effects: fetch sessions quand mois/année/filtres changent
   - Render: grille de 42 jours (padding avant/après mois)

2. [ ] Intégrer API avec React Query (1h)
   - `useQuery(['calendar-month', year, month], () => fetchCalendarMonth(...))`
   - Refetch quand changement mois/année
   - Gestion loading/error states

3. [ ] Navigation mois (1h)
   - Buttons "Mois précédent" / "Mois suivant"
   - Display "Septembre 2026"
   - Limite: pas avant 2026-01

4. [ ] Affichage sessions (1h)
   - SessionCard compacte par jour
   - Max 3 sessions visibles (overflow hidden)
   - Click session → onSessionClick callback

5. [ ] Responsive design (1h)
   - Desktop: grille 7 colonnes
   - Tablet: grille 3 colonnes
   - Mobile: liste par jour (mode dégradé)

6. [ ] Tests Jest (1.5h)
   - Render calendar
   - Navigation mois
   - Sessions affichées
   - Click handlers
   - Loading/error states

**Composant Structure:**
```jsx
<CalendarView>
  <header>
    <button>← Mois précédent</button>
    <h2>Septembre 2026</h2>
    <button>Mois suivant →</button>
  </header>
  
  <div className="calendar-grid">
    {/* 7 colonnes (lun-dim) */}
    {/* 6 lignes × 7 = 42 jours */}
    <div className="day">
      <div className="day-number">5</div>
      <SessionCard session={...} compact />
    </div>
  </div>
</CalendarView>
```

**Estimé:** 8h
**Réel:** TBD

---

### 2A-5: SessionDetailModal Component

**ID:** 2A-5  
**Points:** 8  
**Assigné à:** Frontend Dev 1  
**Dépendances:** 2A-2  
**Priorité:** P0

**User Story:**
```
En tant qu'utilisateur,
Je veux voir/éditer les détails d'une session dans un modal,
Afin que je puisse créer ou modifier une session.
```

**Acceptance Criteria:**
- [ ] Modal affiche formulaire session (create ou edit mode)
- [ ] Champs: date_debut, heure_debut, heure_fin, cours_id, lieu, titre, description, professor_principal_id, nb_eleves_attendus
- [ ] Validations côté client (dates, heures, required fields)
- [ ] Boutons: Enregistrer, Annuler, Supprimer (si edit mode)
- [ ] Submit envoie à l'API (POST ou PUT)
- [ ] Affiche confirmation succès ou erreur
- [ ] Tests Jest passent (8+ tests)

**Tasks Techniques:**
1. [ ] Créer composant `<SessionDetailModal />` (3h)
   - Props: open, onClose, session (optional), coursId (optional), onSave, onDelete
   - State: formData, errors, loading
   - Create mode: formulaire vide + date par défaut = today
   - Edit mode: formulaire rempli avec données session

2. [ ] Formulaire (2h)
   - Input date: date_debut (min: today)
   - Input time: heure_debut, heure_fin (HH:mm)
   - Select: cours (dropdown courses)
   - Input text: lieu, titre (optionnels)
   - Select: professeur_principal (dropdown professeurs)
   - Input number: nb_eleves_attendus (optionnel)
   - Textarea: description (optionnel)

3. [ ] Validations (1h)
   - Client-side: required fields, date >= today, heure_debut < heure_fin
   - Server-side errors affichés sous chaque champ

4. [ ] Soumission (1h)
   - POST /api/course-sessions (create)
   - PUT /api/course-sessions/{id} (edit)
   - Success: affiche toast + onSave callback
   - Error: affiche message d'erreur

5. [ ] Action Supprimer (1h)
   - Button "Supprimer" visible en edit mode
   - Confirmation: "Êtes-vous sûr?"
   - DELETE /api/course-sessions/{id}
   - Success: ferme modal + onDelete callback

6. [ ] Tests Jest (1.5h)
   - Render create mode
   - Render edit mode
   - Validation errors
   - Submit success
   - Submit error
   - Delete confirmation

**Estimé:** 8h
**Réel:** TBD

---

### 2A-6: SessionCard Component

**ID:** 2A-6  
**Points:** 3  
**Assigné à:** Frontend Dev 2  
**Dépendances:** None (utilisé par CalendarView)  
**Priorité:** P1

**User Story:**
```
En tant que développeur,
Je veux un composant SessionCard réutilisable,
Afin de l'afficher dans calendrier/listes.
```

**Acceptance Criteria:**
- [ ] Affiche heure, cours, professeur, lieu compactement
- [ ] Click: onSessionClick callback
- [ ] Peut être en mode "compact" (mini) ou "normal"
- [ ] Couleur fond selon statut (scheduled=bleu, completed=vert, cancelled=gris)
- [ ] Tests passent (3+ tests)

**Tasks Techniques:**
1. [ ] Créer composant `<SessionCard />` (1h)
   - Props: session, onClick, compact=false
   - Render: `09:30-11:30 | Java | Dupont | Salle 201`

2. [ ] Styling (0.5h)
   - Tailwind: padding, border-radius, bg-color selon statut
   - Hover effect: curseur pointer, shadow

3. [ ] Tests (0.5h)
   - Render session
   - Click callback
   - Compact mode

**Estimé:** 2h
**Réel:** TBD

---

### 2A-7: Intégration CoursAdminPage (Onglet Sessions)

**ID:** 2A-7  
**Points:** 5  
**Assigné à:** Frontend Dev 2  
**Dépendances:** 2A-5  
**Priorité:** P1

**User Story:**
```
En tant qu'administrateur,
Je veux voir les sessions d'un cours dans sa page admin,
Afin de gérer facilement les sessions du cours.
```

**Acceptance Criteria:**
- [ ] Page admin cours a nouvel onglet "Sessions"
- [ ] Onglet affiche liste des sessions du cours
- [ ] Button "Nouvelle session" ouvre modal
- [ ] Button "Créer récurrence" visible (lien vers formulaire 2B-6)
- [ ] Sessions triées par date_debut
- [ ] Click session → ouvre détail modal
- [ ] Tests passent (3+ tests)

**Tasks Techniques:**
1. [ ] Modifier `CoursAdminPage` (1.5h)
   - Ajouter onglet "Sessions" à côté des onglets existants
   - Tab: affiche SessionList du cours

2. [ ] Créer `<SessionList />` composant (1.5h)
   - Props: coursId, sessions, onSessionClick, onNew
   - Render: tableau ou liste d'items
   - Chaque ligne: date, heure, prof, lieu, actions (edit/delete)

3. [ ] Button actions (1h)
   - "Nouvelle session" → ouvre modal création avec coursId pré-rempli
   - "Créer récurrence" → lien vers /admin/recurrences (Sprint 2B)
   - "Éditer" → ouvre modal edit

4. [ ] Tests (1h)

**Estimé:** 5h
**Réel:** TBD

---

### 2A-8: Tests Unitaires Backend (Models)

**ID:** 2A-8  
**Points:** 5  
**Assigné à:** Backend Dev 2  
**Dépendances:** 2A-1, 2A-2  
**Priorité:** P1

**User Story:**
```
En tant que développeur,
Je veux des tests unitaires complets pour les modèles,
Afin d'assurer la qualité du code.
```

**Acceptance Criteria:**
- [ ] `tests/Unit/CourseSessionTest.php`: 8+ tests
- [ ] `tests/Unit/CourseRecurrenceTest.php`: 5+ tests
- [ ] `tests/Unit/SessionProfessorTest.php`: 5+ tests
- [ ] Coverage >= 80% des modèles
- [ ] Tous les tests passent

**Tests Requis:**

**CourseSessionTest:**
1. [ ] Relations: belongsTo Cours, Professeur, CourseRecurrence
2. [ ] Relations: hasMany SessionProfessor, hasManyThrough Professeur
3. [ ] Fillable attributes correctes
4. [ ] Casts: date_debut→date, created_at→datetime, etc.
5. [ ] Scope upcomingDate()
6. [ ] Scope byStatut()
7. [ ] Scope byProfesseur()
8. [ ] Scope inDateRange()

**CourseRecurrenceTest:**
1. [ ] Relations: belongsTo Cours, hasMany CourseSession
2. [ ] Fillable attributes
3. [ ] Validation: date_fin >= date_debut
4. [ ] Validation: type in enum
5. [ ] Validation: jours_semaine valides (1-7)

**SessionProfessorTest:**
1. [ ] Relations: belongsTo CourseSession, Professeur
2. [ ] Fillable attributes
3. [ ] Constraint: unique (session_id, professeur_id, role)
4. [ ] Validation: role in enum
5. [ ] Validation: professeur exists

**Estimé:** 4h
**Réel:** TBD

---

## Phase 2B: Récurrence + Override + Workflows Complets

### 2B-1: Récurrence CRUD + Génération Sessions Async

**ID:** 2B-1  
**Points:** 13  
**Assigné à:** Backend Dev 1 + DevOps  
**Dépendances:** 2A-1, 2A-2  
**Priorité:** P0

**User Story:**
```
En tant qu'administrateur,
Je veux créer une règle de récurrence pour auto-générer les sessions,
Afin de ne pas créer manuellement 50+ sessions.
```

**Acceptance Criteria:**
- [ ] `POST /api/course-recurrences` crée récurrence + lance job
- [ ] `GET /api/course-recurrences?cours_id=X` liste les récurrences du cours
- [ ] `PATCH /api/course-recurrences/{id}/generate` force régénération
- [ ] `PATCH /api/course-recurrences/{id}/statut` pause/archive
- [ ] `DELETE /api/course-recurrences/{id}` supprime + sessions futures
- [ ] Job async génère sessions sans bloquer API (retour immédiat)
- [ ] Génération peut créer 100+ sessions sans timeout
- [ ] Tests API passent (10+ tests)

**Tasks Techniques:**
1. [ ] Créer `CourseRecurrenceController@index` (1h)
   - Query param: cours_id (required)
   - Retourner avec count sessions générées, next_session

2. [ ] Créer `CourseRecurrenceController@store` (2h)
   - Validations: cours_id exists, type in enum, date_fin >= date_debut, jours_semaine valides
   - Crée enregistrement CourseRecurrence
   - Dispatchs job: `GenerateCourseSessions::dispatch($recurrence_id)`
   - Retour 201 avec ID du job pour polling

3. [ ] Créer job `GenerateCourseSessions` (3h)
   - Input: recurrence_id
   - Logic:
     - Charge recurrence avec cours
     - Calcule dates selon type (weekly/biweekly/monthly)
     - Génère CourseSession pour chaque date
     - Assigne professor_principal si disponible
     - Limiter à max 200 sessions/job pour éviter timeout
   - Gestion erreurs: log + notification
   - Update recurrence.statut = 'active' si réussi

4. [ ] Créer `CourseRecurrenceController@generate` PATCH (1.5h)
   - Supprime sessions futures (date >= today)
   - Relance job de génération
   - Retourner confirmation + nb sessions créées

5. [ ] Créer `CourseRecurrenceController@updateStatut` (0.5h)
   - PATCH /api/course-recurrences/{id}/statut
   - Transitions: active→paused, active→archived, paused→active
   - Ne pas créer/supprimer sessions, juste marquer récurrence

6. [ ] Créer `CourseRecurrenceController@destroy` (1h)
   - Supprime enregistrement recurrence
   - Soft-delete ou hard-delete sessions générées? → Option: mark as cancelled
   - Retourner 204

7. [ ] Setup Queue (Redis) (1h)
   - Configurer Laravel pour utiliser Redis comme queue driver
   - `QUEUE_CONNECTION=redis` dans .env
   - Job travail asynchrone (artisan queue:work)

8. [ ] Tests API (2h)
   - POST: create, validations, job dispatched
   - GET: list by cours_id
   - PATCH generate: régénère correctement
   - PATCH statut: transitions
   - DELETE: supprime

**Job Logic Pseudocode:**
```php
class GenerateCourseSessions implements ShouldQueue {
    public function handle(CourseRecurrence $recurrence) {
        $sessions = [];
        $dates = $this->calculateDates($recurrence);
        
        foreach ($dates as $date) {
            $sessions[] = [
                'cours_id' => $recurrence->cours_id,
                'date_debut' => $date,
                'heure_debut' => $recurrence->heure_debut,
                'heure_fin' => $recurrence->heure_fin,
                'lieu' => $recurrence->lieu_defaut,
                'professor_principal_id' => $recurrence->cours->professeurs->first()?->id,
            ];
        }
        
        CourseSession::upsert($sessions, ['cours_id', 'date_debut']);
        $recurrence->update(['statut' => 'active']);
    }
}
```

**Estimé:** 11h (répartir entre 2 devs)
**Réel:** TBD

---

### 2B-2: Override Professeur Principal (par Session)

**ID:** 2B-2  
**Points:** 5  
**Assigné à:** Backend Dev 1  
**Dépendances:** 2A-2  
**Priorité:** P0

**User Story:**
```
En tant qu'administrateur,
Je veux changer le professeur principal d'une session,
Afin de gérer les remplaçants ou changements.
```

**Acceptance Criteria:**
- [ ] `PATCH /api/course-sessions/{id}/professor` change le professeur
- [ ] Valide que nouveau professeur existe
- [ ] Met à jour session_professors (remplace le principal)
- [ ] Crée audit log (future: Sprint 3)
- [ ] Tests passent (4+ tests)

**Tasks Techniques:**
1. [ ] Créer `SessionProfessorController@updatePrincipal` (1.5h)
   - PATCH /api/course-sessions/{id}/professor
   - Request body: { professeur_id, raison? }
   - Validations: professeur exists, session exists
   - Logic: 
     - Supprime ancien principal de session_professors
     - Crée nouveau principal dans session_professors
     - Update session.professor_principal_id

2. [ ] Audit logging (1h)
   - Créer table audit_logs (ou utiliser Spatie\Activitylog)
   - Tracer: qui, quoi, quand, raison
   - Future Sprint 3: UI pour voir historique

3. [ ] Tests (1.5h)
   - Change professeur success
   - Professeur invalide → 422
   - Session invalide → 404
   - Audit log créé

**Response:**
```json
{
  "data": {
    "id": 1,
    "professor_principal_id": 4,
    "professor_principal": { "id": 4, "nom": "Martin" },
    "professeurs": [...]
  }
}
```

**Estimé:** 3.5h
**Réel:** TBD

---

### 2B-3: Ajouter Assistant/Remplaçant à Session

**ID:** 2B-3  
**Points:** 3  
**Assigné à:** Backend Dev 2  
**Dépendances:** 2A-2  
**Priorité:** P1

**User Story:**
```
En tant qu'administrateur,
Je veux ajouter un assistant ou remplaçant à une session,
Afin que plusieurs profs puissent gérer la session.
```

**Acceptance Criteria:**
- [ ] `POST /api/course-sessions/{id}/professeurs` ajoute un prof avec rôle
- [ ] Rôles supportés: assistant, substitute, observer
- [ ] Valide professeur exists
- [ ] Empêche duplicata (même prof + rôle)
- [ ] Retourne nouvel enregistrement
- [ ] Tests passent (3+ tests)

**Tasks Techniques:**
1. [ ] Créer endpoint (1h)
   - POST /api/course-sessions/{id}/professeurs
   - Body: { professeur_id, role }
   - Validations: professeur exists, role in enum, not duplicate
   - Retour 201 avec SessionProfessor

2. [ ] Tests (1h)
   - Add assistant success
   - Duplicate role → 409 Conflict
   - Invalid role → 422

**Estimé:** 2h
**Réel:** TBD

---

### 2B-4: Enregistrer Présence Professeur

**ID:** 2B-4  
**Points:** 5  
**Assigné à:** Backend Dev 1  
**Dépendances:** 2A-2  
**Priorité:** P1

**User Story:**
```
En tant que professeur,
Je veux marquer ma présence ou absence à une session,
Afin que l'administration sache si j'ai assuré le cours.
```

**Acceptance Criteria:**
- [ ] `PATCH /api/course-sessions/{id}/professor/{professeur_id}/presence` enregistre présence
- [ ] Peut marquer: present=true, present=false
- [ ] Si absent: peut saisir motif
- [ ] Enregistre timestamp présence
- [ ] Audit log créé
- [ ] Tests passent (3+ tests)

**Tasks Techniques:**
1. [ ] Créer endpoint (1.5h)
   - PATCH /api/course-sessions/{id}/professor/{professeur_id}/presence
   - Body: { present: bool, motif_absence?: string }
   - Update session_professors.present + motif_absence
   - Timestamp: recorded_at

2. [ ] Validations (1h)
   - Professeur assigné à cette session
   - Session peut être passée ou du jour
   - Motif requis si present=false

3. [ ] Tests (1.5h)
   - Mark present
   - Mark absent with motif
   - Absent sans motif → warn but allow
   - Invalid professeur → 404

**Estimé:** 4h
**Réel:** TBD

---

### 2B-5: Gestion Statuts Session

**ID:** 2B-5  
**Points:** 5  
**Assigné à:** Backend Dev 2  
**Dépendances:** 2A-2  
**Priorité:** P1

**User Story:**
```
En tant qu'administrateur,
Je veux gérer le statut d'une session,
Afin de suivre le cycle de vie (scheduled → completed → archived).
```

**Acceptance Criteria:**
- [ ] Transitions: scheduled → in_progress → completed
- [ ] Toute transition vers cancelled (avec motif)
- [ ] Sessions completed/cancelled: en lecture seule
- [ ] Timestamp cancelled_at set si statut=cancelled
- [ ] Tests passent (4+ tests)

**Tasks Techniques:**
1. [ ] Ajouter logique à Controller (1.5h)
   - PATCH /api/course-sessions/{id}/statut
   - Validations: transitions valides
   - Update statut + cancelled_at si needed
   - Notification (future: Sprint 3)

2. [ ] Middleware lecture-seule (1.5h)
   - Empêcher PUT/DELETE si statut = completed, cancelled
   - Retourner 403 Forbidden

3. [ ] Tests (1.5h)
   - Transitions valides
   - Transitions invalides
   - Read-only enforcement

**Estimé:** 4.5h
**Réel:** TBD

---

### 2B-6: RecurrenceForm Component (React)

**ID:** 2B-6  
**Points:** 8  
**Assigné à:** Frontend Dev 1  
**Dépendances:** 2B-1  
**Priorité:** P0

**User Story:**
```
En tant qu'administrateur,
Je veux un formulaire pour créer une récurrence,
Afin de générer automatiquement les sessions d'un cours.
```

**Acceptance Criteria:**
- [ ] Formulaire complet avec tous les champs
- [ ] Prévisualisation: affiche "Génère 20 sessions du 01/10 au 15/12"
- [ ] Submit POST à l'API
- [ ] Affiche toast succès/erreur
- [ ] Tests passent (5+ tests)

**Tasks Techniques:**
1. [ ] Créer composant `<RecurrenceForm />` (3h)
   - Props: coursId, recurrence (optional), onSubmit, onCancel
   - State: formData (type, jours, dates, heures, lieu, professeur_id)

2. [ ] Champs formulaire (2h)
   - Radio: Type (Hebdomadaire / Bi-hebdo / Mensuel)
   - Checkboxes: Jours de semaine (Lun-Dim)
   - Date input: date_debut (min: today)
   - Date input: date_fin (optionnel, min: date_debut)
   - Time input: heure_debut, heure_fin
   - Select: lieu_defaut
   - Select: professeur_principal_id (optionnel)

3. [ ] Prévisualisation (1.5h)
   - Calcule dates selon type + jours
   - Affiche "X sessions du A au B"
   - Update en temps réel quand formulaire change

4. [ ] Soumission (1.5h)
   - POST /api/course-recurrences
   - Success: toast "Génération en cours..." + redirect
   - Error: affiche message

5. [ ] Tests (1h)
   - Render form
   - Prévisualisation
   - Submit success
   - Validations

**Estimé:** 8h
**Réel:** TBD

---

### 2B-7: CalendarView Complète (Drag-drop, Filtres, Modes)

**ID:** 2B-7  
**Points:** 13  
**Assigné à:** Frontend Dev 1 + 2  
**Dépendances:** 2A-4, 2B-1  
**Priorité:** P0

**User Story:**
```
En tant qu'utilisateur,
Je veux un calendrier interactif avec drag-drop, filtres et 3 modes de vue,
Afin de gérer mon agenda efficacement.
```

**Acceptance Criteria:**
- [ ] Mode Mois: grille 7×6, glisser session
- [ ] Mode Semaine: affiche 7 jours avec détail horaire
- [ ] Mode Agenda: liste linéaire sessions
- [ ] Filtres: cours, professeur, statut
- [ ] Drag-drop modifie date/heure
- [ ] Codes couleur: scheduled (bleu), completed (vert), cancelled (gris)
- [ ] Responsive: desktop/tablet/mobile
- [ ] Tests passent (10+ tests)

**Tasks Techniques:**
1. [ ] Intégrer react-big-calendar (2h)
   - Installer + configurer
   - Adapter API response à format calendar
   - Événements = sessions

2. [ ] Implémenter drag-drop (3h)
   - Drag start: session cliquée
   - Drag over: show drop zones
   - Drop: PATCH /api/course-sessions/{id} avec new date/time
   - Undo si erreur API

3. [ ] Implémenter filtres (2h)
   - Select: cours, professeur, statut
   - Refetch sessions quand filtres changent
   - Display: "Filtrant par cours X..."

4. [ ] Implémenter modes de vue (2h)
   - Tabs: Mois / Semaine / Agenda
   - Persist choix en localStorage
   - Chaque mode a sa propre logique render

5. [ ] Couleurs/styles (1h)
   - Tailwind: couleurs selon statut
   - Hover effects
   - Indicator badges (# sessions/jour)

6. [ ] Responsive (1.5h)
   - Desktop: 3 modes complets
   - Tablet: simplifiée
   - Mobile: agenda par défaut

7. [ ] Tests (1h)
   - Render tous modes
   - Drag-drop
   - Filtres
   - Navigation
   - Responsive breakpoints

**Estimé:** 13h
**Réel:** TBD

---

### 2B-8: ProfessorCalendarView Component

**ID:** 2B-8  
**Points:** 8  
**Assigné à:** Frontend Dev 2  
**Dépendances:** 2A-4, 2B-4  
**Priorité:** P1

**User Story:**
```
En tant que professeur,
Je veux voir mon calendrier personnel avec mes sessions,
Afin que je puisse gérer mon agenda.
```

**Acceptance Criteria:**
- [ ] Affiche sessions où professeur = connecté
- [ ] Peut marquer présence/absence
- [ ] Codes couleur: scheduled (bleu), completed (vert), cancelled (gris)
- [ ] Affiche uniquement sessions du professeur
- [ ] Responsive
- [ ] Tests passent (5+ tests)

**Tasks Techniques:**
1. [ ] Créer composant `<ProfessorCalendarView />` (2h)
   - Props: professeur, year, month
   - Fetch: /api/calendar/professor/{id}?year=X&month=Y
   - Render: CalendarView filtré

2. [ ] Marquer présence (2h)
   - Click session → modal simple
   - Radio: Présent / Absent
   - Textarea: motif si absent
   - Submit: PATCH /api/course-sessions/{id}/professor/{id}/presence

3. [ ] Statuts visuels (1h)
   - Codes couleur
   - Badge: "À compléter présence"

4. [ ] Tests (2h)

**Estimé:** 7h
**Réel:** TBD

---

### 2B-9: SessionList (Agenda Mode) Component

**ID:** 2B-9  
**Points:** 5  
**Assigné à:** Frontend Dev 2  
**Dépendances:** 2A-6  
**Priorité:** P1

**User Story:**
```
En tant qu'utilisateur,
Je veux une vue agenda (liste linéaire) des sessions,
Afin de voir mes prochaines sessions dans l'ordre.
```

**Acceptance Criteria:**
- [ ] Affiche sessions du jour/prochains jours en liste
- [ ] Tri: date_debut croissant (défaut)
- [ ] Filtres: cours, professeur, statut
- [ ] Lazy load: scroll bottom → charge plus
- [ ] Durée affichée: "J-6", "Aujourd'hui", "Demain"
- [ ] Tests passent (4+ tests)

**Tasks Techniques:**
1. [ ] Créer composant `<SessionList />` (2h)
   - Props: sessions, loading, onSessionClick, filter
   - Chaque ligne: SessionCard

2. [ ] Pagination infini (1.5h)
   - Détecte scroll bottom
   - Dispatch fetchMore query

3. [ ] Filtres (0.5h)
   - Props filter: { statut?, professeur?, cours? }

4. [ ] Tests (1h)

**Estimé:** 5h
**Réel:** TBD

---

### 2B-10: RecurrencesAdminPage

**ID:** 2B-10  
**Points:** 8  
**Assigné à:** Frontend Dev 1  
**Dépendances:** 2B-1, 2B-6  
**Priorité:** P1

**User Story:**
```
En tant qu'administrateur,
Je veux gérer les récurrences (view/edit/pause/delete),
Afin de contrôler la génération automatique des sessions.
```

**Acceptance Criteria:**
- [ ] Page liste toutes les récurrences (paginé)
- [ ] Filtrer: par cours, statut (active/paused/archived)
- [ ] Actions: Éditer, Voir sessions, Pauser, Archiver, Supprimer
- [ ] Affiche: type, dates, sessions_generated, next_session
- [ ] Éditer ouvre RecurrenceForm dans modal
- [ ] Voir sessions: affiche liste des 10 prochaines
- [ ] Tests passent (4+ tests)

**Tasks Techniques:**
1. [ ] Créer page `<RecurrencesAdminPage />` (2h)
   - Fetch: GET /api/course-recurrences?page=1
   - Render: tableau avec colonnes (cours, type, dates, count, actions)

2. [ ] Tableau (1.5h)
   - Sortable par colonne (type, date_debut)
   - Pagination: prev/next
   - Status badge: active/paused/archived

3. [ ] Actions (2h)
   - Edit: ouvre RecurrenceForm en modal
   - View Sessions: affiche SessionList modal
   - Pause: PATCH /statut
   - Delete: DELETE avec confirmation

4. [ ] Filtres (1h)
   - Select: cours
   - Radio: statut (tous, active, paused, archived)

5. [ ] Tests (1.5h)

**Estimé:** 8h
**Réel:** TBD

---

### 2B-11: Tests API Récurrence + Override

**ID:** 2B-11  
**Points:** 8  
**Assigné à:** Backend Dev 2  
**Dépendances:** 2B-1, 2B-2, 2B-3, 2B-4, 2B-5  
**Priorité:** P1

**User Story:**
```
En tant qu'équipe QA,
Je veux des tests API complets pour récurrence et override,
Afin d'assurer la qualité.
```

**Acceptance Criteria:**
- [ ] 25+ tests API (Feature)
- [ ] Coverage: toutes les transitions, cas d'erreur
- [ ] Tous tests passent

**Tests Requis:**
- RecurrenceControllerTest (12 tests):
  - POST create, validations, job dispatched
  - GET list by cours, filtres
  - PATCH generate
  - PATCH statut
  - DELETE

- SessionProfessorControllerTest (13 tests):
  - PATCH professor (change principal)
  - POST professeurs (add assistant)
  - DELETE professeurs/{id}
  - PATCH presence
  - Validations, permissions

**Estimé:** 6h
**Réel:** TBD

---

### 2B-12: Tests E2E Workflows

**ID:** 2B-12  
**Points:** 10  
**Assigné à:** QA + Frontend Dev  
**Dépendances:** 2B-1-2B-11  
**Priorité:** P1

**User Story:**
```
En tant que testeur,
Je veux des tests E2E pour les workflows complets,
Afin que les users ne rencontrent pas de bugs en production.
```

**Acceptance Criteria:**
- [ ] 8+ tests E2E Cypress/Playwright
- [ ] Coverage: create session, create récurrence, override prof, mark présence
- [ ] Tous tests passent
- [ ] Pas de flakiness

**Tests E2E:**
1. [ ] Créer une session simple
2. [ ] Créer une récurrence + générer 20 sessions
3. [ ] Drag-drop session dans calendrier
4. [ ] Override professeur
5. [ ] Marquer présence
6. [ ] Annuler une session
7. [ ] Vue agenda du professeur
8. [ ] Éditer session → met à jour récurrence

**Estimé:** 8h
**Réel:** TBD

---

## Phase 2C: Polish + Performance + Export

[User stories 2C-1 à 2C-12 suivent le même format. Résumé pour concision:]

### 2C-1: Export PDF Calendrier (5 + 3 pts)
- Bouton "Exporter PDF"
- Génère PDF du mois avec sessions
- Inclut: titre du cours, prof, lieu, heure

### 2C-2: Notifications Email (8 pts)
- Envoyer email quand session créée/modifiée/annulée
- Template: titre, heure, prof, lieu, changements
- A/B test: design template

### 2C-3: Audit Logs (5 pts)
- Tracer modifications: qui, quoi, quand, raison
- Table audit_logs
- Queryable by entity_id, action, user_id

### 2C-4: Caching Calendrier (5 pts)
- Cache GET /api/calendar/month (1 heure)
- Invalidate quand POST/PUT/DELETE session du mois

### 2C-5: Pagination Optimisée (5 pts)
- Lazy load si > 100 sessions
- Scroll infini dans SessionList
- Perf < 200ms par page

### 2C-6: Alertes UI (5 pts)
- Toast notifications: succès, erreur, warning
- Validation feedback: champs requis, invalid format

### 2C-7: Performance DB (8 pts)
- Ajouter indexes manquants
- Optimiser N+1 queries (with/eager loading)
- Explainer: toutes queries < 100ms

### 2C-8: Documentation API (5 pts)
- Générer Swagger/OpenAPI
- Documenter tous endpoints 2A + 2B
- Publier sur /api/docs

### 2C-9: Tests Load (5 pts)
- Générer 1000 sessions
- Perf: fetch month < 2s, cache < 200ms

### 2C-10: Responsive Mobile (8 pts)
- CalendarView, SessionDetailModal, RecurrenceForm
- Layout OK < 768px
- Touch-friendly buttons

### 2C-11: Dark Mode (3 pts)
- CSS variables: light/dark themes
- CalendarView + components

### 2C-12: Intégration Timesheets (3 pts)
- Migration: add session_id to timesheets
- Foreign key
- Préparer Sprint 3

---

## 🎯 Mapping User Stories → Issues GitHub

Créer des issues GitHub avec labels:

```
Label: Sprint2A / Sprint2B / Sprint2C
Label: Backend / Frontend / DevOps
Label: P0 / P1
Label: Database / API / Component / Integration
```

**Assignation Exemple:**

| Story | Dev | Startup | Fin Estimée |
|-------|-----|---------|-------------|
| 2A-1 | Backend-1 | J1 | J3 |
| 2A-2 | Backend-1 | J3 | J6 |
| 2A-3 | Backend-1 | J5 | J7 |
| 2A-4 | Frontend-1 | J3 | J7 |
| 2A-5 | Frontend-1 | J5 | J10 |
| 2A-6 | Frontend-2 | J6 | J8 |
| 2A-7 | Frontend-2 | J8 | J10 |
| 2A-8 | Backend-2 | J3 | J7 |
| ... | ... | ... | ... |

---

## 📊 Estimation Sommaire

| Phase | US | Points | Semaines | Devs |
|-------|-----|--------|----------|------|
| 2A | 8 | 47 | 2 | 2-3 |
| 2B | 12 | 91 | 2 | 3 |
| 2C | 12 | 68 | 2 | 2-3 |
| **Total** | **32** | **206** | **6** | **~3** |

**Vélocité estimée:** 34 points/semaine (standard: 25-35 pour équipe junior)

---

**Document créé:** 29 sept 2026  
**Version:** 1.0  
**Prêt pour planification sprint**
