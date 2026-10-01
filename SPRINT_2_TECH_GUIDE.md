# Sprint 2: Guide Technique Rapide

Guide pour démarrer l'implémentation rapidement, conventions et patterns utilisés dans le projet.

---

## 🚀 Démarrage Rapide

### Cloner et Setup

```bash
# Cloner le projet (si pas déjà fait)
git clone <repo-url> lg-it-app
cd lg-it-app

# Backend setup
cd backend
cp .env.example .env
composer install
php artisan migrate
php artisan db:seed

# Frontend setup
cd ../frontend
npm install

# Docker
docker compose up -d
```

### Branches & Commits

**Pattern de branche:**
```
feature/sprint2a-migrations
feature/sprint2a-api-sessions
feature/sprint2b-recurrence
hotfix/sessions-validation
```

**Message de commit:**
```
feat(sessions): Create course_sessions migration and model

- Add CourseSession model with relations
- Create migration with proper indexes
- Add seeders for test data

Relates to: 2A-1
```

**Attribution:** Commits se terminent automatiquement par:
```
Co-Authored-By: Claude Haiku 4.5 <noreply@anthropic.com>
```

---

## 🏗️ Patterns Backend (Laravel)

### Structure Dossiers

```
backend/
├── app/
│   ├── Models/
│   │   ├── CourseSession.php (new)
│   │   ├── CourseRecurrence.php (new)
│   │   └── SessionProfessor.php (new)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── CourseSessionController.php (new)
│   │   │   ├── CourseRecurrenceController.php (new)
│   │   │   └── SessionProfessorController.php (new)
│   │   └── Resources/
│   │       ├── CourseSessionResource.php (new)
│   │       └── CourseRecurrenceResource.php (new)
│   ├── Jobs/
│   │   └── GenerateCourseSessions.php (new)
│   └── Services/
│       └── RecurrenceGenerationService.php (new)
├── database/
│   ├── migrations/ (3+ new files)
│   └── seeders/
│       ├── CoursSessionSeeder.php (new)
│       ├── CourseRecurrenceSeeder.php (new)
│       └── SessionProfessorSeeder.php (new)
├── routes/
│   └── api.php (update)
└── tests/
    ├── Feature/
    │   ├── CourseSessionControllerTest.php (new)
    │   ├── CourseRecurrenceControllerTest.php (new)
    │   └── SessionProfessorControllerTest.php (new)
    └── Unit/
        ├── CourseSessionTest.php (new)
        ├── CourseRecurrenceTest.php (new)
        └── SessionProfessorTest.php (new)
```

