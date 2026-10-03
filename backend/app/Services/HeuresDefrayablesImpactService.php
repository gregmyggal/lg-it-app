<?php

namespace App\Services;

use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use Illuminate\Support\Collection;

/**
 * DEF-01 T4 : simule l'effet d'une valeur d'heures défrayables sur le plafond journalier, AVANT enregistrement.
 * Information seulement (jamais bloquant) : le lissage permet de régler un dépassement.
 *
 * On ne regarde que les séances à venir ou non encore encodées de l'année (les saisies existantes ne changent pas) :
 * pour chaque (professeur, jour), montant = Σ heures défrayables des séances × tarif horaire du professeur ce jour-là.
 */
class HeuresDefrayablesImpactService
{
    public function __construct(private readonly TimesheetParametreService $parametres, private readonly TarifResolver $tarifs) {}

    /**
     * @param  string  $portee  « global » : le défaut de l'année change (les cours sans valeur propre suivent) ;
     *                          « cours » : la valeur du cours `$coursId` change
     * @return array{plafond_journalier_eur: float, avant: int, apres: int, nouveaux: int, exemples: array<int, array{professeur: string, date: string, montant: float}>}
     */
    public function simuler(int $annee, string $portee, ?int $coursId, float $heures): array
    {
        $plafond = $this->parametres->plafondJournalier($annee);

        $sessions = CourseSession::query()
            ->whereBetween('date', ["{$annee}-01-01", "{$annee}-12-31"])
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->with(['classePeriode.cours', 'sessionProfesseurs' => fn ($q) => $q->where('remplace', false), 'timesheets:id,course_session_id,professeur_id'])
            ->get();

        $profIds = $sessions->flatMap(fn (CourseSession $s) => $s->sessionProfesseurs->pluck('professeur_id'))->unique();
        $tarifs = ProfesseurTarif::whereIn('professeur_id', $profIds)->get()->groupBy('professeur_id');
        $noms = Professeur::whereIn('id', $profIds)->get()->mapWithKeys(fn ($p) => [$p->id => trim($p->prenom.' '.$p->nom)]);

        // [professeur][jour] => ['avant' => €, 'apres' => €]
        $jours = [];
        foreach ($sessions as $s) {
            $cours = $s->classePeriode?->cours;
            $actuelle = $this->parametres->heuresDefrayablesPour($cours, $annee)['valeur'];
            $nouvelle = match (true) {
                $portee === 'cours' && $cours?->id === $coursId => $heures,
                $portee === 'global' && $cours?->heures_defrayables === null => $heures,
                default => $actuelle,
            };
            $jour = $s->date->toDateString();
            $dejaEncodees = $s->timesheets->pluck('professeur_id')->all();
            foreach ($s->sessionProfesseurs as $lien) {
                if (in_array($lien->professeur_id, $dejaEncodees, true)) {
                    continue; // saisie existante : inchangée
                }
                $tarif = $this->tarifs->tarifA($jour, $tarifs->get($lien->professeur_id, new Collection));
                if (! $tarif) {
                    continue;
                }
                $c = &$jours[$lien->professeur_id][$jour];
                $c['avant'] = ($c['avant'] ?? 0) + $actuelle * $tarif;
                $c['apres'] = ($c['apres'] ?? 0) + $nouvelle * $tarif;
                unset($c);
            }
        }

        $avant = $apres = $nouveaux = 0;
        $exemples = [];
        foreach ($jours as $profId => $parJour) {
            foreach ($parJour as $jour => $m) {
                $depasseAvant = round($m['avant'], 2) > $plafond;
                $depasseApres = round($m['apres'], 2) > $plafond;
                $avant += $depasseAvant ? 1 : 0;
                $apres += $depasseApres ? 1 : 0;
                if ($depasseApres && ! $depasseAvant) {
                    $nouveaux++;
                    $exemples[] = ['professeur' => $noms[$profId] ?? '—', 'date' => $jour, 'montant' => round($m['apres'], 2)];
                }
            }
        }
        usort($exemples, fn ($a, $b) => $a['date'] <=> $b['date']);

        return ['plafond_journalier_eur' => $plafond, 'avant' => $avant, 'apres' => $apres, 'nouveaux' => $nouveaux, 'exemples' => array_slice($exemples, 0, 5)];
    }
}
