<?php

namespace App\Services;

use App\Models\Employeur;
use App\Models\ProfesseurEmployeurMois;
use App\Models\TimesheetPdf;
use Illuminate\Support\Facades\DB;

/**
 * EMP-01 (Canvas §7) : amorçage des entités et reprise de l'historique. Tout mois ayant une timesheet, un PDF ou une
 * signature est rattaché à l'ASBL ; les PDF existants reçoivent l'ASBL et son snapshot. Idempotent (rejouable sans effet).
 */
class EmployeurBackfillService
{
    public const MOTIF = 'Reprise de données : historique rattaché à l’ASBL';

    /** @return array{mois_crees: int, pdfs_rattaches: int} */
    public function executer(): array
    {
        Employeur::amorcer();
        $asbl = Employeur::where('code', Employeur::CODE_ASBL)->firstOrFail();

        $triplets = collect()
            ->merge(DB::table('timesheets')->selectRaw('DISTINCT professeur_id, YEAR(date_prestation) AS annee, MONTH(date_prestation) AS mois')->get())
            ->merge(DB::table('timesheet_pdfs')->select('professeur_id', 'annee', 'mois')->distinct()->get())
            ->merge(DB::table('timesheet_signatures')->select('professeur_id', 'annee', 'mois')->distinct()->get())
            ->filter(fn ($t) => $t->annee !== null)
            ->unique(fn ($t) => $t->professeur_id.'-'.$t->annee.'-'.$t->mois);

        $existants = ProfesseurEmployeurMois::query()->get(['professeur_id', 'annee', 'mois'])
            ->map(fn ($l) => $l->professeur_id.'-'.$l->annee.'-'.$l->mois)->flip();
        $nouveaux = $triplets->reject(fn ($t) => $existants->has($t->professeur_id.'-'.$t->annee.'-'.$t->mois));

        $maintenant = now();
        foreach ($nouveaux->chunk(500) as $lot) {
            $lignes = $lot->map(fn ($t) => [
                'professeur_id' => $t->professeur_id, 'annee' => $t->annee, 'mois' => $t->mois, 'employeur_id' => $asbl->id,
                'source' => ProfesseurEmployeurMois::SOURCE_MIGRATION, 'version' => 1, 'created_at' => $maintenant, 'updated_at' => $maintenant,
            ])->all();
            DB::table('professeur_employeurs_mois')->insertOrIgnore($lignes);
            DB::table('employeur_mois_audits')->insert($lot->map(fn ($t) => [
                'professeur_id' => $t->professeur_id, 'annee' => $t->annee, 'mois' => $t->mois, 'employeur_avant_id' => null,
                'employeur_apres_id' => $asbl->id, 'motif' => self::MOTIF, 'user_id' => null, 'created_at' => $maintenant,
            ])->all());
        }

        $pdfs = 0;
        TimesheetPdf::whereNull('employeur_id')->chunkById(200, function ($lot) use ($asbl, &$pdfs) {
            foreach ($lot as $pdf) {
                $pdf->update(['employeur_id' => $asbl->id, 'employeur_snapshot' => $asbl->snapshot()]);
                $pdfs++;
            }
        });

        return ['mois_crees' => $nouveaux->count(), 'pdfs_rattaches' => $pdfs];
    }
}
