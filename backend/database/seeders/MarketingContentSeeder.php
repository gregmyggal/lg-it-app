<?php

namespace Database\Seeders;

use App\Models\Cours;
use App\Models\Stage;
use App\Models\Formation;
use App\Models\Anniversaire;
use App\Support\MarketingExamples;
use Illuminate\Database\Seeder;

class MarketingContentSeeder extends Seeder
{
    /**
     * Seed the application's database with professional marketing content.
     */
    public function run(): void
    {
        // COURSES
        $scratchJunior = Cours::where('slug', 'scratch-junior')->first();
        if ($scratchJunior) {
            $example = MarketingExamples::getScratchJuniorExample();
            $scratchJunior->update($example);
        }

        $pythonAdo = Cours::where('slug', 'python-ado')->first();
        if ($pythonAdo) {
            $example = MarketingExamples::getPythonAdoExample();
            $pythonAdo->update($example);
        }

        // FORMATIONS
        $webDevFormation = Formation::where('slug', 'web-dev-complet')->first();
        if ($webDevFormation) {
            $example = MarketingExamples::getWebDevFormationExample();
            $webDevFormation->update($example);
        }

        // STAGES
        $summerStage = Stage::where('slug', 'stage-ete-2024')->first();
        if ($summerStage) {
            $example = MarketingExamples::getSummerStageExample();
            $summerStage->update($example);
        }

        // ANNIVERSAIRES
        $birthdayEvent = Anniversaire::where('slug', 'anniversaire-tech')->first();
        if ($birthdayEvent) {
            $example = MarketingExamples::getBirthdayEventExample();
            $birthdayEvent->update($example);
        }

        $this->command->info('✅ Marketing content seeded successfully!');
    }
}
