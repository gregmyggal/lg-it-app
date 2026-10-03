<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Http\Resources\AnneeScolaireResource;
use App\Models\AnneeScolaire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Cycle de vie d'une année scolaire (CLS-01, CLS-03) : création, modification versionnée, archivage, suppression. */
class AnneeScolaireService
{
    /** Crée l'année et ses 2 périodes (transaction). */
    public function creer(array $data, ?int $userId = null): AnneeScolaire
    {
        return DB::transaction(function () use ($data, $userId) {
            $annee = AnneeScolaire::create([
                'libelle' => $data['libelle'],
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'],
                'statut' => $data['statut'] ?? AnneeScolaire::STATUT_ACTIVE,
                'updated_by' => $userId,
            ]);

            foreach ($data['periodes'] as $periode) {
                $annee->periodes()->create($periode);
            }

            return $annee->load('periodes');
        });
    }

    /**
     * Modifie l'année (CLS-03 RG-3 : aucun blocage sur les séances existantes, elles ressortent `hors_periode`).
     * Sans dates d'année explicites, l'année suit P1 début → P2 fin (RG-1).
     *
     * @throws RegleMetierException 409 `annee_archivee` (dates verrouillées) ou `modification_concurrente` (version périmée)
     */
    public function modifier(AnneeScolaire $annee, array $data, ?int $userId = null): AnneeScolaire
    {
        return DB::transaction(function () use ($annee, $data, $userId) {
            $annee = AnneeScolaire::lockForUpdate()->findOrFail($annee->id);

            $this->refuserSiArchivee($annee, $data);
            if (array_key_exists('version', $data)) {
                $this->verifierVersion($annee, $data['version']);
            }

            $champs = collect($data)->except('periodes', 'version')->all();
            if (! empty($data['periodes']) && ! isset($champs['date_debut']) && ! isset($champs['date_fin'])) {
                $champs['date_debut'] = collect($data['periodes'])->min('date_debut');
                $champs['date_fin'] = collect($data['periodes'])->max('date_fin');
            }

            $annee->fill($champs);
            $annee->updated_by = $userId;
            $annee->updated_at = $this->prochaineVersion($annee);
            $annee->save();

            foreach ($data['periodes'] ?? [] as $p) {
                $annee->periodes()->where('numero', $p['numero'])->firstOrFail()
                    ->update(['date_debut' => $p['date_debut'], 'date_fin' => $p['date_fin']]);
            }

            return $annee->load('periodes');
        });
    }

    /** Archive l'année (idempotent) : n'affecte ni classes, ni séances, ni timesheets. */
    public function archiver(AnneeScolaire $annee, ?int $userId = null): AnneeScolaire
    {
        return $this->changerStatut($annee, AnneeScolaire::STATUT_ARCHIVEE, $userId);
    }

    /** Réactive une année archivée (idempotent : une année non archivée est renvoyée telle quelle). */
    public function reactiver(AnneeScolaire $annee, ?int $userId = null): AnneeScolaire
    {
        return $annee->isArchivee() ? $this->changerStatut($annee, AnneeScolaire::STATUT_ACTIVE, $userId) : $annee->load('periodes');
    }

    /** @throws RegleMetierException 409 `annee_non_supprimable` si l'année a des classes (archiver à la place) */
    public function supprimer(AnneeScolaire $annee): void
    {
        $classes = $annee->classes()->count();
        if ($classes > 0) {
            $sessions = $annee->sessions()->count();

            throw RegleMetierException::conflit(self::raisonNonSupprimable($classes, $sessions), [
                'code' => 'annee_non_supprimable',
                'classes_count' => $classes,
                'sessions_count' => $sessions,
            ]);
        }

        $annee->delete(); // périodes et calendrier en cascade
    }

    public static function raisonNonSupprimable(int $classes, int $sessions): ?string
    {
        if ($classes === 0) {
            return null;
        }

        return 'Contient '.$classes.' classe'.($classes > 1 ? 's' : '').' ('.$sessions.' séance'.($sessions > 1 ? 's' : '').') : archivez-la plutôt.';
    }

    /**
     * Les dates (et le libellé) d'une année archivée sont verrouillées ; seul le statut peut changer.
     *
     * @throws RegleMetierException 409 `annee_archivee`
     */
    public function refuserSiArchivee(AnneeScolaire $annee, array $data): void
    {
        if ($annee->isArchivee() && array_intersect(['libelle', 'date_debut', 'date_fin', 'periodes'], array_keys($data))) {
            throw RegleMetierException::conflit('Réactivez l\'année pour modifier ses dates.', ['code' => 'annee_archivee']);
        }
    }

    private function changerStatut(AnneeScolaire $annee, string $statut, ?int $userId): AnneeScolaire
    {
        return DB::transaction(function () use ($annee, $statut, $userId) {
            $annee = AnneeScolaire::lockForUpdate()->findOrFail($annee->id);
            if ($annee->statut !== $statut) {
                $annee->statut = $statut;
                $annee->updated_by = $userId;
                $annee->updated_at = $this->prochaineVersion($annee);
                $annee->save();
            }

            return $annee->load('periodes');
        });
    }

    /** `updated_at` a une précision d'une seconde : on garantit qu'il avance à chaque écriture (version optimiste). */
    private function prochaineVersion(AnneeScolaire $annee): Carbon
    {
        $maintenant = now();
        $precedente = $annee->getOriginal('updated_at');

        return $precedente && $maintenant->timestamp <= Carbon::parse($precedente)->timestamp
            ? Carbon::parse($precedente)->addSecond()
            : $maintenant;
    }

    /** @throws RegleMetierException 409 `modification_concurrente` si la version ne correspond plus */
    private function verifierVersion(AnneeScolaire $annee, mixed $version): void
    {
        try {
            $lue = Carbon::parse((string) $version)->timestamp;
        } catch (\Throwable) {
            throw RegleMetierException::invalide('La version fournie est invalide.', ['version' => ['La version fournie est invalide.']]);
        }

        if ($lue === $annee->updated_at?->timestamp) {
            return;
        }

        $auteur = $annee->updatedBy;
        $heure = $annee->updated_at?->copy()->setTimezone('Europe/Brussels')->format('H:i');
        $courante = AnneeScolaire::avecCompteurs()->findOrFail($annee->id);

        throw RegleMetierException::conflit(
            'Ces dates ont été modifiées par '.($auteur?->name ?? 'un autre utilisateur')." à {$heure}.",
            [
                'code' => 'modification_concurrente',
                'modifie_par' => $auteur ? ['id' => $auteur->id, 'name' => $auteur->name] : null,
                'modifie_a' => $annee->updated_at?->copy()->setTimezone('Europe/Brussels')->toIso8601String(),
                'annee' => (new AnneeScolaireResource($courante))->resolve(),
            ]
        );
    }
}
