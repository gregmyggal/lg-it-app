<?php

namespace Tests\Feature;

use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\Timesheet;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\TimesheetParametreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** DEF-01/DEF-02 : temps de préparation (défaut global par année, surcharge par cours), durée de séance par défaut et heures défrayées par séance. */
class HeuresDefrayablesTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private function params(array $extra = []): array
    {
        return ['plafond_journalier_eur' => 44.02, 'plafond_annuel_eur' => 1760.83] + $extra;
    }

    public function test_valeurs_par_defaut_30_min_de_preparation_et_seance_de_1h30(): void
    {
        $this->actingAsRole('directeur');

        $this->getJson('/api/timesheet-parametres/2026')->assertOk()
            ->assertJsonPath('data.temps_preparation', 0.5)
            ->assertJsonPath('data.duree_seance_defaut', 1.5);
    }

    public function test_la_direction_modifie_les_deux_valeurs_et_l_historique_garde_avant_apres(): void
    {
        $this->actingAsRole('admin');

        $this->putJson('/api/timesheet-parametres/2026', $this->params(['temps_preparation' => 1, 'duree_seance_defaut' => 1.75]))
            ->assertOk()->assertJsonPath('data.temps_preparation', 1);

        $h = $this->getJson('/api/timesheet-parametres/2026')->json('historique.0');
        $this->assertEquals(0.5, $h['temps_preparation_avant']);
        $this->assertEquals(1, $h['temps_preparation_apres']);
        $this->assertEquals(1.5, $h['duree_seance_avant']);
        $this->assertEquals(1.75, $h['duree_seance_apres']);
    }

    public function test_sans_valeur_fournie_le_temps_de_preparation_est_conserve(): void
    {
        $this->actingAsRole('directeur');
        $this->putJson('/api/timesheet-parametres/2026', $this->params(['temps_preparation' => 1]))->assertOk();
        $this->putJson('/api/timesheet-parametres/2026', $this->params(['plafond_journalier_eur' => 50]))->assertOk()
            ->assertJsonPath('data.temps_preparation', 1);
    }

    public function test_bornes_et_pas_de_quinze_minutes(): void
    {
        $this->actingAsRole('directeur');

        foreach ([-0.25, 4.25, 0.1, 'abc'] as $mauvaise) {
            $this->putJson('/api/timesheet-parametres/2026', $this->params(['temps_preparation' => $mauvaise]))
                ->assertStatus(422)->assertJsonValidationErrors('temps_preparation');
        }
        $this->putJson('/api/timesheet-parametres/2026', $this->params(['duree_seance_defaut' => 0.3]))
            ->assertStatus(422)->assertJsonValidationErrors('duree_seance_defaut');
        foreach ([0, 0.25, 1.25, 4] as $bonne) {
            $this->putJson('/api/timesheet-parametres/2026', $this->params(['temps_preparation' => $bonne]))->assertOk();
        }
    }

    public function test_un_professeur_ne_peut_pas_modifier(): void
    {
        $this->actingAsRole('professeur');
        $this->putJson('/api/timesheet-parametres/2026', $this->params(['temps_preparation' => 1]))->assertForbidden();
    }

    public function test_une_annee_sans_parametre_herite_du_temps_de_preparation_de_l_annee_precedente(): void
    {
        $service = app(TimesheetParametreService::class);
        $this->actingAsRole('admin');
        $this->putJson('/api/timesheet-parametres/2026', $this->params(['temps_preparation' => 0.75]))->assertOk();

        $this->assertSame(0.75, $service->tempsPreparation(2028));
        $this->assertSame(0.5, $service->tempsPreparation(2019)); // avant tout paramétrage
    }

    public function test_les_heures_proposees_sont_la_duree_de_la_seance_plus_la_preparation(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        $classe = $this->classeAvecSessions($this->annee());
        $alice = Professeur::factory()->create();
        app(ClasseProfesseurAssignmentService::class)->assigner($classe, $alice);
        $session = CourseSession::where('classe_id', $classe->id)->orderBy('date')->first();
        Carbon::setTestNow($session->date->copy()->setTime(20, 0));
        $this->actingAsRole('admin');
        $this->putJson('/api/timesheet-parametres/'.$session->date->year, $this->params(['temps_preparation' => 1]))->assertOk();

        Sanctum::actingAs($alice->user);
        $r = $this->getJson("/api/mes-classes/{$classe->id}/sessions")->assertOk();
        $this->assertEquals(4, $r->json('data.0.duree_par_defaut')); // séance de 3 h + 1 h de préparation

        // Pas d'effet rétroactif : une saisie déjà encodée garde ses heures quand le défaut change.
        $t = $this->postJson('/api/timesheets', ['course_session_id' => $session->id])->assertCreated();
        $this->assertEquals(4, $t->json('nombre_heures'));
        $this->actingAsRole('directeur');
        $this->putJson('/api/timesheet-parametres/'.$session->date->year, $this->params(['temps_preparation' => 2]))->assertOk();
        $this->assertEquals(4, (float) Timesheet::firstOrFail()->nombre_heures);
    }

    // ---- DEF-01 T2 : surcharge par cours ----

    private function sessionEncodable(): array
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        $classe = $this->classeAvecSessions($this->annee());
        $alice = Professeur::factory()->create();
        app(ClasseProfesseurAssignmentService::class)->assigner($classe, $alice);
        $session = CourseSession::where('classe_id', $classe->id)->orderBy('date')->first();
        Carbon::setTestNow($session->date->copy()->setTime(20, 0));

        return [$classe, $alice, $session];
    }

    private function heuresProposees(Professeur $alice, $classe): float
    {
        Sanctum::actingAs($alice->user);

        return (float) $this->getJson("/api/mes-classes/{$classe->id}/sessions")->assertOk()->json('data.0.duree_par_defaut');
    }

    public function test_le_cours_surcharge_le_defaut_global_et_null_revient_au_defaut(): void
    {
        [$classe, $alice] = $this->sessionEncodable();
        $this->actingAsRole('directeur');

        $this->assertEquals(3.5, $this->heuresProposees($alice, $classe)); // 3 h + défaut global 30 min

        $this->actingAsRole('directeur');
        $this->putJson("/api/cours/{$classe->periodes()->first()->cours_id}", ['temps_preparation' => 1])->assertOk()->assertJsonPath('temps_preparation', 1);
        $this->assertEquals(4, $this->heuresProposees($alice, $classe));

        $this->actingAsRole('admin');
        $this->putJson("/api/cours/{$classe->periodes()->first()->cours_id}", ['temps_preparation' => null])->assertOk()->assertJsonPath('temps_preparation', null);
        $this->assertEquals(3.5, $this->heuresProposees($alice, $classe)); // retour au défaut
    }

    public function test_le_defaut_global_ne_joue_pas_quand_le_cours_a_sa_propre_valeur(): void
    {
        [$classe, $alice, $session] = $this->sessionEncodable();
        $this->actingAsRole('admin');
        $this->putJson("/api/cours/{$classe->periodes()->first()->cours_id}", ['temps_preparation' => 0.25])->assertOk();
        $this->putJson('/api/timesheet-parametres/'.$session->date->year, $this->params(['temps_preparation' => 2]))->assertOk();

        $this->assertEquals(3.25, $this->heuresProposees($alice, $classe));
    }

    public function test_changer_la_valeur_du_cours_ne_modifie_pas_une_saisie_existante(): void
    {
        [$classe, $alice, $session] = $this->sessionEncodable();
        Sanctum::actingAs($alice->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $session->id])->assertCreated();

        $this->actingAsRole('directeur');
        $this->putJson("/api/cours/{$classe->periodes()->first()->cours_id}", ['temps_preparation' => 2])->assertOk();

        $this->assertEquals(3.5, (float) Timesheet::firstOrFail()->nombre_heures);
    }

    public function test_validation_et_droits_de_la_valeur_du_cours(): void
    {
        [$classe] = $this->sessionEncodable();
        $this->actingAsRole('directeur');

        foreach ([-0.25, 5, 0.1, 'abc'] as $mauvaise) {
            $this->putJson("/api/cours/{$classe->periodes()->first()->cours_id}", ['temps_preparation' => $mauvaise])
                ->assertStatus(422)->assertJsonValidationErrors('temps_preparation');
        }

        $this->actingAsRole('professeur');
        $this->putJson("/api/cours/{$classe->periodes()->first()->cours_id}", ['temps_preparation' => 1])->assertForbidden();
    }

    // ---- DEF-01 T3 : affichage (création de classe, détail, encodage) ----

    public function test_l_endpoint_de_defauts_donne_la_duree_de_seance_et_la_source_de_la_valeur(): void
    {
        [$classe] = $this->sessionEncodable();
        $this->actingAsRole('directeur');

        $this->getJson('/api/heures-defrayables?annee=2026')->assertOk()
            ->assertJsonPath('data.duree_seance_defaut', 1.5)->assertJsonPath('data.valeur', 0.5)->assertJsonPath('data.source', 'defaut');

        $this->putJson("/api/cours/{$classe->periodes()->first()->cours_id}", ['temps_preparation' => 1])->assertOk();
        $this->getJson("/api/heures-defrayables?annee=2026&cours_id={$classe->periodes()->first()->cours_id}")->assertOk()
            ->assertJsonPath('data.valeur', 1)->assertJsonPath('data.source', 'cours');

        $this->actingAsRole('professeur');
        $this->getJson('/api/heures-defrayables?annee=2026')->assertForbidden();
    }

    public function test_les_sessions_et_la_classe_exposent_la_duree_de_seance_et_la_decomposition_des_heures_defrayees(): void
    {
        [$classe] = $this->sessionEncodable();
        $this->actingAsRole('directeur');

        $s = $this->getJson("/api/classes/{$classe->id}/sessions")->assertOk()->json('data.0');
        $this->assertEquals(3.5, $s['heures_defrayables']['valeur']);
        $this->assertEquals(3, $s['heures_defrayables']['duree']);
        $this->assertEquals(0.5, $s['heures_defrayables']['preparation']);
        $this->assertSame('defaut', $s['heures_defrayables']['source']);

        $this->putJson("/api/cours/{$classe->periodes()->first()->cours_id}", ['temps_preparation' => 0.25])->assertOk();
        $c = $this->getJson("/api/classes/{$classe->id}")->assertOk()->json('data');
        $this->assertEquals(3, $c['duree_seance']); // séance 14 h–17 h au calendrier de tests
        $this->assertEquals(0.25, $c['periodes'][0]['temps_preparation']['valeur']);
        $this->assertSame('cours', $c['periodes'][0]['temps_preparation']['source']);
    }

    public function test_le_professeur_recoit_la_duree_de_seance_pour_l_info_bulle(): void
    {
        [$classe, $alice] = $this->sessionEncodable();
        Sanctum::actingAs($alice->user);

        $r = $this->getJson("/api/mes-classes/{$classe->id}/sessions")->assertOk()->json('data.0');
        $this->assertEquals(3.5, $r['duree_par_defaut']);
        $this->assertEquals(3, $r['duree_seance']);
    }

    // ---- DEF-02 : séance bis = durée seule, sans préparation ----

    public function test_une_bis_est_defrayee_sur_sa_seule_duree_sans_preparation(): void
    {
        [$classe, $alice, $session] = $this->sessionEncodable();
        $this->actingAsRole('directeur');
        $this->postJson("/api/classes/{$classe->id}/sessions/bis", [
            'seance_numero' => $session->seance_numero, 'date' => $session->date->copy()->addDay()->toDateString(),
            'heure_debut' => '10:00', 'heure_fin' => '11:00', 'confirmer_depassement' => true,
        ])->assertCreated();
        $bis = CourseSession::where('classe_id', $classe->id)->where('bis_rang', '>', 0)->firstOrFail();

        $h = app(\App\Services\TimesheetService::class)->heuresDefrayables($bis);
        $this->assertSame(1.0, $h['valeur']);
        $this->assertSame(0.0, $h['preparation']);
        $this->assertTrue($h['bis']);
        $this->assertSame('bis', $h['source']);

        // La séance normale de la même classe garde durée + préparation.
        $this->assertSame(3.5, app(\App\Services\TimesheetService::class)->heuresDefrayables($session)['valeur']);
    }

    // ---- DEF-01 T4 : alerte d'impact sur le plafond journalier ----

    private function tarif(Professeur $p, float $eurParHeure): void
    {
        \App\Models\ProfesseurTarif::create(['professeur_id' => $p->id, 'tarif_horaire_eur' => $eurParHeure, 'date_debut' => '2020-01-01']);
    }

    public function test_l_impact_signale_les_jours_qui_depasseraient_le_plafond_sans_rien_enregistrer(): void
    {
        [$classe, $alice, $session] = $this->sessionEncodable();
        CourseSession::where('classe_id', $classe->id)->update(['heure_debut' => '14:00:00', 'heure_fin' => '15:30:00']);
        $this->tarif($alice, 20); // 1,5 h + 30 min = 2 h = 40 € (sous 44,02 €) ; 1,5 h + 1 h = 2,5 h = 50 € (au-dessus)
        $this->actingAsRole('directeur');
        $annee = $session->date->year;

        $r = $this->postJson('/api/heures-defrayables/impact', ['annee' => $annee, 'portee' => 'global', 'heures' => 1])->assertOk()->json('data');
        $this->assertSame(0, $r['avant']);
        $this->assertGreaterThan(0, $r['apres']);
        $this->assertSame($r['apres'], $r['nouveaux']);
        $this->assertEquals(44.02, $r['plafond_journalier_eur']);
        $this->assertEquals(50.0, $r['exemples'][0]['montant']);

        $r2 = $this->postJson('/api/heures-defrayables/impact', ['annee' => $annee, 'portee' => 'global', 'heures' => 0.5])->assertOk()->json('data');
        $this->assertSame(0, $r2['apres']);

        // Aucune écriture : le défaut reste 30 min.
        $this->assertEquals(0.5, app(TimesheetParametreService::class)->tempsPreparation($annee));
    }

    public function test_la_portee_cours_ne_vise_que_ce_cours_et_les_saisies_existantes_sont_ignorees(): void
    {
        [$classe, $alice, $session] = $this->sessionEncodable();
        CourseSession::where('classe_id', $classe->id)->update(['heure_debut' => '14:00:00', 'heure_fin' => '15:30:00']);
        $this->tarif($alice, 20);
        $this->actingAsRole('directeur');
        $annee = $session->date->year;

        $autre = \App\Models\Cours::factory()->create();
        $this->postJson('/api/heures-defrayables/impact', ['annee' => $annee, 'portee' => 'cours', 'cours_id' => $autre->id, 'heures' => 3])
            ->assertOk()->assertJsonPath('data.apres', 0); // ce cours n'a aucune classe

        $total = $this->postJson('/api/heures-defrayables/impact', ['annee' => $annee, 'portee' => 'cours', 'cours_id' => $classe->periodes()->first()->cours_id, 'heures' => 1])
            ->assertOk()->json('data.apres');
        $this->assertGreaterThan(0, $total);

        // Une séance déjà encodée par le professeur n'est plus concernée.
        Timesheet::create(['professeur_id' => $alice->id, 'course_session_id' => $session->id, 'date_prestation' => $session->date->toDateString(), 'nombre_heures' => 2, 'type_activite' => 'animation', 'statut_validation' => 'brouillon']);
        $apres = $this->postJson('/api/heures-defrayables/impact', ['annee' => $annee, 'portee' => 'cours', 'cours_id' => $classe->periodes()->first()->cours_id, 'heures' => 1])->json('data.apres');
        $this->assertSame($total - 1, $apres);
    }

    public function test_impact_validation_et_droits(): void
    {
        $this->actingAsRole('directeur');
        $this->postJson('/api/heures-defrayables/impact', ['annee' => 2026, 'portee' => 'cours', 'heures' => 1])->assertStatus(422)->assertJsonValidationErrors('cours_id');
        $this->postJson('/api/heures-defrayables/impact', ['annee' => 2026, 'portee' => 'global', 'heures' => 9])->assertStatus(422)->assertJsonValidationErrors('heures');

        $this->actingAsRole('professeur');
        $this->postJson('/api/heures-defrayables/impact', ['annee' => 2026, 'portee' => 'global', 'heures' => 1])->assertForbidden();
    }
}
