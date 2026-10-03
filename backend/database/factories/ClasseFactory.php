<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\Cours;
use App\Models\Periode;
use App\Services\ClasseSessionGenerator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Crée une classe avec, par défaut, UNE période de classe (P1, cours factory, démarrage le 2026-10-07) et ses
 * 14 sessions. États : `deuxPeriodes()` (P1 + P2), `avecPeriodes([...])` (spécifications libres),
 * `sansPeriode()` (classe nue, sans période ni session). L'année et ses périodes sont créées si absentes.
 *
 * @extends Factory<Classe>
 */
class ClasseFactory extends Factory
{
    /** @var list<array{numero: int, cours_id?: int, date_premiere_session?: string}> */
    public array $periodesSpec = [['numero' => 1]];

    public function definition(): array
    {
        return [
            'annee_scolaire_id' => fn () => AnneeScolaire::factory()->create()->id,
            'jour_semaine' => 3,
            'heure_debut' => '14:00',
            'heure_fin' => '17:00',
            'lieu' => 'Salle A',
            'statut' => Classe::STATUT_ACTIVE,
        ];
    }

    public function sansPeriode(): static
    {
        return $this->avecPeriodes([]);
    }

    public function deuxPeriodes(): static
    {
        return $this->avecPeriodes([['numero' => 1], ['numero' => 2]]);
    }

    /** @param list<array{numero: int, cours_id?: int, date_premiere_session?: string}> $specs */
    public function avecPeriodes(array $specs): static
    {
        $clone = $this->newInstance();
        $clone->periodesSpec = $specs;

        return $clone;
    }

    protected function newInstance(array $arguments = []): static
    {
        $nouvelle = parent::newInstance($arguments);
        $nouvelle->periodesSpec = $this->periodesSpec;

        return $nouvelle;
    }

    /** Appelé sur l'instance finale de la factory (donc avec ses états) : crée les périodes et leurs sessions. */
    protected function callAfterCreating(Collection $instances, ?Model $parent = null): void
    {
        parent::callAfterCreating($instances, $parent);

        $instances->each(function (Classe $classe) {
            $generateur = app(ClasseSessionGenerator::class);

            foreach ($this->periodesSpec as $spec) {
                $periode = $this->periode($classe->annee_scolaire_id, $spec['numero']);
                $date = $spec['date_premiere_session'] ?? ($spec['numero'] === 1 ? '2026-10-07' : '2027-03-03');
                $plan = $generateur->plan($classe->annee_scolaire_id, $periode, $classe->jour_semaine, $date);

                $classePeriode = $classe->periodes()->create([
                    'periode_id' => $periode->id,
                    'cours_id' => $spec['cours_id'] ?? Cours::factory()->create()->id,
                    'date_premiere_session' => $plan['date_premiere_session'],
                    'statut' => ClassePeriode::STATUT_ACTIVE,
                ]);
                $generateur->generateSessions($classePeriode, $plan);
            }
        });
    }

    private function periode(int $anneeId, int $numero): Periode
    {
        return Periode::firstOrCreate(
            ['annee_scolaire_id' => $anneeId, 'numero' => $numero],
            $numero === 1
                ? ['date_debut' => '2026-08-24', 'date_fin' => '2027-02-19']
                : ['date_debut' => '2027-02-22', 'date_fin' => '2027-07-02']
        );
    }
}
