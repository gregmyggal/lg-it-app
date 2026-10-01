# Plan Sprint 2: Gestion des Sessions & Calendrier

**Version:** 1.0  
**Date:** 29 septembre 2026  
**Basé sur:** Sprint 1 - Système d'assignation professeur-cours  
**Stack:** Laravel 11 • React 18 • PostgreSQL • Docker  

---

## 📋 Sommaire Exécutif

Sprint 2 transforme le système de gestion de cours en ajoutant les **sessions concrètes** — les occurrences planifiées d'un cours avec dates, horaires, lieux et affectations de professeurs. Nous implémenterons:

- **Modèle de sessions** avec récurrence et calendrier
- **Calendrier interactif** (mois/année) avec drag-drop
- **Gestion d'agenda** avec override de professeurs par session
- **Workflows complets** de création à archivage

**Impact métier:** Les directeurs peuvent voir l'agenda complet, planifier les sessions, gérer les remplaçants; les professeurs voient leurs sessions et les calendriers partagés.

---

## 🏗️ 1. Architecture & Migrations

### 1.1 Tables à Créer

#### `course_sessions`
Sessions concrètes (occurrences) d'un cours.

```sql
-- Table: course_sessions
-- Responsabilité: stocker chaque session planifiée
-- Récurrence: gérée via la table course_recurrences (voir 1.2)

CREATE TABLE course_sessions (
    id BIGINT PRIMARY KEY,
    cours_id BIGINT NOT NULL REFERENCES cours(id) ON DELETE CASCADE,
    recurrence_id BIGINT NULLABLE REFERENCES course_recurrences(id) ON DELETE SET NULL,
    
    -- Dates et horaires
    date_debut DATE NOT NULL,           -- Jour de la session
    heure_debut TIME NOT NULL,          -- 09:30
    heure_fin TIME NOT NULL,            -- 11:30
    
    -- Métadonnées
    titre TEXT,                         -- "Java Avancé - Semaine 5" (override possible)
    lieu VARCHAR(255),                  -- "Salle 201" | "Visio" | NULL
    description TEXT,                   -- Notes, agenda détaillé
    
    -- Statuts
    statut ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    motif_annulation TEXT NULLABLE,     -- Raison si cancelled
    
    -- Suivi
    professor_principal_id BIGINT NULLABLE REFERENCES professeurs(id),
    nb_eleves_attendus INT DEFAULT 0,
    nb_eleves_presentes INT NULLABLE,
    
    -- Audit
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    cancelled_at TIMESTAMP NULLABLE,
    
    -- Index
    INDEX(cours_id, date_debut),
    INDEX(professor_principal_id, date_debut),
    INDEX(statut, date_debut),
    UNIQUE(cours_id, date_debut, heure_debut)  -- Pas 2 sessions identiques
);
```

#### `course_recurrences`
Règles de récurrence pour générer les sessions automatiquement.

```sql
CREATE TABLE course_recurrences (
    id BIGINT PRIMARY KEY,
    cours_id BIGINT NOT NULL REFERENCES cours(id) ON DELETE CASCADE,
    
    -- Paramètres de récurrence
    type ENUM('weekly', 'biweekly', 'monthly') NOT NULL,
    jours_semaine VARCHAR(50),                 -- "1,3,5" (lundi, mercredi, vendredi)
    date_debut DATE NOT NULL,
    date_fin DATE NULLABLE,                    -- NULL = indéfini
    
    -- Heure par défaut pour les sessions générées
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    lieu_defaut VARCHAR(255),
    
    -- Statut
    statut ENUM('active', 'paused', 'archived') DEFAULT 'active',
    
    -- Audit
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    -- Index
    INDEX(cours_id, statut),
    INDEX(date_debut, date_fin)
);
```

#### `session_professors`
Assignation de professeurs aux sessions (override possible du principal).

```sql
CREATE TABLE session_professors (
    id BIGINT PRIMARY KEY,
    course_session_id BIGINT NOT NULL REFERENCES course_sessions(id) ON DELETE CASCADE,
    professeur_id BIGINT NOT NULL REFERENCES professeurs(id) ON DELETE CASCADE,
    
    -- Rôle dans cette session
    role ENUM('principal', 'assistant', 'substitute', 'observer') DEFAULT 'principal',
    
    -- Présence/Statut
    present BOOLEAN NULLABLE,           -- NULL = pas encore enregistré
    motif_absence TEXT NULLABLE,
    
    -- Audit
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    -- Index & Constraints
    INDEX(course_session_id),
    INDEX(professeur_id, created_at),
    UNIQUE(course_session_id, professeur_id, role)  -- Un rôle par prof/session
);
```

#### `session_calendar_views`
Vue calendrier sauvegardée par utilisateur (pour les filtres, zoom, etc.).

```sql
CREATE TABLE session_calendar_views (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Configuration
    nom VARCHAR(255) NOT NULL,
    vue_defaut ENUM('month', 'week', 'agenda') DEFAULT 'month',
    filtres JSON,                       -- {"statuts":["scheduled"],"professors":[1,2]}
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX(user_id),
    UNIQUE(user_id, nom)
);
```

### 1.2 Relations Eloquent

**CourseSession** (modèle)
```
- belongsTo(Cours)
- belongsTo(Professeur, 'professor_principal_id')
- belongsTo(CourseRecurrence, nullable)
- hasMany(SessionProfessor)
- hasManyThrough(Professeur, SessionProfessor)
- hasMany(Timesheet) [lien futur via session_id]
```

**CourseRecurrence**
```
- belongsTo(Cours)
- hasMany(CourseSession)
```

**SessionProfessor**
```
- belongsTo(CourseSession)
- belongsTo(Professeur)
```

