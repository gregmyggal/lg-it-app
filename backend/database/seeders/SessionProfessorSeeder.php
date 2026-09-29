<?php

namespace Database\Seeders;

use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\SessionProfessor;
use Illuminate\Database\Seeder;

class SessionProfessorSeeder extends Seeder
{
    public function run(): void
    {
        $sessions = CourseSession::all();
        $professors = Professeur::all();

        if ($sessions->isEmpty()) {
            $this->command->warn('No sessions found. Run CourseSessionSeeder first.');
            return;
        }

        if ($professors->isEmpty()) {
            $this->command->warn('No professors found. Run ProfesseurSeeder first.');
            return;
        }

        $assignmentCount = 0;

        foreach ($sessions as $session) {
            // Get professors linked to this course
            $courseProfs = $session->cours->professeurs()
                ->limit(rand(1, 3))
                ->inRandomOrder()
                ->get();

            if ($courseProfs->isEmpty()) {
                // Fallback: assign random professors
                $numProfs = min(rand(1, 3), $professors->count());
                $courseProfs = $professors->random($numProfs);
            }

            // Assign professors
            foreach ($courseProfs as $index => $prof) {
                $role = $index === 0 ? 'principal' : collect(['assistant', 'substitute', 'observer'])->random();

                SessionProfessor::create([
                    'course_session_id' => $session->id,
                    'professeur_id' => $prof->id,
                    'role' => $role,
                    'present' => $session->statut === 'completed' ? (bool) rand(0, 1) : null,
                    'motif_absence' => rand(1, 100) <= 20 ? 'Absence non justifiée' : null,
                ]);

                $assignmentCount++;
            }

            $this->command->line("  Assigned {$courseProfs->count()} professors to session #{$session->id}");
        }

        $this->command->info("SessionProfessorSeeder completed: $assignmentCount assignments created");
    }
}
