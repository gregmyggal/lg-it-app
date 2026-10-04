<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\SessionProfesseur;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/**
 * CLS-06 — Déplacer une séance et décaler les suivantes. Classe du mercredi, calendrier FWB 2026-2027
 * (mercredis sautés : 21/10, 28/10, 11/11, 23/12, 30/12, 24/02, 03/03, 28/04, 05/05) ; P1 24/08 → 19/02, P2 22/02 → 02/07.
 * P1 d'origine : 07/10, 14/10, 04/11, 18/11, 25/11, 02/12, 09/12, 16/12, 06/01, 13/01, 20/01, 27/01, 03/02, 10/02.
 */
class SessionReplanificationApiTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-04 10:00:00');
        $this->annee = $this->annee();
        $this->importFwb($this->annee);
    }

    private function classe(bool $avecP2 = false): Classe
    {
        $donnees = $this->donneesClasse($this->annee);
        if ($avecP2) {
            $donnees['periodes'][] = [
                'periode_id' => $this->annee->periodes->firstWhere('numero', 2)->id,
                'cours_id' => Cours::factory()->create()->id,
                'date_premiere_session' => '2027-03-10',
            ];
        }

        return $this->classeAvecSessions($this->annee, ['periodes' => $donnees['periodes']]);
    }

    private function seance(Classe $classe, int $numero, int $periode = 1, int $bis = 0): CourseSession
    {
        return CourseSession::where('classe_id', $classe->id)
            ->whereHas('classePeriode.periode', fn ($q) => $q->where('numero', $periode))
            ->where('seance_numero', $numero)->where('bis_rang', $bis)->firstOrFail();
    }

    /** @return list<string> dates (d/m) des séances de base de la période, dans l'ordre des numéros */
    private function dates(Classe $classe, int $periode = 1): array
    {
        return CourseSession::where('classe_id', $classe->id)->where('bis_rang', 0)
            ->whereHas('classePeriode.periode', fn ($q) => $q->where('numero', $periode))
            ->orderBy('seance_numero')->get()
            ->map(fn ($s) => $s->date->format('d/m'))->all();
    }

    private function verrouiller(CourseSession $session): void
    {
        Timesheet::create([
            'professeur_id' => Professeur::factory()->create()->id,
            'date_prestation' => $session->date->toDateString(),
            'nombre_heures' => 2,
            'course_session_id' => $session->id,
            'statut_validation' => Timesheet::STATUT_SOUMIS,
        ]);
    }

    public function test_ac2_decale_les_suivantes_une_par_semaine_en_sautant_les_conges(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();

        $this->putJson('/api/sessions/'.$this->seance($classe, 1)->id, ['date' => '2026-10-14', 'decaler_suivantes' => true])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-14')
            ->assertJsonPath('replanification.decalees.p1', 13);

        $this->assertSame(
            ['14/10', '04/11', '18/11', '25/11', '02/12', '09/12', '16/12', '06/01', '13/01', '20/01', '27/01', '03/02', '10/02', '17/02'],
            $this->dates($classe)
        );
        $this->assertSame(range(1, 14), CourseSession::where('classe_id', $classe->id)->orderBy('date')->pluck('seance_numero')->all());
    }

    public function test_ac1_sans_option_seule_la_seance_bouge(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();

        $this->putJson('/api/sessions/'.$this->seance($classe, 1)->id, ['date' => '2026-10-08'])->assertOk()->assertJsonMissingPath('replanification');

        $this->assertSame('14/10', $this->dates($classe)[1]);
    }

    public function test_ac4_apercu_sans_ecriture_avec_dates_sautees(): void
    {
        $this->actingAsRole('admin');
        $classe = $this->classe();
        $session = $this->seance($classe, 1);

        $apercu = $this->postJson("/api/sessions/{$session->id}/deplacement/apercu", ['date' => '2026-10-14'])
            ->assertOk()
            ->assertJsonCount(1, 'data.periodes')
            ->assertJsonPath('data.periodes.0.lignes.0.etat', 'deplacee')
            ->assertJsonPath('data.periodes.0.lignes.1.date_avant', '2026-10-14')
            ->assertJsonPath('data.periodes.0.lignes.1.date_apres', '2026-11-04')
            ->assertJsonPath('data.periodes.0.lignes.1.etat', 'decalee')
            ->json('data');

        $this->assertSame(['2026-10-21', '2026-10-28', '2026-11-11', '2026-12-23', '2026-12-30'], array_column($apercu['periodes'][0]['dates_sautees'], 'date'));
        $this->assertSame(40, strlen($apercu['empreinte']));
        $this->assertSame('07/10', $this->dates($classe)[0]);
    }

    public function test_ac5_date_forcee_maintient_la_seance_et_les_suivantes_reprennent_leur_date(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();

        $this->putJson('/api/sessions/'.$this->seance($classe, 1)->id, ['date' => '2026-10-14', 'decaler_suivantes' => true, 'dates_forcees' => ['2026-11-11']])
            ->assertOk()
            ->assertJsonPath('replanification.decalees.p1', 2);

        $this->assertSame(
            ['14/10', '04/11', '11/11', '18/11', '25/11', '02/12', '09/12', '16/12', '06/01', '13/01', '20/01', '27/01', '03/02', '10/02'],
            $this->dates($classe)
        );
    }

    public function test_ac6_ac7_les_suivantes_restent_au_jour_de_la_classe(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $id = $this->seance($classe, 1)->id;

        $this->postJson("/api/sessions/{$id}/deplacement/apercu", ['date' => '2026-10-08'])
            ->assertOk()->assertJsonPath('data.decalees.p1', 0);

        $this->putJson("/api/sessions/{$id}", ['date' => '2026-10-15', 'decaler_suivantes' => true])->assertOk();

        $this->assertSame('2026-11-04', $this->seance($classe, 2)->date->toDateString());
        $this->assertSame(3, $this->seance($classe, 2)->date->dayOfWeekIso);
    }

    public function test_ac12_ac13_avancer_et_ordre_avec_la_seance_precedente(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $id = $this->seance($classe, 4)->id; // 18/11, précédente P1 · 3 le 04/11

        $this->putJson("/api/sessions/{$id}", ['date' => '2026-11-04', 'decaler_suivantes' => true])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', 'La nouvelle date doit être après la séance précédente P1 · Séance 3 (04/11).');

        $this->putJson("/api/sessions/{$id}", ['date' => '2026-11-12', 'decaler_suivantes' => true])->assertOk();

        $this->assertSame(
            ['07/10', '14/10', '04/11', '12/11', '18/11', '25/11', '02/12', '09/12', '16/12', '06/01', '13/01', '20/01', '27/01', '03/02'],
            $this->dates($classe)
        );
    }

    public function test_ac11_annulee_et_bis_figes_avec_avertissement_bis_devance(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $annulee = $this->seance($classe, 6); // 02/12
        $annulee->update(['statut' => CourseSession::STATUT_ANNULEE, 'motif_annulation' => 'test']);
        CourseSession::create([
            'classe_id' => $classe->id, 'classe_periode_id' => $annulee->classe_periode_id, 'seance_numero' => 6, 'bis_rang' => 1,
            'remplace_session_id' => $annulee->id, 'date' => '2026-11-28', 'heure_debut' => '14:00', 'heure_fin' => '17:00', 'statut' => CourseSession::STATUT_PLANIFIEE,
        ]);

        $avertissements = $this->putJson('/api/sessions/'.$this->seance($classe, 1)->id, ['date' => '2026-10-14', 'decaler_suivantes' => true])
            ->assertOk()->json('replanification.avertissements');

        // P1 · 6 annulée garde le 02/12 et n'occupe pas de semaine : P1 · 5 prend le 02/12, P1 · 7 reste le 09/12.
        $this->assertSame(
            ['14/10', '04/11', '18/11', '25/11', '02/12', '02/12', '09/12', '16/12', '06/01', '13/01', '20/01', '27/01', '03/02', '10/02'],
            $this->dates($classe)
        );
        $this->assertSame('2026-11-28', $this->seance($classe, 6, 1, 1)->date->toDateString());
        $this->assertContains('La P1 · Séance 6 bis (28/11) est désormais avant la P1 · Séance 5 (02/12).', array_column($avertissements, 'message'));
    }

    public function test_ac10_arret_sur_seance_avec_heures_encodees(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $this->verrouiller($this->seance($classe, 9)); // 06/01

        $reponse = $this->putJson('/api/sessions/'.$this->seance($classe, 1)->id, ['date' => '2026-10-14', 'decaler_suivantes' => true])
            ->assertOk()
            ->assertJsonPath('replanification.decalees.p1', 7)
            ->assertJsonPath('replanification.periodes.0.arret.seance', 'P1 · Séance 9')
            ->assertJsonPath('replanification.periodes.0.arret.motif', 'heures_validees');

        $this->assertSame(
            ['14/10', '04/11', '18/11', '25/11', '02/12', '09/12', '16/12', '06/01', '06/01', '13/01', '20/01', '27/01', '03/02', '10/02'],
            $this->dates($classe)
        );
        $this->assertContains('ordre_inverse', array_column($reponse->json('replanification.avertissements'), 'code'));
    }

    public function test_heures_en_brouillon_ne_verrouillent_pas_et_suivent_la_seance(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $brouillon = Timesheet::create([
            'professeur_id' => Professeur::factory()->create()->id, 'date_prestation' => '2026-11-18', 'nombre_heures' => 2,
            'course_session_id' => $this->seance($classe, 4)->id, 'statut_validation' => Timesheet::STATUT_BROUILLON,
        ]);
        $this->seance($classe, 10)->update(['statut' => CourseSession::STATUT_TERMINEE]);

        $this->putJson('/api/sessions/'.$this->seance($classe, 1)->id, ['date' => '2026-10-14', 'decaler_suivantes' => true])
            ->assertOk()
            ->assertJsonPath('replanification.decalees.p1', 13)
            ->assertJsonPath('replanification.periodes.0.arret', null);

        $this->assertSame('2026-11-25', $this->seance($classe, 4)->date->toDateString());
        $this->assertSame('2026-11-25', $brouillon->fresh()->date_prestation->toDateString());
    }

    public function test_decalage_depuis_une_seance_passee(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $this->travelTo('2026-11-20 10:00:00'); // P1 · 1 à 4 passées

        $this->getJson("/api/classes/{$classe->id}/sessions")->assertOk()->assertJsonPath('data.1.can.update', true);
        // P1 · 2 (14/10, passée) → 04/11 : P1 · 3 et 4, passées elles aussi, sont recalculées.
        $this->putJson('/api/sessions/'.$this->seance($classe, 2)->id, ['date' => '2026-11-04', 'decaler_suivantes' => true])
            ->assertOk()
            ->assertJsonPath('replanification.decalees.p1', 12);

        $this->assertSame(
            ['07/10', '04/11', '18/11', '25/11', '02/12', '09/12', '16/12', '06/01', '13/01', '20/01', '27/01', '03/02', '10/02', '17/02'],
            $this->dates($classe)
        );
    }

    public function test_ac23_ac24_chevauchement_autorise_avec_avertissement_et_levee_par_forcage(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $this->verrouiller($this->seance($classe, 4)); // 18/11
        $id = $this->seance($classe, 1)->id;

        $avertissements = $this->postJson("/api/sessions/{$id}/deplacement/apercu", ['date' => '2026-10-14'])->assertOk()->json('data.avertissements');
        $this->assertContains(
            "La P1 · Séance 3 tombera le 18/11, le même jour que la P1 · Séance 4 (18/11, heures soumises ou validées) : l'ordre des séances ne suivra plus leur numéro.",
            array_column($avertissements, 'message')
        );

        $avertissements = $this->putJson("/api/sessions/{$id}", ['date' => '2026-10-14', 'decaler_suivantes' => true, 'dates_forcees' => ['2026-11-11']])
            ->assertOk()->json('replanification.avertissements');
        $this->assertNotContains('ordre_inverse', array_column($avertissements, 'code'));
        $this->assertSame(['14/10', '04/11', '11/11', '18/11'], array_slice($this->dates($classe), 0, 4));
    }

    public function test_ac8_ac18_ac26_ac27_cascade_vers_la_periode_2(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe(avecP2: true);
        $id = $this->seance($classe, 1)->id;

        $this->postJson("/api/sessions/{$id}/deplacement/apercu", ['date' => '2026-11-25'])
            ->assertOk()
            ->assertJsonCount(2, 'data.periodes')
            ->assertJsonPath('data.periodes.1.declencheur', 'P1 · Séance 14 le 24/03 ≥ P2 · Séance 1 le 10/03');

        $avertissements = $this->putJson("/api/sessions/{$id}", ['date' => '2026-11-25', 'decaler_suivantes' => true])
            ->assertOk()
            ->assertJsonPath('replanification.decalees.p1', 13)
            ->assertJsonPath('replanification.decalees.p2', 14)
            ->json('replanification.avertissements');

        $this->assertSame(
            ['25/11', '02/12', '09/12', '16/12', '06/01', '13/01', '20/01', '27/01', '03/02', '10/02', '17/02', '10/03', '17/03', '24/03'],
            $this->dates($classe)
        );
        $this->assertSame(
            ['31/03', '07/04', '14/04', '21/04', '12/05', '19/05', '26/05', '02/06', '09/06', '16/06', '23/06', '30/06', '07/07', '14/07'],
            $this->dates($classe, 2)
        );
        $codes = array_count_values(array_column($avertissements, 'code'));
        $this->assertSame(3, $codes['hors_periode']); // P1 · 12 à 14 après le 19/02
        $this->assertSame(2, $codes['fin_annee']); // P2 · 13 et 14 après le 02/07
        $p2 = $classe->periodes()->with('periode')->get()->first(fn ($cp) => $cp->periode->numero === 2);
        $this->assertSame('2027-03-31', $p2->date_premiere_session->toDateString());
    }

    public function test_ac20_pas_de_cascade_si_la_p1_ne_deborde_pas(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe(avecP2: true);

        $this->putJson('/api/sessions/'.$this->seance($classe, 1)->id, ['date' => '2026-10-14', 'decaler_suivantes' => true])
            ->assertOk()->assertJsonPath('replanification.decalees', ['p1' => 13]);

        $this->assertSame('10/03', $this->dates($classe, 2)[0]);
    }

    public function test_ac22_arret_en_p1_empeche_la_cascade(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe(avecP2: true);
        $this->verrouiller($this->seance($classe, 14)); // 10/02

        $avertissements = $this->putJson('/api/sessions/'.$this->seance($classe, 1)->id, ['date' => '2026-11-25', 'decaler_suivantes' => true])
            ->assertOk()->json('replanification.avertissements');

        $this->assertSame('10/03', $this->dates($classe, 2)[0]);
        $this->assertContains('chevauche_periode', array_column($avertissements, 'code'));
    }

    public function test_ac14_planning_modifie_entre_apercu_et_confirmation(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $id = $this->seance($classe, 1)->id;
        $empreinte = $this->postJson("/api/sessions/{$id}/deplacement/apercu", ['date' => '2026-10-14'])->json('data.empreinte');

        $this->seance($classe, 10)->update(['statut' => CourseSession::STATUT_ANNULEE, 'motif_annulation' => 'autre utilisateur']);

        $this->putJson("/api/sessions/{$id}", ['date' => '2026-10-14', 'decaler_suivantes' => true, 'empreinte' => $empreinte])
            ->assertStatus(409)->assertJsonPath('code', 'planning_modifie');
        $this->assertSame('07/10', $this->dates($classe)[0]);
    }

    public function test_un_bis_ne_decale_pas_les_suivantes(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $base = $this->seance($classe, 3);
        $bis = CourseSession::create([
            'classe_id' => $classe->id, 'classe_periode_id' => $base->classe_periode_id, 'seance_numero' => 3, 'bis_rang' => 1,
            'date' => '2026-11-07', 'heure_debut' => '14:00', 'heure_fin' => '17:00', 'statut' => CourseSession::STATUT_PLANIFIEE,
        ]);

        $this->putJson("/api/sessions/{$bis->id}", ['date' => '2026-11-14', 'decaler_suivantes' => true])
            ->assertStatus(422)->assertJsonValidationErrors('decaler_suivantes');
    }

    public function test_ac15_professeur_403(): void
    {
        $classe = $this->classe();
        $id = $this->seance($classe, 1)->id;

        $this->postJson("/api/sessions/{$id}/deplacement/apercu", ['date' => '2026-10-14'])->assertStatus(401);

        $this->actingAsRole('professeur');
        $this->postJson("/api/sessions/{$id}/deplacement/apercu", ['date' => '2026-10-14'])->assertStatus(403);
        $this->putJson("/api/sessions/{$id}", ['date' => '2026-10-14', 'decaler_suivantes' => true])->assertStatus(403);
    }

    public function test_rg12_conflit_d_horaire_d_un_professeur_a_la_nouvelle_date(): void
    {
        $this->actingAsRole('directeur');
        $classe = $this->classe();
        $autre = $this->classe();
        $professeur = Professeur::factory()->create(['prenom' => 'Carol', 'nom' => 'Martin']);
        SessionProfesseur::factory()->create(['course_session_id' => $this->seance($classe, 2)->id, 'professeur_id' => $professeur->id]);
        SessionProfesseur::factory()->create(['course_session_id' => $this->seance($autre, 3)->id, 'professeur_id' => $professeur->id]); // 04/11

        $avertissements = $this->postJson('/api/sessions/'.$this->seance($classe, 1)->id.'/deplacement/apercu', ['date' => '2026-10-14'])
            ->assertOk()->json('data.avertissements');

        $conflits = array_values(array_filter($avertissements, fn ($a) => $a['code'] === 'conflit_professeur'));
        $this->assertCount(1, $conflits);
        $this->assertStringStartsWith("Conflit d'horaire pour Carol Martin le 04/11", $conflits[0]['message']);
    }
}
