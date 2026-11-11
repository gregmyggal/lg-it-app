<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** STG-01 : stages planifiés d'une semaine (classe de type « stage », 1 à 5 séances d'une demi-journée). */
class ClasseStageApiTest extends TestCase
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

    /** Fiche du catalogue des thèmes de stage (modèle `Stage`). */
    private function theme(string $titre = 'Minecraft Redstone'): Stage
    {
        return Stage::firstOrCreate(['slug' => str($titre)->slug()->toString()], [
            'titre' => $titre, 'theme_stage' => 'Minecraft',
            'tranche_age' => '8-12 ans', 'lieu' => 'Namur', 'prix' => 150, 'statut' => 'publish',
        ]);
    }

    /** Semaine du 12 au 16 octobre 2026 : aucun jour férié ni congé. */
    private function payload(array $attrs = []): array
    {
        return $attrs + ['date_debut' => '2026-10-14', 'stage_id' => $this->theme()->id, 'lieu' => 'Salle B'];
    }

    public function test_401_et_403_professeur(): void
    {
        $this->annee();
        $this->postJson('/api/classes/stage/apercu', ['date_debut' => '2026-10-12'])->assertStatus(401);
        $this->postJson('/api/classes/stage', [])->assertStatus(401);

        $this->actingAsRole('professeur');
        $this->postJson('/api/classes/stage/apercu', ['date_debut' => '2026-10-12'])->assertStatus(403);
        $this->postJson('/api/classes/stage', $this->payload())->assertStatus(403);
    }

    public function test_apercu_par_defaut_cinq_matins_ramenes_au_lundi(): void
    {
        $this->annee();
        $this->actingAsRole('directeur');

        $this->postJson('/api/classes/stage/apercu', ['date_debut' => '2026-10-14'])
            ->assertOk()
            ->assertJsonPath('data.date_debut', '2026-10-12')
            ->assertJsonPath('data.date_fin', '2026-10-16')
            ->assertJsonPath('data.nb_seances', 5)
            ->assertJsonPath('data.duree_totale_minutes', 900)
            ->assertJsonPath('data.seances.0.heure_debut', '09:00')
            ->assertJsonPath('data.seances.4.date', '2026-10-16')
            ->assertJsonPath('data.blocage', null);
    }

    public function test_ferie_decoche_par_defaut_et_avertissement_si_recoche(): void
    {
        $annee = $this->annee();
        $this->importFwb($annee);
        $this->actingAsRole('directeur');

        // Mercredi 11/11/2026 : Armistice.
        $this->postJson('/api/classes/stage/apercu', ['date_debut' => '2026-11-09'])
            ->assertOk()
            ->assertJsonPath('data.nb_seances', 4)
            ->assertJsonPath('data.jours.2.ferme', true)
            ->assertJsonPath('data.jours.2.actif', false)
            ->assertJsonPath('data.avertissements', []);

        $jours = collect(range(9, 13))->map(fn ($d) => ['date' => sprintf('2026-11-%02d', $d), 'demi_journee' => 'matin'])->all();
        $reponse = $this->postJson('/api/classes/stage/apercu', ['date_debut' => '2026-11-09', 'jours' => $jours])
            ->assertOk()
            ->assertJsonPath('data.nb_seances', 5);
        $this->assertCount(1, $reponse->json('data.avertissements'));
    }

    public function test_creation_de_trois_seances_matin_et_apres_midi(): void
    {
        $this->annee();
        $this->actingAsRole('directeur');
        $payload = $this->payload(['jours' => [
            ['date' => '2026-10-12', 'demi_journee' => 'matin'],
            ['date' => '2026-10-14', 'demi_journee' => 'apres_midi'],
            ['date' => '2026-10-16', 'demi_journee' => 'matin'],
        ]]);

        $this->postJson('/api/classes/stage', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'stage')
            ->assertJsonPath('data.nb_sessions', 3)
            ->assertJsonPath('data.semaine.debut', '2026-10-12')
            ->assertJsonPath('data.periodes.0.numero', null)
            ->assertJsonPath('data.titre', 'Minecraft Redstone')
            ->assertJsonPath('data.periodes.0.cours_id', null)
            ->assertJsonPath('data.periodes.0.stage_id', $payload['stage_id'])
            ->assertJsonPath('data.periodes.0.cours.titre', 'Minecraft Redstone')
            ->assertJsonPath('data.prochaine_session.libelle', 'Séance 1');

        $classe = Classe::where('type', 'stage')->firstOrFail();
        $sessions = CourseSession::where('classe_id', $classe->id)->orderBy('seance_numero')->get();
        $this->assertSame([1, 2, 3], $sessions->pluck('seance_numero')->all());
        $this->assertSame('13:30:00', $sessions[1]->heure_debut);
        $this->assertSame('16:30:00', $sessions[1]->heure_fin);
        $this->assertSame('Salle B', $sessions[1]->lieu);
    }

    public function test_creneaux_personnalises(): void
    {
        $this->annee();
        $this->actingAsRole('directeur');

        $this->postJson('/api/classes/stage/apercu', ['date_debut' => '2026-10-12', 'creneaux' => ['matin' => ['heure_debut' => '08:30', 'heure_fin' => '11:30']]])
            ->assertOk()->assertJsonPath('data.seances.0.heure_debut', '08:30');

        $this->postJson('/api/classes/stage/apercu', ['date_debut' => '2026-10-12', 'creneaux' => ['matin' => ['heure_debut' => '12:00', 'heure_fin' => '09:00']]])
            ->assertStatus(422)->assertJsonValidationErrors('creneaux.matin.heure_fin');
    }

    public function test_theme_obligatoire_et_inexistant_refuse_et_pas_de_cours(): void
    {
        $this->annee();
        $this->actingAsRole('directeur');
        $jours = ['jours' => [['date' => '2026-10-12', 'demi_journee' => 'matin']]];

        $this->postJson('/api/classes/stage', ['date_debut' => '2026-10-12', 'lieu' => 'X'] + $jours)
            ->assertStatus(422)->assertJsonValidationErrors('stage_id');
        $this->postJson('/api/classes/stage', ['date_debut' => '2026-10-12', 'stage_id' => 9999] + $jours)
            ->assertStatus(422)->assertJsonValidationErrors('stage_id');
        $this->assertSame(0, Classe::count());
    }

    public function test_aucun_jour_coche_ou_jour_hors_semaine_refuse(): void
    {
        $this->annee();
        $this->actingAsRole('directeur');

        $this->postJson('/api/classes/stage', $this->payload(['jours' => []]))->assertStatus(422)->assertJsonValidationErrors('jours');
        $this->postJson('/api/classes/stage', $this->payload(['jours' => [['date' => '2026-10-19', 'demi_journee' => 'matin']]]))
            ->assertStatus(422)->assertJsonValidationErrors('jours.0.date');
        $this->postJson('/api/classes/stage', $this->payload(['jours' => [['date' => '2026-10-12', 'demi_journee' => 'soir']]]))
            ->assertStatus(422)->assertJsonValidationErrors('jours.0.demi_journee');
        $this->assertSame(0, Classe::count());
    }

    public function test_stage_d_ete_hors_annee_utilise_la_derniere_annee(): void
    {
        $annee = $this->annee();
        $this->actingAsRole('directeur');

        $this->postJson('/api/classes/stage', $this->payload(['date_debut' => '2027-07-12', 'jours' => [['date' => '2027-07-12', 'demi_journee' => 'matin']]]))
            ->assertStatus(201)
            ->assertJsonPath('data.annee_scolaire_id', $annee->id);
    }

    public function test_liste_filtre_par_type_et_les_classes_restent_inchangees(): void
    {
        $annee = $this->annee();
        $this->classeAvecSessions($annee);
        $this->actingAsRole('directeur');
        $this->postJson('/api/classes/stage', $this->payload(['jours' => [['date' => '2026-10-12', 'demi_journee' => 'matin']]]))->assertStatus(201);

        $this->getJson('/api/classes')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/classes?type=stage')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'stage');
        $this->getJson('/api/classes?type=classe')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'classe');
    }

    public function test_professeur_assigne_voit_son_stage_et_ses_seances(): void
    {
        $this->annee();
        $directeur = $this->actingAsRole('directeur');
        $id = $this->postJson('/api/classes/stage', $this->payload(['jours' => [
            ['date' => '2026-10-12', 'demi_journee' => 'matin'], ['date' => '2026-10-13', 'demi_journee' => 'matin'],
        ]]))->assertStatus(201)->json('data.id');

        $professeur = Professeur::factory()->create();
        $this->postJson("/api/classes/{$id}/professeurs", ['professeur_id' => $professeur->id, 'date_debut' => '2026-10-12'])->assertSuccessful();
        $this->assertSame(2, \App\Models\SessionProfesseur::where('professeur_id', $professeur->id)->count());

        \Laravel\Sanctum\Sanctum::actingAs($professeur->user);
        $this->getJson('/api/mes-classes')->assertOk()->assertJsonFragment(['id' => $id]);
        $this->getJson("/api/mes-classes/{$id}/sessions")->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.cours.titre', 'Minecraft Redstone')
            ->assertJsonPath('data.0.heures_defrayables.source', 'defaut');
        $this->postJson('/api/classes/stage', $this->payload())->assertStatus(403);
    }

    public function test_regles_specifiques_aux_stages(): void
    {
        $this->annee();
        $this->actingAsRole('directeur');
        $id = $this->postJson('/api/classes/stage', $this->payload(['jours' => [
            ['date' => '2026-10-12', 'demi_journee' => 'matin'], ['date' => '2026-10-13', 'demi_journee' => 'matin'],
        ]]))->json('data.id');
        $sessionId = CourseSession::where('classe_id', $id)->where('seance_numero', 1)->value('id');

        // Horaires par séance, pas pour le stage ; le lieu reste modifiable.
        $this->putJson("/api/classes/{$id}", ['heure_debut' => '10:00', 'heure_fin' => '11:00'])->assertStatus(422);
        $this->putJson("/api/classes/{$id}", ['lieu' => 'Salle C'])->assertOk();
        // Le thème ne se change pas comme un cours.
        $periodeId = \App\Models\ClassePeriode::where('classe_id', $id)->value('id');
        $this->putJson("/api/classes/{$id}/periodes/{$periodeId}", ['cours_id' => \App\Models\Cours::factory()->create()->id])->assertStatus(422);
        // Pas de période à ajouter, ni de duplication, ni de décalage des suivantes.
        $this->getJson("/api/classes/{$id}/duplication")->assertStatus(422);
        $this->putJson("/api/sessions/{$sessionId}", ['date' => '2026-10-14', 'decaler_suivantes' => true])->assertStatus(422);
        // Déplacer une seule séance reste possible.
        $this->putJson("/api/sessions/{$sessionId}", ['date' => '2026-10-12', 'heure_debut' => '10:00', 'heure_fin' => '12:00'])->assertOk();
    }
}