### Pattern: Model avec Relations

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseSession extends Model
{
    // Attributs
    protected $fillable = [
        'cours_id',
        'recurrence_id',
        'date_debut',
        'heure_debut',
        'heure_fin',
        'titre',
        'lieu',
        'description',
        'statut',
        'motif_annulation',
        'professor_principal_id',
        'nb_eleves_attendus',
        'nb_eleves_presentes',
        'cancelled_at',
    ];

    // Casts
    protected $casts = [
        'date_debut' => 'date',
        'heure_debut' => 'datetime:H:i',
        'heure_fin' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relations
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function professeurPrincipal(): BelongsTo
    {
        return $this->belongsTo(Professeur::class, 'professor_principal_id');
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(CourseRecurrence::class);
    }

    public function sessionProfesseurs(): HasMany
    {
        return $this->hasMany(SessionProfessor::class, 'course_session_id');
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('date_debut', '>=', now()->toDateString());
    }

    public function scopeByStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopeByProfesseur($query, $professeurId)
    {
        return $query->whereHas('sessionProfesseurs', function ($q) use ($professeurId) {
            $q->where('professeur_id', $professeurId);
        });
    }

    public function scopeInDateRange($query, $dateFrom, $dateTo)
    {
        return $query->whereBetween('date_debut', [$dateFrom, $dateTo]);
    }
}
```

### Pattern: Controller avec CRUD

```php
<?php
namespace App\Http\Controllers;

use App\Models\CourseSession;
use App\Http\Resources\CourseSessionResource;
use Illuminate\Http\Request;

class CourseSessionController extends Controller
{
    // Authentification middleware vérifié dans routes/api.php

    public function index(Request $request)
    {
        $validated = $request->validate([
            'cours_id' => 'nullable|exists:cours,id',
            'statut' => 'nullable|in:scheduled,in_progress,completed,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'professeur_id' => 'nullable|exists:professeurs,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CourseSession::with(['cours', 'professeurPrincipal', 'sessionProfesseurs.professeur']);

        if ($validated['cours_id'] ?? null) {
            $query->where('cours_id', $validated['cours_id']);
        }
        if ($validated['statut'] ?? null) {
            $query->where('statut', $validated['statut']);
        }
        if ($validated['date_from'] ?? null && $validated['date_to'] ?? null) {
            $query->inDateRange($validated['date_from'], $validated['date_to']);
        }
        if ($validated['professeur_id'] ?? null) {
            $query->byProfesseur($validated['professeur_id']);
        }

        return CourseSessionResource::collection(
            $query->orderBy('date_debut')->paginate($validated['per_page'] ?? 25)
        );
    }

    public function store(Request $request)
    {
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

        // Validation personnalisée: pas de duplicate
        $exists = CourseSession::where('cours_id', $validated['cours_id'])
            ->where('date_debut', $validated['date_debut'])
            ->where('heure_debut', $validated['heure_debut'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Une session existe déjà à cette date/heure pour ce cours',
                'errors' => ['date_heure' => 'Duplicate session'],
            ], 422);
        }

        $session = CourseSession::create($validated);

        // Assigner professeur principal si fourni
        if ($validated['professor_principal_id'] ?? null) {
            SessionProfessor::create([
                'course_session_id' => $session->id,
                'professeur_id' => $validated['professor_principal_id'],
                'role' => 'principal',
            ]);
        }

        return new CourseSessionResource($session->fresh(['cours', 'professeurPrincipal', 'sessionProfesseurs'])), 201);
    }

    public function show(CourseSession $session)
    {
        return new CourseSessionResource($session->load(['cours', 'professeurPrincipal', 'sessionProfesseurs.professeur']));
    }

    public function update(Request $request, CourseSession $session)
    {
        // Lecture-seule si completed/cancelled
        if (in_array($session->statut, ['completed', 'cancelled'])) {
            return response()->json([
                'message' => 'Session archivée, modifications non autorisées',
            ], 403);
        }

        $validated = $request->validate([
            'titre' => 'nullable|string|max:255',
            'lieu' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'heure_debut' => 'nullable|date_format:H:i',
            'heure_fin' => 'nullable|date_format:H:i|after:heure_debut',
            'professor_principal_id' => 'nullable|exists:professeurs,id',
            'nb_eleves_attendus' => 'nullable|integer|min:0',
        ]);

        $session->update($validated);

        return new CourseSessionResource($session->fresh(['cours', 'professeurPrincipal', 'sessionProfesseurs']));
    }

    public function destroy(CourseSession $session)
    {
        // TODO: Vérifier s'il y a des timesheets associées
        $session->delete();
        return response()->noContent();
    }

    public function updateStatut(Request $request, CourseSession $session)
    {
        $validated = $request->validate([
            'statut' => 'required|in:scheduled,in_progress,completed,cancelled',
            'motif_annulation' => 'required_if:statut,cancelled|nullable|string',
        ]);

        $transitionsValides = [
            'scheduled' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => ['cancelled'],
            'cancelled' => [],
        ];

        if (!in_array($validated['statut'], $transitionsValides[$session->statut] ?? [])) {
            return response()->json([
                'message' => "Transition non autorisée: {$session->statut} → {$validated['statut']}",
            ], 422);
        }

        $session->update([
            'statut' => $validated['statut'],
            'motif_annulation' => $validated['motif_annulation'] ?? null,
            'cancelled_at' => $validated['statut'] === 'cancelled' ? now() : null,
        ]);

        return new CourseSessionResource($session->fresh(['cours', 'professeurPrincipal', 'sessionProfesseurs']));
    }
}
```

### Pattern: Migration

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('recurrence_id')->nullable()->constrained('course_recurrences')->nullOnDelete();
            
            $table->date('date_debut');
            $table->time('heure_debut');
            $table->time('heure_fin');
            
            $table->string('titre')->nullable();
            $table->string('lieu')->nullable();
            $table->text('description')->nullable();
            
            $table->enum('statut', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->text('motif_annulation')->nullable();
            
            $table->foreignId('professor_principal_id')->nullable()->constrained('professeurs')->nullOnDelete();
            
            $table->integer('nb_eleves_attendus')->default(0);
            $table->integer('nb_eleves_presentes')->nullable();
            
            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();
            
            // Indexes
            $table->index(['cours_id', 'date_debut']);
            $table->index(['professor_principal_id', 'date_debut']);
            $table->index(['statut', 'date_debut']);
            $table->unique(['cours_id', 'date_debut', 'heure_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sessions');
    }
};
```

### Pattern: Job Async

```php
<?php
namespace App\Jobs;

use App\Models\CourseSession;
use App\Models\CourseRecurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class GenerateCourseSessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $recurrenceId;

    public function __construct($recurrenceId)
    {
        $this->recurrenceId = $recurrenceId;
    }

    public function handle(): void
    {
        $recurrence = CourseRecurrence::find($this->recurrenceId);
        if (!$recurrence) {
            return;
        }

        $dates = $this->generateDates($recurrence);
        $sessions = [];

        foreach ($dates as $date) {
            $sessions[] = [
                'cours_id' => $recurrence->cours_id,
                'recurrence_id' => $recurrence->id,
                'date_debut' => $date,
                'heure_debut' => $recurrence->heure_debut,
                'heure_fin' => $recurrence->heure_fin,
                'lieu' => $recurrence->lieu_defaut,
                'professor_principal_id' => $recurrence->cours->professeurs->first()?->id,
                'statut' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Upsert: crée ou ignore si existe déjà
        CourseSession::upsert(
            $sessions,
            ['cours_id', 'date_debut'],
            ['lieu', 'professor_principal_id', 'updated_at']
        );

        $recurrence->update(['statut' => 'active']);
    }

    protected function generateDates(CourseRecurrence $recurrence): array
    {
        $dates = [];
        $current = Carbon::parse($recurrence->date_debut);
        $end = $recurrence->date_fin ? Carbon::parse($recurrence->date_fin) : $current->addMonths(6);
        $jours = explode(',', $recurrence->jours_semaine);

        while ($current <= $end) {
            if (in_array($current->dayOfWeek, $jours)) {
                $dates[] = $current->toDateString();
            }

            if ($recurrence->type === 'weekly') {
                $current->addDay();
            } elseif ($recurrence->type === 'biweekly') {
                $current->addDays(14);
            } elseif ($recurrence->type === 'monthly') {
                $current->addMonth();
            }
        }

        return array_slice($dates, 0, 200); // Max 200 sessions par job
    }
}
```

### Pattern: Test API (Feature)

```php
<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Cours;
use App\Models\CourseSession;
use App\Models\Professeur;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CourseSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cours;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cours = Cours::factory()->create();
    }

    public function test_index_returns_sessions_paginated(): void
    {
        CourseSession::factory(30)->create(['cours_id' => $this->cours->id]);

        $response = $this->actingAs($this->user)->getJson('/api/course-sessions');

        $response->assertStatus(200);
        $response->assertJsonCount(25, 'data');
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_store_creates_session(): void
    {
        $data = [
            'cours_id' => $this->cours->id,
            'date_debut' => now()->addDay()->toDateString(),
            'heure_debut' => '09:30',
            'heure_fin' => '11:30',
            'lieu' => 'Salle 201',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/course-sessions', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('course_sessions', ['cours_id' => $this->cours->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/course-sessions', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cours_id', 'date_debut', 'heure_debut', 'heure_fin']);
    }

    public function test_store_rejects_duplicate_session(): void
    {
        CourseSession::create([
            'cours_id' => $this->cours->id,
            'date_debut' => '2026-10-05',
            'heure_debut' => '09:30',
            'heure_fin' => '11:30',
        ]);

        $data = [
            'cours_id' => $this->cours->id,
            'date_debut' => '2026-10-05',
            'heure_debut' => '09:30',
            'heure_fin' => '11:30',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/course-sessions', $data);

        $response->assertStatus(422);
    }

    public function test_update_prevents_modification_of_completed_session(): void
    {
        $session = CourseSession::factory()->create(['statut' => 'completed']);

        $response = $this->actingAs($this->user)->putJson("/api/course-sessions/{$session->id}", [
            'titre' => 'Modified',
        ]);

        $response->assertStatus(403);
    }
}
```

---

## 🎨 Patterns Frontend (React)

### Structure Dossiers

```
frontend/src/
├── components/
│   ├── CalendarView.jsx (new)
│   ├── SessionDetailModal.jsx (new)
│   ├── SessionCard.jsx (new)
│   ├── SessionList.jsx (new)
│   ├── RecurrenceForm.jsx (new)
│   ├── ProfessorCalendarView.jsx (new)
│   ├── ProfessorOverrideModal.jsx (new)
│   └── __tests__/
│       └── *.test.jsx
├── hooks/
│   ├── useSessions.js (new)
│   └── useCalendarMonth.js (new)
├── pages/
│   ├── CoursesCalendarPage.jsx (new)
│   ├── ProfessorCalendarPage.jsx (new)
│   ├── RecurrencesAdminPage.jsx (new)
│   └── admin/
│       └── CoursAdminPage.jsx (update)
└── api/
    ├── sessions.js (new)
    └── calendar.js (new)
```

### Pattern: Hook Custom (React Query)

```javascript
// hooks/useSessions.js
import { useQuery, useMutation, useQueryClient } from 'react-query';
import * as api from '../api/sessions';

export function useSessions(filters = {}) {
  return useQuery(
    ['sessions', filters],
    () => api.fetchSessions(filters),
    {
      staleTime: 1000 * 60 * 5, // 5 min
      cacheTime: 1000 * 60 * 10, // 10 min
    }
  );
}

export function useSessionDetail(id) {
  return useQuery(
    ['session', id],
    () => api.fetchSession(id),
    {
      enabled: !!id,
    }
  );
}

export function useCreateSession() {
  const queryClient = useQueryClient();

  return useMutation(
    (data) => api.createSession(data),
    {
      onSuccess: () => {
        // Invalider cache des sessions
        queryClient.invalidateQueries('sessions');
      },
    }
  );
}

export function useUpdateSession() {
  const queryClient = useQueryClient();

  return useMutation(
    ({ id, data }) => api.updateSession(id, data),
    {
      onSuccess: (data) => {
        queryClient.invalidateQueries('sessions');
        queryClient.setQueryData(['session', data.id], data);
      },
    }
  );
}

export function useDeleteSession() {
  const queryClient = useQueryClient();

  return useMutation(
    (id) => api.deleteSession(id),
    {
      onSuccess: () => {
        queryClient.invalidateQueries('sessions');
      },
    }
  );
}
```

### Pattern: Component avec State

```javascript
// components/SessionDetailModal.jsx
import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useCreateSession, useUpdateSession, useDeleteSession } from '../hooks/useSessions';
import { useCourses } from '../hooks/useCourses';
import { useProfesseurs } from '../hooks/useProfesseurs';

export function SessionDetailModal({ open, onClose, session, coursId, onSave, onDelete }) {
  const { register, handleSubmit, watch, formState: { errors }, reset } = useForm({
    defaultValues: session || {
      date_debut: new Date().toISOString().split('T')[0],
      heure_debut: '09:30',
      heure_fin: '11:30',
      cours_id: coursId,
    },
  });

  const { data: courses } = useCourses();
  const { data: professeurs } = useProfesseurs();
  const createSession = useCreateSession();
  const updateSession = useUpdateSession();
  const deleteSession = useDeleteSession();

  const [confirmDelete, setConfirmDelete] = useState(false);

  useEffect(() => {
    if (session) {
      reset(session);
    }
  }, [session, reset]);

  const onSubmit = async (data) => {
    if (session?.id) {
      updateSession.mutate({ id: session.id, data }, {
        onSuccess: (saved) => {
          onSave?.(saved);
          onClose();
        },
      });
    } else {
      createSession.mutate(data, {
        onSuccess: (saved) => {
          onSave?.(saved);
          onClose();
        },
      });
    }
  };

  const handleDelete = async () => {
    deleteSession.mutate(session.id, {
      onSuccess: () => {
        onDelete?.(session.id);
        onClose();
      },
    });
  };

  if (!open) return null;

  const isLoading = createSession.isLoading || updateSession.isLoading || deleteSession.isLoading;
  const heureFin = watch('heure_fin');
  const heureDebut = watch('heure_debut');

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-2xl max-h-screen overflow-y-auto">
        <h2 className="text-2xl font-bold mb-4">
          {session?.id ? 'Éditer la session' : 'Nouvelle session'}
        </h2>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {/* Cours */}
          <div>
            <label className="block text-sm font-medium">Cours *</label>
            <select {...register('cours_id', { required: true })} className="w-full border rounded p-2">
              <option value="">-- Sélectionner --</option>
              {courses?.map(c => <option key={c.id} value={c.id}>{c.titre}</option>)}
            </select>
            {errors.cours_id && <span className="text-red-500 text-sm">Requis</span>}
          </div>

          {/* Date */}
          <div>
            <label className="block text-sm font-medium">Date *</label>
            <input type="date" {...register('date_debut', { required: true })} className="w-full border rounded p-2" />
            {errors.date_debut && <span className="text-red-500 text-sm">Requis, date future</span>}
          </div>

          {/* Horaires */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium">De *</label>
              <input type="time" {...register('heure_debut', { required: true })} className="w-full border rounded p-2" />
            </div>
            <div>
              <label className="block text-sm font-medium">À *</label>
              <input type="time" {...register('heure_fin', { required: true })} className="w-full border rounded p-2" />
              {heureDebut && heureFin && heureDebut >= heureFin && <span className="text-red-500 text-sm">Doit être après l'heure de début</span>}
            </div>
          </div>

          {/* Lieu */}
          <div>
            <label className="block text-sm font-medium">Lieu</label>
            <input type="text" {...register('lieu')} placeholder="ex: Salle 201" className="w-full border rounded p-2" />
          </div>

          {/* Professeur Principal */}
          <div>
            <label className="block text-sm font-medium">Professeur</label>
            <select {...register('professor_principal_id')} className="w-full border rounded p-2">
              <option value="">-- Aucun --</option>
              {professeurs?.map(p => <option key={p.id} value={p.id}>{p.nom}</option>)}
            </select>
          </div>

          {/* Effectif */}
          <div>
            <label className="block text-sm font-medium">Nombre d'élèves attendus</label>
            <input type="number" {...register('nb_eleves_attendus')} min="0" className="w-full border rounded p-2" />
          </div>

          {/* Description */}
          <div>
            <label className="block text-sm font-medium">Description</label>
            <textarea {...register('description')} rows="3" className="w-full border rounded p-2"></textarea>
          </div>

          {/* Buttons */}
          <div className="flex gap-2 justify-between pt-4">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded border"
            >
              Annuler
            </button>

            {session?.id && !confirmDelete && (
              <button
                type="button"
                onClick={() => setConfirmDelete(true)}
                className="px-4 py-2 rounded bg-red-500 text-white"
              >
                Supprimer
              </button>
            )}

            {session?.id && confirmDelete && (
              <>
                <button
                  type="button"
                  onClick={() => setConfirmDelete(false)}
                  className="px-4 py-2 rounded border"
                >
                  Annuler
                </button>
                <button
                  type="button"
                  onClick={handleDelete}
                  disabled={isLoading}
                  className="px-4 py-2 rounded bg-red-700 text-white disabled:opacity-50"
                >
                  {isLoading ? 'Suppression...' : 'Confirmer la suppression'}
                </button>
              </>
            )}

            {!confirmDelete && (
              <button
                type="submit"
                disabled={isLoading}
                className="px-4 py-2 rounded bg-blue-500 text-white disabled:opacity-50"
              >
                {isLoading ? 'En cours...' : session?.id ? 'Enregistrer' : 'Créer'}
              </button>
            )}
          </div>
        </form>
      </div>
    </div>
  );
}
```

### Pattern: Test React (Jest)

```javascript
// components/__tests__/SessionDetailModal.test.jsx
import React from 'react';
import { render, screen, userEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from 'react-query';
import { SessionDetailModal } from '../SessionDetailModal';

// Mock API
jest.mock('../api/sessions', () => ({
  fetchCourses: jest.fn(() => Promise.resolve([
    { id: 1, titre: 'Java' },
    { id: 2, titre: 'Python' },
  ])),
  fetchProfesseurs: jest.fn(() => Promise.resolve([
    { id: 1, nom: 'Dupont' },
    { id: 2, nom: 'Martin' },
  ])),
  createSession: jest.fn((data) => Promise.resolve({ id: 123, ...data })),
  updateSession: jest.fn((id, data) => Promise.resolve({ id, ...data })),
}));

const createWrapper = () => {
  const queryClient = new QueryClient();
  return ({ children }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
};

describe('SessionDetailModal', () => {
  it('renders create form when no session provided', () => {
    render(
      <SessionDetailModal open={true} onClose={jest.fn()} />,
      { wrapper: createWrapper() }
    );
    expect(screen.getByText('Nouvelle session')).toBeInTheDocument();
  });

  it('renders edit form when session provided', () => {
    const session = {
      id: 1,
      titre: 'Java - Semaine 1',
      date_debut: '2026-10-05',
      heure_debut: '09:30',
      heure_fin: '11:30',
    };
    render(
      <SessionDetailModal open={true} onClose={jest.fn()} session={session} />,
      { wrapper: createWrapper() }
    );
    expect(screen.getByText('Éditer la session')).toBeInTheDocument();
    expect(screen.getByDisplayValue('09:30')).toBeInTheDocument();
  });

  it('submits form and calls onSave', async () => {
    const onSave = jest.fn();
    const { getByRole, getByLabelText } = render(
      <SessionDetailModal open={true} onClose={jest.fn()} onSave={onSave} />,
      { wrapper: createWrapper() }
    );

    await userEvent.selectOptions(getByLabelText(/Cours/i), '1');
    await userEvent.type(getByLabelText(/Date/i), '2026-10-05');
    await userEvent.type(getByLabelText(/De/i), '09:30');
    await userEvent.type(getByLabelText(/À/i), '11:30');

    await userEvent.click(getByRole('button', { name: /Créer/i }));

    await waitFor(() => {
      expect(onSave).toHaveBeenCalled();
    });
  });

  it('validates heure_fin must be after heure_debut', async () => {
    const { getByLabelText, getByText } = render(
      <SessionDetailModal open={true} onClose={jest.fn()} />,
      { wrapper: createWrapper() }
    );

    await userEvent.type(getByLabelText(/De/i), '11:30');
    await userEvent.type(getByLabelText(/À/i), '09:30');

    expect(getByText(/Doit être après/i)).toBeInTheDocument();
  });
});
```

---

## 🔄 Workflows Communs

### Créer une Migration + Modèle

```bash
# 1. Générer migration + modèle
php artisan make:model CourseSession -m

# 2. Éditer migration
nano database/migrations/2026_XX_XX_create_course_sessions_table.php
# → Ajouter colonnes, indexes, constraints

# 3. Éditer modèle
nano app/Models/CourseSession.php
# → Ajouter fillable, casts, relations

# 4. Exécuter
php artisan migrate

# 5. Créer seeder si besoin
php artisan make:seeder CourseSessionSeeder
php artisan db:seed --class=CourseSessionSeeder
```

### Créer un Controller + Tests

```bash
# 1. Générer controller
php artisan make:controller CourseSessionController --api

# 2. Créer tests
php artisan make:test CourseSessionControllerTest --feature

# 3. Implémenter controller + tests en parallèle (TDD)

# 4. Lancer tests
php artisan test tests/Feature/CourseSessionControllerTest.php
```

### Créer un Component React + Tests

```bash
# 1. Créer fichier composant
touch frontend/src/components/SessionDetailModal.jsx

# 2. Créer fichier test
mkdir -p frontend/src/components/__tests__
touch frontend/src/components/__tests__/SessionDetailModal.test.jsx

# 3. Implémenter TDD

# 4. Lancer tests
cd frontend && npm test
```

---

## 📊 Checklist Phase 2A

- [ ] Créer branches feature (git checkout -b feature/2a-migrations)
- [ ] Implémenter migrations (2A-1)
- [ ] Implémenter modèles + relations (2A-1)
- [ ] Implémenter CRUD API (2A-2)
- [ ] Implémenter calendrier endpoint (2A-3)
- [ ] Implémenter CalendarView component (2A-4)
- [ ] Implémenter SessionDetailModal (2A-5)
- [ ] Implémenter SessionCard component (2A-6)
- [ ] Intégrer CoursAdminPage (2A-7)
- [ ] Tests unitaires (2A-8)
- [ ] Merges PRs avec code review (min 1 approval)
- [ ] Tests passent en CI/CD
- [ ] Déployer en staging

---

## 🛠️ Debugging Tips

**Backend Laravel:**
```bash
# Logs en temps réel
tail -f storage/logs/laravel.log

# Tinker (REPL)
php artisan tinker
> $session = \App\Models\CourseSession::first();
> $session->cours->titre;

# Query logging
\Illuminate\Support\Facades\DB::listen(function($query) {
    echo $query->sql . " [" . implode(", ", $query->bindings) . "]\n";
});

# Test spécifique
php artisan test --filter test_index_returns_sessions
```

**Frontend React:**
```bash
# React DevTools extension
# Redux DevTools (si utilisé)

# Logs
console.log('data:', data);
console.table(sessions);

# Debug breakpoints
debugger; // dans le code
// ou DevTools > Sources > Breakpoints

# Test uniquement
npm test -- --testNamePattern="renders create form"
```

---

## 📚 Ressources

**Documentation Officielle:**
- Laravel Docs: https://laravel.com/docs/11.x
- React Docs: https://react.dev
- React Query: https://tanstack.com/query/latest
- Jest: https://jestjs.io/

**Conventions du Projet:**
- Voir commit `aa491d2` pour structure Sprint 1
- Utiliser camelCase en frontend, snake_case en backend
- Énumérations: `enum()` en Laravel, `string` en React (pour souplesse)

**Outils:**
- Laravel Debugbar: `composer require barryvdh/laravel-debugbar --dev`
- Postman/Insomnia: pour tester API
- Swagger UI: `/api/docs` (future 2C)

---

**Bon développement!**
