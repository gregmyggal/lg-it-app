<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Chaînage des périodes de tarif d'un professeur : jamais deux tarifs le même jour de début,
 * et le tarif précédent est arrêté automatiquement à la date de début du nouveau (date_fin exclusive,
 * cf. ProfesseurTarif::effectiveAt). Un tarif sans fin est borné au début du tarif suivant.
 */
class ProfesseurTarifService
{
    /** @param array{tarif_horaire_eur: numeric, date_debut: string, date_fin?: ?string} $data */
    public function creer(Professeur $professeur, array $data): ProfesseurTarif
    {
        return DB::transaction(function () use ($professeur, $data) {
            $tarif = new ProfesseurTarif(['professeur_id' => $professeur->id]);

            return $this->enregistrer($tarif, $data);
        });
    }

    /** @param array<string, mixed> $data */
    public function modifier(ProfesseurTarif $tarif, array $data): ProfesseurTarif
    {
        return DB::transaction(fn () => $this->enregistrer($tarif, $data));
    }

    /** Supprime un tarif ; le précédent, arrêté à son début, reprend la fin du tarif supprimé (pas de trou). */
    public function supprimer(ProfesseurTarif $tarif): void
    {
        DB::transaction(function () use ($tarif) {
            ProfesseurTarif::query()
                ->where('professeur_id', $tarif->professeur_id)
                ->whereDate('date_fin', $tarif->date_debut->toDateString())
                ->update(['date_fin' => $tarif->date_fin?->toDateString()]);

            $tarif->delete();
        });
    }

    /** @param array<string, mixed> $data */
    private function enregistrer(ProfesseurTarif $tarif, array $data): ProfesseurTarif
    {
        $debut = Carbon::parse($data['date_debut'] ?? $tarif->date_debut)->toDateString();
        $fin = array_key_exists('date_fin', $data)
            ? ($data['date_fin'] ? Carbon::parse($data['date_fin'])->toDateString() : null)
            : $tarif->date_fin?->toDateString();

        if ($fin !== null && $fin <= $debut) {
            throw RegleMetierException::invalide('La date de fin doit être postérieure à la date de début.', ['date_fin' => ['La date de fin doit être postérieure à la date de début.']]);
        }

        $autres = ProfesseurTarif::query()
            ->where('professeur_id', $tarif->professeur_id)
            ->when($tarif->exists, fn ($q) => $q->where('id', '!=', $tarif->id));

        if ((clone $autres)->whereDate('date_debut', $debut)->exists()) {
            throw RegleMetierException::invalide('Un tarif commence déjà à cette date.', ['date_debut' => ['Un tarif commence déjà à cette date.']]);
        }

        // Tarif suivant : on borne la fin (ou on la raccourcit) à son début pour éviter tout chevauchement.
        $suivant = (clone $autres)->whereDate('date_debut', '>', $debut)->orderBy('date_debut')->first();
        if ($suivant && ($fin === null || $fin > $suivant->date_debut->toDateString())) {
            $fin = $suivant->date_debut->toDateString();
        }

        // Tarif précédent encore en cours à cette date : arrêté à la date de début du nouveau.
        (clone $autres)->whereDate('date_debut', '<', $debut)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>', $debut))
            ->update(['date_fin' => $debut]);

        $tarif->fill([
            'tarif_horaire_eur' => $data['tarif_horaire_eur'] ?? $tarif->tarif_horaire_eur,
            'date_debut' => $debut,
            'date_fin' => $fin,
        ])->save();

        return $tarif->refresh();
    }
}
