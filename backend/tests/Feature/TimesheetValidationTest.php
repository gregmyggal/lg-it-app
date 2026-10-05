<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Vue directeur par session, validation en lot avec lissage (AC-26, AC-32), blocage annuler/déplacer (AC-33). */
class TimesheetValidationTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Classe $classe;

    private Professeur $alice;

    private Professeur $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->classe = $this->classeAvecSessions($this->annee());
        $this->alice = Professeur::factory()->create();
        $this->bob = Professeur::factory()->create();
        $service = app(ClasseProfesseurAssignmentService::class);
        $service->assigner($this->classe, $this->alice);
        $service->assigner($this->classe, $this->bob);
        ProfesseurTarif::create(['professeur_id' => $this->alice->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);
        Carbon::setTestNow($this->seance(2)->date->copy()->setTime(20, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(int $n): CourseSession
    {
        return $this->classe->sessions()->where('seance_numero', $n)->where('bis_rang', 0)->firstOrFail();
    }

    /** Saisie soumise de la séance donnée pour le professeur. */
    private function soumise(Professeur $p, int $seance, float $heures = 3.0): Timesheet
    {
        $s = $this->seance($seance);

        return Timesheet::create([
            'professeur_id' => $p->id, 'course_session_id' => $s->id, 'cours_id' => $this->classe->periodes()->first()->cours_id,
            'date_prestation' => $s->date->toDateString(), 'nombre_heures' => $heures, 'type_activite' => 'animation',
            'statut_validation' => Timesheet::STATUT_SOUMIS,
        ]);
    }

    public function test_sessions_sans_heures_liste_les_professeurs_attendus_sans_saisie_ac26(): void
    {
        $this->soumise($this->alice, 1); // Alice a encodé la séance 1 ; Bob non
        $this->actingAsRole('directeur');

        $r = $this->getJson('/api/timesheets/sessions-sans-heures')->assertOk()->json('data');
        $items = collect($r)->keyBy('libelle');

        $this->assertSame([$this->bob->id], collect($items['Séance 1']['professeurs_sans_heures'])->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$this->alice->id, $this->bob->id], collect($items['Séance 2']['professeurs_sans_heures'])->pluck('id')->all());
        $this->assertArrayNotHasKey('Séance 3', $items->all()); // pas encore passée
        $this->assertGreaterThanOrEqual(0, $items['Séance 1']['jours_de_retard']);
        $this->getJson('/api/timesheets/sessions-sans-heures?classe_id=999999')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_session_annulee_non_listee_et_professeur_remplace_non_attendu(): void
    {
        $this->seance(1)->update(['statut' => CourseSession::STATUT_ANNULEE]);
        $this->actingAsRole('admin');

        $libelles = collect($this->getJson('/api/timesheets/sessions-sans-heures')->json('data'))->pluck('libelle');

        $this->assertFalse($libelles->contains('Séance 1'));
    }

    public function test_sessions_sans_heures_reservees_au_staff(): void
    {
        Sanctum::actingAs($this->alice->user);

        $this->getJson('/api/timesheets/sessions-sans-heures')->assertForbidden();
    }

    public function test_valider_en_lot_avec_lissage_applique_et_valide_en_une_transaction_ac32(): void
    {
        $alice = $this->soumise($this->alice, 1, 3.0);   // 3 h × 20 € = 60 € > 44,02 €
        $bob = $this->soumise($this->bob, 1, 2.0);
        $this->actingAsRole('directeur');

        $r = $this->postJson('/api/timesheets/valider-lot', [
            'ids' => [$alice->id, $bob->id],
            'lissages' => [['timesheet_id' => $alice->id, 'date_to' => '2026-10-14', 'montant_to_move' => 15.98]],
        ])->assertOk();

        $this->assertSame(2, $r->json('validees'));
        $this->assertSame(1, $r->json('lissages_appliques'));
        $alice->refresh();
        $this->assertSame('confirme', $alice->statut_validation);
        $this->assertTrue((bool) $alice->lissage_applique);
        $this->assertEquals(2.2, $alice->nombre_heures); // 3 h − 15,98 € / 20 € = 3 − 0,8
        $this->assertSame('confirme', $bob->fresh()->statut_validation);
        $this->assertNotNull($alice->validated_at);
        $deplacee = Timesheet::where('commentaire', 'like', 'Lissé depuis%')->firstOrFail();
        $this->assertEquals(0.8, $deplacee->nombre_heures);
        $this->assertSame('2026-10-14', $deplacee->date_prestation->toDateString());
    }

    public function test_lot_sans_lissage_n_exclut_aucune_saisie_en_depassement(): void
    {
        $alice = $this->soumise($this->alice, 1, 3.0); // dépasse le plafond mais « valider sans lisser » reste possible
        $this->actingAsRole('admin');

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$alice->id]])->assertOk()->assertJsonPath('validees', 1);

        $this->assertSame('confirme', $alice->fresh()->statut_validation);
    }

    public function test_lot_atomique_une_saisie_non_validable_bloque_tout(): void
    {
        $a = $this->soumise($this->alice, 1);
        $b = $this->soumise($this->bob, 1);
        $brouillon = $this->soumise($this->bob, 2);
        $brouillon->update(['statut_validation' => 'brouillon']);
        $this->actingAsRole('directeur');

        $r = $this->postJson('/api/timesheets/valider-lot', ['ids' => [$a->id, $b->id, $brouillon->id]])->assertStatus(422);

        $this->assertSame($brouillon->id, $r->json('invalides.0.id'));
        $this->assertSame('soumis', $a->fresh()->statut_validation);
        $this->assertSame('soumis', $b->fresh()->statut_validation);
    }

    public function test_lissage_impossible_annule_toute_la_transaction(): void
    {
        $a = $this->soumise($this->alice, 1, 1.0); // 1 h : on ne peut pas déplacer 44,02 € (2,2 h)
        $b = $this->soumise($this->bob, 1);
        $this->actingAsRole('directeur');

        $this->postJson('/api/timesheets/valider-lot', [
            'ids' => [$b->id, $a->id],
            'lissages' => [['timesheet_id' => $a->id, 'date_to' => '2026-10-14', 'montant_to_move' => 44.02]],
        ])->assertStatus(422);

        $this->assertSame('soumis', $a->fresh()->statut_validation);
        $this->assertSame('soumis', $b->fresh()->statut_validation);
        $this->assertSame(2, Timesheet::count());
    }

    public function test_un_professeur_ne_peut_pas_valider_en_lot(): void
    {
        $a = $this->soumise($this->alice, 1);
        Sanctum::actingAs($this->alice->user);

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$a->id]])->assertStatus(422); // non validable (policy)
        $this->assertSame('soumis', $a->fresh()->statut_validation);
    }

    public function test_session_avec_heures_ne_peut_etre_ni_annulee_ni_deplacee_ac33(): void
    {
        $this->soumise($this->alice, 5);
        $s = $this->seance(5);
        $this->actingAsRole('directeur');

        $this->postJson("/api/sessions/{$s->id}/cancel", ['motif_annulation' => 'Test'])->assertStatus(409);
        $this->putJson("/api/sessions/{$s->id}", ['date' => $s->date->copy()->addDay()->toDateString()])->assertStatus(409);
        $this->assertSame('planifiee', $s->fresh()->statut);
    }

    public function test_le_workflow_existant_fonctionne_avec_les_statuts_normalises(): void
    {
        $a = $this->soumise($this->alice, 1, 2.0);
        $this->actingAsRole('directeur');

        $this->postJson("/api/timesheets/{$a->id}/validate")->assertOk();
        $this->assertSame('confirme', $a->fresh()->statut_validation);
        $this->assertTrue($a->fresh()->isLocked());

        Sanctum::actingAs($this->alice->user);
        $this->getJson('/api/timesheets')->assertOk()->assertJsonPath('0.statut_validation', 'confirme');
    }
}
