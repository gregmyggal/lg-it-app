<?php

namespace App\Services;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\TimesheetVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TS-04 (ADR-0002) : instantanés immuables d'un mois de timesheet. « Soumise » = le mois tel qu'envoyé par le
 * professeur ; « validée » = le mois tel que validé par la direction. Les appelants ouvrent la transaction et
 * verrouillent les lignes du mois avant d'appeler ce service.
 *
 * Canonisation : lignes triées (date, id), clés dans un ordre fixe, décimaux en chaînes, JSON sans échappement. Le hash
 * est calculé sur le texte stocké (relu octet pour octet), jamais sur une réencodation.
 */
class TimesheetVersionService
{
    public function __construct(private readonly TarifResolver $tarifs, private readonly FicheDefraiementLignes $fiche) {}

    public function figerSoumise(Professeur $prof, int $annee, int $mois, ?User $auteur): TimesheetVersion
    {
        return $this->figer(TimesheetVersion::TYPE_SOUMISE, $prof, $annee, $mois, $auteur);
    }

    public function figerValidee(Professeur $prof, int $annee, int $mois, ?User $auteur): TimesheetVersion
    {
        return $this->figer(TimesheetVersion::TYPE_VALIDEE, $prof, $annee, $mois, $auteur);
    }

    public function active(Professeur|int $prof, int $annee, int $mois, string $type): ?TimesheetVersion
    {
        return TimesheetVersion::where([
            'professeur_id' => $prof instanceof Professeur ? $prof->id : $prof, 'annee' => $annee, 'mois' => $mois,
            'type' => $type, 'statut' => TimesheetVersion::STATUT_ACTIVE,
        ])->first();
    }

    /** Texte canonique et hash des lignes (hors brouillons) du mois, tels qu'ils seraient figés maintenant. @return array{lignes: string, hash: string} */
    public function canoniser(Professeur $prof, int $annee, int $mois): array
    {
        $saisies = $this->fiche->saisies($prof, $annee, $mois);
        $texte = $this->encoder($prof, $saisies);

        return ['lignes' => $texte, 'hash' => hash('sha256', $texte)];
    }

    private function figer(string $type, Professeur $prof, int $annee, int $mois, ?User $auteur): TimesheetVersion
    {
        return DB::transaction(function () use ($type, $prof, $annee, $mois, $auteur) {
            // Verrou de numérotation : une seule allocation à la fois par professeur (pas de verrou de gap sur la table).
            Professeur::whereKey($prof->id)->lockForUpdate()->firstOrFail();

            $saisies = $this->fiche->saisies($prof, $annee, $mois);
            $texte = $this->encoder($prof, $saisies);
            $lignes = $this->fiche->lignes($prof, $saisies);

            TimesheetVersion::where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'type' => $type, 'statut' => TimesheetVersion::STATUT_ACTIVE])
                ->get()->each->update(['statut' => TimesheetVersion::STATUT_REMPLACEE, 'actif_cle' => null]);

            $numero = (int) TimesheetVersion::where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'type' => $type])->max('numero') + 1;

            return TimesheetVersion::create([
                'professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'type' => $type, 'numero' => $numero,
                'statut' => TimesheetVersion::STATUT_ACTIVE,
                'actif_cle' => TimesheetVersion::cleActive($prof->id, $annee, $mois, $type),
                'format' => TimesheetVersion::FORMAT,
                'lignes' => $texte,
                'total_heures' => round((float) $saisies->where('type_activite', '!=', TimesheetService::TYPE_DEPLACEMENT)->sum('nombre_heures'), 2),
                'total_eur' => round((float) $lignes->sum('total'), 2),
                'content_hash' => hash('sha256', $texte),
                'auteur_id' => $auteur?->id,
            ]);
        });
    }

    /** @param Collection<int, Timesheet> $saisies */
    private function encoder(Professeur $prof, Collection $saisies): string
    {
        $tarifsProf = ProfesseurTarif::where('professeur_id', $prof->id)->get();
        $origines = $this->origines($saisies);

        $lignes = $saisies
            ->sortBy(fn (Timesheet $t) => $t->date_prestation->toDateString().'|'.str_pad((string) $t->id, 12, '0', STR_PAD_LEFT))
            ->map(fn (Timesheet $t) => [
                'id' => $t->id,
                'date' => $t->date_prestation->toDateString(),
                'type' => $t->type_activite,
                'heures' => number_format((float) $t->nombre_heures, 2, '.', ''),
                'unite' => ($u = $this->tarifs->unite($t, $tarifsProf)) === null ? null : number_format($u, 2, '.', ''),
                'montant' => ($m = $this->tarifs->montant($t, $tarifsProf)) === null ? null : number_format($m, 2, '.', ''),
                'session_id' => $t->course_session_id,
                'cours_id' => $t->cours_id,
                'origine_id' => $origines[$t->id] ?? null,
            ])->values()->all();

        return json_encode($lignes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Lien d'une ligne issue d'un lissage vers sa ligne d'origine (audit « lissage » : nouvelle_saisie_id).
     *
     * @param  Collection<int, Timesheet>  $saisies
     * @return array<int, int> id de la nouvelle ligne => id de la ligne d'origine
     */
    private function origines(Collection $saisies): array
    {
        if ($saisies->isEmpty()) {
            return [];
        }

        return TimesheetAudit::where('action', TimesheetAudit::ACTION_LISSAGE)
            ->whereIn('timesheet_id', $saisies->pluck('id'))->get()
            ->mapWithKeys(fn (TimesheetAudit $a) => isset($a->apres['nouvelle_saisie_id']) ? [(int) $a->apres['nouvelle_saisie_id'] => $a->timesheet_id] : [])
            ->all();
    }

    /** Bornes du mois, pour les verrous et les requêtes (comme `FicheDefraiementLignes::saisies`). @return array{string, string} */
    public static function bornes(int $annee, int $mois): array
    {
        $debut = Carbon::create($annee, $mois, 1);

        return [$debut->toDateString(), $debut->copy()->endOfMonth()->toDateString()];
    }
}
