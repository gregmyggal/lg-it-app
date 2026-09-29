<?php

namespace Database\Seeders;

use App\Models\Cours;
use App\Models\CourseRecurrence;
use Illuminate\Database\Seeder;

class CourseRecurrenceSeeder extends Seeder
{
    public function run(): void
    {
        // Get first 3 courses
        $courses = Cours::limit(3)->get();

        if ($courses->isEmpty()) {
            $this->command->warn('No courses found. Run CoursSeeder first.');
            return;
        }

        foreach ($courses as $index => $cours) {
            // Recurrence 1: Weekly on Mondays
            CourseRecurrence::create([
                'cours_id' => $cours->id,
                'type' => 'weekly',
                'jours_semaine' => '1', // Monday
                'date_debut' => '2026-09-01',
                'date_fin' => '2026-12-31',
                'heure_debut' => '09:00',
                'heure_fin' => '11:00',
                'lieu_defaut' => 'Salle ' . (201 + $index),
                'statut' => 'active',
            ]);

            // Recurrence 2: Weekly on Wednesdays
            CourseRecurrence::create([
                'cours_id' => $cours->id,
                'type' => 'weekly',
                'jours_semaine' => '3', // Wednesday
                'date_debut' => '2026-09-01',
                'date_fin' => '2026-12-31',
                'heure_debut' => '14:00',
                'heure_fin' => '16:00',
                'lieu_defaut' => 'Visio',
                'statut' => 'active',
            ]);

            // Recurrence 3: Biweekly on Fridays (starting next Friday)
            CourseRecurrence::create([
                'cours_id' => $cours->id,
                'type' => 'biweekly',
                'jours_semaine' => '5', // Friday
                'date_debut' => '2026-10-02',
                'date_fin' => '2026-12-31',
                'heure_debut' => '18:00',
                'heure_fin' => '20:00',
                'lieu_defaut' => 'Salle ' . (301 + $index),
                'statut' => 'active',
            ]);

            $this->command->info("Created 3 recurrences for course: {$cours->titre}");
        }

        $this->command->info('CourseRecurrenceSeeder completed: ' . (count($courses) * 3) . ' recurrences created');
    }
}
