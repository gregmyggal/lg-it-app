<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\TimesheetVersion;
use App\Services\TimesheetVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-04 T3 : le détail des versions est effacé 24 mois après la fin du mois ; totaux et empreinte restent. */
class TimesheetVersionRetentionTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Professeur $prof;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2028-11-15 10:00:00');
        $this->prof = Professeur::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function version(int $annee, int $mois, string $type = 'soumise'): TimesheetVersion
    {
        return TimesheetVersion::create([
            'professeur_id' => $this->prof->id, 'annee' => $annee, 'mois' => $mois, 'type' => $type, 'numero' => 1,
            'statut' => 'active', 'actif_cle' => TimesheetVersion::cleActive($this->prof->id, $annee, $mois, $type),
            'lignes' => '[{"id":1}]', 'total_heures' => 5, 'total_eur' => 100, 'content_hash' => hash('sha256', '[{"id":1}]'),
        ]);
    }

    public function test_seules_les_versions_de_plus_de_24_mois_apres_la_fin_du_mois_sont_purgees(): void
    {
        // Aujourd'hui : 15/11/2028 ; limite = 15/11/2026. Le mois d'octobre 2026 s'est terminé le 31/10/2026 (> 24 mois).
        $ancienne = $this->version(2026, 10);
        $limite = $this->version(2026, 11, 'validee'); // se termine le 30/11/2026 : pas encore 24 mois
        $recente = $this->version(2028, 9);

        $n = app(TimesheetVersionService::class)->purger();

        $this->assertSame(1, $n);
        $this->assertNull($ancienne->fresh()->lignes);
        $this->assertNotNull($ancienne->fresh()->purge_at);
        $this->assertNotNull($limite->fresh()->lignes);
        $this->assertNotNull($recente->fresh()->lignes);
    }

    public function test_totaux_empreinte_dates_et_numero_sont_conserves(): void
    {
        $v = $this->version(2026, 10);
        $hash = $v->content_hash;

        app(TimesheetVersionService::class)->purger();

        $v->refresh();
        $this->assertSame($hash, $v->content_hash);
        $this->assertEquals(100.0, $v->total_eur);
        $this->assertEquals(5.0, $v->total_heures);
        $this->assertSame(1, $v->numero);
        $this->assertSame('active', $v->statut);
    }

    public function test_la_purge_est_idempotente_et_la_simulation_n_efface_rien(): void
    {
        $v = $this->version(2026, 10);
        $service = app(TimesheetVersionService::class);

        $this->assertSame(1, $service->purger(simulation: true));
        $this->assertNotNull($v->fresh()->lignes);
        $this->assertSame(1, $service->purger());
        $this->assertSame(0, $service->purger());
    }

    public function test_la_commande_planifiee_purge(): void
    {
        $v = $this->version(2026, 10);

        $this->artisan('timesheets:purger-versions')->expectsOutput('1 version(s) purgée(s).')->assertSuccessful();

        $this->assertNull($v->fresh()->lignes);
    }

    public function test_l_immuabilite_reste_la_regle_hors_purge(): void
    {
        $v = $this->version(2028, 9);

        $this->expectException(LogicException::class);
        $v->update(['lignes' => null]);
    }
}
