<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\CourseSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CLS-06 : déplacer une séance et replanifier les séances suivantes (une par semaine, au jour de la classe,
 * congés sautés sauf dates forcées), sans jamais renuméroter. Arrêt sur la première séance verrouillée
 * (heures encodées ou terminée) ; cascade vers la P2 si la P1 déborde sur sa 1re séance.
 */
class SessionReplanificationService
{
    private const MAX_SEMAINES = 200;

    public function __construct(
        private readonly CalendrierScolaireService $calendrier,
        private readonly ClasseProfesseurAssignmentService $assignations,
        private readonly CourseSessionService $sessions,
    ) {}

    /**
     * Plan sans écriture.
     *
     * @param  array{date: string, heure_debut?: string, heure_fin?: string, lieu?: ?string, dates_forcees?: list<string>}  $data
     */
    public function apercu(CourseSession $session, array $data): array
    {
        $this->sessions->verifierDeplacable($session);

        return $this->calculer($session, $data);
    }

    /**
     * Applique le plan (tout ou rien). Avec `empreinte`, refuse (409) si le planning a changé depuis l'aperçu.
     *
     * @param  array{date: string, heure_debut?: string, heure_fin?: string, lieu?: ?string, dates_forcees?: list<string>, empreinte?: string}  $data
     */
    public function appliquer(CourseSession $session, array $data): array
    {
        return DB::transaction(function () use ($session, $data) {
            CourseSession::where('classe_id', $session->classe_id)->lockForUpdate()->get();
            $session->refresh();
            $this->sessions->verifierDeplacable($session);
            $plan = $this->calculer($session, $data);

            if (isset($data['empreinte']) && $data['empreinte'] !== $plan['empreinte']) {
                throw RegleMetierException::conflit(
                    "Le planning de la classe a changé depuis l'aperçu. Vérifiez les nouvelles dates puis confirmez à nouveau.",
                    ['code' => 'planning_modifie']
                );
            }

            foreach ($plan['periodes'] as $bloc) {
                foreach ($bloc['lignes'] as $ligne) {
                    if ($ligne['etat'] === 'decalee') {
                        CourseSession::whereKey($ligne['id'])->update(['date' => $ligne['date_apres']]);
                    }
                }
                if ($bloc['cascade']) {
                    $premiere = collect($bloc['lignes'])->first(fn ($l) => $l['seance_numero'] === 1 && $l['bis_rang'] === 0);
                    if ($premiere) {
                        ClassePeriode::whereKey($bloc['classe_periode_id'])->update(['date_premiere_session' => $premiere['date_apres']]);
                    }
                }
            }

            $session->update(array_intersect_key($data, array_flip(['date', 'heure_debut', 'heure_fin', 'lieu'])));

            return $plan;
        });
    }

    private function calculer(CourseSession $session, array $data): array
    {
        if ($session->bis_rang > 0) {
            $message = 'Un bis ne décale pas les séances suivantes : décochez « Décaler aussi les séances suivantes ».';
            throw RegleMetierException::invalide($message, ['decaler_suivantes' => [$message]]);
        }

        $session->loadMissing('classe.anneeScolaire');
        $classe = $session->classe;
        $date = $data['date'];
        $forcees = $data['dates_forcees'] ?? [];
        $entrees = $this->calendrier->entreesActives($classe->annee_scolaire_id);

        $periodes = $classe->periodes()->with('periode')->get()->sortBy(fn (ClassePeriode $cp) => $cp->periode->numero)->values();
        $parPeriode = CourseSession::where('classe_id', $classe->id)
            ->withCount('timesheets')
            ->with('sessionProfesseurs.professeur')
            ->orderBy('seance_numero')->orderBy('bis_rang')
            ->get()
            ->groupBy('classe_periode_id');

        $cp = $periodes->firstWhere('id', $session->classe_periode_id);
        $sessionsPeriode = $parPeriode->get($cp->id, collect());
        $this->verifierOrdre($session, $date, $sessionsPeriode, $cp);

        $avertissements = [];
        $contexte = ['classe' => $classe, 'entrees' => $entrees, 'forcees' => $forcees];
        $blocs = [$this->recalculerPeriode($cp, $sessionsPeriode, $session, $date, $date, $contexte, $avertissements)];

        $suivante = $periodes->first(fn (ClassePeriode $p) => $p->periode->numero > $cp->periode->numero);
        if ($suivante) {
            $this->cascade($blocs[0], $sessionsPeriode, $suivante, $parPeriode->get($suivante->id, collect()), $contexte, $blocs, $avertissements);
        }

        $avertissements = array_merge($avertissements, $this->conflitsProfesseurs($classe, $blocs, $session, $data));

        $empreinte = sha1(json_encode([
            $date, $data['heure_debut'] ?? null, $data['heure_fin'] ?? null,
            array_map(fn ($b) => array_map(fn ($l) => [$l['id'], $l['date_avant'], $l['date_apres'], $l['etat']], $b['lignes']), $blocs),
        ]));

        return [
            'seance' => ['id' => $session->id, 'libelle' => $this->libelle($session, $cp), 'date_avant' => $session->date->toDateString(), 'date_apres' => $date],
            'periodes' => $blocs,
            'decalees' => collect($blocs)->mapWithKeys(fn ($b) => ['p'.$b['numero'] => $b['decalees']])->all(),
            'avertissements' => $avertissements,
            'empreinte' => $empreinte,
        ];
    }

