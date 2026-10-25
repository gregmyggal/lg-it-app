<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Les colonnes « date » sortent en `YYYY-MM-DD` quel que soit APP_TIMEZONE : en production (Europe/Brussels), un cast
 * `date` nu sérialisait minuit local en UTC (`2026-10-25T23:00:00Z` pour le 26/10) et l'écran affichait la veille.
 */
class DatesSansFuseauTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function fuseaux(): array
    {
        return [
            'UTC' => ['UTC', '2026-10-26'],
            'Bruxelles, après le passage à l\'heure d\'hiver' => ['Europe/Brussels', '2026-10-26'],
            'Bruxelles, en heure d\'été' => ['Europe/Brussels', '2026-10-04'],
        ];
    }

    /** @dataProvider fuseaux */
    public function test_date_prestation_sort_sans_heure_ni_fuseau(string $fuseau, string $date): void
    {
        config(['app.timezone' => $fuseau]);
        date_default_timezone_set($fuseau);

        $prof = Professeur::factory()->create();
        $ligne = Timesheet::create([
            'professeur_id' => $prof->id,
            'date_prestation' => $date,
            'nombre_heures' => 5,
            'type_activite' => 'cours',
        ]);
        Sanctum::actingAs($prof->user);

        $this->assertSame($date, $ligne->fresh()->toArray()['date_prestation']);
        $this->assertSame($date, $this->getJson("/api/timesheets?date_from={$date}&date_to={$date}")->assertOk()->json('0.date_prestation'));
    }

    /** @dataProvider fuseaux */
    public function test_dates_du_professeur_sortent_sans_heure(string $fuseau, string $date): void
    {
        config(['app.timezone' => $fuseau]);
        date_default_timezone_set($fuseau);

        $prof = Professeur::factory()->create(['date_entree' => $date]);

        $this->assertSame($date, $prof->fresh()->toArray()['date_entree']);
    }
}
