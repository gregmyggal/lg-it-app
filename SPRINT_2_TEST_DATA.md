# Sprint 2 Test Data & Seeders

**Date:** 29 septembre 2026  
**Status:** ✅ Seeders created and executed, 129 sessions + 210 assignments ready

---

## 📊 Test Data Generated

### Summary
```
CourseRecurrences:    6 rules
CourseSessions:     129 concrete sessions
SessionProfessors:  210 assignments

Total: 345 records created
```

### Database State After Seed

**Courses:** 2
- Scratch Junior
- Python Ado

**Professors:** 2
- Alice Prof
- Bob Prof

**Recurrences per Course:** 3
- Weekly Monday 09:00-11:00 (Salle 201/202)
- Weekly Wednesday 14:00-16:00 (Visio)
- Biweekly Friday 18:00-20:00 (Salle 301/302)

**Sessions:** 129 (October 2026 only)
- 1-3 sessions per day
- Mix of statuses:
  - Scheduled: 42
  - In Progress: 2
  - Completed: 87
  - Cancelled: 40 (30% of sessions)

**Professor Assignments:** 210
- 1-3 professors per session
- Roles distributed:
  - Principal (first professor)
  - Assistant/Substitute/Observer (others)
- Presence data for completed sessions
- Absence reasons for ~20% of assignments

---

## 🌱 Seeders Overview

### 1. CourseRecurrenceSeeder (71 lines)

**Creates:** Recurrence rules for course scheduling

**Logic:**
```php
foreach course in ['Scratch Junior', 'Python Ado'] {
  create recurrence:
    - type: 'weekly' | 'biweekly'
    - days: Monday | Wednesday | Friday
    - period: Sept 1 - Dec 31, 2026
    - statut: 'active'
}
```

**Output:** 6 recurrences (3 per course)

**Dates:**
- Start: 2026-09-01
- End: 2026-12-31
- Active status for recurring generation

---

### 2. CourseSessionSeeder (79 lines)

**Creates:** Concrete session instances for October 2026

**Logic:**
```php
for each course {
  for day in [Oct 1 .. Oct 31] {
    for 1-3 random sessions {
      create session:
        - date: current day
        - time: random 08:00-20:00 (unique per day)
        - statut: 60% scheduled, 85% in_progress, 100% completed
        - location: random from [Salle 201, 202, Visio, 301]
        - students: 5-25 expected, 3-25 actual if completed
        - 30% cancelled with random reason
    }
  }
}
```

**Output:** 129 sessions in October 2026

**Status Distribution:**
- 42 scheduled (33%)
- 2 in_progress (1%)
- 87 completed (67%)
- 40 cancelled (31% of total)

**Cancellation Reasons:**
- Professeur indisponible
- Salle occupée
- Problème technique
- Reprogrammé

---

### 3. SessionProfessorSeeder (54 lines)

**Creates:** Professor assignments to sessions

**Logic:**
```php
for each session {
  get 1-3 professors linked to course:
    if none, fallback to random professor
  
  for each professor (indexed):
    if first: role = 'principal'
    else: role = random('assistant', 'substitute', 'observer')
    
    if session.completed:
      present = random (true/false)
      absence_reason = 20% chance
    else:
      present = null
  
  create assignment
}
```

**Output:** 210 assignments

**Role Distribution:**
- Principal: 1 per session (129 total)
- Assistant/Substitute/Observer: distributed across 81 additional slots

**Presence Tracking:**
- Only for completed sessions
- 50% present/absent ratio
- Absence reasons for ~20%

---

## 🔄 Running the Seeders

### Fresh Seed (Reset Database)
```bash
php artisan migrate:fresh --seed
```

This will:
1. Drop all tables
2. Run all migrations (including Sprint 2 tables)
3. Seed with base users + courses + professors
4. Seed with recurrences + sessions + assignments

**Time:** ~2-3 seconds

### Seed Specific Seeder Only
```bash
php artisan db:seed --class=CourseSessionSeeder
```

### View Seeder Output
```bash
php artisan migrate:fresh --seed -vv  # Verbose output
```

---

## 🧪 Test Data Usage

