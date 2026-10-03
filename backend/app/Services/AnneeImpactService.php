<?php

namespace App\Services;

use App\Http\Resources\ClasseResource;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\CourseSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aperçu en LECTURE SEULE de l'effet d'un changement de dates de période sur l'existant (CLS-03 RG-3/RG-4).
 * Les règles « hors période », « démarre avant le début » et « P2 à planifier » viennent de {@see PeriodeRegles},
 * les mêmes que celles de l'état réel (ClasseResource, CourseSessionResource) : l'aperçu appelle donc ces règles
 * avec des bornes hypothétiques, sans rien écrire.
 */
class AnneeImpactService
{
    private const JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    public function __construct(private readonly AnneePeriodesRegles $regles) {}

    /**
     * @param  list<array{numero: int|string, date_debut: string, date_fin: string}>  $nouvelles
     * @return array<string, mixed>
     */
    public function apercu(AnneeScolaire $annee, array $nouvelles): array
    {
        $annee->loadMissing('periodes');
        $nouvelles = collect($nouvelles)->keyBy(fn ($p) => (int) $p['numero']);

        $bloquants = $this->regles->bloquants($nouvelles->values()->all(), $annee->id);
        if ($annee->isArchivee()) {
            array_unshift($bloquants, "Réactivez l'année pour modifier ses dates.");
        }
        $avertissements = $this->regles->avertissements($nouvelles->values()->all());

        $periodes = $annee->periodes->map(fn ($p) => [
            'numero' => $p->numero,
            'avant' => ['date_debut' => $p->date_debut->toDateString(), 'date_fin' => $p->date_fin->toDateString()],
            'apres' => ['date_debut' => $nouvelles[$p->numero]['date_debut'], 'date_fin' => $nouvelles[$p->numero]['date_fin']],
            'evolution' => $this->evolution($p->date_debut->toDateString(), $p->date_fin->toDateString(), $nouvelles[$p->numero]['date_debut'], $nouvelles[$p->numero]['date_fin']),
        ])->values()->all();

        $resultat = [
            'bloquants' => $bloquants,
            'avertissements' => $avertissements,
            'periodes' => $periodes,
            'classes_touchees' => 0,
            'seances_hors_periode_en_plus' => 0,
            'seances_redevenant_dans_periode' => 0,
            'classes_demarrant_avant_debut' => 0,
            'classes_sans_p2' => 0,
            'alertes_p2_creees' => 0,
            'alertes_p2_supprimees' => 0,
            'calendrier_hors_annee' => 0,
            'par_classe' => [],
        ];
        if ($bloquants) {
            return $resultat;
        }

        $classes = Classe::where('annee_scolaire_id', $annee->id)
            ->with(['periodes.periode', 'periodes.cours'])
            ->withMax(['sessionsActives as derniere_p1' => fn ($q) => $q->whereHas('classePeriode.periode', fn ($p) => $p->where('numero', 1))], 'date')
            ->orderBy('jour_semaine')->orderBy('heure_debut')->orderBy('id')
            ->get();
        $sessionsParPeriodeClasse = CourseSession::query()
            ->whereIn('classe_periode_id', $classes->flatMap->periodes->pluck('id'))
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->orderBy('date')
            ->get(['classe_periode_id', 'date'])
            ->groupBy('classe_periode_id');

        $parClasse = [];
        $touchees = [];
        foreach ($classes as $classe) {
            foreach ($classe->periodes as $cp) {
                $periode = $cp->periode;
                $avant = ['debut' => $periode->date_debut->toDateString(), 'fin' => $periode->date_fin->toDateString()];
                $apres = ['debut' => $nouvelles[$periode->numero]['date_debut'], 'fin' => $nouvelles[$periode->numero]['date_fin']];

                $enPlus = [];
                foreach ($sessionsParPeriodeClasse->get($cp->id, collect()) as $session) {
                    $date = $session->date->toDateString();
                    $avantHors = PeriodeRegles::horsPeriode($date, $avant['fin']);
                    $apresHors = PeriodeRegles::horsPeriode($date, $apres['fin']);
                    if ($apresHors && ! $avantHors) {
                        $enPlus[] = $date;
                    } elseif ($avantHors && ! $apresHors) {
                        $resultat['seances_redevenant_dans_periode']++;
                    }
                }
                if ($enPlus) {
                    $resultat['seances_hors_periode_en_plus'] += count($enPlus);
                    $parClasse[] = $this->ligne($classe, $periode->numero, 'hors_periode', $enPlus);
                    $touchees[$classe->id] = true;
                }

                $premiere = $cp->date_premiere_session->toDateString();
                if ($cp->statut === ClassePeriode::STATUT_ACTIVE
                    && PeriodeRegles::demarreAvantDebut($premiere, $apres['debut'])
                    && ! PeriodeRegles::demarreAvantDebut($premiere, $avant['debut'])) {
                    $resultat['classes_demarrant_avant_debut']++;
                    $parClasse[] = $this->ligne($classe, $periode->numero, 'demarre_avant_debut', [$premiere]);
                    $touchees[$classe->id] = true;
                }
            }

            $this->alerteP2($annee, $classe, $resultat, $parClasse, $touchees);
        }

        $resultat['classes_touchees'] = count($touchees);
        $resultat['par_classe'] = $parClasse;
        $resultat['calendrier_hors_annee'] = $this->calendrierHorsAnnee($annee, $nouvelles);

        return $resultat;
    }

