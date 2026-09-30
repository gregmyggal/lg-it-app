<?php

namespace App\Console\Commands;

use App\Exceptions\RegleMetierException;
use App\Models\AnneeScolaire;
use App\Services\FwbCalendarImporter;
use Illuminate\Console\Command;

class ImportCalendrierFwb extends Command
{
    protected $signature = 'calendrier:import-fwb {annee : id ou libellé de l\'année scolaire (ex. 2026-2027)} {--fichier= : chemin d\'un fichier JSON alternatif}';

    protected $description = 'Importe (de façon idempotente) le calendrier scolaire FWB dans une année scolaire';

    public function handle(FwbCalendarImporter $importer): int
    {
        $ref = (string) $this->argument('annee');
        $annee = ctype_digit($ref)
            ? AnneeScolaire::find((int) $ref)
            : AnneeScolaire::where('libelle', $ref)->first();

        if (! $annee) {
            $this->error("Année scolaire introuvable : {$ref}");

            return self::FAILURE;
        }

        try {
            $resultat = $importer->importer($annee, $this->option('fichier') ?: null);
        } catch (RegleMetierException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Import FWB {$annee->libelle} : {$resultat['creees']} créée(s), {$resultat['ignorees']} ignorée(s).");
        if (! $resultat['verifie']) {
            $this->warn('Attention : ce calendrier n\'a pas encore été confirmé par la direction (verifie = false).');
        }

        return self::SUCCESS;
    }
}