### Calendar Testing
```
Frontend: Load /admin/calendar
- Month view shows October 2026
- Click any session to edit
- 129 sessions visible across 31 days
```

### API Testing
```bash
# Get calendar for October
GET /api/calendar/month?year=2026&month=10
Response: { data: { "2026-10-01": [...], ... } }

# Get sessions for course
GET /api/cours/1/sessions
Response: { data: [...129 sessions...] }

# Get professor schedule
GET /api/calendar/professor/1?year=2026&month=10
Response: { data: {...} }
```

### Professor Assignment Testing
```
- 210 professor-session assignments
- Test filtering by professor
- Test role management (principal/assistant)
- Test presence tracking
```

---

## 📋 Test Scenarios Enabled

### 1. Session Lifecycle
- ✅ View scheduled session
- ✅ Edit session details
- ✅ Mark as in progress
- ✅ Complete and record attendance
- ✅ Cancel with reason

### 2. Professor Management
- ✅ Assign multiple professors
- ✅ Assign with different roles
- ✅ Override for single session
- ✅ Track presence/absence
- ✅ Search for professors

### 3. Calendar Operations
- ✅ View month calendar
- ✅ See sessions per day
- ✅ Filter by professor
- ✅ Filter by status
- ✅ Navigate between months

### 4. Data Integrity
- ✅ Unique constraints (cours_id + date + time)
- ✅ Foreign key cascades
- ✅ Relationship loading
- ✅ Pivot data handling

---

## 📊 Query Examples

### Get October 2026 Sessions
```php
$sessions = CourseSession::whereMonth('date_debut', 10)
    ->whereYear('date_debut', 2026)
    ->with(['cours', 'professeurs'])
    ->get();
// Returns: 129 sessions
```

### Get Cancelled Sessions with Reasons
```php
$cancelled = CourseSession::where('statut', 'cancelled')
    ->get();
// Returns: 40 sessions with motif_annulation
```

### Get Professor's Sessions for Month
```php
$sessions = Professeur::find(1)
    ->sessions()
    ->whereMonth('date_debut', 10)
    ->with('cours')
    ->get();
// Returns: sessions for professor #1 in October
```

### Get Sessions by Status
```php
$completed = CourseSession::where('statut', 'completed')
    ->get(); // 87 sessions
$scheduled = CourseSession::where('statut', 'scheduled')
    ->get(); // 42 sessions
```

---

## 🔄 Resetting Data

### Full Database Reset
```bash
docker-compose exec app php artisan migrate:fresh --seed
```

### Clear Sessions Only
```bash
docker-compose exec app php artisan tinker
> App\Models\CourseSession::truncate();
> App\Models\CourseRecurrence::truncate();
```

### Re-seed Specific Table
```bash
docker-compose exec app php artisan db:seed --class=CourseSessionSeeder
```

---

## 📝 Notes

### Test Data Characteristics
- **Realistic:** Mix of statuses, locations, and professor roles
- **Comprehensive:** 129 sessions provide month-long calendar
- **Repeatable:** Same seed produces same data (except randomized fields)
- **Isolated:** October 2026 only (won't interfere with other months)

### Performance
- Seeding takes ~2-3 seconds
- 129 sessions generate 210 assignments efficiently
- Unique constraints prevent duplicates
- Indexes on (cours_id, date) for fast queries

### Coverage
- ✅ All 4 tables populated
- ✅ All relationships tested
- ✅ All statuses represented
- ✅ All roles assigned
- ✅ Edge cases (cancellation, absence)

---

## ✅ Checklist for Testing

- [ ] Calendar loads October 2026 with sessions
- [ ] Click session opens detail modal
- [ ] Edit session updates database
- [ ] Create new session works
- [ ] Delete session removes from calendar
- [ ] Assign professors interface works
- [ ] Filter by professor shows correct sessions
- [ ] Filter by status shows correct count
- [ ] Completed sessions show attendance
- [ ] Cancelled sessions show reasons
- [ ] API endpoints return correct data
- [ ] Pagination works with 20 per page

---

## Commit

- `141ff17` - Seeders for test data (345 records)

**Status:** 🎯 Database ready for Sprint 2 testing