    /** RG-8 : la nouvelle date doit être après la séance active précédente de la période (bis inclus). */
    private function verifierOrdre(CourseSession $session, string $date, Collection $sessionsPeriode, ClassePeriode $cp): void
    {
        $precedente = $sessionsPeriode
            ->filter(fn (CourseSession $s) => $s->isActive() && $s->seance_numero < $session->seance_numero)
            ->sortBy(fn (CourseSession $s) => $s->date->toDateString())
            ->last();

        if ($precedente && $date <= $precedente->date->toDateString()) {
            $message = 'La nouvelle date doit être après la séance précédente '.$this->libelle($precedente, $cp).' ('.$this->court($precedente->date->toDateString()).').';
            throw RegleMetierException::invalide($message, ['date' => [$message]]);
        }
    }

    /**
     * Recalcule une période : la séance déplacée (si elle en fait partie) prend sa date, puis chaque séance de base
     * suivante prend le prochain jour de classe libre à partir de la semaine qui suit $reference.
     */
    private function recalculerPeriode(ClassePeriode $cp, Collection $sessionsPeriode, ?CourseSession $deplacee, ?string $dateDeplacee, string $reference, array $contexte, array &$avertissements): array
    {
        $numero = $cp->periode->numero;
        $depart = $deplacee?->seance_numero ?? 0;
        $creneau = Carbon::parse($reference)->startOfWeek()->addWeek()->addDays($contexte['classe']->jour_semaine - 1);
        $lignes = [];
        $sautees = [];
        $verrou = null;

        if ($deplacee) {
            $lignes[] = $this->ligne($deplacee, $cp, $dateDeplacee, 'deplacee');
        }

        foreach ($sessionsPeriode as $s) {
            if ($s->seance_numero < $depart || ($deplacee && $s->id === $deplacee->id)) {
                continue;
            }
            $avant = $s->date->toDateString();

            if ($verrou) {
                $lignes[] = $this->ligne($s, $cp, $avant, 'inchangee');
            } elseif ($s->bis_rang > 0 || $s->isAnnulee()) {
                $lignes[] = $this->ligne($s, $cp, $avant, 'figee');
            } elseif ($s->timesheets_count > 0 || $s->statut === CourseSession::STATUT_TERMINEE) {
                $verrou = $s;
                $lignes[] = $this->ligne($s, $cp, $avant, 'verrouillee') + ['motif' => $s->timesheets_count > 0 ? 'heures_encodees' : 'terminee'];
            } else {
                $apres = $this->prochainCreneau($creneau, $contexte, $sautees);
                $lignes[] = $this->ligne($s, $cp, $apres, $apres === $avant ? 'inchangee' : 'decalee');
            }
        }

        $this->avertissementsPeriode($lignes, $cp, $verrou, $contexte['classe'], $avertissements);

        return [
            'classe_periode_id' => $cp->id,
            'numero' => $numero,
            'cascade' => $deplacee === null,
            'declencheur' => null,
            'lignes' => $lignes,
            'dates_sautees' => $sautees,
            'arret' => $verrou ? ['seance' => $this->libelle($verrou, $cp), 'motif' => $verrou->timesheets_count > 0 ? 'heures_encodees' : 'terminee'] : null,
            'decalees' => count(array_filter($lignes, fn ($l) => $l['etat'] === 'decalee')),
        ];
    }