**Professeur** (ajouts)
```
- hasMany(CourseSession, 'professor_principal_id')
- hasMany(SessionProfessor)
- hasManyThrough(CourseSession, SessionProfessor)
```

**Cours** (ajouts)
```
- hasMany(CourseSession)
- hasMany(CourseRecurrence)
```

### 1.3 Seeders & Fixtures

**CoursSessionSeeder**
- Génère 3-5 sessions par cours (pour développement)
- Assigne des professeurs aléatoires
- Mix de statuts: scheduled (80%), completed (15%), cancelled (5%)

**CourseRecurrenceSeeder**
- Crée 2-3 récurrences par cours (hebdo, bihebdo)
- Dates d'exemple: 1 semestre (15 semaines)

**SessionProfessorSeeder**
- Assigne les professeurs principaux
- Ajoute 20% d'assistants/remplaçants

---

## 🔌 2. Backend API

### 2.1 Endpoints Sessions CRUD

#### `GET /api/course-sessions`
Récupère les sessions avec filtres.

**Query Parameters:**
```
- cours_id: integer
- date_from: date (YYYY-MM-DD)
- date_to: date (YYYY-MM-DD)
- statut: enum(scheduled|in_progress|completed|cancelled)
- professeur_id: integer
- lieu: string
- sort_by: enum(date_debut|-date_debut|titre)
- page: integer
- per_page: integer (défaut 25)
```

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "cours_id": 5,
      "cours": {
        "id": 5,
        "titre": "Java Avancé"
      },
      "date_debut": "2026-10-05",
      "heure_debut": "09:30",
      "heure_fin": "11:30",
      "titre": null,
      "lieu": "Salle 201",
      "statut": "scheduled",
      "professor_principal": {
        "id": 3,
        "nom": "Dupont"
      },
      "professeurs": [
        {
          "id": 3,
          "nom": "Dupont",
          "role": "principal",
          "present": null
        },
        {
          "id": 5,
          "nom": "Martin",
          "role": "assistant"
        }
      ],
      "nb_eleves_attendus": 15,
      "nb_eleves_presentes": null,
      "created_at": "2026-09-29T10:00:00Z",
      "updated_at": "2026-09-29T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 150,
    "from": 1,
    "to": 25,
    "last_page": 6
  }
}
```

#### `GET /api/course-sessions/{id}`
Détail d'une session.

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "cours_id": 5,
    "cours": { "id": 5, "titre": "Java Avancé" },
    "recurrence_id": 2,
    "date_debut": "2026-10-05",
    "heure_debut": "09:30",
    "heure_fin": "11:30",
    "titre": null,
    "lieu": "Salle 201",
    "description": "Introduction aux Streams",
    "statut": "scheduled",
    "professor_principal": { "id": 3, "nom": "Dupont" },
    "professeurs": [
      { "id": 3, "nom": "Dupont", "role": "principal", "present": null },
      { "id": 5, "nom": "Martin", "role": "assistant", "present": true }
    ],
    "nb_eleves_attendus": 15,
    "nb_eleves_presentes": null,
    "created_at": "2026-09-29T10:00:00Z"
  }
}
```

#### `POST /api/course-sessions`
Crée une session unique.

**Request Body:**
```json
{
  "cours_id": 5,
  "date_debut": "2026-10-05",
  "heure_debut": "09:30",
  "heure_fin": "11:30",
  "titre": null,
  "lieu": "Salle 201",
  "description": "Introduction aux Streams",
  "professor_principal_id": 3,
  "professeurs": [
    { "professeur_id": 3, "role": "principal" },
    { "professeur_id": 5, "role": "assistant" }
  ],
  "nb_eleves_attendus": 15
}
```

**Validations:**
- cours_id: required, exists in cours
- date_debut: required, date, >= today
- heure_debut < heure_fin
- Pas de session dupliquée (même cours, même date/heure)

**Response (201):**
```json
{
  "data": { /* objet session complète */ }
}
```

#### `PUT /api/course-sessions/{id}`
Met à jour une session.

**Request Body:**
```json
{
  "titre": "Semaine 5 - Édition",
  "lieu": "Salle 202",
  "description": "Plan modifié",
  "heure_debut": "10:00",
  "heure_fin": "12:00",
  "professor_principal_id": 4,
  "nb_eleves_attendus": 20
}
```

**Validations:**
- Pas de modification si statut = completed ou cancelled
- Professeur valide si changé

**Response (200):**
```json
{
  "data": { /* session mise à jour */ }
}
```

#### `DELETE /api/course-sessions/{id}`
Supprime physiquement une session (si possible).

**Rules:**
- Lecture seule si statut = in_progress, completed ou cancelled
- Soft-delete si des timesheets associées existent (futur: Sprint 3)
- Sinon, hard-delete

**Response (204):** No Content

#### `PATCH /api/course-sessions/{id}/statut`
Change le statut d'une session.

**Request Body:**
```json
{
  "statut": "cancelled",
  "motif_annulation": "Absence du professeur"
}
```

