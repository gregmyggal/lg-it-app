<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\Classe;
use App\Models\CourseSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

class CourseSessionApiTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->classe = $this->classeAvecSessions($this->annee());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(int $numero, int $bis = 0): CourseSession
    {
        return $this->classe->sessions()->where('seance_numero', $numero)->where('bis_rang', $bis)->firstOrFail();
    }

    public function test_401_et_403_professeur(): void
    {
        $session = $this->seance(3);

        $this->getJson('/api/sessions')->assertStatus(401);
        $this->putJson("/api/sessions/{$session->id}", ['date' => '2026-10-22'])->assertStatus(401);
        $this->postJson("/api/sessions/{$session->id}/cancel", ['motif_annulation' => 'x'])->assertStatus(401);

        $this->actingAsRole('professeur');
        $this->getJson('/api/sessions')->assertStatus(403);
        $this->putJson("/api/sessions/{$session->id}", ['date' => '2026-10-22'])->assertStatus(403);
        $this->postJson("/api/sessions/{$session->id}/cancel", ['motif_annulation' => 'x'])->assertStatus(403);
        // 403 prioritaire même sur un payload invalide
        $this->postJson("/api/sessions/{$session->id}/cancel", [])->assertStatus(403);
    }

    public function test_index_filtres_et_pagination(): void
    {
        $autre = $this->classeAvecSessions($this->annee2(), ['jour_semaine' => 6, 'date_premiere_session' => '2026-10-10']);
        $this->seance(2)->update(['statut' => 'annulee']);
        $this->actingAsRole('directeur');

        $this->getJson('/api/sessions?per_page=100')->assertOk()->assertJsonCount(28, 'data')->assertJsonPath('meta.total', 28);
        $this->getJson("/api/sessions?classe_id={$autre->id}&per_page=100")->assertOk()->assertJsonCount(14, 'data');
        $this->getJson("/api/sessions?cours_id={$this->classe->periodes()->first()->cours_id}&per_page=100")->assertOk()->assertJsonCount(14, 'data');
        $this->getJson('/api/sessions?date_from=2026-10-07&date_to=2026-10-14&classe_id='.$this->classe->id)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/sessions?statut=annulee&classe_id={$this->classe->id}")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.seance_numero', 2);
        $this->getJson('/api/sessions?per_page=10')->assertOk()->assertJsonCount(10, 'data');
        $this->getJson('/api/sessions?date_from=2026-10-07&date_to=2026-10-01')->assertStatus(422);
        $this->getJson('/api/sessions?statut=scheduled')->assertStatus(422);
    }

    public function test_deplacer_200_avec_capacites(): void
    {
        $session = $this->seance(3);
        $this->actingAsRole('directeur');

        $this->putJson("/api/sessions/{$session->id}", ['date' => '2026-10-22', 'heure_debut' => '15:00', 'heure_fin' => '18:00'])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-22')
            ->assertJsonPath('data.heure_debut', '15:00')
            ->assertJsonPath('data.seance_numero', 3)
            ->assertJsonPath('data.libelle', 'Séance 3')
            ->assertJsonPath('data.can', ['update' => true, 'cancel' => true, 'bis' => true])
            ->assertJsonPath('data.cours.id', $this->classe->periodes()->first()->cours_id);
    }

    public function test_deplacer_apres_fin_de_periode_accepte_avec_avertissement(): void
    {
        $session = $this->seance(3);
        $this->actingAsRole('directeur');

        $this->putJson("/api/sessions/{$session->id}", ['date' => 'demain'])->assertStatus(422)->assertJsonValidationErrors(['date']);
        $this->putJson("/api/sessions/{$session->id}", ['heure_fin' => '10:00'])->assertStatus(422)->assertJsonValidationErrors(['heure_fin']);
        $this->putJson("/api/sessions/{$session->id}", ['date' => '2027-02-24'])
            ->assertOk()
            ->assertJsonPath('data.hors_periode', true)
            ->assertJsonPath('data.avertissements.0', 'Cette date est après la fin de la période 1');
        $this->putJson('/api/sessions/99999', ['date' => '2026-10-22'])->assertStatus(404);
    }

    public function test_session_passee_deplacable_session_annulee_409(): void
    {
        $this->actingAsRole('directeur');
        Carbon::setTestNow('2026-10-20 10:00:00');
        $passee = $this->seance(2);

        $this->putJson("/api/sessions/{$passee->id}", ['date' => '2026-10-15'])->assertOk()->assertJsonPath('data.date', '2026-10-15');

        $reponse = $this->getJson('/api/sessions?classe_id='.$this->classe->id.'&per_page=100');
        $this->assertTrue($reponse->json('data.1.can.update'));

        $annulee = $this->seance(5);
        $annulee->update(['statut' => 'annulee']);
        $this->putJson("/api/sessions/{$annulee->id}", ['date' => '2026-11-26'])->assertStatus(409);
    }

    public function test_annuler_422_sans_motif_puis_200_puis_409(): void
    {
        $session = $this->seance(5);
        $this->actingAsRole('directeur');

        $this->postJson("/api/sessions/{$session->id}/cancel", [])->assertStatus(422)
            ->assertJsonPath('errors.motif_annulation.0', "Le champ motif d'annulation est obligatoire.");

        $this->postJson("/api/sessions/{$session->id}/cancel", ['motif_annulation' => 'Professeur malade'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'annulee')
            ->assertJsonPath('data.seance_numero', 5)
            ->assertJsonPath('data.motif_annulation', 'Professeur malade')
            ->assertJsonPath('data.can.cancel', false)
            ->assertJsonPath('data.can.update', false);

        $this->postJson("/api/sessions/{$session->id}/cancel", ['motif_annulation' => 'Encore'])->assertStatus(409);
    }

    public function test_bis_201_409_422(): void
    {
        $this->actingAsRole('directeur');
        $url = "/api/classes/{$this->classe->id}/sessions/bis";

        // 409 : la classe a déjà 14 sessions actives → confirmation requise.
        $this->postJson($url, ['seance_numero' => 3, 'date' => '2027-01-20'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette période passera à 15 sessions')
            ->assertJsonPath('nb_sessions', 15);

        $this->postJson($url, ['seance_numero' => 3, 'date' => '2027-01-20', 'confirmer_depassement' => true])
            ->assertStatus(201)
            ->assertJsonPath('data.libelle', 'Séance 3 bis')
            ->assertJsonPath('data.bis_rang', 1)
            ->assertJsonPath('data.remplace_session_id', null);

        // 422 : séance hors 1..14, date hors période, champs manquants
        $this->postJson($url, ['seance_numero' => 15, 'date' => '2027-01-20'])->assertStatus(422)->assertJsonValidationErrors(['seance_numero']);
        $this->postJson($url, ['seance_numero' => 3, 'date' => '2027-02-22', 'confirmer_depassement' => true])
            ->assertStatus(201)->assertJsonPath('data.hors_periode', true)
            ->assertJsonPath('data.avertissements.0', 'Cette date est après la fin de la période 1');
        $this->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors(['seance_numero', 'date']);

        // Annulation puis bis : le rang et remplace_session_id sont renseignés, sans confirmation.
        $session = $this->seance(6);
        $this->postJson("/api/sessions/{$session->id}/cancel", ['motif_annulation' => 'Panne'])->assertOk();
        $this->postJson('/api/sessions/'.$this->seance(7)->id.'/cancel', ['motif_annulation' => 'Panne'])->assertOk();
        // 16 sessions - 2 annulées = 14 actives : un nouveau bis dépasserait → on retire d'abord le bis hors période
        CourseSession::where('date', '2027-02-22')->delete();
        // 15 sessions - 2 annulées = 13 actives : le bis ramène à 14, sans dépassement.
        $this->postJson($url, ['seance_numero' => 6, 'date' => '2027-01-27', 'lieu' => 'Salle B'])
            ->assertStatus(201)->assertJsonPath('data.remplace_session_id', $session->id)->assertJsonPath('data.lieu', 'Salle B');

        $this->getJson('/api/classes/99999/sessions')->assertStatus(404);
    }

    public function test_alerte_calendrier_exposee_sans_deplacement(): void
    {
        CalendrierScolaire::factory()->create([
            'annee_scolaire_id' => $this->classe->annee_scolaire_id, 'type' => 'fermeture',
            'libelle' => 'Fermeture exceptionnelle', 'date_debut' => '2026-10-14', 'date_fin' => '2026-10-14',
        ]);
        $this->actingAsRole('directeur');

        $sessions = $this->getJson("/api/classes/{$this->classe->id}/sessions")->assertOk();
        $this->assertSame(['libelle' => 'Fermeture exceptionnelle', 'type' => 'fermeture'], $sessions->json('data.1.alerte_calendrier'));
        $this->assertNull($sessions->json('data.0.alerte_calendrier'));
        $this->assertSame('2026-10-14', $sessions->json('data.1.date'));

        $liste = $this->getJson("/api/sessions?classe_id={$this->classe->id}&per_page=100");
        $this->assertSame('Fermeture exceptionnelle', $liste->json('data.1.alerte_calendrier.libelle'));
    }

    public function test_calendrier_month_week_year_agenda(): void
    {
        $this->actingAsRole('directeur');

        $mois = $this->getJson('/api/calendar/month?year=2026&month=10')->assertOk();
        $this->assertSame(4, $mois->json('summary.total_sessions')); // 7, 14, 21, 28 octobre
        $this->assertSame('Séance 1', $mois->json('data.2026-10-07.0.libelle'));
        $this->assertNotNull($mois->json('data.2026-10-07.0.cours.titre'));

        $semaine = $this->getJson('/api/calendar/week?year=2026&week=41')->assertOk(); // 5–11 octobre 2026
        $this->assertSame(1, $semaine->json('summary.total_sessions'));

        $annee = $this->getJson('/api/calendar/year?year=2026')->assertOk();
        $this->assertSame(['2026-10', '2026-11', '2026-12'], array_keys($annee->json('data')));

        $agenda = $this->getJson('/api/calendar/agenda?from_date=2026-10-01&to_date=2026-10-31&statut[]=planifiee')->assertOk();
        $this->assertSame(4, $agenda->json('summary.total'));
        $this->getJson("/api/calendar/month?year=2026&month=10&classe_id={$this->classe->id}")->assertOk();
        $this->getJson('/api/calendar/month?year=2026&month=10&cours_id=99999')->assertStatus(422);
        $this->getJson('/api/calendar/agenda?from_date=2026-10-31&to_date=2026-10-01')->assertStatus(422);
        $this->getJson('/api/calendar/agenda?from_date=2026-10-01&to_date=2026-10-31&statut[]=scheduled')->assertStatus(422);
    }

    public function test_calendrier_401_403_et_route_professeur_retiree(): void
    {
        $this->getJson('/api/calendar/month?year=2026&month=10')->assertStatus(401);

        $user = $this->actingAsRole('professeur');
        $this->getJson('/api/calendar/month?year=2026&month=10')->assertStatus(403);
        $this->getJson('/api/calendar/agenda?from_date=2026-10-01&to_date=2026-10-31')->assertStatus(403);
        $this->getJson('/api/calendar/professor/1')->assertStatus(404);
    }

    public function test_anciennes_routes_sprint_2_retirees(): void
    {
        $this->actingAsRole('admin');
        $session = $this->seance(1);

        $this->postJson("/api/sessions/{$session->id}/in-progress")->assertStatus(404);
        $this->getJson("/api/sessions/{$session->id}/professors")->assertStatus(404);
        $this->getJson("/api/cours/{$this->classe->periodes()->first()->cours_id}/recurrences")->assertStatus(404);
        $this->getJson("/api/cours/{$this->classe->periodes()->first()->cours_id}/sessions")->assertStatus(404);
    }

    private function annee2()
    {
        $annee = AnneeScolaire::create([
            'libelle' => '2027-2028', 'date_debut' => '2026-08-24', 'date_fin' => '2027-07-02', 'statut' => 'active',
        ]);
        $annee->periodes()->create(['numero' => 1, 'date_debut' => '2026-08-24', 'date_fin' => '2027-02-19']);
        $annee->periodes()->create(['numero' => 2, 'date_debut' => '2027-02-22', 'date_fin' => '2027-07-02']);

        return $annee->load('periodes');
    }
}
