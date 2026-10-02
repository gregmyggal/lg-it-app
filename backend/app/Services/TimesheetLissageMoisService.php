<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TS-01 T3 : lissage d'un mois — répartir des heures d'un jour sur d'autres jours du MÊME mois, sans changer le total
 * d'heures du mois, pour respecter le plafond journalier (paramétré par année, TS-00). Deux modes : proposition
 * automatique des dépassements, ou déplacements manuels. Chaque lissage est tracé (motif obligatoire).
 *
 * Un déplacement = { timesheet_id, date_to, montant } ; les heures déplacées = montant / tarif, arrondies au centième.
 */
class TimesheetLissageMoisService
{
    private const EPS = 0.005;

    public function __construct(private readonly TimesheetParametreService $parametres, private readonly TarifResolver $tarifs) {}

    /** Proposition automatique : pour chaque jour au-delà du plafond, déplace l'excédent vers les jours proches avec capacité. */
    public function proposer(Professeur $prof, int $annee, int $mois): array
    {
        $ctx = $this->contexte($prof, $annee, $mois);
        $jours = $ctx['jours'];
        $deplaces = []; // timesheet_id => heures déjà déplacées
        $moves = [];
        $nonResolus = [];

        foreach (collect($jours)->keys()->sort() as $jour) {
            $excedent = round($jours[$jour] - $ctx['plafond'], 2);
            if ($excedent <= self::EPS) {
                continue;
            }
            $lignes = $ctx['lignes']->filter(fn (Timesheet $t) => $t->date_prestation->toDateString() === $jour && $this->lissable($t))
                ->sortByDesc(fn (Timesheet $t) => (float) $t->nombre_heures);

            foreach ($lignes as $t) {
                $tarif = $this->tarifs->tarifA($jour, $ctx['tarifs']);
                if ($tarif === null || $tarif <= 0) {
                    continue;
                }
                foreach ($this->joursParProximite($jour, $ctx['debut'], $ctx['fin']) as $cible) {
                    if ($excedent <= self::EPS) {
                        break;
                    }
                    $capacite = round($ctx['plafond'] - ($jours[$cible] ?? 0), 2);
                    $heuresLibres = (float) $t->nombre_heures - ($deplaces[$t->id] ?? 0) - 0.01;
                    if ($capacite <= self::EPS || $heuresLibres <= 0 || $this->tarifs->tarifA($cible, $ctx['tarifs']) !== $tarif) {
                        continue;
                    }
                    $voulu = min($excedent, $capacite, $heuresLibres * $tarif);
                    $heures = ceil($voulu / $tarif * 100 - 1e-9) / 100;
                    if (round($heures * $tarif, 2) > $capacite + self::EPS || $heures > $heuresLibres) {
                        $heures = floor($voulu / $tarif * 100 + 1e-9) / 100;
                    }
                    if ($heures <= 0) {
                        continue;
                    }
                    $montant = round($heures * $tarif, 2);
                    $moves[] = ['timesheet_id' => $t->id, 'date_to' => $cible, 'montant' => $montant];
                    $deplaces[$t->id] = ($deplaces[$t->id] ?? 0) + $heures;
                    $jours[$jour] = round($jours[$jour] - $montant, 2);
                    $jours[$cible] = round(($jours[$cible] ?? 0) + $montant, 2);
                    $excedent = round($excedent - $montant, 2);
                }
            }
            if ($excedent > self::EPS) {
                $nonResolus[] = ['date' => $jour, 'depassement' => $excedent];
            }
        }

        return ['deplacements' => $moves, 'non_resolus' => $nonResolus];
    }