    /**
     * Alertes « P2 à planifier » avant / après. La règle (PeriodeRegles::alertePeriode2) dépend de la dernière séance de P1
     * et de la date du jour, pas des bornes : les dates hypothétiques ne créent/suppriment donc d'alerte que si
     * la règle l'exige ; les classes candidates sont comptées dans `classes_sans_p2`.
     */
    private function alerteP2(AnneeScolaire $annee, Classe $classe, array &$resultat, array &$parClasse, array &$touchees): void
    {
        $numeros = $classe->periodes->map(fn (ClassePeriode $p) => $p->periode?->numero);
        if (PeriodeRegles::candidateAlerteP2($classe, $annee, $numeros)) {
            $resultat['classes_sans_p2']++;
        }

        $derniere = $classe->getAttributes()['derniere_p1'] ?? null;
        $avant = PeriodeRegles::alertePeriode2($classe, $annee, $numeros, $derniere);
        $apres = PeriodeRegles::alertePeriode2($classe, $annee, $numeros, $derniere);
        if ($apres && ! $avant) {
            $resultat['alertes_p2_creees']++;
            $parClasse[] = $this->ligne($classe, 1, 'alerte_p2', []);
            $touchees[$classe->id] = true;
        } elseif ($avant && ! $apres) {
            $resultat['alertes_p2_supprimees']++;
        }
    }

    /** @param Collection<int, array<string, string>> $nouvelles */
    private function calendrierHorsAnnee(AnneeScolaire $annee, Collection $nouvelles): int
    {
        $debut = $nouvelles->min('date_debut');
        $fin = $nouvelles->max('date_fin');

        return CalendrierScolaire::where('annee_scolaire_id', $annee->id)
            ->where(fn ($q) => $q->where('date_fin', '<', $debut)->orWhere('date_debut', '>', $fin))
            ->count();
    }

    /** @param list<string> $dates */
    private function ligne(Classe $classe, int $numero, string $type, array $dates): array
    {
        return [
            'classe_id' => $classe->id,
            'titre' => ClasseResource::titreDe($classe),
            'creneau' => self::JOURS[$classe->jour_semaine].' '.$this->heure($classe->heure_debut).'–'.$this->heure($classe->heure_fin),
            'periode_numero' => $numero,
            'nb_seances' => count($dates),
            'dates' => $dates,
            'type' => $type,
        ];
    }

    private function heure(string $hms): string
    {
        [$h, $m] = array_map('intval', explode(':', $hms));

        return $h.'h'.($m ? str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
    }

    private function evolution(string $debutAvant, string $finAvant, string $debutApres, string $finApres): string
    {
        $parties = [];
        foreach (['début' => [$debutAvant, $debutApres], 'fin' => [$finAvant, $finApres]] as $nom => [$avant, $apres]) {
            $jours = (int) Carbon::parse($avant)->diffInDays(Carbon::parse($apres), false);
            if ($jours !== 0) {
                $parties[] = "{$nom} ".($jours > 0 ? '+' : '−').abs($jours).' j';
            }
        }

        return $parties ? implode(' · ', $parties) : 'inchangée';
    }
}
