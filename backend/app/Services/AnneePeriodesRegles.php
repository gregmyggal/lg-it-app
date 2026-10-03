<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use Illuminate\Support\Carbon;

/**
 * Règles de cohérence des dates d'une année et de ses 2 périodes (CLS-03 RG-1, RG-2).
 * Partagée par la validation (création/modification) et par l'aperçu d'impact.
 */
class AnneePeriodesRegles
{
    /** Trou entre P1 et P2 au-delà duquel on avertit (6 semaines). */
    public const TROU_MAX_JOURS = 42;

    /**
     * Messages bloquants. L'année s'étend du début de P1 à la fin de P2 ; si $debutAnnee/$finAnnee sont fournis,
     * les périodes doivent y être comprises.
     *
     * @param  list<array{numero: int|string, date_debut: string, date_fin: string}>  $periodes
     * @return list<string>
     */
    public function bloquants(array $periodes, ?int $ignoreAnneeId, ?string $debutAnnee = null, ?string $finAnnee = null): array
    {
        $parNumero = collect($periodes)->keyBy(fn ($p) => (int) $p['numero']);
        if ($parNumero->count() !== 2 || ! $parNumero->has(1) || ! $parNumero->has(2)) {
            return [];
        }

        $erreurs = [];
        foreach ($parNumero as $numero => $p) {
            if ($p['date_fin'] <= $p['date_debut']) {
                $erreurs[] = "La fin de la période {$numero} doit être postérieure à son début.";
            }
            if ($debutAnnee && $finAnnee && ($p['date_debut'] < $debutAnnee || $p['date_fin'] > $finAnnee)) {
                $erreurs[] = "La période {$numero} doit être comprise dans l'année scolaire.";
            }
        }

        $finP1 = $parNumero[1]['date_fin'];
        if ($parNumero[2]['date_debut'] <= $finP1) {
            $erreurs[] = 'La période 2 doit commencer après la fin de la période 1 ('.Carbon::parse($finP1)->format('d/m/Y')
                .'). Au plus tôt le '.Carbon::parse($finP1)->addDay()->format('d/m/Y').'.';
        }

        $chevauchement = $this->chevauchement(
            $debutAnnee ?? min($parNumero[1]['date_debut'], $parNumero[2]['date_debut']),
            $finAnnee ?? max($parNumero[1]['date_fin'], $parNumero[2]['date_fin']),
            $ignoreAnneeId,
        );
        if ($chevauchement) {
            $erreurs[] = $chevauchement;
        }

        return $erreurs;
    }

    /**
     * Avertissements non bloquants (trou entre P1 et P2 supérieur à 6 semaines).
     *
     * @param  list<array{numero: int|string, date_debut: string, date_fin: string}>  $periodes
     * @return list<string>
     */
    public function avertissements(array $periodes): array
    {
        $parNumero = collect($periodes)->keyBy(fn ($p) => (int) $p['numero']);
        if (! $parNumero->has(1) || ! $parNumero->has(2)) {
            return [];
        }

        $jours = (int) Carbon::parse($parNumero[1]['date_fin'])->diffInDays(Carbon::parse($parNumero[2]['date_debut']), false) - 1;

        return $jours > self::TROU_MAX_JOURS
            ? ["{$jours} jours sans période : aucune classe ne pourra démarrer dans cet intervalle."]
            : [];
    }

    /** Message si [debut, fin] chevauche une autre année (toutes années confondues, archivées incluses). */
    public function chevauchement(string $debut, string $fin, ?int $ignoreAnneeId): ?string
    {
        $autre = AnneeScolaire::query()
            ->when($ignoreAnneeId, fn ($q) => $q->where('id', '!=', $ignoreAnneeId))
            ->where('date_debut', '<=', $fin)
            ->where('date_fin', '>=', $debut)
            ->orderBy('date_debut')
            ->first();

        return $autre ? "Cette année chevauche {$autre->libelle} (qui se termine le ".$autre->date_fin->format('d/m/Y').').' : null;
    }
}
