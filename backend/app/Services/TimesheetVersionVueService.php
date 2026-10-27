<?php

namespace App\Services;

use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\TimesheetAudit;
use App\Models\TimesheetVersion;
use Illuminate\Support\Collection;

/**
 * TS-04 : lecture des versions d'un mois (soumise, validée, historique) et écart ligne à ligne, calculé côté serveur.
 * Le professeur ne voit ni empreinte ni nom d'auteur ; la direction et l'admin voient tout.
 */
class TimesheetVersionVueService
{
    /**
     * @return array<string, mixed>
     */
    public function vue(Professeur $prof, int $annee, int $mois, bool $staff, ?int $soumiseNumero = null, ?int $valideeNumero = null): array
    {
        $toutes = TimesheetVersion::with('auteur:id,name')
            ->where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois])->orderBy('numero')->get();
        $soumises = $toutes->where('type', TimesheetVersion::TYPE_SOUMISE);
        $validees = $toutes->where('type', TimesheetVersion::TYPE_VALIDEE);

        $soumise = $soumiseNumero ? $soumises->firstWhere('numero', $soumiseNumero) : $soumises->firstWhere('statut', TimesheetVersion::STATUT_ACTIVE);
        $validee = $valideeNumero ? $validees->firstWhere('numero', $valideeNumero) : $validees->firstWhere('statut', TimesheetVersion::STATUT_ACTIVE);

        $sessions = $this->sessions(collect([$soumise, $validee])->filter());

        return [
            'annee' => $annee,
            'mois' => $mois,
            'disponibilite' => ['soumise' => $soumise !== null, 'validee' => $validee !== null],
            'soumise' => $soumise ? $this->version($soumise, $staff, $sessions) : null,
            'validee' => $validee ? $this->version($validee, $staff, $sessions) : null,
            'historique' => [
                'soumises' => $soumises->map(fn (TimesheetVersion $v) => $this->resume($v))->values(),
                'validees' => $validees->map(fn (TimesheetVersion $v) => $this->resume($v))->values(),
            ],
            'ecart' => $soumise && $validee && $soumise->lignes !== null && $validee->lignes !== null ? $this->ecart($soumise, $validee, $staff, $sessions) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function resume(TimesheetVersion $v): array
    {
        return ['numero' => $v->numero, 'statut' => $v->statut, 'created_at' => $v->created_at, 'total_heures' => $v->total_heures, 'total_eur' => $v->total_eur];
    }

    /** @param Collection<int, CourseSession> $sessions */
    private function version(TimesheetVersion $v, bool $staff, Collection $sessions): array
    {
        return $this->resume($v) + [
            'detail_efface' => $v->lignes === null,
            'lignes' => $v->lignes === null ? [] : $this->lignes($v, $sessions),
        ] + ($staff ? ['auteur' => $v->auteur?->name, 'empreinte' => $v->content_hash] : []);
    }

    /** @param Collection<int, CourseSession> $sessions */
    private function lignes(TimesheetVersion $v, Collection $sessions): array
    {
        return array_map(fn (array $l) => $l + ['libelle' => $l['session_id'] ? ($sessions->get($l['session_id'])?->libelle() ?? 'Séance') : 'Heures libres'], $v->lignesDecodees());
    }

    /** @param Collection<int, TimesheetVersion> $versions @return Collection<int, CourseSession> */
    private function sessions(Collection $versions): Collection
    {
        $ids = $versions->flatMap(fn (TimesheetVersion $v) => $v->lignes === null ? [] : array_column($v->lignesDecodees(), 'session_id'))->filter()->unique();

        return $ids->isEmpty() ? collect() : CourseSession::whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Écart soumise → validée : une entrée par ligne, appariée sur son identifiant.
     * « modifiée » = heures, date, type ou montant différents ; « ajoutée » = absente de la soumise (ex. lissage) ;
     * « retirée » = absente de la validée.
     *
     * @param  Collection<int, CourseSession>  $sessions
     * @return array<string, mixed>
     */
    private function ecart(TimesheetVersion $soumise, TimesheetVersion $validee, bool $staff, Collection $sessions): array
    {
        $avant = collect($this->lignes($soumise, $sessions))->keyBy('id');
        $apres = collect($this->lignes($validee, $sessions))->keyBy('id');
        $motifs = $this->motifs($avant->keys()->merge($apres->keys())->unique(), $soumise->created_at, $validee->created_at, $staff);

        $lignes = $avant->keys()->merge($apres->keys())->unique()
            ->sortBy(fn ($id) => ($apres[$id]['date'] ?? $avant[$id]['date']).'|'.str_pad((string) $id, 12, '0', STR_PAD_LEFT))
            ->map(function ($id) use ($avant, $apres, $motifs, $staff) {
                $a = $avant->get($id);
                $b = $apres->get($id);
                $etat = $a === null ? 'ajoutee' : ($b === null ? 'retiree' : (
                    ($a['heures'] !== $b['heures'] || $a['date'] !== $b['date'] || $a['type'] !== $b['type'] || $a['montant'] !== $b['montant']) ? 'modifiee' : 'identique'
                ));
                // Une ligne issue d'un lissage hérite du motif de sa ligne d'origine.
                $motif = $motifs[$id] ?? ($b['origine_id'] ?? null ? ($motifs[$b['origine_id']] ?? null) : null);

                return [
                    'id' => $id,
                    'etat' => $etat,
                    'avant' => $a,
                    'apres' => $b,
                    'ecart_heures' => round((float) ($b['heures'] ?? 0) - (float) ($a['heures'] ?? 0), 2),
                    'ecart_eur' => round((float) ($b['montant'] ?? 0) - (float) ($a['montant'] ?? 0), 2),
                    'motif' => $motif['motif'] ?? null,
                    'auteur' => $staff ? ($motif['auteur'] ?? null) : null,
                ];
            })->values();

        return [
            'identique' => $lignes->every(fn ($l) => $l['etat'] === 'identique'),
            'lignes' => $lignes,
            'total_avant' => ['heures' => $soumise->total_heures, 'eur' => $soumise->total_eur],
            'total_apres' => ['heures' => $validee->total_heures, 'eur' => $validee->total_eur],
            'ecart_heures' => round($validee->total_heures - $soumise->total_heures, 2),
            'ecart_eur' => round($validee->total_eur - $soumise->total_eur, 2),
        ];
    }

    /**
     * Dernier motif d'adaptation ou de lissage de chaque ligne, entre la soumission et la validation.
     *
     * @param  Collection<int, int|string>  $ids
     * @return array<int, array{motif: string, auteur: ?string}>
     */
    private function motifs(Collection $ids, $depuis, $jusqua, bool $staff): array
    {
        return TimesheetAudit::with('auteur:id,name')
            ->whereIn('timesheet_id', $ids)->whereIn('action', [TimesheetAudit::ACTION_ADAPTATION, TimesheetAudit::ACTION_LISSAGE])
            ->where('created_at', '>=', $depuis->copy()->startOfSecond())->where('created_at', '<=', $jusqua->copy()->addSecond())
            ->orderBy('id')->get()
            ->mapWithKeys(fn (TimesheetAudit $a) => [$a->timesheet_id => ['motif' => $a->motif, 'auteur' => $a->auteur?->name]])
            ->all();
    }
}
