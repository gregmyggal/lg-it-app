<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

class ClasseApiTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_401_et_403_professeur_sur_toutes_les_routes(): void
    {
        $annee = $this->annee();
        $classe = $this->classeAvecSessions($annee);
        $payload = $this->donneesClasse($annee);

        $this->getJson('/api/classes')->assertStatus(401);
        $this->postJson('/api/classes', $payload)->assertStatus(401);
        $this->getJson("/api/classes/{$classe->id}/sessions")->assertStatus(401);

        $this->actingAsRole('professeur');
        $this->getJson('/api/classes')->assertStatus(403);
        $this->postJson('/api/classes', $payload)->assertStatus(403);
        $this->postJson('/api/classes/apercu', $payload)->assertStatus(403);
        $this->getJson("/api/classes/{$classe->id}")->assertStatus(403);
        $this->putJson("/api/classes/{$classe->id}", ['lieu' => 'X'])->assertStatus(403);
        $this->deleteJson("/api/classes/{$classe->id}")->assertStatus(403);
        $this->getJson("/api/classes/{$classe->id}/sessions")->assertStatus(403);
        $this->postJson("/api/classes/{$classe->id}/sessions/bis", ['seance_numero' => 1, 'date' => '2027-01-20'])->assertStatus(403);
    }

    public function test_creation_genere_14_sessions(): void
    {
        $annee = $this->annee();
        $this->importFwb($annee);
        $this->actingAsRole('directeur');

        $reponse = $this->postJson('/api/classes', $this->donneesClasse($annee))
            ->assertStatus(201)
            ->assertJsonPath('data.nb_sessions', 14)
            ->assertJsonPath('data.jour_semaine', 3)
            ->assertJsonPath('data.heure_debut', '14:00')
            ->assertJsonPath('data.periode.numero', 1)
            ->assertJsonPath('data.annee_scolaire.libelle', '2026-2027')
            ->assertJsonPath('data.prochaine_session.libelle', 'Séance 1')
            ->assertJsonPath('data.prochaine_session.date', '2026-10-07')
            ->assertJsonPath('data.can.delete', true);

        $this->assertSame(14, CourseSession::where('classe_id', $reponse->json('data.id'))->count());
    }

    public function test_creation_422_validation_et_blocage_fin_de_periode(): void
    {
        $annee = $this->annee();
        $this->actingAsRole('admin');

        $this->postJson('/api/classes', [])->assertStatus(422)
            ->assertJsonValidationErrors(['cours_id', 'annee_scolaire_id', 'periode_id', 'jour_semaine', 'heure_debut', 'heure_fin', 'date_premiere_session']);

        $this->postJson('/api/classes', $this->donneesClasse($annee, ['heure_fin' => '13:00']))
            ->assertStatus(422)->assertJsonValidationErrors(['heure_fin']);
        $this->postJson('/api/classes', $this->donneesClasse($annee, ['jour_semaine' => 8]))
            ->assertStatus(422)->assertJsonValidationErrors(['jour_semaine']);

        $this->postJson('/api/classes', $this->donneesClasse($annee, ['date_premiere_session' => '2027-01-13']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'La séance 14 dépasse la fin de la période 1')
            ->assertJsonPath('errors.date_premiere_session.0', 'La séance 14 dépasse la fin de la période 1');

        $autre = AnneeScolaire::factory()->avecPeriodes()->create();
        $this->postJson('/api/classes', $this->donneesClasse($annee, ['periode_id' => $autre->periodes()->first()->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['periode_id']);

        $this->assertSame(0, Classe::count());
        $this->assertSame(0, CourseSession::count());
    }

    public function test_apercu_ne_persiste_rien(): void
    {
        $annee = $this->annee();
        $this->importFwb($annee);
        $this->actingAsRole('directeur');

        $this->postJson('/api/classes/apercu', $this->donneesClasse($annee, ['date_premiere_session' => '2026-10-06']))
            ->assertOk()
            ->assertJsonCount(14, 'data.seances')
            ->assertJsonPath('data.recale', true)
            ->assertJsonPath('data.date_premiere_session', '2026-10-07')
            ->assertJsonPath('data.dates_sautees.0.date', '2026-10-21')
            ->assertJsonPath('data.dates_sautees.0.libelle', "Vacances d'automne (Toussaint)")
            ->assertJsonPath('data.blocage', null);

        $this->postJson('/api/classes/apercu', $this->donneesClasse($annee, ['date_premiere_session' => '2027-01-13']))
            ->assertOk()->assertJsonPath('data.blocage.periode_numero', 1);

        $this->postJson('/api/classes/apercu', [])->assertStatus(422);
        $this->assertSame(0, Classe::count());
        $this->assertSame(0, CourseSession::count());
    }

    public function test_liste_filtres_et_pagination(): void
    {
        $annee = $this->annee();
        $react = Cours::factory()->create();
        $this->classeAvecSessions($annee, ['cours_id' => $react->id]);
        $this->classeAvecSessions($annee, ['cours_id' => $react->id, 'jour_semaine' => 6, 'date_premiere_session' => '2026-10-10']);
        $periode2 = $annee->periodes->firstWhere('numero', 2);
        $this->classeAvecSessions($annee, ['periode_id' => $periode2->id, 'date_premiere_session' => '2027-03-10']);
        $this->actingAsRole('directeur');

        $this->getJson('/api/classes')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson("/api/classes?cours_id={$react->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/classes?jour_semaine=6')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/classes?periode_id={$periode2->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/classes?annee_scolaire_id={$annee->id}&statut=active")->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/classes?statut=archivee')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/classes?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/classes?per_page=101')->assertStatus(422);
        $this->getJson('/api/classes?statut=zzz')->assertStatus(422);
    }

    public function test_detail_et_sessions_dune_classe_incluent_bis_et_annulees(): void
    {
        $annee = $this->annee();
        $classe = $this->classeAvecSessions($annee);
        $this->actingAsRole('directeur');

        $this->postJson("/api/sessions/{$classe->sessions()->where('seance_numero', 5)->first()->id}/cancel", ['motif_annulation' => 'Grève'])->assertOk();
        $this->postJson("/api/classes/{$classe->id}/sessions/bis", ['seance_numero' => 5, 'date' => '2027-01-20'])->assertStatus(201);

        $this->getJson("/api/classes/{$classe->id}")->assertOk()
            ->assertJsonPath('data.nb_sessions', 14) // 13 actives + 1 bis
            ->assertJsonPath('data.cours.id', $classe->cours_id);
        $this->getJson('/api/classes/9999')->assertStatus(404);

        $sessions = $this->getJson("/api/classes/{$classe->id}/sessions")->assertOk()->assertJsonCount(15, 'data');
        $this->assertSame(['Séance 5', 'Séance 5 bis'], array_slice($sessions->json('data.*.libelle'), 4, 2));
        $this->assertSame('annulee', $sessions->json('data.4.statut'));
        $this->assertSame('Grève', $sessions->json('data.4.motif_annulation'));
        $this->assertSame($sessions->json('data.4.id'), $sessions->json('data.5.remplace_session_id'));
    }

    public function test_modification_du_creneau_repercutee_sur_les_sessions_a_venir_non_ajustees(): void
    {
        $annee = $this->annee();
        $classe = $this->classeAvecSessions($annee);
        $ajustee = $classe->sessions()->where('seance_numero', 3)->first();
        $ajustee->update(['heure_debut' => '10:00', 'heure_fin' => '12:00']);
        $this->actingAsRole('directeur');

        Carbon::setTestNow('2026-10-20 10:00:00'); // séances 1 et 2 passées
        $this->putJson("/api/classes/{$classe->id}", ['heure_debut' => '15:00', 'heure_fin' => '18:00', 'lieu' => 'Salle B'])
            ->assertOk()->assertJsonPath('data.heure_debut', '15:00')->assertJsonPath('data.lieu', 'Salle B');

        $sessions = $classe->sessions()->get()->keyBy('seance_numero');
        $this->assertSame('14:00:00', $sessions[1]->heure_debut, 'Session passée intacte.');
        $this->assertSame('Salle A', $sessions[2]->lieu, 'Session passée intacte.');
        $this->assertSame('10:00:00', $sessions[3]->heure_debut, 'Session ajustée à la main intacte.');
        $this->assertSame('Salle B', $sessions[3]->lieu);
        $this->assertSame('15:00:00', $sessions[4]->heure_debut);
        $this->assertSame('18:00:00', $sessions[14]->heure_fin);
        $this->assertSame(14, $classe->sessions()->count(), 'Aucune régénération.');

        $this->putJson("/api/classes/{$classe->id}", ['heure_fin' => '14:30'])->assertStatus(422)->assertJsonValidationErrors(['heure_fin']);
        $this->putJson("/api/classes/{$classe->id}", ['statut' => 'archivee'])->assertOk()->assertJsonPath('data.statut', 'archivee');
        $this->putJson("/api/classes/{$classe->id}", ['statut' => 'brouillon'])->assertStatus(422);
    }

    public function test_suppression_204_si_aucune_dependance(): void
    {
        $classe = $this->classeAvecSessions($this->annee());
        $this->actingAsRole('admin');

        $this->deleteJson("/api/classes/{$classe->id}")->assertStatus(204);
        $this->assertDatabaseMissing('classes', ['id' => $classe->id]);
        $this->assertSame(0, CourseSession::count());
    }

    public function test_suppression_409_avec_session_annulee_passee_ou_timesheet(): void
    {
        $this->actingAsRole('admin');

        // session annulée
        $classe = $this->classeAvecSessions($this->annee());
        $classe->sessions()->where('seance_numero', 2)->update(['statut' => 'annulee']);
        $this->deleteJson("/api/classes/{$classe->id}")->assertStatus(409)->assertJsonStructure(['message']);

        // session passée
        $classe2 = $this->classeAvecSessions($this->annee2());
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->deleteJson("/api/classes/{$classe2->id}")->assertStatus(409);
        $this->assertDatabaseHas('classes', ['id' => $classe2->id]);

        // timesheet rattachée
        Carbon::setTestNow('2026-09-30 10:00:00');
        $classe3 = $this->classeAvecSessions($this->annee3());
        $professeur = Professeur::create(['user_id' => User::factory()->professeur()->create()->id, 'prenom' => 'A', 'nom' => 'B', 'email' => 'ab@test.com', 'statut' => 'actif', 'date_entree' => '2025-09-01']);
        Timesheet::create([
            'professeur_id' => $professeur->id, 'date_prestation' => '2026-10-07', 'nombre_heures' => 3,
            'course_session_id' => $classe3->sessions()->first()->id, 'statut_validation' => 'brouillon',
        ]);
        $this->deleteJson("/api/classes/{$classe3->id}")->assertStatus(409);
        $this->assertSame(14, $classe3->sessions()->count());
    }

    private function annee2()
    {
        return $this->autreAnnee('2027-2028', 2027);
    }

    private function annee3()
    {
        return $this->autreAnnee('2028-2029', 2028);
    }

    /** Année dont la période 1 accueille le même scénario (mercredi 2026-10-07 → périodes larges). */
    private function autreAnnee(string $libelle, int $debut)
    {
        $annee = AnneeScolaire::create([
            'libelle' => $libelle, 'date_debut' => '2026-08-24', 'date_fin' => '2027-07-02', 'statut' => 'active',
        ]);
        $annee->periodes()->create(['numero' => 1, 'date_debut' => '2026-08-24', 'date_fin' => '2027-02-19']);
        $annee->periodes()->create(['numero' => 2, 'date_debut' => '2027-02-22', 'date_fin' => '2027-07-02']);

        return $annee->load('periodes');
    }
}