**Validations:**
- scheduled → in_progress (au jour J)
- in_progress → completed (après heure_fin)
- Tout statut → cancelled (avec motif)

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "statut": "cancelled",
    "motif_annulation": "Absence du professeur"
  }
}
```

### 2.2 Endpoints Récurrence

#### `GET /api/course-recurrences`
Liste les récurrences d'un cours.

**Query Parameters:**
```
- cours_id: integer (required)
- statut: enum(active|paused|archived)
```

**Response (200):**
```json
{
  "data": [
    {
      "id": 2,
      "cours_id": 5,
      "type": "weekly",
      "jours_semaine": "1,3,5",
      "date_debut": "2026-10-01",
      "date_fin": "2027-03-31",
      "heure_debut": "09:30",
      "heure_fin": "11:30",
      "lieu_defaut": "Salle 201",
      "statut": "active",
      "sessions_generated": 20,
      "next_session": "2026-10-07"
    }
  ]
}
```

#### `POST /api/course-recurrences`
Crée une récurrence et génère les sessions.

**Request Body:**
```json
{
  "cours_id": 5,
  "type": "weekly",
  "jours_semaine": "1,3,5",
  "date_debut": "2026-10-01",
  "date_fin": "2027-03-31",
  "heure_debut": "09:30",
  "heure_fin": "11:30",
  "lieu_defaut": "Salle 201",
  "professor_principal_id": 3
}
```

**Logic:**
1. Valide les paramètres
2. Crée la recurrence_record
3. **Lance un job async** pour générer les sessions
4. Retourne immédiatement avec ID + status "generating"

**Response (201):**
```json
{
  "data": {
    "id": 2,
    "cours_id": 5,
    "type": "weekly",
    "statut": "active",
    "job_id": "uuid-xyz",
    "message": "Génération en cours..."
  }
}
```

#### `PATCH /api/course-recurrences/{id}/generate`
Force la génération des sessions pour une récurrence.

**Logic:**
- Supprime les sessions futures (date >= today)
- Régénère selon la règle

**Response (200):**
```json
{
  "data": {
    "id": 2,
    "sessions_generated": 22,
    "next_session": "2026-10-07"
  }
}
```

#### `PATCH /api/course-recurrences/{id}/statut`
Change le statut (active → paused/archived).

**Request Body:**
```json
{
  "statut": "paused"
}
```

**Response (200):**
```json
{
  "data": { "id": 2, "statut": "paused" }
}
```

#### `DELETE /api/course-recurrences/{id}`
Supprime la récurrence et ses sessions futures.

**Response (204):** No Content

### 2.3 Endpoints Override Professeurs

#### `PATCH /api/course-sessions/{id}/professor`
Change le professeur principal d'une session.

**Request Body:**
```json
{
  "professeur_id": 4,
  "raison": "Remplacement temporaire"
}
```

**Logic:**
1. Valide le professeur
2. Remplace le principal dans session_professors
3. Crée une trace (audit log future)

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "professor_principal_id": 4,
    "professeurs": [
      { "id": 4, "nom": "Martin", "role": "principal" }
    ]
  }
}
```

#### `POST /api/course-sessions/{id}/professeurs`
Ajoute un assistant/remplaçant à une session.

**Request Body:**
```json
{
  "professeur_id": 5,
  "role": "assistant"
}
```

**Response (201):**
```json
{
  "data": {
    "id": 5,
    "nom": "Durand",
    "role": "assistant"
  }
}
```

#### `DELETE /api/course-sessions/{id}/professeurs/{professeur_id}`
Retire un professeur d'une session.

**Response (204):** No Content

### 2.4 Endpoints Calendrier

#### `GET /api/calendar/month`
Récupère les sessions pour un mois complet.

**Query Parameters:**
```
- year: integer
- month: integer (1-12)
- professeur_id: integer (filtrer par prof)
- cours_id: integer (filtrer par cours)
- statut: enum
```

**Response (200):**
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
            "cours_titre": "Java Avancé",
            "heure_debut": "09:30",
            "heure_fin": "11:30",
            "professeur": "Dupont",
            "statut": "scheduled",
            "lieu": "Salle 201"
          }
        ]
      },
      {
        "date": "2026-10-02",
        "sessions": []
      }
    ]
  }
}
```

#### `GET /api/calendar/year`
Aperçu annuel (heatmap: nombre de sessions par mois).

**Query Parameters:**
```
- year: integer
- cours_id: integer (optional)
```

**Response (200):**
```json
{
  "data": {
    "year": 2026,
    "months": [
      {
        "month": 1,
        "nom": "Janvier",
        "sessions_count": 12,
        "total_heures": 24.5
      }
    ]
  }
}
```

#### `GET /api/calendar/professor/{id}`
Calendrier d'un professeur (vue personnalisée).

**Query Parameters:**
```
- year: integer
- month: integer
```

**Response (200):** (similaire à /month, mais filtré)

#### `GET /api/calendar/agenda`
Vue agenda (liste linéaire des sessions prochaines).

**Query Parameters:**
```
- date_from: date
- date_to: date
- limit: integer (défaut 50)
- professeur_id: integer (optional)
```

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "cours": "Java Avancé",
      "date": "2026-10-05",
      "heure_debut": "09:30",
      "heure_fin": "11:30",
      "professeur": "Dupont",
      "lieu": "Salle 201",
      "statut": "scheduled",
      "jours_jusqu_session": 6
    }
  ]
}
```

### 2.5 Endpoints Professeur-Session

#### `PATCH /api/course-sessions/{id}/professor/{professeur_id}/presence`
Enregistre la présence d'un professeur.

**Request Body:**
```json
{
  "present": true,
  "motif_absence": null
}
```

**Response (200):**
```json
{
  "data": {
    "professeur_id": 3,
    "present": true
  }
}
```

#### `PATCH /api/course-sessions/{id}/attendance`
Enregistre l'effectif d'une session.

