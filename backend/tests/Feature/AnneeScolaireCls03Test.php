<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\CourseSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** CLS-03 : gestion des années scolaires et des dates de période (AC-1 à AC-12). P1 24/08→19/02, P2 22/02→02/07. */
class AnneeScolaireCls03Test extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->annee = $this->annee();
        $this->actingAsRole('directeur');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function url(string $suffixe = '', ?AnneeScolaire $annee = null): string
    {
        return '/api/annees-scolaires/'.($annee ?? $this->annee)->id.$suffixe;
    }

    private function periodes(string $finP1 = '2027-02-19', string $debutP1 = '2026-08-24', string $debutP2 = '2027-02-22', string $finP2 = '2027-07-02'): array
    {
        return [
            ['numero' => 1, 'date_debut' => $debutP1, 'date_fin' => $finP1],
            ['numero' => 2, 'date_debut' => $debutP2, 'date_fin' => $finP2],
        ];
    }

    private function version(?AnneeScolaire $annee = null): string
    {
        return ($annee ?? $this->annee)->fresh()->updated_at->toIso8601String();
    }

    private function payloadSuivante(array $overrides = []): array
    {
        return $overrides + [
            'libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-06-30',
            'periodes' => [
                ['numero' => 1, 'date_debut' => '2027-09-01', 'date_fin' => '2028-01-31'],
                ['numero' => 2, 'date_debut' => '2028-02-07', 'date_fin' => '2028-06-30'],
            ],
        ];
    }

    public function test_ac1_proposition_fwb_ou_defaut(): void
    {
        // Libellé par défaut = année suivant la dernière (2026-2027 → 2027-2028), sans calendrier FWB → défaut.
        $this->getJson('/api/annees-scolaires/proposition')->assertOk()
            ->assertJsonPath('data.libelle', '2027-2028')
            ->assertJsonPath('data.source', 'defaut')
            ->assertJsonPath('data.message', 'Proposition par défaut : calendrier FWB non importé.')
            ->assertJsonPath('data.date_debut', '2027-09-01')
            ->assertJsonPath('data.date_fin', '2028-06-30')
            ->assertJsonPath('data.periodes.0.date_fin', '2028-01-31')
            ->assertJsonPath('data.periodes.1.date_debut', '2028-02-01');

        // Calendrier FWB connu (fichier du dépôt) : P1 jusqu'au vendredi avant Carnaval, P2 dès le lundi de reprise.
        $this->getJson('/api/annees-scolaires/proposition?libelle=2026-2027')->assertOk()
            ->assertJsonPath('data.source', 'fwb')
            ->assertJsonPath('data.message', null)
            ->assertJsonPath('data.periodes.0.date_debut', '2026-09-01')
            ->assertJsonPath('data.periodes.0.date_fin', '2027-02-19')
            ->assertJsonPath('data.periodes.1.date_debut', '2027-03-08')
            ->assertJsonPath('data.periodes.1.date_fin', '2027-07-02');

        // Même résultat quand le calendrier est importé en base pour une année existante.
        $this->importFwb($this->annee);
        $this->getJson('/api/annees-scolaires/proposition?libelle=2026-2027')->assertOk()->assertJsonPath('data.source', 'fwb');

        $this->getJson('/api/annees-scolaires/proposition?libelle=abc')->assertStatus(422);
    }

    public function test_ac2_creation_ressource_enrichie(): void
    {
        $user = User::first();
        $this->postJson('/api/annees-scolaires', $this->payloadSuivante())->assertStatus(201)
            ->assertJsonCount(2, 'data.periodes')
            ->assertJsonPath('data.classes_count', 0)
            ->assertJsonPath('data.sessions_count', 0)
            ->assertJsonPath('data.en_cours', false)
            ->assertJsonPath('data.updated_by.id', $user->id)
            ->assertJsonPath('data.can.delete', true)
            ->assertJsonPath('data.can.raison_non_supprimable', null)
            ->assertJsonPath('avertissements', []);
    }

    public function test_ac3_refus_de_coherence_et_avertissement_trou(): void
    {
        // P2 <= fin de P1 : message avec la date au plus tôt
        $this->postJson('/api/annees-scolaires', $this->payloadSuivante(['periodes' => [
            ['numero' => 1, 'date_debut' => '2027-09-01', 'date_fin' => '2028-02-18'],
            ['numero' => 2, 'date_debut' => '2028-02-18', 'date_fin' => '2028-06-30'],
        ]]))->assertStatus(422)->assertJsonPath('errors.periodes.0', 'La période 2 doit commencer après la fin de la période 1 (18/02/2028). Au plus tôt le 19/02/2028.');

        // fin <= début
        $this->postJson('/api/annees-scolaires', $this->payloadSuivante(['periodes' => [
            ['numero' => 1, 'date_debut' => '2027-09-01', 'date_fin' => '2027-09-01'],
            ['numero' => 2, 'date_debut' => '2028-02-07', 'date_fin' => '2028-06-30'],
        ]]))->assertStatus(422)->assertJsonPath('errors.periodes.0', 'La fin de la période 1 doit être postérieure à son début.');

        // chevauchement avec 2026-2027
        $this->postJson('/api/annees-scolaires', $this->payloadSuivante(['date_debut' => '2027-06-15', 'periodes' => [
            ['numero' => 1, 'date_debut' => '2027-06-15', 'date_fin' => '2028-01-31'],
            ['numero' => 2, 'date_debut' => '2028-02-07', 'date_fin' => '2028-06-30'],
        ]]))->assertStatus(422)->assertJsonPath('errors.periodes.0', 'Cette année chevauche 2026-2027 (qui se termine le 02/07/2027).');

        // libellé déjà utilisé
        $this->postJson('/api/annees-scolaires', $this->payloadSuivante(['libelle' => '2026-2027']))->assertStatus(422)->assertJsonValidationErrors(['libelle']);
        $this->assertSame(1, AnneeScolaire::count());

        // trou > 6 semaines : accepté, avec avertissement (44 jours : 20/01/2028 → 04/03/2028)
        $this->postJson('/api/annees-scolaires', $this->payloadSuivante(['periodes' => [
            ['numero' => 1, 'date_debut' => '2027-09-01', 'date_fin' => '2028-01-20'],
            ['numero' => 2, 'date_debut' => '2028-03-05', 'date_fin' => '2028-06-30'],
        ]]))->assertStatus(201)
            ->assertJsonPath('avertissements.0', '44 jours sans période : aucune classe ne pourra démarrer dans cet intervalle.');
    }

    public function test_ac3_modification_refuse_chevauchement_et_exige_la_version(): void
    {
        $suivante = AnneeScolaire::factory()->avecPeriodes()->create(['libelle' => '2027-2028', 'date_debut' => '2027-08-24', 'date_fin' => '2028-07-02']);

        // allonger P2 de 2026-2027 sur 2027-2028
        $this->putJson($this->url(), ['version' => $this->version(), 'periodes' => $this->periodes(finP2: '2027-09-10')])
            ->assertStatus(422)->assertJsonPath('errors.periodes.0', 'Cette année chevauche 2027-2028 (qui se termine le 02/07/2028).');

        $this->putJson($this->url(), ['periodes' => $this->periodes()])->assertStatus(422)->assertJsonValidationErrors(['version']);
        $this->putJson($this->url(), ['statut' => 'brouillon'])->assertOk()->assertJsonPath('data.statut', 'brouillon'); // statut seul : pas de version

        // l'année suit P1 début → P2 fin quand seules les périodes sont envoyées
        $this->putJson($this->url(), ['version' => $this->version(), 'periodes' => $this->periodes(debutP1: '2026-08-31', finP2: '2027-06-30')])
            ->assertOk()->assertJsonPath('data.date_debut', '2026-08-31')->assertJsonPath('data.date_fin', '2027-06-30');
        $this->assertNotNull($suivante->id);
    }

    public function test_ac4_raccourcir_est_accepte_et_les_seances_ressortent_hors_periode(): void
    {
        $classe = $this->classeAvecSessions($this->annee); // 14 séances du 07/10/2026 au 06/01/2027

        $this->putJson($this->url(), ['version' => $this->version(), 'periodes' => $this->periodes(finP1: '2026-12-18')])->assertOk();

        $sessions = $this->getJson("/api/classes/{$classe->id}/sessions")->assertOk()->json('data');
        $this->assertSame(3, collect($sessions)->where('hors_periode', true)->count());
        $this->getJson("/api/classes/{$classe->id}")->assertJsonPath('data.periodes.0.nb_hors_periode', 3);

        // réversible : rétablir la fin supprime les marqueurs
        $this->putJson($this->url(), ['version' => $this->version(), 'periodes' => $this->periodes()])->assertOk();
        $this->getJson("/api/classes/{$classe->id}")->assertJsonPath('data.periodes.0.nb_hors_periode', 0);
    }

    public function test_ac5_apercu_identique_au_calcul_reel_et_sans_ecriture(): void
    {
        $classe = $this->classeAvecSessions($this->annee); // P1 démarre le 07/10/2026, dernière séance 06/01/2027
        $avant = $this->annee->fresh();

        $r = $this->postJson($this->url('/apercu-impact'), ['periodes' => $this->periodes(finP1: '2026-12-18', debutP1: '2026-10-14')])->assertOk()
            ->assertJsonPath('data.bloquants', [])
            ->assertJsonPath('data.classes_touchees', 1)
            ->assertJsonPath('data.seances_hors_periode_en_plus', 3)
            ->assertJsonPath('data.seances_redevenant_dans_periode', 0)
            ->assertJsonPath('data.classes_demarrant_avant_debut', 1)
            ->assertJsonPath('data.periodes.0.evolution', 'début +51 j · fin −63 j')
            ->assertJsonPath('data.periodes.1.evolution', 'inchangée')
            ->assertJsonPath('data.par_classe.0.type', 'hors_periode')
            ->assertJsonPath('data.par_classe.0.nb_seances', 3)
            ->assertJsonPath('data.par_classe.0.dates', ['2026-12-23', '2026-12-30', '2027-01-06'])
            ->assertJsonPath('data.par_classe.0.creneau', 'mercredi 14h–17h')
            ->assertJsonPath('data.par_classe.1.type', 'demarre_avant_debut');
        $this->assertSame($classe->id, $r->json('data.par_classe.0.classe_id'));

        // lecture seule : rien n'a changé
        $this->assertSame($this->version(), $avant->updated_at->toIso8601String());
        $this->assertSame('2027-02-19', $this->annee->periodes()->where('numero', 1)->first()->date_fin->toDateString());

        // le calcul réel après enregistrement donne les mêmes nombres
        $this->putJson($this->url(), ['version' => $this->version(), 'periodes' => $this->periodes(finP1: '2026-12-18', debutP1: '2026-10-14')])->assertOk();
        $this->getJson("/api/classes/{$classe->id}")->assertJsonPath('data.periodes.0.nb_hors_periode', $r->json('data.seances_hors_periode_en_plus'));

        // allonger : les séances redeviennent dans la période
        $this->postJson($this->url('/apercu-impact'), ['periodes' => $this->periodes()])->assertOk()
            ->assertJsonPath('data.seances_hors_periode_en_plus', 0)
            ->assertJsonPath('data.seances_redevenant_dans_periode', 3)
            ->assertJsonPath('data.classes_touchees', 0);
    }

    public function test_ac5_apercu_bloquants_et_alerte_p2_et_calendrier(): void
    {
        $classe = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id]); // P1 seule
        $this->importFwb($this->annee);

        // P2 <= fin de P1 : bloquant, compteurs à zéro
        $this->postJson($this->url('/apercu-impact'), ['periodes' => $this->periodes(finP1: '2027-03-10')])->assertOk()
            ->assertJsonPath('data.bloquants.0', 'La période 2 doit commencer après la fin de la période 1 (10/03/2027). Au plus tôt le 11/03/2027.')
            ->assertJsonPath('data.classes_touchees', 0)
            ->assertJsonPath('data.par_classe', []);

        // classes sans P2 chiffrées ; mêmes règles que l'alerte réelle (la dernière séance de P1 est le 06/01/2027)
        $this->postJson($this->url('/apercu-impact'), ['periodes' => $this->periodes()])->assertOk()
            ->assertJsonPath('data.classes_sans_p2', 1)
            ->assertJsonPath('data.alertes_p2_creees', 0)
            ->assertJsonPath('data.alertes_p2_supprimees', 0);

        // calendrier hors année : l'année se termine avant des vacances du calendrier
        $this->postJson($this->url('/apercu-impact'), ['periodes' => $this->periodes(finP2: '2027-04-01')])->assertOk()
            ->assertJsonPath('data.calendrier_hors_annee', 4);

        $this->postJson($this->url('/apercu-impact'), [])->assertStatus(422);
        $this->assertNotNull($classe->id);
    }

    public function test_ac6_modification_concurrente_409(): void
    {
        $v0 = $this->version();
        $this->putJson($this->url(), ['version' => $v0, 'periodes' => $this->periodes(finP2: '2027-06-25')])->assertOk();

        $autre = User::factory()->admin()->create(['name' => 'Sophie Martin']);
        Sanctum::actingAs($autre);
        $this->putJson($this->url(), ['version' => $v0, 'periodes' => $this->periodes(finP2: '2027-06-18')])
            ->assertStatus(409)
            ->assertJsonPath('code', 'modification_concurrente')
            ->assertJsonPath('modifie_par.name', User::where('id', '!=', $autre->id)->first()->name)
            ->assertJsonPath('annee.periodes.1.date_fin', '2027-06-25')
            ->assertJsonStructure(['message', 'modifie_a', 'annee' => ['id', 'updated_at']]);

        $this->assertSame('2027-06-25', $this->annee->periodes()->where('numero', 2)->first()->date_fin->toDateString());

        // avec la version à jour, le second utilisateur peut enregistrer, et l'auteur est tracé
        $this->putJson($this->url(), ['version' => $this->version(), 'periodes' => $this->periodes(finP2: '2027-06-18')])
            ->assertOk()->assertJsonPath('data.updated_by.name', 'Sophie Martin');
    }

    public function test_ac7_annee_archivee_dates_verrouillees_et_classes_refusees(): void
    {
        $classe = $this->classeAvecSessions($this->annee);
        $sessionsAvant = CourseSession::pluck('date', 'id')->map->toDateString()->all();
        $this->putJson($this->url(), ['statut' => 'archivee'])->assertOk();

        $this->putJson($this->url(), ['version' => $this->version(), 'periodes' => $this->periodes(finP1: '2026-12-18')])
            ->assertStatus(409)->assertJsonPath('code', 'annee_archivee')->assertJsonPath('message', "Réactivez l'année pour modifier ses dates.");
        $this->putJson($this->url(), ['libelle' => '2026-2028'])->assertStatus(409)->assertJsonPath('code', 'annee_archivee');
        $this->assertSame('2027-02-19', $this->annee->periodes()->where('numero', 1)->first()->date_fin->toDateString());
        $this->assertSame($sessionsAvant, CourseSession::pluck('date', 'id')->map->toDateString()->all());

        // création de classe / ajout de période refusés
        $cours = Cours::factory()->create();
        $p = fn (int $n) => $this->annee->periodes->firstWhere('numero', $n)->id;
        $payload = $this->donneesClasse($this->annee, ['cours_id' => $cours->id, 'date_premiere_session' => '2026-10-14']);
        $this->postJson('/api/classes', $payload)->assertStatus(422)->assertJsonPath('message', 'Cette année scolaire est archivée.');
        $this->postJson('/api/classes/apercu', $payload)->assertStatus(422)->assertJsonPath('message', 'Cette année scolaire est archivée.');
        $this->postJson("/api/classes/{$classe->id}/periodes", ['periode_id' => $p(2), 'cours_id' => $cours->id, 'date_premiere_session' => '2027-03-03'])
            ->assertStatus(422)->assertJsonPath('message', 'Cette année scolaire est archivée.');

        // l'alerte « P2 à planifier » ignore les années archivées
        Carbon::setTestNow('2026-12-20 10:00:00');
        $this->getJson("/api/classes/{$classe->id}")->assertJsonPath('data.alerte_periode_2', null);
        $this->postJson($this->url('/reactiver'))->assertOk();
        $this->getJson("/api/classes/{$classe->id}")->assertJsonPath('data.alerte_periode_2.jours_restants', 17);

        // le statut seul peut changer sur une année archivée (PUT {statut:"active"})
        $this->putJson($this->url(), ['statut' => 'archivee'])->assertOk();
        $this->putJson($this->url(), ['statut' => 'active'])->assertOk()->assertJsonPath('data.statut', 'active');
    }

    public function test_ac8_archiver_puis_reactiver_sans_toucher_aux_seances(): void
    {
        $classe = $this->classeAvecSessions($this->annee);
        $sessions = CourseSession::count();

        $this->postJson($this->url('/archiver'))->assertOk()->assertJsonPath('data.statut', 'archivee')->assertJsonPath('data.classes_count', 1)
            ->assertJsonPath('data.sessions_count', 14);
        $this->postJson($this->url('/archiver'))->assertOk()->assertJsonPath('data.statut', 'archivee'); // idempotent
        $this->assertSame($sessions, CourseSession::count());
        $this->assertSame('active', $classe->fresh()->statut);

        $this->postJson($this->url('/reactiver'))->assertOk()->assertJsonPath('data.statut', 'active');
        $this->assertSame($sessions, CourseSession::count());
    }

    public function test_ac9_suppression_refusee_avec_classes_et_compteurs(): void
    {
        $this->classeAvecSessions($this->annee);
        $this->deleteJson($this->url())->assertStatus(409)
            ->assertJsonPath('code', 'annee_non_supprimable')
            ->assertJsonPath('classes_count', 1)
            ->assertJsonPath('sessions_count', 14)
            ->assertJsonPath('message', 'Contient 1 classe (14 séances) : archivez-la plutôt.');

        $vide = AnneeScolaire::factory()->avecPeriodes()->create();
        $this->deleteJson($this->url('', $vide))->assertStatus(204);
    }

    public function test_ac10_date_hors_bornes_code_et_contexte(): void
    {
        $classe = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id]);
        $cours = Cours::factory()->create();
        $p2 = $this->annee->periodes->firstWhere('numero', 2);
        $contexte = ['annee_id' => $this->annee->id, 'annee_libelle' => '2026-2027', 'numero' => 2, 'debut' => '2027-02-22', 'fin' => '2027-07-02'];

        $creation = $this->donneesClasse($this->annee, ['periode_id' => $p2->id, 'cours_id' => $cours->id, 'date_premiere_session' => '2027-07-07']);
        foreach (['/api/classes', '/api/classes/apercu'] as $route) {
            $this->postJson($route, $creation)->assertStatus(422)
                ->assertJsonPath('code', 'date_hors_bornes_periode')
                ->assertJsonPath('contexte', $contexte)
                ->assertJsonValidationErrors(['periodes.0.date_premiere_session']);
        }

        $ajout = ['periode_id' => $p2->id, 'cours_id' => $cours->id, 'date_premiere_session' => '2027-07-07'];
        foreach (["/api/classes/{$classe->id}/periodes", "/api/classes/{$classe->id}/periodes/apercu"] as $route) {
            $this->postJson($route, $ajout)->assertStatus(422)
                ->assertJsonPath('code', 'date_hors_bornes_periode')
                ->assertJsonPath('contexte', $contexte)
                ->assertJsonValidationErrors(['date_premiere_session']);
        }

        // modifier les dates de la période débloque la saisie
        $this->putJson($this->url(), ['version' => $this->version(), 'periodes' => $this->periodes(finP2: '2027-07-30')])->assertOk();
        $this->postJson("/api/classes/{$classe->id}/periodes/apercu", $ajout)->assertOk();
    }

    public function test_ac11_professeur_403_sur_toutes_les_routes(): void
    {
        $this->actingAsRole('professeur');
        $this->getJson('/api/annees-scolaires/proposition')->assertStatus(403);
        $this->postJson($this->url('/apercu-impact'), ['periodes' => $this->periodes()])->assertStatus(403);
        $this->postJson($this->url('/archiver'))->assertStatus(403);
        $this->postJson($this->url('/reactiver'))->assertStatus(403);
        $this->putJson($this->url(), ['statut' => 'archivee'])->assertStatus(403);
        $this->deleteJson($this->url())->assertStatus(403);
        $this->getJson('/api/annees-scolaires')->assertStatus(403);
    }

    public function test_ac12_liste_enrichie(): void
    {
        $this->classeAvecSessions($this->annee);
        $this->importFwb($this->annee);
        $vide = AnneeScolaire::factory()->avecPeriodes()->create(['libelle' => '2040-2041', 'date_debut' => '2040-08-24', 'date_fin' => '2041-07-02']);

        $liste = $this->getJson('/api/annees-scolaires')->assertOk()->json('data');
        $this->assertSame([$vide->id, $this->annee->id], array_column($liste, 'id')); // plus récente d'abord

        [$futur, $courante] = $liste;
        $this->assertSame(1, $courante['classes_count']);
        $this->assertSame(14, $courante['sessions_count']);
        $this->assertGreaterThan(0, $courante['calendrier_count']);
        $this->assertTrue($courante['en_cours']);
        $this->assertFalse($courante['can']['delete']);
        $this->assertSame('Contient 1 classe (14 séances) : archivez-la plutôt.', $courante['can']['raison_non_supprimable']);
        $this->assertNotNull($courante['updated_at']);
        $this->assertArrayHasKey('updated_by', $courante);
        $this->assertTrue($courante['can']['archiver']);

        $this->assertFalse($futur['en_cours']);
        $this->assertTrue($futur['can']['delete']);
        $this->assertNull($futur['can']['raison_non_supprimable']);
        $this->assertSame(0, $futur['calendrier_count']);
    }
}
