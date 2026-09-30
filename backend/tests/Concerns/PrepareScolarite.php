<?php

namespace Tests\Concerns;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;
use App\Services\ClasseSessionGenerator;
use App\Services\FwbCalendarImporter;
use Laravel\Sanctum\Sanctum;

/** Données communes aux tests CLS-01 T1 : année 2026-2027 (P1 24/08→19/02, P2 22/02→02/07). */
trait PrepareScolarite
{
    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->{$role}()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function annee(): AnneeScolaire
    {
        return AnneeScolaire::factory()->avecPeriodes()->create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-08-24',
            'date_fin' => '2027-07-02',
        ])->load('periodes');
    }

    protected function importFwb(AnneeScolaire $annee): array
    {
        return app(FwbCalendarImporter::class)->importer($annee);
    }

    /** @param array<string, mixed> $attrs surcharge du payload de création (mercredi 14h–17h, P1) */
    protected function donneesClasse(AnneeScolaire $annee, array $attrs = []): array
    {
        return $attrs + [
            'cours_id' => Cours::factory()->create()->id,
            'annee_scolaire_id' => $annee->id,
            'periode_id' => $annee->periodes->firstWhere('numero', 1)->id,
            'jour_semaine' => 3,
            'heure_debut' => '14:00',
            'heure_fin' => '17:00',
            'lieu' => 'Salle A',
            'date_premiere_session' => '2026-10-07',
        ];
    }

    protected function classeAvecSessions(AnneeScolaire $annee, array $attrs = []): Classe
    {
        return app(ClasseSessionGenerator::class)->create($this->donneesClasse($annee, $attrs));
    }
}
