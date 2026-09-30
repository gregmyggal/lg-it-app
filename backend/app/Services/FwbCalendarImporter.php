<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use Illuminate\Support\Facades\DB;

/**
 * Import idempotent du calendrier FWB (vacances scolaires + jours fériés) dans une année scolaire.
 * Identité d'une entrée = `cle` du fichier (colonne `cle_fwb`) : une entrée déjà présente
 * (même masquée, modifiée à la main ou supprimée par le directeur) n'est jamais écrasée ;
 * les entrées `ecole` ne sont jamais touchées.
 */
class FwbCalendarImporter
{
    public function cheminParDefaut(AnneeScolaire $annee): string
    {
        return database_path("data/calendrier_fwb_{$annee->libelle}.json");
    }

    /**
     * @return array{creees: int, ignorees: int, source_url: ?string, verifie: bool}
     */
    public function importer(AnneeScolaire $annee, ?string $chemin = null): array
    {
        $chemin ??= $this->cheminParDefaut($annee);

        if (! is_file($chemin)) {
            throw RegleMetierException::invalide("Aucun calendrier FWB disponible pour l'année {$annee->libelle}.");
        }

        $donnees = json_decode((string) file_get_contents($chemin), true);
        if (! is_array($donnees) || ! isset($donnees['entrees']) || ! is_array($donnees['entrees'])) {
            throw RegleMetierException::invalide('Le fichier du calendrier FWB est invalide.');
        }

        $creees = 0;
        $ignorees = 0;

        DB::transaction(function () use ($annee, $donnees, &$creees, &$ignorees) {
            foreach ($donnees['entrees'] as $entree) {
                // Hors de l'année scolaire : sans objet.
                if ($entree['date_fin'] < $annee->date_debut->toDateString()
                    || $entree['date_debut'] > $annee->date_fin->toDateString()) {
                    $ignorees++;

                    continue;
                }

                $existe = CalendrierScolaire::query()
                    ->where('annee_scolaire_id', $annee->id)
                    ->where('cle_fwb', $entree['cle'])
                    ->exists();

                if ($existe) {
                    $ignorees++;

                    continue;
                }

                CalendrierScolaire::create([
                    'annee_scolaire_id' => $annee->id,
                    'date_debut' => $entree['date_debut'],
                    'date_fin' => $entree['date_fin'],
                    'type' => $entree['type'],
                    'libelle' => $entree['libelle'],
                    'source' => CalendrierScolaire::SOURCE_FWB,
                    'masque' => false,
                    'cle_fwb' => $entree['cle'],
                    'modifie_manuellement' => false,
                ]);
                $creees++;
            }
        });

        return [
            'creees' => $creees,
            'ignorees' => $ignorees,
            'source_url' => $donnees['source_url'] ?? null,
            'verifie' => (bool) ($donnees['verifie'] ?? false),
        ];
    }
}
