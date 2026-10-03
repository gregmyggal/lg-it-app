<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Propose les dates d'une nouvelle année (CLS-03 RG, décision 3) :
 * - calendrier FWB connu pour le libellé (déjà importé en base, sinon fichier FWB du dépôt) : P1 = rentrée (1er septembre)
 *   → vendredi précédant la coupure centrale (vacances de Carnaval/détente) ; P2 = lundi de reprise → fin d'année
 *   (premier vendredi de juillet) ;
 * - sinon : P1 01/09 → 31/01, P2 01/02 → 30/06.
 */
class AnneePropositionService
{
    /** Libellé de l'année suivant la dernière année existante (ou de l'année scolaire courante si aucune). */
    public function libelleSuivant(): string
    {
        $derniere = AnneeScolaire::orderByDesc('date_debut')->first();
        if ($derniere && preg_match('/^(\d{4})-(\d{4})$/', $derniere->libelle, $m)) {
            return ($m[1] + 1).'-'.($m[2] + 1);
        }

        $maintenant = now('Europe/Brussels');
        $debut = $maintenant->month >= 8 ? $maintenant->year : $maintenant->year - 1;

        return $debut.'-'.($debut + 1);
    }

    /**
     * @return array{libelle: string, date_debut: string, date_fin: string, periodes: list<array{numero: int, date_debut: string, date_fin: string}>, source: string, message: ?string}
     */
    public function proposer(?string $libelle = null): array
    {
        $libelle = $libelle ?: $this->libelleSuivant();
        preg_match('/^(\d{4})-(\d{4})$/', $libelle, $m);
        $debutAnnee = (int) ($m[1] ?? now('Europe/Brussels')->year);

        $fwb = $this->depuisFwb($libelle, $debutAnnee);
        $periodes = $fwb ?? [
            ['numero' => 1, 'date_debut' => "{$debutAnnee}-09-01", 'date_fin' => ($debutAnnee + 1).'-01-31'],
            ['numero' => 2, 'date_debut' => ($debutAnnee + 1).'-02-01', 'date_fin' => ($debutAnnee + 1).'-06-30'],
        ];

        return [
            'libelle' => $libelle,
            'date_debut' => $periodes[0]['date_debut'],
            'date_fin' => $periodes[1]['date_fin'],
            'periodes' => $periodes,
            'source' => $fwb ? 'fwb' : 'defaut',
            'message' => $fwb ? null : 'Proposition par défaut : calendrier FWB non importé.',
        ];
    }

    /** @return list<array{numero: int, date_debut: string, date_fin: string}>|null */
    private function depuisFwb(string $libelle, int $debutAnnee): ?array
    {
        $vacances = $this->vacancesFwb($libelle)
            ->filter(fn (array $e) => $e['date_debut'] >= ($debutAnnee + 1).'-02-01' && $e['date_debut'] <= ($debutAnnee + 1).'-03-31')
            ->sortBy('date_debut')
            ->first();
        if (! $vacances) {
            return null;
        }

        $finP1 = Carbon::parse($vacances['date_debut'])->previous(Carbon::FRIDAY);
        $debutP2 = Carbon::parse($vacances['date_fin'])->next(Carbon::MONDAY);
        $finAnnee = Carbon::create($debutAnnee + 1, 7, 1)->isFriday() ? Carbon::create($debutAnnee + 1, 7, 1) : Carbon::create($debutAnnee + 1, 7, 1)->next(Carbon::FRIDAY);

        return [
            ['numero' => 1, 'date_debut' => "{$debutAnnee}-09-01", 'date_fin' => $finP1->toDateString()],
            ['numero' => 2, 'date_debut' => $debutP2->toDateString(), 'date_fin' => $finAnnee->toDateString()],
        ];
    }

    /**
     * Vacances FWB de l'année : entrées importées en base si l'année existe, sinon fichier FWB du dépôt.
     *
     * @return Collection<int, array{date_debut: string, date_fin: string}>
     */
    private function vacancesFwb(string $libelle): Collection
    {
        $annee = AnneeScolaire::where('libelle', $libelle)->first();
        if ($annee) {
            $entrees = CalendrierScolaire::where('annee_scolaire_id', $annee->id)
                ->where('source', CalendrierScolaire::SOURCE_FWB)->where('type', CalendrierScolaire::TYPE_VACANCES)->get();
            if ($entrees->isNotEmpty()) {
                return $entrees->map(fn ($e) => ['date_debut' => $e->date_debut->toDateString(), 'date_fin' => $e->date_fin->toDateString()]);
            }
        }

        $chemin = database_path("data/calendrier_fwb_{$libelle}.json");
        $donnees = is_file($chemin) ? json_decode((string) file_get_contents($chemin), true) : null;

        return collect($donnees['entrees'] ?? [])
            ->where('type', CalendrierScolaire::TYPE_VACANCES)
            ->map(fn ($e) => ['date_debut' => $e['date_debut'], 'date_fin' => $e['date_fin']])
            ->values();
    }
}