**Request Body:**
```json
{
  "nb_eleves_presentes": 14
}
```

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "nb_eleves_attendus": 15,
    "nb_eleves_presentes": 14,
    "taux_presence": 0.93
  }
}
```

---

## 🎨 3. Frontend Components

### 3.1 Components de Base

#### `<CalendarView />`
Affiche le calendrier (mois/année/agenda).

**Props:**
```typescript
interface CalendarViewProps {
  view?: 'month' | 'week' | 'agenda';      // défaut: 'month'
  year?: number;
  month?: number;
  coursId?: number;                         // Filtrer un cours
  professeurId?: number;                    // Filtrer un prof
  onSessionClick?: (session: CourseSession) => void;
  onDateClick?: (date: Date) => void;
  editable?: boolean;                       // Peut glisser les sessions?
  loading?: boolean;
}
```

**State Interne:**
- Mode vue (month/week/agenda)
- Mois/année affichés
- Filtres actifs
- Sessions chargées

**Features:**
- Drag-drop pour déplacer une session (modifie date/heure)
- Click sur jour → crée une session
- Click sur session → ouvre modal
- Navigation mois précédent/suivant
- Indicateurs: nombre de sessions/jour, codes couleur (scheduled=bleu, completed=vert, etc.)

#### `<SessionCard />`
Affiche une session en mode compact (dans calendrier ou liste).

**Props:**
```typescript
interface SessionCardProps {
  session: CourseSession;
  onClick?: () => void;
  onDelete?: (id: number) => void;
  compact?: boolean;
  showProfessor?: boolean;
}
```

**Render:**
```
┌─────────────────────────────┐
│ 09:30-11:30 | Java Avancé  │ ← heure + cours
│ Salle 201 | 15 élèves      │ ← lieu + effectif
│ Prof: Dupont (principal)    │
│ [Edit] [Delete] [+]         │ ← actions
└─────────────────────────────┘
```

#### `<SessionDetailModal />`
Affiche/édite une session en modal.

**Props:**
```typescript
interface SessionDetailModalProps {
  session?: CourseSession;
  coursId?: number;
  open: boolean;
  onClose: () => void;
  onSave?: (session: CourseSession) => void;
  onDelete?: (id: number) => void;
}
```

**Sections:**
1. **Infos de base:** date, heure, lieu, titre (override)
2. **Cours:** sélecteur de cours
3. **Professeurs:**
   - Principal (dropdown)
   - Assistants (tag list + "Ajouter")
4. **Effectifs:** nb_attendus, nb_présents
5. **Statut:** dropdown (scheduled/in_progress/completed/cancelled)
6. **Description:** textarea
7. **Buttons:** Enregistrer / Annuler / Supprimer (si créée)

#### `<SessionList />`
Affiche les sessions en liste (agenda view).

**Props:**
```typescript
interface SessionListProps {
  sessions: CourseSession[];
  loading?: boolean;
  onSessionClick?: (session: CourseSession) => void;
  filter?: {
    statut?: string;
    professeur?: number;
    cours?: number;
  };
}
```

**Features:**
- Tri par date (défaut)
- Chaque ligne = SessionCard compacte
- Click → ouvre modal
- Scroll infini ou pagination

#### `<RecurrenceForm />`
Formulaire pour créer/éditer une récurrence.

**Props:**
```typescript
interface RecurrenceFormProps {
  coursId: number;
  recurrence?: CourseRecurrence;
  onSubmit: (data: RecurrenceFormData) => void;
  onCancel: () => void;
}
```

**Champs:**
1. **Type:** Hebdomadaire / Bi-hebdomadaire / Mensuel
2. **Jours de semaine:** checkboxes (lun-dim)
3. **Dates:** date_debut, date_fin
4. **Horaire:** heure_debut, heure_fin
5. **Lieu par défaut:** input
6. **Professeur principal:** dropdown
7. **Prévisualisation:** "Génère X sessions jusqu'au Y"
8. **Boutons:** Créer / Annuler

#### `<ProfessorOverrideModal />`
Modal pour changer le professeur d'une session.

**Props:**
```typescript
interface ProfessorOverrideModalProps {
  session: CourseSession;
  open: boolean;
  onClose: () => void;
  onSave: (professeurId: number) => void;
}
```

**Contenu:**
- Professeur actuel (affichage)
- Dropdown de remplacement
- Champ "Raison" (optionnel)
- Boutons: Sauvegarder / Annuler

#### `<ProfessorCalendarView />`
Vue calendrier spécialisée pour un professeur.

**Props:**
```typescript
interface ProfessorCalendarViewProps {
  professeur: Professeur;
  year?: number;
  month?: number;
}
```

**Features:**
- Calendrier du mois
- Codes couleur par statut (scheduled/completed/cancelled)
- Affiche seulement les sessions du prof
- Permet marquer présence/absence
- Export PDF possible

### 3.2 Pages

#### `CoursesCalendarPage`
Page principale: calendrier des cours.

**Route:** `/courses/calendar`

**Sections:**
1. **Header:** Mode vue (month/week/agenda), mois/année, filtres
2. **Filtres:** Cours, Professeur, Statut
3. **Contenu principal:** CalendarView
4. **Sidebar:** Sessions prochaines (agenda)

**Actions:**
- Créer une session
- Créer une récurrence
- Voir détails d'une session
- Drag-drop pour déplacer

#### `SessionDetailPage`
Page de détail (alternative au modal).

**Route:** `/courses/sessions/{id}`

**Contenu:**
- SessionDetailModal en pleine page
- Breadcrumbs: Calendrier > Cours > Session

#### `RecurrencesPage`
Page de gestion des récurrences (admin).

**Route:** `/admin/recurrences`

**Contenu:**
1. **Liste des récurrences** (par cours)
2. **Actions:** Éditer, Voir sessions, Pauser, Supprimer
3. **Stats:** Sessions générées, prochain, période

#### `ProfessorCalendarPage`
Vue calendrier du professeur (vue personnalisée).

**Route:** `/professor/calendar`

**Contenu:**
- ProfessorCalendarView
- Sessions de ce mois
- Permet marquer présence/absence

### 3.3 Integration avec Pages Existantes

#### `MesCoursPage` (mise à jour)
- Ajouter section "Sessions prochaines"
- Afficher le calendrier du cours
- Lien vers détail session

#### `CoursAdminPage` (mise à jour)
- Onglet "Sessions"
  - Liste des sessions du cours
  - Button "Nouvelle session"
  - Button "Créer récurrence"
  - Affichage calendrier inline

#### `ProfesseurCoursCard` (mise à jour)
- Afficher "X sessions ce mois" en badge
- Lien vers calendrier du prof

---

## 🔄 4. Workflows & Features

### 4.1 Workflow: Créer un Cours avec Sessions Automatiques

```
1. Admin: Crée un cours (Sprint 1)
   → Redirige vers édition du cours

