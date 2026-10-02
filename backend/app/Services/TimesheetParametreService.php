<?php

namespace App\Services;

use App\Models\TimesheetParametre;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Source unique des plafonds de défraiement. Une année sans paramètre hérite du plus récent
 * paramétrage antérieur (puis des valeurs historiques par défaut).
 */
class TimesheetParametreService
{
    /** @return array{annee:int, plafond_journalier_eur:float, plafond_annuel_eur:float, herite:bool} */
    public function pour(int $annee): array
    {
        $exact = TimesheetParametre::where('annee', $annee)->first();
        $source = $exact ?? TimesheetParametre::where('annee', '<', $annee)->orderByDesc('annee')->first();

        return [
            'annee' => $annee,
            'plafond_journalier_eur' => $source?->plafond_journalier_eur ?? TimesheetParametre::DEFAUT_JOURNALIER,
            'plafond_annuel_eur' => $source?->plafond_annuel_eur ?? TimesheetParametre::DEFAUT_ANNUEL,
            'frais_deplacement_eur' => $source?->frais_deplacement_eur ?? TimesheetParametre::DEFAUT_DEPLACEMENT,
            'herite' => $exact === null,
        ];
    }

    public function plafondJournalier(int $annee): float
    {
        return $this->pour($annee)['plafond_journalier_eur'];
    }

    public function fraisDeplacement(int $annee): float
    {
        return $this->pour($annee)['frais_deplacement_eur'];
    }

    public function plafondAnnuel(int $annee): float
    {
        return $this->pour($annee)['plafond_annuel_eur'];
    }

    /** @param array{plafond_journalier_eur:float|string, plafond_annuel_eur:float|string, frais_deplacement_eur:float|string} $valeurs */
    public function enregistrer(int $annee, array $valeurs, User $user): TimesheetParametre
    {
        return DB::transaction(function () use ($annee, $valeurs, $user) {
            $avant = $this->pour($annee);
            $param = TimesheetParametre::updateOrCreate(['annee' => $annee], [
                'plafond_journalier_eur' => $valeurs['plafond_journalier_eur'],
                'plafond_annuel_eur' => $valeurs['plafond_annuel_eur'],
                'frais_deplacement_eur' => $valeurs['frais_deplacement_eur'],
                'updated_by' => $user->id,
            ]);
            DB::table('timesheet_parametres_historique')->insert([
                'annee' => $annee,
                'plafond_journalier_avant' => $avant['plafond_journalier_eur'],
                'plafond_journalier_apres' => $param->plafond_journalier_eur,
                'plafond_annuel_avant' => $avant['plafond_annuel_eur'],
                'plafond_annuel_apres' => $param->plafond_annuel_eur,
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            return $param;
        });
    }
}
