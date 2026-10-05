<?php

namespace App\Http\Resources;

use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Services\ClasseDuplicationService;
use App\Services\PeriodeRegles;
use App\Services\TimesheetParametreService;
use App\Services\TimesheetService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * nb_sessions = sessions non annulées (bis inclus) de toutes les périodes, pour coller à la règle de dépassement de 14.
 * Les agrégats par période viennent de {@see Classe::relationsResource()}.
 *
 * @mixin Classe
 */
class ClasseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $periodes = $this->relationLoaded('periodes') ? $this->periodes : null;

        return [
            'id' => $this->id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'annee_scolaire' => $this->whenLoaded('anneeScolaire', fn () => [
                'id' => $this->anneeScolaire->id,
                'libelle' => $this->anneeScolaire->libelle,
            ]),
            'titre' => $this->when($periodes !== null, fn () => $this->titre()),
            'jour_semaine' => $this->jour_semaine,
            'heure_debut' => substr($this->heure_debut, 0, 5),
            'heure_fin' => substr($this->heure_fin, 0, 5),
            'lieu' => $this->lieu,
            'duree_seance' => TimesheetService::dureeEntre($this->heure_debut, $this->heure_fin),
            'statut' => $this->statut,
            'source' => $this->whenLoaded('source', fn () => $this->source ? [
                'id' => $this->source->id,
                'libelle' => ClasseDuplicationService::libelle($this->source),
            ] : null),
            'periodes' => $this->when($periodes !== null, fn () => $periodes->map(fn (ClassePeriode $p) => self::periodeDeClasse($p))->values()),
            'nb_sessions' => $this->whenCounted('sessionsActives'),
            'alerte_periode_2' => $this->when($periodes !== null && $this->relationLoaded('anneeScolaire'), fn () => $this->alertePeriode2()),
            'prochaine_session' => $this->when(
                $this->relationLoaded('prochaineSession'),
                fn () => $this->prochaineSession ? [
                    'id' => $this->prochaineSession->id,
                    'seance_numero' => $this->prochaineSession->seance_numero,
                    'bis_rang' => $this->prochaineSession->bis_rang,
                    'periode_numero' => $this->prochaineSession->classePeriode?->periode?->numero,
                    'libelle' => $this->prochaineSession->libelleComplet(),
                    'date' => $this->prochaineSession->date->toDateString(),
                ] : null
            ),
            'professeurs' => $this->whenLoaded('assignationsActives', fn () => $this->assignationsActives
                ->filter(fn ($a) => $a->relationLoaded('professeur'))
                ->map(fn ($a) => ProfesseurClasseResource::professeurLeger($a->professeur) + [
                    'role' => $a->role,
                    'remplace' => false,
                ])->values()),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }

    /** « Cours P1 → Cours P2 » ; cours seul si une seule période ou si le cours est le même. */
    public static function titreDe(Classe $classe): string
    {
        $titres = $classe->periodes->map(fn (ClassePeriode $p) => $p->cours?->titre)->filter()->values();

        return $titres->unique()->count() <= 1 ? (string) $titres->first() : $titres->implode(' → ');
    }

    /** @return array<string, mixed> */
    public static function periodeDeClasse(ClassePeriode $p): array
    {
        $periode = $p->periode;
        $derniere = $p->getAttributes()['derniere_session_date'] ?? null;

        return [
            'id' => $p->id,
            'periode_id' => $p->periode_id,
            'numero' => $periode?->numero,
            'periode' => $periode ? [
                'id' => $periode->id,
                'numero' => $periode->numero,
                'date_debut' => $periode->date_debut->toDateString(),
                'date_fin' => $periode->date_fin->toDateString(),
            ] : null,
            'cours_id' => $p->cours_id,
            'cours' => $p->cours ? ['id' => $p->cours->id, 'titre' => $p->cours->titre, 'slug' => $p->cours->slug] : null,
            'date_premiere_session' => $p->date_premiere_session->toDateString(),
            'date_derniere_session' => $derniere ? substr((string) $derniere, 0, 10) : null,
            'statut' => $p->statut,
            'motif_annulation' => $p->motif_annulation,
            'nb_sessions' => (int) ($p->getAttributes()['sessions_actives_count'] ?? 0),
            'nb_changements_cours' => (int) ($p->getAttributes()['historique_cours_count'] ?? 0),
            'nb_hors_periode' => (int) ($p->getAttributes()['nb_hors_periode'] ?? 0),
            'heures_defrayables' => $p->cours ? app(TimesheetParametreService::class)
                ->heuresDefrayablesPour($p->cours, (int) $p->date_premiere_session->format('Y')) : null,
        ];
    }

    private function titre(): string
    {
        return self::titreDe($this->resource);
    }

    /**
     * RG-8 : P1 présente, P2 absente, année active/brouillon avec une P2 définie, classe active, dernière séance P1 active à ≤ 28 jours (règle : PeriodeRegles).
     *
     * @return array{date_fin_periode_1: string, jours_restants: int, message: string}|null
     */
    private function alertePeriode2(): ?array
    {
        $p1 = $this->periodes->first(fn (ClassePeriode $p) => $p->periode?->numero === 1);

        return PeriodeRegles::alertePeriode2(
            $this->resource,
            $this->anneeScolaire,
            $this->periodes->map(fn (ClassePeriode $p) => $p->periode?->numero),
            $p1?->getAttributes()['derniere_session_date'] ?? null,
        );
    }
}
