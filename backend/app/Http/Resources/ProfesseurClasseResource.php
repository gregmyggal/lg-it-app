<?php

namespace App\Http\Resources;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Assignation professeur ⇄ classe. Aucun tarif exposé. `nb_sessions_assignees` = sessions non annulées
 * où le professeur est assigné (non remplacé) ; renseigné par {@see self::hydrater()}.
 *
 * @mixin ProfesseurClasse
 */
class ProfesseurClasseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'professeur_id' => $this->professeur_id,
            'professeur' => $this->whenLoaded('professeur', fn () => self::professeurLeger($this->professeur)),
            'classe_id' => $this->classe_id,
            'classe' => $this->whenLoaded('classe', fn () => [
                'id' => $this->classe->id,
                'titre' => $this->classe->relationLoaded('periodes') ? ClasseResource::titreDe($this->classe) : null,
                'periodes' => $this->classe->relationLoaded('periodes') ? $this->classe->periodes->map(fn ($p) => [
                    'id' => $p->id,
                    'periode_id' => $p->periode_id,
                    'numero' => $p->relationLoaded('periode') ? $p->periode->numero : null,
                    'cours_id' => $p->cours_id,
                    'cours' => $p->relationLoaded('cours') ? ['id' => $p->cours->id, 'titre' => $p->cours->titre] : null,
                    'statut' => $p->statut,
                ])->values() : null,
                'jour_semaine' => $this->classe->jour_semaine,
                'heure_debut' => substr($this->classe->heure_debut, 0, 5),
                'heure_fin' => substr($this->classe->heure_fin, 0, 5),
                'lieu' => $this->classe->lieu,
                'statut' => $this->classe->statut,
            ]),
            'role' => $this->role,
            'date_debut' => $this->date_debut->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'actif' => $this->isActif(),
            'nb_sessions_assignees' => $this->when(
                array_key_exists('nb_sessions_assignees', $this->resource->getAttributes()),
                fn () => (int) $this->resource->getAttributes()['nb_sessions_assignees']
            ),
            'co_professeurs' => $this->when(
                $this->resource->relationLoaded('co_professeurs'),
                fn () => $this->resource->getRelation('co_professeurs')->map(fn ($a) => self::professeurLeger($a->professeur) + ['role' => $a->role])->values()
            ),
            'can' => [
                'update' => (bool) $user?->can('manageProfesseurs', Classe::class),
                'delete' => (bool) $user?->can('manageProfesseurs', Classe::class) && $this->isActif(),
            ],
        ];
    }

    /** @return array{id: int, nom: string} */
    public static function professeurLeger($professeur): array
    {
        return ['id' => $professeur->id, 'nom' => trim($professeur->prenom.' '.$professeur->nom)];
    }

    /**
     * Renseigne `nb_sessions_assignees` sur les assignations en une seule requête.
     *
     * @param  iterable<ProfesseurClasse>  $assignations
     */
    public static function hydrater(iterable $assignations): void
    {
        $assignations = collect($assignations);
        if ($assignations->isEmpty()) {
            return;
        }

        $compteurs = SessionProfesseur::query()
            ->join('course_sessions', 'course_sessions.id', '=', 'session_professors.course_session_id')
            ->whereIn('course_sessions.classe_id', $assignations->pluck('classe_id')->unique())
            ->whereIn('session_professors.professeur_id', $assignations->pluck('professeur_id')->unique())
            ->where('session_professors.remplace', false)
            ->where('course_sessions.statut', '!=', CourseSession::STATUT_ANNULEE)
            ->select('course_sessions.classe_id', 'session_professors.professeur_id')
            ->selectRaw('count(*) as nb')
            ->groupBy('course_sessions.classe_id', 'session_professors.professeur_id')
            ->get()
            ->keyBy(fn ($r) => $r->classe_id.'-'.$r->professeur_id);

        $assignations->each(fn (ProfesseurClasse $a) => $a->setAttribute(
            'nb_sessions_assignees',
            $compteurs->get($a->classe_id.'-'.$a->professeur_id)?->nb ?? 0
        ));
    }

    /**
     * Attache à chaque assignation la relation « co_professeurs » : les autres assignations de la même classe.
     *
     * @param  Collection<int, ProfesseurClasse>  $assignations
     */
    public static function attacherCoProfesseurs(Collection $assignations, bool $actifsSeulement = true): void
    {
        if ($assignations->isEmpty()) {
            return;
        }

        $autres = ProfesseurClasse::query()->with('professeur')
            ->whereIn('classe_id', $assignations->pluck('classe_id')->unique())
            ->when($actifsSeulement, fn ($q) => $q->actif())
            ->orderBy('id')->get()->groupBy('classe_id');

        $assignations->each(fn (ProfesseurClasse $a) => $a->setRelation(
            'co_professeurs',
            $autres->get($a->classe_id, collect())->reject(fn ($o) => $o->professeur_id === $a->professeur_id)->values()
        ));
    }
}