    /**
     * Aperçu avant/après d'une liste de déplacements, sans rien écrire.
     *
     * @param  array<int, array{timesheet_id:int, date_to:string, montant:float|string}>  $deplacements
     */
    public function apercu(Professeur $prof, int $annee, int $mois, array $deplacements): array
    {
        $ctx = $this->contexte($prof, $annee, $mois);
        $avant = $ctx['jours'];
        $apres = $ctx['jours'];
        $erreurs = [];
        $detail = [];
        $heuresParLigne = [];

        foreach ($deplacements as $i => $d) {
            $t = $ctx['lignes']->get((int) ($d['timesheet_id'] ?? 0));
            $cible = (string) ($d['date_to'] ?? '');
            $montant = (float) ($d['montant'] ?? 0);

            if (! $t || ! $this->lissable($t)) {
                $erreurs[] = "Déplacement ".($i + 1).' : saisie introuvable ou non adaptable (seules les saisies d\'heures soumises, confirmées ou contestées du mois le sont).';

                continue;
            }
            $source = $t->date_prestation->toDateString();
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $cible) || $cible < $ctx['debut']->toDateString() || $cible > $ctx['fin']->toDateString()) {
                $erreurs[] = 'Déplacement '.($i + 1).' : la date cible doit rester dans le mois.';

                continue;
            }
            if ($cible === $source) {
                $erreurs[] = 'Déplacement '.($i + 1).' : la date cible est identique à la date d\'origine.';

                continue;
            }
            $tarif = $this->tarifs->tarifA($source, $ctx['tarifs']);
            if ($tarif === null || $tarif <= 0 || $this->tarifs->tarifA($cible, $ctx['tarifs']) !== $tarif) {
                $erreurs[] = 'Déplacement '.($i + 1).' : tarif absent ou différent à la date cible.';

                continue;
            }
            $heures = round($montant / $tarif, 2);
            $heuresParLigne[$t->id] = ($heuresParLigne[$t->id] ?? 0) + $heures;
            if ($heures <= 0 || $heuresParLigne[$t->id] >= (float) $t->nombre_heures - 0.001) {
                $erreurs[] = 'Déplacement '.($i + 1).' : le montant doit être positif et laisser des heures sur la saisie d\'origine.';

                continue;
            }
            $montantEff = round($heures * $tarif, 2);
            $apres[$source] = round(($apres[$source] ?? 0) - $montantEff, 2);
            $apres[$cible] = round(($apres[$cible] ?? 0) + $montantEff, 2);
            $detail[] = ['timesheet_id' => $t->id, 'date_from' => $source, 'date_to' => $cible, 'heures' => $heures, 'montant' => $montantEff];
        }

        foreach ($detail as $d) {
            if (($apres[$d['date_to']] ?? 0) > $ctx['plafond'] + self::EPS) {
                $erreurs[] = 'Le '.Carbon::parse($d['date_to'])->format('d/m/Y').' dépasserait le plafond journalier de '.number_format($ctx['plafond'], 2, ',', ' ').' €.';
            }
        }
        $erreurs = array_values(array_unique($erreurs));

        $jours = collect($avant)->keys()->merge(collect($apres)->keys())->unique()->sort()->values()
            ->filter(fn ($j) => round(($avant[$j] ?? 0), 2) !== round(($apres[$j] ?? 0), 2))
            ->map(fn ($j) => ['date' => $j, 'avant' => round($avant[$j] ?? 0, 2), 'apres' => round($apres[$j] ?? 0, 2)])->values();

        return [
            'plafond_journalier_eur' => $ctx['plafond'],
            'deplacements' => $detail,
            'jours' => $jours,
            'total_avant' => round(collect($avant)->sum(), 2),
            'total_apres' => round(collect($apres)->sum(), 2),
            'erreurs' => $erreurs,
        ];
    }

    /**
     * Applique le lissage de façon atomique (aucun effet si un déplacement est invalide).
     *
     * @return array{deplacements: int}
     */
    public function appliquer(Professeur $prof, int $annee, int $mois, array $deplacements, string $motif, User $auteur): array
    {
        if ($deplacements === []) {
            throw RegleMetierException::invalide('Aucun déplacement à appliquer.');
        }

        return DB::transaction(function () use ($prof, $annee, $mois, $deplacements, $motif, $auteur) {
            // Verrouille les saisies du mois pour la durée de la transaction, puis revalide sur l'état verrouillé.
            Timesheet::where('professeur_id', $prof->id)
                ->whereBetween('date_prestation', [Carbon::create($annee, $mois, 1)->toDateString(), Carbon::create($annee, $mois, 1)->endOfMonth()->toDateString()])
                ->lockForUpdate()->get();

            $apercu = $this->apercu($prof, $annee, $mois, $deplacements);
            if ($apercu['erreurs'] !== []) {
                throw RegleMetierException::invalide('Lissage impossible : '.$apercu['erreurs'][0].' Rien n\'a été modifié.', ['deplacements' => $apercu['erreurs']]);
            }

            foreach ($apercu['deplacements'] as $d) {
                $origine = Timesheet::findOrFail($d['timesheet_id']);
                $avantHeures = round((float) $origine->nombre_heures, 2);
                $apresHeures = round($avantHeures - $d['heures'], 2);

                $origine->nombre_heures = $apresHeures;
                $origine->lissage_applique = true;
                $origine->signature_professeur = null;
                $origine->save();

                $nouvelle = Timesheet::create([
                    'professeur_id' => $origine->professeur_id,
                    'date_prestation' => $d['date_to'],
                    'nombre_heures' => $d['heures'],
                    'type_activite' => $origine->type_activite,
                    'cours_id' => $origine->cours_id,
                    'commentaire' => 'Lissé depuis le '.Carbon::parse($d['date_from'])->format('d/m/Y'),
                    'statut_validation' => $origine->statut_validation,
                    'lissage_applique' => true,
                    'validated_at' => $origine->validated_at,
                    'validated_by' => $origine->validated_by,
                ]);

                TimesheetAudit::create([
                    'timesheet_id' => $origine->id,
                    'professeur_id' => $origine->professeur_id,
                    'user_id' => $auteur->id,
                    'action' => TimesheetAudit::ACTION_LISSAGE,
                    'avant' => ['nombre_heures' => $avantHeures],
                    'apres' => [
                        'nombre_heures' => $apresHeures,
                        'heures_deplacees' => $d['heures'],
                        'montant_deplace' => $d['montant'],
                        'date_cible' => $d['date_to'],
                        'nouvelle_saisie_id' => $nouvelle->id,
                    ],
                    'motif' => $motif,
                ]);
            }

            return ['deplacements' => count($apercu['deplacements'])];
        });
    }

    /** @return array{lignes: Collection<int, Timesheet>, tarifs: Collection<int, ProfesseurTarif>, jours: array<string,float>, plafond: float, debut: Carbon, fin: Carbon} */
    private function contexte(Professeur $prof, int $annee, int $mois): array
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfDay();
        $fin = $debut->copy()->endOfMonth()->startOfDay();
        $tarifs = ProfesseurTarif::where('professeur_id', $prof->id)->get();
        $lignes = Timesheet::where('professeur_id', $prof->id)
            ->whereBetween('date_prestation', [$debut->toDateString(), $fin->toDateString()])
            ->where('statut_validation', '!=', Timesheet::STATUT_BROUILLON)
            ->get()->keyBy('id');

        $jours = [];
        foreach ($lignes as $t) {
            $jour = $t->date_prestation->toDateString();
            $jours[$jour] = round(($jours[$jour] ?? 0) + (float) $this->tarifs->montant($t, $tarifs), 2);
        }

        return ['lignes' => $lignes, 'tarifs' => $tarifs, 'jours' => $jours, 'plafond' => $this->parametres->plafondJournalier($annee), 'debut' => $debut, 'fin' => $fin];
    }

    /** Un frais de déplacement n'est pas un nombre d'heures : il ne se lisse pas. */
    private function lissable(Timesheet $t): bool
    {
        return $this->adaptable($t) && $t->type_activite !== TimesheetService::TYPE_DEPLACEMENT;
    }

    private function adaptable(Timesheet $t): bool
    {
        return in_array($t->statut_validation, [Timesheet::STATUT_SOUMIS, Timesheet::STATUT_CONFIRME, Timesheet::STATUT_CONTESTE], true);
    }

    /** @return string[] jours du mois triés par distance croissante au jour donné (avant avant après à distance égale) */
    private function joursParProximite(string $jour, Carbon $debut, Carbon $fin): array
    {
        $ref = Carbon::parse($jour);
        $out = [];
        for ($i = 1; $i <= 31; $i++) {
            foreach ([$ref->copy()->subDays($i), $ref->copy()->addDays($i)] as $c) {
                if ($c->betweenIncluded($debut, $fin)) {
                    $out[] = $c->toDateString();
                }
            }
        }

        return $out;
    }
}