2. Admin: Onglet "Sessions" → Button "Créer récurrence"
   → Ouvre RecurrenceForm

3. Admin: Remplit le formulaire
   - Type: Hebdomadaire
   - Jours: Lun, Mer, Ven
   - Dates: 01/10/2026 → 31/03/2027
   - Heure: 09:30 - 11:30
   - Lieu: Salle 201
   - Prof: Dupont
   → Valide

4. Backend: Crée recurrence + lance job async
   → Génère 45 sessions

5. Frontend: Affiche "Génération en cours..." puis rafraîchit
   → Liste des sessions apparaît

6. Admin: Peut maintenant voir sessions dans calendrier
```

### 4.2 Workflow: Modifier une Récurrence

```
1. Admin: Va à /admin/recurrences
2. Clique sur une récurrence
3. Édite les paramètres (ex: changement de jour)
   → Click "Régénérer"
4. Backend: Supprime sessions futures + régénère
5. Admin: Voit les nouvelles sessions dans le calendrier
```

### 4.3 Workflow: Override Professeur pour une Session

```
1. Admin/Professeur: Voit calendrier
2. Clique sur une session
3. Ouvre SessionDetailModal
4. Section "Professeurs": Change le principal
   → Dropdown → sélectionne "Martin"
5. Clique "Enregistrer"
6. Backend: Met à jour session_professors
7. Frontend: Rafraîchit la session
```

### 4.4 Workflow: Enregistrer Présence Professeur

```
1. Professeur: Va à /professor/calendar
2. Voir une session du jour (ou passée)
3. Clique sur session
4. Modal → Section "Présence professeur"
5. Sélectionne "Présent" ou "Absent"
   → Si absent: remplit "Motif"
6. Clique "Enregistrer"
7. Backend: Met à jour session_professors.present
8. Audit log créé (future: Sprint 3)
```

### 4.5 Workflow: Vue Agenda Personnel (Professeur)

```
1. Professeur: Va à /professor/calendar
2. Voit liste des sessions (30 jours)
   - Colorées par statut
   - Affiche cours, heure, lieu
3. Peut marquer présence/absence
4. Peut exporter PDF (future: Sprint 2C)
5. Peut voir liste d'alertes:
   - Sessions annulées
   - Remplaçants assignés
```

### 4.6 Workflow: Annuler une Session

```
1. Admin: Calendrier → Clique session
2. Modal → Dropdown "Statut" → "Annulée"
   → Remplit "Motif d'annulation"
3. Clique "Enregistrer"
4. Backend: Mise à jour + timestamp cancelled_at
5. Notification envoyée aux professeurs assignés (futur: Sprint 3)
```

---

## ✅ 5. Testing

### 5.1 Tests Unitaires (Backend)

**File:** `tests/Unit/CourseSessionTest.php`

```php
// Relations
✓ CourseSession->cours()
✓ CourseSession->professeurs()
✓ CourseSession->recurrence()

// Scopes
✓ Scope::upcoming()        // date >= today
✓ Scope::byStatut('scheduled')
✓ Scope::byProfesseur(id)
✓ Scope::inRange(from, to)
✓ Scope::orderByDate()
```

**File:** `tests/Unit/CourseRecurrenceTest.php`

```php
// Génération
✓ WeeklyRecurrence::generate() // crée 20 sessions
✓ BiweeklyRecurrence::generate()
✓ MonthlyRecurrence::generate()

// Validations
✓ Rejet: date_fin < date_debut
✓ Rejet: jours_semaine invalides
```

**File:** `tests/Unit/SessionProfessorTest.php`

```php
// Relations
✓ SessionProfessor->session()
✓ SessionProfessor->professeur()

// Constraints
✓ Unique: (session_id, professeur_id, role)
✓ Rejet: assignation dupliquée
```

### 5.2 Tests API (Feature)

**File:** `tests/Feature/CourseSessionControllerTest.php`

```php
// CRUD
✓ GET /api/course-sessions             // 200
✓ POST /api/course-sessions            // 201
✓ GET /api/course-sessions/{id}        // 200
✓ PUT /api/course-sessions/{id}        // 200
✓ DELETE /api/course-sessions/{id}     // 204

// Filtres
✓ GET /api/course-sessions?statut=scheduled
✓ GET /api/course-sessions?professeur_id=3
✓ GET /api/course-sessions?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD

// Validations
✓ POST sans cours_id → 422
✓ POST avec date_debut < today → 422
✓ PUT sur session completed → 403 (lecture seule)

// Permissions
✓ Admin peut CRUD
✓ Professeur peut GET mais pas PUT
✓ Élève: 404
```

**File:** `tests/Feature/CourseRecurrenceControllerTest.php`

```php
// CRUD + Génération
✓ POST /api/course-recurrences         // 201 + job async
✓ GET /api/course-recurrences?cours_id=5
✓ PATCH /api/course-recurrences/{id}/generate  // régénère
✓ PATCH /api/course-recurrences/{id}/statut   // pause/archive
✓ DELETE /api/course-recurrences/{id}         // 204

// Génération
✓ Generate weekly: génère 20 sessions (5 mois)
✓ Generate biweekly: génère 10 sessions
✓ Generate monthly: génère 6 sessions

