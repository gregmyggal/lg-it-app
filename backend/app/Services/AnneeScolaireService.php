<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\AnneeScolaire;
use App\Models\CourseSession;
use Illuminate\Support\Facades\DB;

class AnneeScolaireService
{
    /** Crée l'année et ses 2 périodes (transaction). */
    public function creer(array $data): AnneeScolaire
    {
        return DB::transaction(function () use ($data) {
            $annee = AnneeScolaire::create([
                'libelle' => $data['libelle'],
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'],
                'statut' => $data['statut'] ?? AnneeScolaire::STATUT_ACTIVE,
            ]);

            foreach ($data['periodes'] as $periode) {
                $annee->periodes()->create($periode);
            }

            return $annee->load('periodes');
        });
    }

    /**
     * @throws RegleMetierException 422 si une session existante dépasserait la nouvelle fin de période
     */
    public function modifier(AnneeScolaire $annee, array $data): AnneeScolaire
    {
        return DB::transaction(function () use ($annee, $data) {
            $annee->update(collect($data)->except('periodes')->all());

            foreach ($data['periodes'] ?? [] as $p) {
                $periode = $annee->periodes()->where('numero', $p['numero'])->firstOrFail();

                $depasse = CourseSession::query()
                    ->whereHas('classe', fn ($q) => $q->where('periode_id', $periode->id))
                    ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
                    ->where('date', '>', $p['date_fin'])
                    ->exists();

                if ($depasse) {
                    $message = "Des sessions dépassent la nouvelle fin de la période {$periode->numero}.";
                    throw RegleMetierException::invalide($message, ['periodes' => [$message]]);
                }

                $periode->update(['date_debut' => $p['date_debut'], 'date_fin' => $p['date_fin']]);
            }

            return $annee->refresh()->load('periodes');
        });
    }

    /** @throws RegleMetierException 409 si l'année a des classes (archiver à la place) */
    public function supprimer(AnneeScolaire $annee): void
    {
        if ($annee->classes()->exists()) {
            throw RegleMetierException::conflit(
                'Cette année scolaire contient des classes : archivez-la plutôt que de la supprimer.'
            );
        }

        $annee->delete(); // périodes et calendrier en cascade
    }
}
