<?php

namespace App\Services;

use App\Http\Resources\ClasseResource;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\CourseSession;
use App\Models\Periode;
use Illuminate\Support\Carbon;

/**
 * CLS-07 : dupliquer une classe sur un autre jour et/ou horaire. Proposition pré-remplie pour l'écran
 * « Nouvelle classe » (dates transposées dans la même semaine que la source) et enrichissement de l'aperçu :
 * comparaison source → copie, séances passées, congés forcés dans la source, doublons probables.
 * La création reste celle de ClasseSessionGenerator ; aucun professeur n'est repris.
 */
class ClasseDuplicationService
{
    private const JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    public function __construct(
        private readonly ClasseSessionGenerator $generator,
        private readonly CalendrierScolaireService $calendrier,
    ) {}

    /** « Scratch — mercredi 14h–15h30 » */
    public static function libelle(Classe $classe): string
    {
        $classe->loadMissing('periodes.cours');

        return ClasseResource::titreDe($classe).' — '.self::JOURS[$classe->jour_semaine].' '
            .self::heure($classe->heure_debut).'–'.self::heure($classe->heure_fin);
    }

    /** Même format que le front (`formatHeure`) : « 14h », « 15h30 ». */
    private static function heure(string $hhmm): string
    {
        [$h, $m] = explode(':', $hhmm);

        return (int) $h.'h'.($m !== '00' ? $m : '');
    }

    /** Formulaire pré-rempli (RG-2, RG-3, RG-7, RG-8). */
    public function proposer(Classe $source, ?int $jour = null, ?string $heureDebut = null, ?string $heureFin = null): array
    {
        $source->loadMissing('periodes.periode', 'periodes.cours', 'anneeScolaire');
        $jour ??= $source->jour_semaine;
        $archivee = $source->anneeScolaire->isArchivee();
        $periodes = [];
        $nonReprises = [];

        foreach ($source->periodes->sortBy(fn (ClassePeriode $cp) => $cp->periode->numero) as $cp) {
            $numero = $cp->periode->numero;
            if ($cp->statut === ClassePeriode::STATUT_ANNULEE) {
                $nonReprises[] = ['numero' => $numero, 'motif' => "La période {$numero} de la classe source est annulée : elle n'est pas reprise."];

                continue;
            }
            [$date, $raison] = $archivee ? [null, null] : $this->transposer($this->demarrageReel($cp), $jour, $cp->periode);
            $periodes[] = [
                'periode_id' => $cp->periode_id,
                'numero' => $numero,
                'cours_id' => $cp->cours_id,
                'cours' => $cp->cours?->titre,
                'date_premiere_session' => $date,
                'raison' => $raison,
            ];
        }

        return [
            'source' => ['id' => $source->id, 'libelle' => self::libelle($source)],
            'annee_scolaire_id' => $archivee ? null : $source->annee_scolaire_id,
            'annee_source_archivee' => $archivee,
            'jour_semaine' => $jour,
            'heure_debut' => $heureDebut ?? substr($source->heure_debut, 0, 5),
            'heure_fin' => $heureFin ?? substr($source->heure_fin, 0, 5),
            'lieu' => $source->lieu,
            'periodes' => $periodes,
            'periodes_non_reprises' => $nonReprises,
        ];
    }

    /** Date actuelle de la séance 1 de la période (elle a pu être déplacée, CLS-06) ; à défaut, la date de démarrage enregistrée. */
    private function demarrageReel(ClassePeriode $cp): Carbon
    {
        $seance1 = CourseSession::where('classe_periode_id', $cp->id)->where('seance_numero', 1)->where('bis_rang', 0)
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)->first();