    /** Premier jour de classe non couvert par un congé (sauf date forcée) à partir de $creneau, qui avance d'une semaine. */
    private function prochainCreneau(Carbon $creneau, array $contexte, array &$sautees): string
    {
        for ($i = 0; $i < self::MAX_SEMAINES; $i++) {
            $date = $creneau->toDateString();
            $creneau->addWeek();
            $entree = $this->calendrier->entreeCouvrant($contexte['entrees'], $date);
            if (! $entree || in_array($date, $contexte['forcees'], true)) {
                return $date;
            }
            $sautees[] = ['date' => $date, 'libelle' => $entree->libelle, 'type' => $entree->type];
        }

        throw RegleMetierException::invalide('Calendrier scolaire saturé : impossible de replanifier les séances.');
    }

    /** RG-6 : si la nouvelle dernière séance active atteint la 1re séance active de la période suivante, celle-ci est recalculée. */
    private function cascade(array $bloc, Collection $sessionsPeriode, ClassePeriode $suivante, Collection $sessionsSuivante, array $contexte, array &$blocs, array &$avertissements): void
    {
        $nouvelles = collect($bloc['lignes'])->pluck('date_apres', 'id');
        $actives = $sessionsPeriode->filter(fn (CourseSession $s) => $s->isActive());
        $derniere = $actives->sortBy(fn (CourseSession $s) => $nouvelles[$s->id] ?? $s->date->toDateString())->last();
        $premiereSuivante = $sessionsSuivante->filter(fn (CourseSession $s) => $s->isActive())->sortBy(fn (CourseSession $s) => $s->date->toDateString())->first();
        if (! $derniere || ! $premiereSuivante) {
            return;
        }

        $dateDerniere = $nouvelles[$derniere->id] ?? $derniere->date->toDateString();
        $datePremiere = $premiereSuivante->date->toDateString();
        if ($dateDerniere < $datePremiere) {
            return;
        }

        $cp = $bloc['numero'];
        $cpSuivante = $suivante->periode->numero;
        $libelleDerniere = "P{$cp} · ".$derniere->libelle();
        $libellePremiere = "P{$cpSuivante} · ".$premiereSuivante->libelle();

        if ($bloc['arret']) {
            $avertissements[] = [
                'code' => 'chevauche_periode',
                'message' => "La {$libelleDerniere} ({$this->court($dateDerniere)}) chevauche la période {$cpSuivante}, qui n'est pas décalée car le décalage s'arrête sur la {$bloc['arret']['seance']}.",
            ];

            return;
        }

        $nouveau = $this->recalculerPeriode($suivante, $sessionsSuivante, null, null, $dateDerniere, $contexte, $avertissements);
        $nouveau['declencheur'] = "{$libelleDerniere} le {$this->court($dateDerniere)} ≥ {$libellePremiere} le {$this->court($datePremiere)}";
        $blocs[] = $nouveau;
    }

