<?php

namespace App\Services;

use App\Models\Employeur;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lignes de la fiche de défraiement d'un mois (TS-01 T5), partagées par le PDF et la signature (SIG-01) : ce que le
 * professeur signe est exactement ce qui est imprimé. Une ligne par (date, objet, montant unitaire).
 */
class FicheDefraiementLignes
{
    public const OBJETS = [
        TimesheetService::TYPE_ANIMATION => 'Animation',
        TimesheetService::TYPE_COURS => 'Cours',
        TimesheetService::TYPE_PREPARATION => 'Préparation',
        TimesheetService::TYPE_DEPLACEMENT => 'Frais de déplacement',
    ];

    public function __construct(private readonly TarifResolver $tarifs, private readonly EmployeurMoisService $employeurs) {}

    /** Saisies du mois hors brouillons. @return Collection<int, Timesheet> */
    public function saisies(Professeur $prof, int $annee, int $mois, bool $verrou = false): Collection
    {
        $debut = Carbon::create($annee, $mois, 1);
        $q = Timesheet::where('professeur_id', $prof->id)
            ->whereBetween('date_prestation', [$debut->toDateString(), $debut->copy()->endOfMonth()->toDateString()])
            ->where('statut_validation', '!=', Timesheet::STATUT_BROUILLON)
            ->orderBy('date_prestation')->orderBy('id');

        return ($verrou ? $q->lockForUpdate() : $q)->get();
    }

    /** @return Collection<int, array{date: string, objet: string, unite: float, nombre: float, total: float}> */
    public function lignes(Professeur $prof, Collection $saisies): Collection
    {
        $tarifsProf = ProfesseurTarif::where('professeur_id', $prof->id)->get();

        // Les saisies identiques d'un même jour (date, objet, montant unitaire) sont regroupées.
        return $saisies
            ->map(fn (Timesheet $t) => ['date' => $t->date_prestation->toDateString(), 'objet' => self::OBJETS[$t->type_activite] ?? ucfirst($t->type_activite), 'unite' => (float) $this->tarifs->unite($t, $tarifsProf), 'nombre' => (float) $t->nombre_heures])
            ->groupBy(fn ($l) => $l['date'].'|'.$l['objet'].'|'.$l['unite'])
            ->map(fn ($g) => ['date' => $g[0]['date'], 'objet' => $g[0]['objet'], 'unite' => $g[0]['unite'], 'nombre' => round($g->sum('nombre'), 2)])
            ->map(fn ($l) => $l + ['total' => round($l['nombre'] * $l['unite'], 2)])
            ->sortBy(['date', 'objet'])->values();
    }

    /**
     * Contenu signé : identité, période, compte bancaire, lignes et total, dans un ordre fixe (sérialisation stable).
     *
     * @return array{professeur_id: int, nom: string, annee: int, mois: int, iban: string, lignes: list<array<string, string>>, total: string}
     */
    public function contenu(Professeur $prof, int $annee, int $mois): array
    {
        $lignes = $this->lignes($prof, $this->saisies($prof, $annee, $mois));

        $employeur = $this->employeurs->effectif($prof, $annee, $mois);

        return [
            'professeur_id' => $prof->id,
            'nom' => trim($prof->prenom.' '.$prof->nom),
            'annee' => $annee,
            'mois' => $mois,
            'iban' => (string) $prof->compte_bancaire,
            'lignes' => $lignes->map(fn ($l) => [
                'date' => $l['date'], 'objet' => $l['objet'],
                'unite' => number_format($l['unite'], 2, '.', ''), 'nombre' => number_format($l['nombre'], 2, '.', ''), 'total' => number_format($l['total'], 2, '.', ''),
            ])->all(),
            'total' => number_format((float) $lignes->sum('total'), 2, '.', ''),
        ] + $this->cleEmployeur($employeur);
    }

    /**
     * EMP-01 : l'employeur du mois fait partie du contenu signé (changer d'employeur rend la signature « à re-signer »).
     * Rétrocompatibilité : les signatures antérieures à EMP-01 ne le contenaient pas et supposent l'ASBL ; pour elles
     * l'empreinte reste identique, la clé n'est donc ajoutée que pour une autre entité.
     *
     * @return array{employeur?: array{id: int, nom: string}}
     */
    private function cleEmployeur(Employeur $employeur): array
    {
        return $employeur->code === Employeur::CODE_ASBL ? [] : ['employeur' => ['id' => $employeur->id, 'nom' => $employeur->nom]];
    }
}
