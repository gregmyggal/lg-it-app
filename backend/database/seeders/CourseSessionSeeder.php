<?php

namespace Database\Seeders;

use App\Models\Cours;
use App\Models\CourseSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CourseSessionSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Cours::limit(3)->get();

        if ($courses->isEmpty()) {
            $this->command->warn('No courses found. Run CoursSeeder first.');
            return;
        }

        $statuses = ['scheduled', 'in_progress', 'completed', 'scheduled', 'scheduled'];
        $statusWeights = [0.6 => 'scheduled', 0.85 => 'in_progress', 1.0 => 'completed'];

        $sessionCount = 0;

        foreach ($courses as $cours) {
            // Generate sessions for October 2026
            $date = Carbon::create(2026, 10, 1);
            $endDate = Carbon::create(2026, 10, 31);

            while ($date->lessThanOrEqualTo($endDate)) {
                // Create 1-3 sessions per day randomly
                $sessionsPerDay = rand(1, 3);
                $usedTimes = [];

                for ($i = 0; $i < $sessionsPerDay; $i++) {
                    // Generate unique time slot
                    do {
                        $startHour = rand(8, 18);
                        $startMin = rand(0, 59);
                        $timeKey = sprintf('%02d:%02d', $startHour, $startMin);
                    } while (in_array($timeKey, $usedTimes));

                    $usedTimes[] = $timeKey;
                    $startTime = $timeKey;
                    $endTime = sprintf('%02d:%02d', $startHour + 2, $startMin);

                    // Determine status
                    $rand = (float) rand(0, 100) / 100;
                    $status = 'scheduled';
                    foreach ($statusWeights as $threshold => $s) {
                        if ($rand <= $threshold) {
                            $status = $s;
                            break;
                        }
                    }

                    // Create session
                    $session = CourseSession::create([
                        'cours_id' => $cours->id,
                        'date_debut' => $date->copy(),
                        'heure_debut' => $startTime,
                        'heure_fin' => $endTime,
                        'titre' => $cours->titre . ' - ' . $date->format('d/m'),
                        'lieu' => collect(['Salle 201', 'Salle 202', 'Visio', 'Salle 301'])->random(),
                        'description' => 'Session test créée par seeder',
                        'statut' => $status,
                        'nb_eleves_attendus' => rand(5, 25),
                        'nb_eleves_presentes' => $status === 'completed' ? rand(3, 25) : null,
                    ]);

                    $sessionCount++;

                    // 30% chance of cancellation
                    if (rand(1, 100) <= 30) {
                        $session->update([
                            'statut' => 'cancelled',
                            'motif_annulation' => collect([
                                'Professeur indisponible',
                                'Salle occupée',
                                'Problème technique',
                                'Reprogrammé',
                            ])->random(),
                        ]);
                    }
                }

                $date->addDay();
            }

            $this->command->info("Created $sessionCount sessions for course: {$cours->titre}");
        }

        $this->command->info("CourseSessionSeeder completed: $sessionCount sessions created for October 2026");
    }
}