    /** Ordre inversé (RG-5bis), bis devancé (RG-4), hors période et fin d'année (RG-10). */
    private function avertissementsPeriode(array &$lignes, ClassePeriode $cp, ?CourseSession $verrou, Classe $classe, array &$avertissements): void
    {
        $finPeriode = $cp->periode->date_fin->toDateString();
        $finAnnee = $classe->anneeScolaire->date_fin->toDateString();
        $bougees = array_filter($lignes, fn ($l) => in_array($l['etat'], ['deplacee', 'decalee'], true));

        foreach ($lignes as &$ligne) {
            $ligne['hors_periode'] = PeriodeRegles::horsPeriode($ligne['date_apres'], $finPeriode);
        }
        unset($ligne);

        foreach ($bougees as $l) {
            if ($verrou && $l['date_apres'] >= $verrou->date->toDateString()) {
                $position = $l['date_apres'] === $verrou->date->toDateString() ? 'le même jour que' : 'après';
                $motif = $verrou->timesheets_count > 0 ? 'heures encodées' : 'terminée';
                $avertissements[] = [
                    'code' => 'ordre_inverse',
                    'message' => "La {$l['libelle']} tombera le {$this->court($l['date_apres'])}, {$position} la ".$this->libelle($verrou, $cp)." ({$this->court($verrou->date->toDateString())}, {$motif}) : l'ordre des séances ne suivra plus leur numéro.",
                ];
            }
            if ($l['date_apres'] > $finAnnee) {
                $avertissements[] = ['code' => 'fin_annee', 'message' => "La {$l['libelle']} ({$this->court($l['date_apres'])}) tombe après la fin de l'année scolaire ({$this->court($finAnnee)})."];
            } elseif ($l['date_apres'] > $finPeriode) {
                $avertissements[] = ['code' => 'hors_periode', 'message' => "La {$l['libelle']} ({$this->court($l['date_apres'])}) dépasse la fin de la période {$cp->periode->numero} ({$this->court($finPeriode)})."];
            }
        }

        foreach ($lignes as $bis) {
            if ($bis['bis_rang'] === 0 || $bis['statut'] === CourseSession::STATUT_ANNULEE) {
                continue;
            }
            $devant = collect($bougees)
                ->filter(fn ($l) => $l['seance_numero'] < $bis['seance_numero'] && $l['date_apres'] > $bis['date_apres'])
                ->sortBy('date_apres')->last();
            if ($devant) {
                $avertissements[] = [
                    'code' => 'bis_avant',
                    'message' => "La {$bis['libelle']} ({$this->court($bis['date_apres'])}) est désormais avant la {$devant['libelle']} ({$this->court($devant['date_apres'])}).",
                ];
            }
        }
    }

    /** RG-12 : conflit d'horaire d'un professeur de la séance à sa nouvelle date (hors séances de la même classe). */
    private function conflitsProfesseurs(Classe $classe, array $blocs, CourseSession $deplacee, array $data): array
    {
        $parProfesseur = [];
        $parId = CourseSession::with('sessionProfesseurs.professeur')
            ->whereIn('id', collect($blocs)->flatMap(fn ($b) => $b['lignes'])->whereIn('etat', ['deplacee', 'decalee'])->pluck('id'))
            ->get()->keyBy('id');

        foreach ($blocs as $bloc) {
            foreach ($bloc['lignes'] as $ligne) {
                if (! in_array($ligne['etat'], ['deplacee', 'decalee'], true) || ! $parId->has($ligne['id'])) {
                    continue;
                }
                $copie = clone $parId[$ligne['id']];
                $copie->date = Carbon::parse($ligne['date_apres']);
                if ($copie->id === $deplacee->id) {
                    $copie->heure_debut = isset($data['heure_debut']) ? $data['heure_debut'].':00' : $copie->heure_debut;
                    $copie->heure_fin = isset($data['heure_fin']) ? $data['heure_fin'].':00' : $copie->heure_fin;
                }
                foreach ($copie->sessionProfesseurs->where('remplace', false) as $sp) {
                    $parProfesseur[$sp->professeur_id]['professeur'] = $sp->professeur;
                    $parProfesseur[$sp->professeur_id]['sessions'][] = $copie;
                }
            }
        }

        $avertissements = [];
        foreach ($parProfesseur as $professeurId => $groupe) {
            foreach ($this->assignations->conflits($groupe['sessions'], $professeurId) as $conflit) {
                if ($conflit['classe_id'] === $classe->id) {
                    continue;
                }
                $nom = trim(($groupe['professeur']->prenom ?? '').' '.($groupe['professeur']->nom ?? ''));
                $avertissements[] = [
                    'code' => 'conflit_professeur',
                    'message' => "Conflit d'horaire pour {$nom} le {$this->court($conflit['date'])} : déjà sur « {$conflit['classe']} » de {$conflit['heure_debut']} à {$conflit['heure_fin']}.",
                ];
            }
        }

        return $avertissements;
    }

    private function ligne(CourseSession $s, ClassePeriode $cp, string $dateApres, string $etat): array
    {
        return [
            'id' => $s->id,
            'libelle' => $this->libelle($s, $cp),
            'seance_numero' => $s->seance_numero,
            'bis_rang' => $s->bis_rang,
            'statut' => $s->statut,
            'date_avant' => $s->date->toDateString(),
            'date_apres' => $dateApres,
            'etat' => $etat,
        ];
    }

    private function libelle(CourseSession $s, ClassePeriode $cp): string
    {
        return "P{$cp->periode->numero} · ".$s->libelle();
    }

    private function court(string $date): string
    {
        return Carbon::parse($date)->format('d/m');
    }
}
