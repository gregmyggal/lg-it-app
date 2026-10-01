<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\SessionReplacementService;
use Illuminate\Database\Seeder;

/**
 * Démo CLS-01 T2 : Alice principale de la classe du mercredi, Bob principal de celle du samedi ;
 * sur la séance 5 du mercredi, Alice est remplacée ponctuellement par Bob (remplaçant sans assignation à la classe).
 * Idempotent, passe par les services (propagation et règles réelles). Sans effet si Alice/Bob ou les classes manquent.
 */
class ProfesseurClasseSeeder extends Seeder
{
    public function run(ClasseProfesseurAssignmentService $assignations, SessionReplacementService $remplacements): void
    {
        $alice = Professeur::where('email', 'alice@test.com')->first();
        $bob = Professeur::where('email', 'bob@test.com')->first();
        $mercredi = Classe::where('jour_semaine', 3)->orderBy('id')->first();
        $samedi = Classe::where('jour_semaine', 6)->orderBy('id')->first();

        if (! $alice || ! $bob || ! $mercredi || ! $samedi) {
            $this->command?->warn('Alice, Bob ou les classes mercredi/samedi introuvables : rien à assigner.');

            return;
        }

        $assignations->assigner($mercredi, $alice, ['role' => ProfesseurClasse::ROLE_PRINCIPAL]);
        $assignations->assigner($samedi, $bob, ['role' => ProfesseurClasse::ROLE_PRINCIPAL]);

        $seance5 = CourseSession::where('classe_id', $mercredi->id)->where('seance_numero', 5)->where('bis_rang', 0)->first();
        $dejaRemplacee = $seance5?->sessionProfesseurs()->where('professeur_id', $alice->id)->where('remplace', true)->exists();

        if ($seance5 && ! $seance5->isAnnulee() && ! $dejaRemplacee
            && $seance5->sessionProfesseurs()->where('professeur_id', $alice->id)->exists()
            && ! $seance5->sessionProfesseurs()->where('professeur_id', $bob->id)->exists()) {
            $remplacements->remplacer($seance5, $alice->id, $bob->id);
        }
    }
}
