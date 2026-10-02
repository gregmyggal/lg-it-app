<?php

namespace App\Services;

use App\Models\Cours;
use App\Models\TimesheetParametre;
use App\Models\User;
use Illuminate\Support\Arr;
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
            'heures_defrayables' => $source?->heures_defrayables ?? TimesheetParametre::DEFAUT_HEURES_DEFRAYABLES,
            'duree_seance_defaut' => $source?->duree_seance_defaut ?? TimesheetParametre::DEFAUT_DUREE_SEANCE,
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

    /** Heures défrayables par séance : valeur par défaut de l'année (la surcharge par cours vient de DEF-01 T2). */
    public function heuresDefrayables(int $annee): float
    {
        return $this->pour($annee)['heures_defrayables'];
    }

    /**
     * Heures défrayables par séance pour un cours et une année : la valeur du cours (DEF-01 T2) sinon le défaut de
     * l'année (DEF-01 T1). `source` : « cours » ou « defaut » (pour afficher « hérité du défaut »).
     *
     * @return array{valeur: float, source: string}
     */
    public function heuresDefrayablesPour(?Cours $cours, int $annee): array
    {
        if ($cours?->heures_defrayables !== null) {
            return ['valeur' => (float) $cours->heures_defrayables, 'source' => 'cours'];
        }

        return ['valeur' => $this->heuresDefrayables($annee), 'source' => 'defaut'];
    }

    /** Durée d'une séance proposée à la création d'une classe (préremplissage de l'heure de fin). */
    public function dureeSeanceDefaut(int $annee): float
    {
        return $this->pour($annee)['duree_seance_defaut'];
    }

    public function plafondAnnuel(int $annee): float
    {
        return $this->pour($annee)['plafond_annuel_eur'];
    }

    /** @param array{plafond_journalier_eur:float|string, plafond_annuel_eur:float|string, frais_deplacement_eur?:float|string, heures_defrayables?:float|string, duree_seance_defaut?:float|string} $valeurs */
    public function enregistrer(int $annee, array $valeurs, User $user): TimesheetParametre
    {
        return DB::transaction(function () use ($annee, $valeurs, $user) {
            $avant = $this->pour($annee);
            // Les valeurs non fournies conservent la valeur effective de l'année (héritée ou déjà fixée).
            $v = $valeurs + Arr::only($avant, ['frais_deplacement_eur', 'heures_defrayables', 'duree_seance_defaut']);
            $param = TimesheetParametre::updateOrCreate(['annee' => $annee], [
                'plafond_journalier_eur' => $v['plafond_journalier_eur'],
                'plafond_annuel_eur' => $v['plafond_annuel_eur'],
                'frais_deplacement_eur' => $v['frais_deplacement_eur'],
                'heures_defrayables' => $v['heures_defrayables'],
                'duree_seance_defaut' => $v['duree_seance_defaut'],
                'updated_by' => $user->id,
            ]);
            DB::table('timesheet_parametres_historique')->insert([
                'annee' => $annee,
                'plafond_journalier_avant' => $avant['plafond_journalier_eur'],
                'plafond_journalier_apres' => $param->plafond_journalier_eur,
                'plafond_annuel_avant' => $avant['plafond_annuel_eur'],
                'plafond_annuel_apres' => $param->plafond_annuel_eur,
                'heures_defrayables_avant' => $avant['heures_defrayables'],
                'heures_defrayables_apres' => $param->heures_defrayables,
                'duree_seance_avant' => $avant['duree_seance_defaut'],
                'duree_seance_apres' => $param->duree_seance_defaut,
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            return $param;
        });
    }
}
