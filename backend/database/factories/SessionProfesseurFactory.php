<?php

namespace Database\Factories;

use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SessionProfesseur> */
class SessionProfesseurFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_session_id' => CourseSession::factory(),
            'professeur_id' => Professeur::factory(),
            'role' => ProfesseurClasse::ROLE_CO_ENSEIGNANT,
            'origine' => SessionProfesseur::ORIGINE_CLASSE,
            'remplace' => false,
            'remplace_par_professeur_id' => null,
        ];
    }
}