// Permissions
✓ Admin: full CRUD
✓ Professeur: GET seul
```

**File:** `tests/Feature/CalendarControllerTest.php`

```php
// Calendrier
✓ GET /api/calendar/month?year=2026&month=10           // 200
✓ GET /api/calendar/year?year=2026                      // 200
✓ GET /api/calendar/professor/{id}?year=2026&month=10
✓ GET /api/calendar/agenda?date_from=...&date_to=...

// Filtres
✓ ?professeur_id=3
✓ ?cours_id=5
✓ ?statut=scheduled
```

**File:** `tests/Feature/SessionProfessorControllerTest.php`

```php
// Professeurs
✓ PATCH /api/course-sessions/{id}/professor           // change principal
✓ POST /api/course-sessions/{id}/professeurs          // ajoute assistant
✓ DELETE /api/course-sessions/{id}/professeurs/{prof_id}

// Présence
✓ PATCH /api/course-sessions/{id}/professor/{prof_id}/presence
✓ PATCH /api/course-sessions/{id}/attendance
```

### 5.3 Tests Frontend (React)

**File:** `frontend/src/components/__tests__/CalendarView.test.jsx`

```javascript
✓ Render mois courant
✓ Navigation mois précédent/suivant
✓ Affichage sessions du mois
✓ Click sur jour → crée session
✓ Click sur session → ouvre modal
✓ Drag-drop session (modifie date)
✓ Filtres: par cours, par professeur, par statut
✓ Loading state
```

**File:** `frontend/src/components/__tests__/SessionDetailModal.test.jsx`

```javascript
✓ Render formulaire vide (create)
✓ Render formulaire rempli (edit)
✓ Validation: date future
✓ Validation: professeur valide
✓ Submit: appel API
✓ Delete: confirmation puis appel
✓ Close: cancel sans changements
```

**File:** `frontend/src/components/__tests__/RecurrenceForm.test.jsx`

```javascript
✓ Render formulaire
✓ Select type (weekly/biweekly/monthly)
✓ Select jours de semaine
✓ Validation: date_fin >= date_debut
✓ Prévisualisation: affiche nb sessions
✓ Submit: appel API
```

**File:** `frontend/src/pages/__tests__/CoursesCalendarPage.test.jsx`

```javascript
✓ Affiche calendrier du mois
✓ Charge sessions via API
✓ Filtres: cours, professeur, statut
✓ Crée session
✓ Crée récurrence
✓ Responsive: mobile/tablet/desktop
```

### 5.4 Tests E2E (Cypress/Playwright)

**File:** `e2e/workflows/create-session.spec.js`

```javascript
✓ Admin: Va à /courses/calendar
✓ Clique jour → crée session
✓ Remplit form (cours, heure, lieu, prof)
✓ Submit → session apparaît dans calendrier

