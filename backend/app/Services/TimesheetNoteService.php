<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Professeur;
use App\Models\Timesheet;
use App\Models\TimesheetNote;
use App\Models\TimesheetNoteHistorique;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TS-06 : notes internes de la direction et de l'admin sur un mois ou une saisie. Une seule note par mois et par
 * saisie, modifiable avec historique. Lecture et écriture réservées au staff (contrôlé par le contrôleur) ; modifier ou
 * supprimer la note d'un autre n'est permis qu'à l'admin.
 */
class TimesheetNoteService
{
    /** Durée de conservation, en mois après la fin du mois concerné. */
    public const RETENTION_MOIS = 24;

    /**
     * Crée la note, ou remplace son texte (l'ancienne version va dans l'historique).
     *
     * @throws RegleMetierException 422 saisie étrangère au mois, 403 note d'un autre auteur
     */
    public function enregistrer(Professeur $prof, int $annee, int $mois, ?int $timesheetId, string $texte, User $auteur): TimesheetNote
    {
        $texte = trim($texte);
        if ($timesheetId !== null) {
            [$debut, $fin] = TimesheetVersionService::bornes($annee, $mois);
            $appartient = Timesheet::whereKey($timesheetId)->where('professeur_id', $prof->id)->whereBetween('date_prestation', [$debut, $fin])->exists();
            if (! $appartient) {
                throw RegleMetierException::invalide('Cette saisie n\'appartient pas à ce mois.', ['timesheet_id' => ['Saisie introuvable pour ce mois.']]);
            }
        }

        return DB::transaction(function () use ($prof, $annee, $mois, $timesheetId, $texte, $auteur) {
            $cle = TimesheetNote::cle($prof->id, $annee, $mois, $timesheetId);
            $note = TimesheetNote::where('cle', $cle)->lockForUpdate()->first();
            if ($note === null) {
                return TimesheetNote::create([
                    'professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'timesheet_id' => $timesheetId,
                    'cle' => $cle, 'auteur_id' => $auteur->id, 'texte' => $texte,
                ]);
            }
            $this->verifierDroit($note, $auteur);
            if ($note->texte !== $texte) {
                TimesheetNoteHistorique::create(['note_id' => $note->id, 'texte' => $note->texte, 'auteur_id' => $auteur->id]);
                $note->update(['texte' => $texte]);
            }

            return $note;
        });
    }

    /** @throws RegleMetierException 404 note absente, 403 note d'un autre auteur */
    public function supprimer(Professeur $prof, int $annee, int $mois, ?int $timesheetId, User $auteur): void
    {
        $note = TimesheetNote::where('cle', TimesheetNote::cle($prof->id, $annee, $mois, $timesheetId))->first();
        if ($note === null) {
            throw new RegleMetierException('Aucune note interne à supprimer.', 404);
        }
        $this->verifierDroit($note, $auteur);
        $note->delete();
    }

    /** @return array{mois: ?array, lignes: array<int, array>} notes du mois, vues par `$lecteur` */
    public function pourMois(Professeur $prof, int $annee, int $mois, User $lecteur): array
    {
        $notes = TimesheetNote::with('auteur:id,name,role')->withCount('historique')
            ->where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois])->get();

        return [
            'mois' => ($n = $notes->firstWhere('timesheet_id', null)) ? $this->vue($n, $lecteur) : null,
            'lignes' => $notes->whereNotNull('timesheet_id')->mapWithKeys(fn (TimesheetNote $n) => [$n->timesheet_id => $this->vue($n, $lecteur)])->all(),
        ];
    }

    /**
     * Présence de notes pour plusieurs professeurs (modale de validation) : la note du mois (vue complète) et le nombre de
     * notes de ligne de chacun.
     *
     * @param  list<int>  $professeurIds
     * @return list<array{professeur_id: int, nom: string, mois: ?array, lignes: int}>
     */
    public function presence(array $professeurIds, int $annee, int $mois, User $lecteur): array
    {
        $notes = TimesheetNote::with('auteur:id,name,role')->withCount('historique')
            ->whereIn('professeur_id', $professeurIds)->where(['annee' => $annee, 'mois' => $mois])->get()->groupBy('professeur_id');

        return Professeur::whereIn('id', $professeurIds)->orderBy('nom')->orderBy('prenom')->get()->map(function (Professeur $p) use ($notes, $lecteur) {
            $siennes = $notes->get($p->id, collect());
            $moisNote = $siennes->firstWhere('timesheet_id', null);

            return [
                'professeur_id' => $p->id,
                'nom' => trim($p->prenom.' '.$p->nom),
                'mois' => $moisNote ? $this->vue($moisNote, $lecteur) : null,
                'lignes' => $siennes->whereNotNull('timesheet_id')->count(),
            ];
        })->values()->all();
    }

    /** Versions remplacées d'une note, de la plus récente à la plus ancienne (lecture seule). @return list<array> */
    public function historique(Professeur $prof, TimesheetNote $note): array
    {
        abort_unless($note->professeur_id === $prof->id, 404);

        return $note->historique()->with('auteur:id,name')->orderByDesc('id')->get()
            ->map(fn (TimesheetNoteHistorique $h) => ['texte' => $h->texte, 'remplace_par' => $h->auteur?->name, 'remplace_le' => $h->created_at])->all();
    }

    /**
     * Rétention : supprime (historique compris) les notes dont le mois s'est terminé il y a plus de 24 mois. Idempotent.
     *
     * @return int nombre de notes supprimées (ou concernées en simulation)
     */
    public function purger(?Carbon $maintenant = null, bool $simulation = false): int
    {
        $limite = ($maintenant ?? Carbon::now())->copy()->subMonths(self::RETENTION_MOIS);
        $requete = DB::table('timesheet_notes')->whereRaw('annee * 12 + mois < ?', [$limite->year * 12 + $limite->month]);

        return $simulation ? $requete->count() : $requete->delete();
    }

    private function verifierDroit(TimesheetNote $note, User $acteur): void
    {
        if ($note->auteur_id !== $acteur->id && ! $acteur->isAdmin()) {
            throw new RegleMetierException('Seul l\'auteur de la note ou un administrateur peut la modifier.', 403);
        }
    }

    /** @return array<string, mixed> */
    private function vue(TimesheetNote $n, User $lecteur): array
    {
        return [
            'id' => $n->id,
            'texte' => $n->texte,
            'auteur' => $n->auteur ? ['id' => $n->auteur->id, 'nom' => $n->auteur->name, 'role' => $n->auteur->role] : null,
            'created_at' => $n->created_at,
            'updated_at' => $n->updated_at,
            'modifications' => (int) $n->historique_count,
            'peut_modifier' => $n->auteur_id === $lecteur->id || $lecteur->isAdmin(),
        ];
    }
}
