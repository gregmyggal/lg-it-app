<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use App\Models\CourseSession;
use Illuminate\Support\Facades\DB;

/**
 * Ajustement des sessions d'une classe : déplacer, annuler (le numéro de séance est conservé),
 * ajouter un « bis » (RG-2). Jamais de renumérotation, jamais après la fin de la période.
 */
class CourseSessionService
{
    /** @param array{date?: string, heure_debut?: string, heure_fin?: string, lieu?: ?string} $data */
    public function deplacer(CourseSession $session, array $data): CourseSession
    {
        if ($session->isAnnulee()) {
            throw RegleMetierException::conflit('Une session annulée ne peut pas être déplacée.');
        }
        if ($session->isPassee()) {
            throw RegleMetierException::conflit('Une session passée ne peut pas être déplacée.');
        }

        if (isset($data['date'])) {
            $this->assertDansLaPeriode($session->classe()->with('periode')->first(), $data['date']);
        }

        $session->update($data);

        return $session->refresh();
    }

    public function annuler(CourseSession $session, string $motif): CourseSession
    {
        if (! $session->isCancellable()) {
            throw RegleMetierException::conflit('Seule une session planifiée ou en cours peut être annulée.');
        }

        $session->update([
            'statut' => CourseSession::STATUT_ANNULEE,
            'motif_annulation' => $motif,
            'cancelled_at' => now(),
        ]);

        return $session->refresh();
    }

    /**
     * Crée un « bis » rattaché à l'une des séances de la classe.
     *
     * @param  array{seance_numero: int, date: string, heure_debut?: ?string, heure_fin?: ?string, lieu?: ?string}  $data
     *
     * @throws RegleMetierException 422 séance inconnue / date hors période ; 409 dépassement non confirmé
     */
    public function ajouterBis(Classe $classe, array $data, bool $confirmerDepassement = false): CourseSession
    {
        $classe->loadMissing('periode');

        return DB::transaction(function () use ($classe, $data, $confirmerDepassement) {
            $seance = CourseSession::query()
                ->where('classe_id', $classe->id)
                ->where('seance_numero', $data['seance_numero'])
                ->orderByDesc('bis_rang')
                ->lockForUpdate()
                ->get();

            if ($seance->isEmpty()) {
                $message = "La séance {$data['seance_numero']} n'existe pas dans cette classe : un bis doit se rattacher à l'une de ses séances.";
                throw RegleMetierException::invalide($message, ['seance_numero' => [$message]]);
            }

            $this->assertDansLaPeriode($classe, $data['date']);

            $nbActives = CourseSession::where('classe_id', $classe->id)
                ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
                ->count();

            if ($nbActives + 1 > ClasseSessionGenerator::NB_SEANCES && ! $confirmerDepassement) {
                $nb = $nbActives + 1;
                throw RegleMetierException::conflit(
                    "Cette classe passera à {$nb} sessions",
                    ['nb_sessions' => $nb]
                );
            }

            // Dernière session annulée de la séance qui n'a pas encore de remplaçante.
            $dejaRemplacees = $seance->pluck('remplace_session_id')->filter()->all();
            $remplacee = $seance->first(
                fn (CourseSession $s) => $s->isAnnulee() && ! in_array($s->id, $dejaRemplacees, true)
            );

            return CourseSession::create([
                'classe_id' => $classe->id,
                'seance_numero' => $data['seance_numero'],
                'bis_rang' => $seance->max('bis_rang') + 1,
                'remplace_session_id' => $remplacee?->id,
                'date' => $data['date'],
                'heure_debut' => $data['heure_debut'] ?? $classe->heure_debut,
                'heure_fin' => $data['heure_fin'] ?? $classe->heure_fin,
                'lieu' => array_key_exists('lieu', $data) ? $data['lieu'] : $classe->lieu,
                'statut' => CourseSession::STATUT_PLANIFIEE,
            ])->refresh();
        });
    }

    /** Aucune session ne peut être créée ni déplacée après la fin de la période (RG-2). */
    private function assertDansLaPeriode(Classe $classe, string $date): void
    {
        $periode = $classe->periode;

        if ($date > $periode->date_fin->toDateString()) {
            $message = "Cette date est après la fin de la période {$periode->numero}";
            throw RegleMetierException::invalide($message, ['date' => [$message]]);
        }
    }
}