✓ Admin: Crée récurrence (weekly, lun-mer-ven)
✓ Voir 20 sessions générées
✓ Calendrier rafraîchit
```

**File:** `e2e/workflows/override-professor.spec.js`

```javascript
✓ Admin: Ouvre session
✓ Clique "Changer professeur"
✓ Select nouveau prof (Martin)
✓ Submit → session mise à jour
✓ Calendrier rafraîchit
```

---

## 📦 6. Phases d'Implémentation

### Phase 2A: Fondations (Semaine 1-2)

**Titre:** Infrastructure DB + API Basique + UI Simple

**Objectifs:**
- Tables + seeders fonctionnels
- API CRUD complète pour sessions
- Affichage calendrier (read-only)
- SessionDetailModal (create/edit)

**User Stories:**

| ID | Titre | Story | AC | Points |
|----|-------|-------|----|----|
| 2A-1 | Migrations sessions | Créer tables course_sessions, course_recurrences, session_professors | Migrations up/down ; Seeders OK ; Modèles avec relations | 5 |
| 2A-2 | API Sessions CRUD | GET/POST/PUT/DELETE /api/course-sessions | Tous endpoints retournent 200/201/204 ; Validations OK | 8 |
| 2A-3 | Calendrier mois (read) | GET /api/calendar/month ; Affiche jour + sessions | Retourne structure JSON ; Pagination OK | 5 |
| 2A-4 | CalendarView component | Affiche mois + jours + sessions | Render correct ; Navigation mois OK ; Click jour | 8 |
| 2A-5 | SessionDetailModal | Create + Edit modal | Soumission OK ; Validations ; Cancel | 8 |
| 2A-6 | SessionCard component | Affiche session compacte | Layout OK ; Click → detail ; Compact mode | 3 |
| 2A-7 | Intégration CoursAdminPage | Ajouter onglet "Sessions" | Tab apparaît ; Affiche sessions du cours | 5 |
| 2A-8 | Tests unitaires BD | CourseSession, CourseRecurrence tests | Tous tests passent | 5 |

**Total: 47 story points**

**Dépendances:**
```
2A-1 (Migrations) → 2A-2 (API) → 2A-4 (UI) → 2A-5 (Modal)
2A-2 → 2A-3 (Calendrier)
2A-1 → 2A-8 (Tests)
```

**Livrables:**
- Migration files
- Modèles Eloquent + relations
- Seeders
- 4 controllers (Sessions, Recurrence, Calendar, SessionProfessor)
- 4 React components (CalendarView, SessionDetailModal, SessionCard, CalendarPage)
- Tests (20+ unit tests, 15+ API tests, 10+ component tests)

**Risques:**
- Génération de sessions async peut être lente (100+ sessions)
  → Solution: Job queue (Redis) + monitoring

---

### Phase 2B: Récurrence + Override + Workflows (Semaine 3-4)

**Titre:** Génération sessions, Override profs, Workflows complets

**Objectifs:**
- API récurrence complète
- Auto-génération sessions (async job)
- Override professeur par session
- Présence/absence professeur
- Statuts: scheduled → in_progress → completed
- CalendarView complète (drag-drop, filtres)
- RecurrenceForm
- ProfessorCalendarView

**User Stories:**

| ID | Titre | Story | AC | Points |
|----|-------|-------|----|----|
| 2B-1 | Récurrence CRUD + génération | POST /api/course-recurrences ; génère N sessions async | API OK ; Sessions générées ; Job tracking | 13 |
| 2B-2 | Override professeur | PATCH session/professor ; change principal | Mise à jour OK ; SessionProfessor mis à jour | 5 |
| 2B-3 | Ajout assistant/remplaçant | POST session/professeurs | Assignation OK ; Validations | 3 |
| 2B-4 | Présence professeur | PATCH session/professor/{id}/presence | Enregistrement OK ; Motif d'absence | 5 |
| 2B-5 | Statuts sessions | PATCH session/statut ; scheduled→completed | Transitions OK ; Immutabilité après completion | 5 |
| 2B-6 | RecurrenceForm component | Formulaire complet + prévisualisation | Render OK ; Submit → API ; Génération visuelle | 8 |
| 2B-7 | CalendarView complète | Drag-drop, filtres, modes (month/week/agenda) | Drag modifie date ; Filtres OK ; 3 modes | 13 |
| 2B-8 | ProfessorCalendarView | Vue perso prof ; présence/absence | Affiche sessions du prof ; Can mark present | 8 |
| 2B-9 | SessionList (agenda mode) | Liste linéaire sessions ; tri/filtres | Render OK ; Infinite scroll | 5 |
| 2B-10 | RecurrencesAdminPage | Gestion récurrences (view/edit/pause/delete) | Page OK ; Actions fonctionnelles | 8 |
| 2B-11 | Tests API récurrence + override | Feature tests pour endpoints 2B | Tous tests passent | 8 |
| 2B-12 | Tests UI workflows | Drag-drop, create session, override, présence | Cypress/Playwright OK | 10 |

**Total: 91 story points**

**Dépendances:**
```
2A-1,2A-2 → 2B-1 (Récurrence)
2A-2 → 2B-2,2B-3,2B-4 (Override)
2B-1 → 2B-6 (Form)
2A-4 → 2B-7 (CalendarView avancée)
2B-7 + 2B-2 → 2B-8 (ProfessorView)
2B-1 → 2B-10 (RecurrencesAdminPage)
```

**Livrables:**
- Endpoints: Récurrence, Override, Présence, Statut
- Jobs: GenerateCourseSessions
- Components: RecurrenceForm, CalendarView complète, ProfessorCalendarView, SessionList
- Pages: RecurrencesAdminPage
- Tests: 25+ API tests, 15+ component tests, 8+ E2E tests

**Risques:**
- Drag-drop complexe en calendrier
  → Solution: React-big-calendar + gestion état locale
- Jobs async non déterministes
  → Solution: Tests avec seeding déterministe

---

### Phase 2C: Polish + Performance + Export (Semaine 5-6)

**Titre:** Finalisations, Performance, Export, Docs

**Objectifs:**
- Export PDF calendrier
- Notifications changements session (future: notifications push)
- Performance: caching calendrier, pagination
- Notifications email (directeur + profs)
- Audit logs (qui a changé quoi, quand)
- Documentation API
- Performance testing + optimisation DB

**User Stories:**

| ID | Titre | Story | AC | Points |
|----|-------|-------|----|----|
| 2C-1 | Export PDF calendrier | Button "Exporter PDF" ; génère PDF du mois | PDF lisible ; Inclut sessions, profs, lieux | 8 |
| 2C-2 | Notifications email | Envoyer email quand session créée/modifiée/annulée | Emails reçus ; Template OK | 8 |
| 2C-3 | Audit logs | Tracer modifications session/recurrence/présence | Logs stockés ; Queryable | 5 |
| 2C-4 | Caching calendrier | Mettre en cache GET /api/calendar/month | Cache invalidé quand changement | 5 |
| 2C-5 | Pagination optimisée | Lazy load sessions si > 100 | Perf OK ; Scroll fluide | 5 |
| 2C-6 | Alertes UI | Toast notifications ; validations améliores | UX fluide ; Erreurs claires | 5 |
| 2C-7 | Performance DB | Ajouter indexes ; optimiser N+1 queries | Queries < 100ms ; Explain OK | 8 |
| 2C-8 | Documentation API | Générer Swagger/OpenAPI pour routes 2A/2B | Docs complètes ; Tests OK | 5 |
| 2C-9 | Tests load | Générer 1000 sessions ; Perf acceptable | Load < 2s ; Memory OK | 5 |
| 2C-10 | Responsive mobile | CalendarView, SessionDetailModal mobiles | Layout OK < 768px | 8 |
| 2C-11 | Dark mode | Support dark theme (CSS variables) | CalendarView OK en dark | 3 |
| 2C-12 | Intégration timesheets | Lier session_id à timesheets (futur Sprint 3) | Relation créée ; Foreign key OK | 3 |

**Total: 68 story points**

**Dépendances:**
```
2B-7 → 2C-1 (Export PDF)
2B-1 + 2B-4 → 2C-2 (Notifications)
2B-3 → 2C-3 (Audit)
2A-3 → 2C-4,2C-5 (Caching)
2A-2 → 2C-7 (DB Perf)
```

**Livrables:**
- Export PDF (TCPDF ou Dompdf)
- Notifications email (Mailable + Queue)
- Audit middleware + table
- Swagger documentation
- Performance tests + benchmarks
- Responsive CSS + dark mode
- Migration pour session_id dans timesheets (future)

**Risques:**
- Emails en production
  → Solution: Queue + Redis ; Tests avec Mailtrap
- Perf PDF si beaucoup de sessions
  → Solution: Génération async (job)

---

## 👥 7. Ressources & Estimations

### Timeline (Par Phase)

| Phase | Durée | Story Points | Points/Semaine | Équipe |
|-------|-------|--------------|-----------------|--------|
| 2A | 2 sem | 47 | 23-24 | 2-3 devs |
| 2B | 2 sem | 91 | 45-46 | 3 devs |
| 2C | 2 sem | 68 | 34-35 | 2-3 devs |
| **Total** | **6 sem** | **206** | **~34/sem** | **3 devs** |

### Rôles

**Backend Developer (1-2)**
- Migrations, Modèles, Controllers
- Jobs async (Récurrence)
- Tests API + unitaires
- Optimisation DB

**Frontend Developer (1-2)**
- Components React
- Pages
- Tests Jest + Cypress
- Responsive design

**QA/Tester (0.5)**
- Tests exploratoires
- E2E tests
- Performance testing

**DevOps (0.5)**
- CI/CD pour tests
- Monitoring jobs async
- Database migrations en prod

### Stack & Outils

**Backend:**
- Laravel 11 + Sanctum
- PostgreSQL 15+
- Redis (queue)
- Faker (fixtures)
- PHPUnit (tests)

**Frontend:**
- React 18
- React Query (data fetching)
- React-big-calendar (calendrier)
- Tailwind CSS
- Jest + React Testing Library
- Cypress/Playwright (E2E)

**DevOps:**
- Docker Compose
- GitHub Actions (CI)
- Sentry (error tracking)

---

## 🚨 8. Risks & Mitigations

| Risk | Probabilité | Impact | Mitigation |
|------|-------------|--------|-----------|
| Génération sessions lente (100+) | Moyen | Haut | Job async + monitoring ; limite à 50/batch |
| Drag-drop calendrier buggé | Moyen | Moyen | Utiliser lib éprouvée (react-big-calendar) ; tests complets |
| N+1 queries (professeurs, cours) | Élevée | Moyen | Eager loading (with/load) ; tests de perf |
| Notifications email non livrées | Bas | Moyen | Queue résiliente ; logs détaillés ; retry |
| Perf PDF avec beaucoup de sessions | Bas | Bas | Génération async ; pagination |
| Conflits concurrence (2 admins modifient) | Moyen | Moyen | Optimistic locking ou versioning (futur) |
| Tests E2E flaky | Moyen | Moyen | Selectors robustes ; waits explicites ; retry logic |

---

## ✨ 9. Améliorations Futures (Sprint 3+)

- **Sessions recurrentes imbriquées:** Semestres → Modules → Sessions
- **Récurrence par bloc:** "Vacances scolaires → pause récurrence"
- **Timesheets intégrées:** Lier sessions à timesheets automatiquement
- **Ressources:** Salles, équipements réservables
- **Notifications:** Push notifications + SMS
- **Webhooks:** Intégrations externes (Slack, Google Calendar)
- **Versioning:** Historique complet avec rollback
- **Rapports:** Taux présence prof/cours, statistiques sessions
- **Localisation:** Support multi-langue (FR/EN/...)

---

## 📚 10. Références & Templates

### Template Story (Markdown)

```markdown
## US-2X-Y: Titre court