        return $seance1?->date ?? $cp->date_premiere_session;
    }

    /**
     * RG-3 : le jour choisi dans la même semaine civile que le démarrage de la source ; hors bornes de la période,
     * le premier (ou dernier) jour de classe dans les bornes (RG-3c).
     *
     * @return array{0: string, 1: string}
     */
    private function transposer(Carbon $demarrage, int $jour, Periode $periode): array
    {
        $date = $demarrage->copy()->startOfWeek()->addDays($jour - 1);
        $debut = $periode->date_debut->copy();
        $fin = $periode->date_fin->copy();

        if ($date->lt($debut)) {
            $date = $debut->addDays(($jour - $debut->dayOfWeekIso + 7) % 7);

            return [$date->toDateString(), 'borne_periode'];
        }
        if ($date->gt($fin)) {
            $date = $fin->subDays(($fin->dayOfWeekIso - $jour + 7) % 7);

            return [$date->toDateString(), 'borne_periode'];
        }

        return [$date->toDateString(), 'meme_semaine'];
    }

    /**
     * Ajoute à l'aperçu de création : `periodes[].comparaison`, `periodes[].dates_sautees[].forcee_dans_source`,
     * `seances_passees`, `avertissements` et `doublons` (RG-3b, RG-5, RG-5bis, RG-9).
     *
     * @param  array{periodes: list<array<string, mixed>>}  $apercu
     */
    public function enrichirApercu(array $apercu, array $data): array
    {
        $source = Classe::with('periodes.periode', 'anneeScolaire')->find($data['source_classe_id'] ?? null);
        $aujourdhui = now('Europe/Brussels')->toDateString();

        if ($source && (int) $data['annee_scolaire_id'] === $source->annee_scolaire_id) {
            $entrees = $this->calendrier->entreesActives($source->annee_scolaire_id);
            foreach ($apercu['periodes'] as &$plan) {
                $cp = $source->periodes->first(fn (ClassePeriode $p) => $p->periode_id === $plan['periode_id'] && $p->statut !== ClassePeriode::STATUT_ANNULEE);
                if ($cp) {
                    $this->comparer($plan, $cp, $source, $entrees, $aujourdhui);
                }
            }
            unset($plan);
        }

        $passees = collect($apercu['periodes'])->flatMap(fn ($p) => $p['seances'])->filter(fn ($s) => $s['date'] < $aujourdhui)->count();
        $doublons = $this->doublons($data);
        $avertissements = [];
        if ($passees > 0) {
            $avertissements[] = [
                'code' => 'demarrage_passe',
                'message' => "La copie démarre dans le passé : {$passees} séance".($passees > 1 ? 's seront créées à des dates déjà passées' : ' sera créée à une date déjà passée').'. Vous pouvez choisir une date de démarrage plus tardive.',
            ];
        }
        foreach ($doublons as $d) {
            $avertissements[] = [
                'code' => 'doublon',
                'message' => "Une classe semblable existe déjà : « {$d['libelle']} » (même jour, horaire qui se chevauche, cours en commun : ".implode(', ', $d['cours_communs']).').',
            ];
        }

        return $apercu + ['seances_passees' => $passees, 'avertissements' => $avertissements, 'doublons' => $doublons];
    }

    /** RG-5 / RG-5bis : séance n de la copie ↔ séance de base n de la source. */
    private function comparer(array &$plan, ClassePeriode $cp, Classe $source, $entrees, string $aujourdhui): void
    {
        $sessions = CourseSession::where('classe_periode_id', $cp->id)->orderBy('seance_numero')->orderBy('bis_rang')->get();
        $bases = $sessions->where('bis_rang', 0)->keyBy('seance_numero');
        $bis = $sessions->filter(fn (CourseSession $s) => $s->bis_rang > 0 && $s->isActive())->groupBy('seance_numero');
        $standard = collect($this->generator->plan($source->annee_scolaire_id, $cp->periode, $source->jour_semaine, $cp->date_premiere_session->toDateString())['seances'])
            ->pluck('date', 'seance_numero');

        $plan['comparaison'] = array_map(function (array $seance) use ($bases, $bis, $standard, $entrees, $aujourdhui) {
            $s = $bases->get($seance['seance_numero']);
            $dateSource = $s?->date->toDateString();
            $etat = null;
            $conge = null;
            if ($s) {
                $entree = $this->calendrier->entreeCouvrant($entrees, $dateSource);
                $etat = match (true) {
                    $s->isAnnulee() => 'annulee',
                    $entree !== null => 'pendant_conge',
                    ($standard[$seance['seance_numero']] ?? $dateSource) !== $dateSource => 'deplacee',
                    default => 'normale',
                };
                $conge = $entree?->libelle;
            }

            return [
                'seance_numero' => $seance['seance_numero'],
                'date_source' => $dateSource,
                'etat_source' => $etat,
                'date_source_prevue' => $etat === 'deplacee' ? $standard[$seance['seance_numero']] : null,
                'conge_source' => $etat === 'pendant_conge' ? $conge : null,
                'bis_source' => $bis->get($seance['seance_numero'])?->first()?->date->toDateString(),
                'date_copie' => $seance['date'],
                'passee' => $seance['date'] < $aujourdhui,
                'ecart_jours' => $dateSource ? (int) Carbon::parse($dateSource)->diffInDays(Carbon::parse($seance['date']), false) : null,
            ];
        }, $plan['seances']);

        $tenuesEnConge = $sessions->filter(fn (CourseSession $s) => $s->isActive() && $this->calendrier->entreeCouvrant($entrees, $s->date->toDateString()))
            ->map(fn (CourseSession $s) => $s->date->toDateString())->all();
        foreach ($plan['dates_sautees'] as &$sautee) {
            $sautee['forcee_dans_source'] = in_array($sautee['date'], $tenuesEnConge, true);
        }
        unset($sautee);
    }

    /**
     * RG-9 : classe non archivée de la même année, même jour, horaire qui chevauche, au moins un cours en commun.
     *
     * @return list<array{classe_id: int, libelle: string, cours_communs: list<string>}>
     */
    private function doublons(array $data): array
    {
        $cours = collect($data['periodes'])->pluck('cours_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($cours->isEmpty()) {
            return [];
        }
        $debut = substr($data['heure_debut'], 0, 5).':00';
        $fin = substr($data['heure_fin'], 0, 5).':00';

        return Classe::query()
            ->where('annee_scolaire_id', $data['annee_scolaire_id'])
            ->where('jour_semaine', $data['jour_semaine'])
            ->where('statut', '!=', Classe::STATUT_ARCHIVEE)
            ->where('heure_debut', '<', $fin)
            ->where('heure_fin', '>', $debut)
            ->whereHas('periodes', fn ($q) => $q->where('statut', '!=', ClassePeriode::STATUT_ANNULEE)->whereIn('cours_id', $cours))
            ->with('periodes.cours')
            ->get()
            ->map(fn (Classe $c) => [
                'classe_id' => $c->id,
                'libelle' => self::libelle($c),
                'cours_communs' => $c->periodes
                    ->filter(fn (ClassePeriode $p) => $p->statut !== ClassePeriode::STATUT_ANNULEE && $cours->contains($p->cours_id))
                    ->map(fn (ClassePeriode $p) => $p->cours?->titre)
                    ->unique()->values()->all(),
            ])
            ->values()->all();
    }
}
