<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Source de vérité unique des règles calculées sur les périodes (CLS-02) : « séance hors période »,
 * « classe qui démarre avant le début de la période » et alerte « P2 à planifier ».
 * Utilisée par les ressources/générateurs (état réel) ET par l'aperçu d'impact CLS-03 (dates hypothétiques).
 */
class PeriodeRegles
{
    /** Délai (jours) avant la fin de la dernière séance de P1 sous lequel l'alerte « P2 à planifier » apparaît. */
    public const SEUIL_ALERTE_P2_JOURS = 28;

    /** Séance datée après la fin de sa période (calculé, jamais persisté). Dates au format Y-m-d. */
    public static function horsPeriode(string $date, string $finPeriode): bool
    {
        return $date > $finPeriode;
    }

    /** Équivalent SQL de horsPeriode() pour une session `course_sessions` rattachée à `classe_periodes`. */
    public static function sqlHorsPeriode(): string
    {
        // SQL brut : les noms de tables ne sont pas préfixés automatiquement (préfixe de tables en production).
        $t = DB::getTablePrefix();

        return "{$t}course_sessions.date > (select p.date_fin from {$t}periodes p where p.id = {$t}classe_periodes.periode_id)";
    }

    /** Première séance planifiée avant le début officiel de la période. Dates au format Y-m-d. */
    public static function demarreAvantDebut(string $datePremiereSession, string $debutPeriode): bool
    {
        return $datePremiereSession < $debutPeriode;
    }

    /**
     * La classe est candidate à l'alerte « P2 à planifier » : active, P1 présente, P2 absente,
     * année non archivée avec une P2 définie.
     *
     * @param  Collection<int, int|null>  $numerosPeriodesClasse
     */
    public static function candidateAlerteP2(Classe $classe, AnneeScolaire $annee, Collection $numerosPeriodesClasse): bool
    {
        return $classe->statut === Classe::STATUT_ACTIVE
            && ! $annee->isArchivee()
            && $numerosPeriodesClasse->contains(1)
            && ! $numerosPeriodesClasse->contains(2)
            && $annee->periodes->contains('numero', 2);
    }

    /**
     * RG-8 (CLS-02) : candidate ET dernière séance P1 active à ≤ 28 jours d'aujourd'hui.
     *
     * @param  Collection<int, int|null>  $numerosPeriodesClasse
     * @return array{date_fin_periode_1: string, jours_restants: int, message: string}|null
     */
    public static function alertePeriode2(Classe $classe, AnneeScolaire $annee, Collection $numerosPeriodesClasse, ?string $derniereSeanceP1): ?array
    {
        if (! $derniereSeanceP1 || ! self::candidateAlerteP2($classe, $annee, $numerosPeriodesClasse)) {
            return null;
        }

        $derniere = substr($derniereSeanceP1, 0, 10);
        $aujourdhui = now('Europe/Brussels')->startOfDay();
        $jours = (int) $aujourdhui->diffInDays(Carbon::parse($derniere, 'Europe/Brussels')->startOfDay(), false);

        return $jours <= self::SEUIL_ALERTE_P2_JOURS
            ? ['date_fin_periode_1' => $derniere, 'jours_restants' => $jours, 'message' => 'La période 2 est à planifier']
            : null;
    }
}