**En tant que** directeur / professeur / élève,  
**Je veux** pouvoir faire X,  
**Afin que** je puisse Y.

### Acceptance Criteria
- [ ] Critère 1
- [ ] Critère 2
- [ ] Critère 3

### Tasks (Dépendances)
- [ ] T1: Tâche 1 (2h)
- [ ] T2: Tâche 2 (T1 terminée, 4h)

### Notes
- Considération technique
- Lien vers docs
```

### Template Migration (Laravel)

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('table_name', function (Blueprint $table) {
            $table->id();
            // colonnes
            $table->timestamps();
            $table->index(['col1', 'col2']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('table_name');
    }
};
```

### Template Test Feature (PHPUnit)

```php
<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;

class SampleTest extends TestCase {
    public function test_endpoint_returns_200() {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/endpoint');
        $response->assertStatus(200);
    }
}
```

### Template Component (React)

```jsx
import React, { useState, useEffect } from 'react';
import { useQuery } from 'react-query';

export const SampleComponent = ({ propA }) => {
  const { data, isLoading, error } = useQuery(
    ['key', propA],
    () => fetch(`/api/endpoint?param=${propA}`).then(r => r.json())
  );

  if (isLoading) return <div>Chargement...</div>;
  if (error) return <div>Erreur: {error.message}</div>;

  return <div>{data?.map(item => <div key={item.id}>{item.name}</div>)}</div>;
};
```

---

## 📝 Checklist de Démarrage Sprint 2A

- [ ] Créer branches feature (`feature/2a-migrations`, `feature/2a-api-sessions`, etc.)
- [ ] Configurer timers sprint (standups quotidiens, demo fin de phase)
- [ ] Setup CI/CD pour tests automatiques
- [ ] Créer issues GitHub avec labels (Sprint2A, 2B, 2C)
- [ ] Planifier avec équipe (estimation, assignation)
- [ ] Préparer environment de dev (DB, seeding)
- [ ] Établir conventions de code (naming, structure)
- [ ] Configurer Sentry / error tracking
- [ ] Mettre à jour CI/CD pour nouvelles tables

---

## 📞 Points de Contact

- **Product Owner:** Valider user stories, priorités
- **Tech Lead:** Architecture, reviews, migrations DB
- **QA:** Plan test, test cases, E2E
- **DevOps:** Setup CI/CD, monitoring, production deployment

---

**Document créé:** 29 sept 2026  
**Version:** 1.0  
**Statut:** Prêt pour sprint planning
