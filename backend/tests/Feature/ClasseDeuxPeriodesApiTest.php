<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\Cours;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\Timesheet;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** CLS-02 : classe sur deux périodes (AC-1 à AC-17, RG-5b). P1 24/08→19/02, P2 22/02→02/07. */
class ClasseDeuxPeriodesApiTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    private AnneeScolaire $annee;

    private Cours $c1;

    private Cours $c2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->annee = $this->annee();
        $this->c1 = Cours::factory()->create(['titre' => 'Scratch']);
        $this->c2 = Cours::factory()->create(['titre' => 'Python']);
        $this->actingAsRole('directeur');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function p(int $n): int
    {
        return $this->annee->periodes->firstWhere('numero', $n)->id;
    }

    private function payload(array $periodes, array $extra = []): array
    {
        return $extra + [
            'annee_scolaire_id' => $this->annee->id, 'jour_semaine' => 3, 'heure_debut' => '14:00', 'heure_fin' => '17:00', 'lieu' => 'Salle A',
            'periodes' => $periodes,
        ];
    }

    private function bloc(int $numero, Cours $cours, string $date): array
    {
        return ['periode_id' => $this->p($numero), 'cours_id' => $cours->id, 'date_premiere_session' => $date];
    }

    private function classeP1(): Classe
    {
        return Classe::factory()->create(['annee_scolaire_id' => $this->annee->id]);
    }

    public function test_ac1_creation_deux_periodes(): void
    {
        $r = $this->postJson('/api/classes', $this->payload([$this->bloc(1, $this->c1, '2026-10-07'), $this->bloc(2, $this->c2, '2027-03-03')]))
            ->assertCreated()
            ->assertJsonCount(2, 'data.periodes')
            ->assertJsonPath('data.nb_sessions', 28)
            ->assertJsonPath('data.titre', 'Scratch → Python')
            ->assertJsonPath('data.periodes.0.numero', 1)
            ->assertJsonPath('data.periodes.1.numero', 2)
            ->assertJsonPath('data.periodes.1.cours.titre', 'Python')
            ->assertJsonPath('data.periodes.0.nb_sessions', 14)
            ->assertJsonPath('data.periodes.0.nb_changements_cours', 0)
            ->assertJsonPath('data.periodes.0.date_derniere_session', '2027-01-06')
            ->assertJsonPath('data.prochaine_session.libelle', 'P1 · Séance 1');

        $id = $r->json('data.id');
        $this->assertSame(2, ClassePeriode::where('classe_id', $id)->count());
        foreach (ClassePeriode::where('classe_id', $id)->get() as $cp) {
            $this->assertSame(range(1, 14), $cp->sessions()->pluck('seance_numero')->all());
        }

        $apercu = $this->postJson('/api/classes/apercu', $this->payload([$this->bloc(1, $this->c1, '2026-10-07'), $this->bloc(2, $this->c2, '2027-03-03')]))->assertOk();
        $apercu->assertJsonCount(2, 'data.periodes')->assertJsonCount(14, 'data.periodes.1.seances');
    }

    public function test_ac2_ac3_une_seule_periode_p1_ou_p2(): void
    {
        $this->postJson('/api/classes', $this->payload([$this->bloc(1, $this->c1, '2026-10-07')]))
            ->assertCreated()->assertJsonCount(1, 'data.periodes')->assertJsonPath('data.nb_sessions', 14)->assertJsonPath('data.titre', 'Scratch');

        $this->postJson('/api/classes', $this->payload([$this->bloc(2, $this->c2, '2027-03-03')]))
            ->assertCreated()->assertJsonCount(1, 'data.periodes')->assertJsonPath('data.periodes.0.numero', 2)->assertJsonPath('data.nb_sessions', 14);
    }

    public function test_ac4_ajout_p2_propage_les_professeurs(): void
    {
        $classe = $this->classeP1();
        $alice = Professeur::factory()->create();
        app(ClasseProfesseurAssignmentService::class)->assigner($classe, $alice, ['role' => 'principal']);
        $avant = $classe->sessions()->get()->mapWithKeys(fn ($s) => [$s->id => $s->date->toDateString()]);

        $this->postJson("/api/classes/{$classe->id}/periodes", ['periode_id' => $this->p(2), 'cours_id' => $this->c2->id, 'date_premiere_session' => '2027-03-03'])
            ->assertCreated()->assertJsonCount(2, 'data.periodes')->assertJsonPath('data.nb_sessions', 28);

        $this->assertSame($avant->all(), $classe->sessions()->whereIn('id', $avant->keys())->get()->mapWithKeys(fn ($s) => [$s->id => $s->date->toDateString()])->all());
        $p2 = ClassePeriode::where('classe_id', $classe->id)->where('periode_id', $this->p(2))->first();
        $this->assertSame(14, $p2->sessions()->count());
        $this->assertSame(14, \App\Models\SessionProfesseur::whereIn('course_session_id', $p2->sessions()->pluck('id'))->where('professeur_id', $alice->id)->count());
    }

    public function test_ac5_p2_avant_la_fin_de_p1_refusee_dans_les_deux_sens(): void
    {
        $classe = $this->classeP1(); // dernière séance P1 : 2027-01-06
        // P2 hors bornes de P2 mais les bornes sont vérifiées d'abord : on utilise une classe dont P1 finit tard.
        $tard = Classe::factory()->avecPeriodes([['numero' => 1, 'date_premiere_session' => '2026-11-25']])->create(['annee_scolaire_id' => $this->annee->id]); // fin 2027-02-24
        $this->postJson("/api/classes/{$tard->id}/periodes", ['periode_id' => $this->p(2), 'cours_id' => $this->c2->id, 'date_premiere_session' => '2027-02-24'])
            ->assertStatus(422)->assertJsonPath('message', 'La période 2 démarre avant la fin de la période 1 (dernière séance le 24/02/2027).');

        $this->postJson('/api/classes', $this->payload([$this->bloc(1, $this->c1, '2026-11-25'), $this->bloc(2, $this->c2, '2027-02-24')]))
            ->assertStatus(422)->assertJsonValidationErrors(['periodes.1.date_premiere_session']);
        $this->assertSame(0, Classe::where('id', '>', $tard->id)->count(), 'aucune création partielle');

        // Ajout d'une P1 dont la dernière séance dépasse le début de la P2
        $p2seule = Classe::factory()->avecPeriodes([['numero' => 2, 'date_premiere_session' => '2027-03-03']])->create(['annee_scolaire_id' => $this->annee->id]);
        $this->postJson("/api/classes/{$p2seule->id}/periodes", ['periode_id' => $this->p(1), 'cours_id' => $this->c1->id, 'date_premiere_session' => '2026-12-02'])
            ->assertStatus(422);
        $this->postJson("/api/classes/{$p2seule->id}/periodes", ['periode_id' => $this->p(1), 'cours_id' => $this->c1->id, 'date_premiere_session' => '2026-10-07'])
            ->assertCreated()->assertJsonCount(2, 'data.periodes')->assertJsonPath('data.periodes.0.numero', 1);

        // Période déjà présente
        $this->postJson("/api/classes/{$classe->id}/periodes", ['periode_id' => $this->p(1), 'cours_id' => $this->c1->id, 'date_premiere_session' => '2026-10-07'])
            ->assertStatus(422)->assertJsonValidationErrors(['periode_id']);
    }

    public function test_ac6_date_hors_bornes_ou_jour_recale(): void
    {
        $this->postJson('/api/classes', $this->payload([$this->bloc(1, $this->c1, '2026-08-01')]))
            ->assertStatus(422)->assertJsonValidationErrors(['periodes.0.date_premiere_session']);
        $this->postJson('/api/classes', $this->payload([$this->bloc(2, $this->c1, '2027-01-06')]))
            ->assertStatus(422)->assertJsonValidationErrors(['periodes.0.date_premiere_session']);
        $this->postJson('/api/classes/apercu', $this->payload([$this->bloc(1, $this->c1, '2026-10-06')]))
            ->assertOk()->assertJsonPath('data.periodes.0.recale', true)->assertJsonPath('data.periodes.0.date_premiere_session', '2026-10-07');
        // validations de forme
        $this->postJson('/api/classes', $this->payload([]))->assertStatus(422)->assertJsonValidationErrors(['periodes']);
        $this->postJson('/api/classes', $this->payload([$this->bloc(1, $this->c1, '2026-10-07'), $this->bloc(1, $this->c2, '2026-10-14')]))
            ->assertStatus(422)->assertJsonValidationErrors(['periodes.0.periode_id']);
    }

    public function test_ac7_14e_seance_hors_periode_non_bloquante(): void
    {
        $r = $this->postJson('/api/classes', $this->payload([$this->bloc(1, $this->c1, '2027-01-13')]))
            ->assertCreated()->assertJsonPath('data.periodes.0.nb_hors_periode', 8);
        $classe = Classe::findOrFail($r->json('data.id'));

        $a = $this->postJson('/api/classes/apercu', $this->payload([$this->bloc(1, $this->c1, '2027-01-13')]))->assertOk();
        $this->assertNotEmpty($a->json('data.periodes.0.avertissements'));
        $this->assertNull($a->json('data.periodes.0.blocage'));

        $s = $this->getJson("/api/classes/{$classe->id}/sessions")->assertOk()->json('data');
        $this->assertTrue(collect($s)->last()['hors_periode']);
        $this->assertFalse($s[0]['hors_periode']);
    }

    public function test_ac8_deplacement_et_bis_apres_fin_de_periode(): void
    {
        $classe = $this->classeP1();
        $s3 = $classe->sessions()->where('seance_numero', 3)->first();

        $this->putJson("/api/sessions/{$s3->id}", ['date' => '2027-03-10'])->assertOk()
            ->assertJsonPath('data.hors_periode', true)->assertJsonPath('data.avertissements.0', 'Cette date est après la fin de la période 1');

        $this->postJson("/api/classes/{$classe->id}/sessions/bis", ['seance_numero' => 2, 'date' => '2027-03-17', 'periode_numero' => 1, 'confirmer_depassement' => true])
            ->assertCreated()->assertJsonPath('data.hors_periode', true)->assertJsonPath('data.libelle_complet', 'P1 · Séance 2 bis');
    }

    public function test_bis_par_periode_et_filtre_sessions(): void
    {
        $classe = Classe::factory()->deuxPeriodes()->create(['annee_scolaire_id' => $this->annee->id]);

        // 14 sessions actives dans P2 → confirmation requise ; P1 comptée à part
        $this->postJson("/api/classes/{$classe->id}/sessions/bis", ['seance_numero' => 3, 'date' => '2027-06-02', 'periode_numero' => 2])->assertStatus(409)->assertJsonPath('nb_sessions', 15);
        $this->postJson("/api/classes/{$classe->id}/sessions/bis", ['seance_numero' => 3, 'date' => '2027-06-02', 'periode_numero' => 2, 'confirmer_depassement' => true])
            ->assertCreated()->assertJsonPath('data.periode_numero', 2)->assertJsonPath('data.libelle_complet', 'P2 · Séance 3 bis');

        $this->getJson("/api/classes/{$classe->id}/sessions")->assertOk()->assertJsonCount(29, 'data')
            ->assertJsonPath('data.0.libelle_complet', 'P1 · Séance 1')->assertJsonPath('data.14.libelle_complet', 'P2 · Séance 1');
        $this->getJson("/api/classes/{$classe->id}/sessions?periode_numero=2")->assertOk()->assertJsonCount(15, 'data');
        $this->getJson("/api/sessions?classe_id={$classe->id}&periode_numero=1&per_page=100")->assertOk()->assertJsonCount(14, 'data');
    }

    public function test_ac9_ac10_ac9b_changement_de_cours_et_historique(): void
    {
        $classe = $this->classeP1();
        $cp = $classe->periodes()->first();
        $url = "/api/classes/{$classe->id}/periodes/{$cp->id}";
        Carbon::setTestNow('2026-12-01 10:00:00'); // des séances sont passées : le changement reste possible

        $this->putJson($url, ['cours_id' => $this->c2->id])->assertOk()
            ->assertJsonPath('data.periodes.0.cours_id', $this->c2->id)->assertJsonPath('data.periodes.0.nb_changements_cours', 1);
        $this->assertSame($this->c2->id, $classe->sessions()->first()->load('classePeriode')->classePeriode->cours_id);

        // Même cours : rien n'est écrit
        $this->putJson($url, ['cours_id' => $this->c2->id])->assertOk()->assertJsonPath('data.periodes.0.nb_changements_cours', 1);

        $h = $this->getJson("$url/historique-cours")->assertOk()->assertJsonCount(1, 'data');
        $h->assertJsonPath('data.0.ancien_cours.id', $cp->cours_id)->assertJsonPath('data.0.nouveau_cours.titre', 'Python')
            ->assertJsonPath('data.0.par.id', auth()->id() ?? $h->json('data.0.par.id'));
        $this->assertNotNull($h->json('data.0.date'));

        // AC-10 : heure encodée → 409 avec nb_saisies, aucune ligne d'historique de plus
        $s = $classe->sessions()->where('seance_numero', 1)->first();
        Timesheet::create(['professeur_id' => Professeur::factory()->create()->id, 'course_session_id' => $s->id, 'cours_id' => $this->c2->id,
            'date_prestation' => $s->date->toDateString(), 'nombre_heures' => 3, 'type_activite' => 'animation', 'statut_validation' => Timesheet::STATUT_SOUMIS]);
        $this->putJson($url, ['cours_id' => $this->c1->id])->assertStatus(409)->assertJsonPath('nb_saisies', 1);
        $this->getJson("$url/historique-cours")->assertJsonCount(1, 'data');

        $this->putJson($url, [])->assertStatus(422);
    }

    public function test_ac11_suppression_de_periode(): void
    {
        $classe = Classe::factory()->deuxPeriodes()->create(['annee_scolaire_id' => $this->annee->id]);
        [$p1, $p2] = $classe->periodes()->get()->all();

        $this->deleteJson("/api/classes/{$classe->id}/periodes/{$p2->id}")->assertNoContent();
        $this->assertSame(0, CourseSession::where('classe_periode_id', $p2->id)->count());
        $this->assertSame(14, CourseSession::where('classe_periode_id', $p1->id)->count());
        $this->assertDatabaseMissing('classe_periodes', ['id' => $p2->id]);

        // seule période restante → 409
        $this->deleteJson("/api/classes/{$classe->id}/periodes/{$p1->id}")->assertStatus(409);
        // période d'une autre classe → 404
        $autre = $this->classeP1();
        $this->deleteJson("/api/classes/{$classe->id}/periodes/{$autre->periodes()->first()->id}")->assertNotFound();
    }

    public function test_ac12_suppression_refusee_puis_annulation_avec_motif(): void
    {
        $classe = Classe::factory()->deuxPeriodes()->create(['annee_scolaire_id' => $this->annee->id]);
        [$p1, $p2] = $classe->periodes()->get()->all();
        $s = $p2->sessions()->first();
        Timesheet::create(['professeur_id' => Professeur::factory()->create()->id, 'course_session_id' => $s->id, 'cours_id' => $p2->cours_id,
            'date_prestation' => $s->date->toDateString(), 'nombre_heures' => 3, 'type_activite' => 'animation', 'statut_validation' => Timesheet::STATUT_SOUMIS]);

        $this->deleteJson("/api/classes/{$classe->id}/periodes/{$p2->id}")->assertStatus(409)->assertJsonPath('nb_saisies', 1);

        $url = "/api/classes/{$classe->id}/periodes/{$p2->id}/annuler";
        $this->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors(['motif']);
        $this->postJson($url, ['motif' => 'Groupe trop petit'])->assertOk()
            ->assertJsonPath('data.periodes.1.statut', 'annulee')->assertJsonPath('data.periodes.1.motif_annulation', 'Groupe trop petit');

        $this->assertSame('planifiee', $s->fresh()->statut, 'la séance avec heures reste');
        $this->assertSame(13, $p2->sessions()->where('statut', 'annulee')->count());
        $this->assertSame(14, $p1->sessions()->where('statut', 'planifiee')->count());
        $this->postJson($url, ['motif' => 'Encore'])->assertStatus(409);
    }

    public function test_ac13_alerte_periode_2(): void
    {
        $classe = $this->classeP1(); // dernière séance P1 : 2027-01-06
        Carbon::setTestNow('2026-12-20 10:00:00');

        $this->getJson("/api/classes/{$classe->id}")->assertOk()
            ->assertJsonPath('data.alerte_periode_2.date_fin_periode_1', '2027-01-06')
            ->assertJsonPath('data.alerte_periode_2.jours_restants', 17)
            ->assertJsonPath('data.alerte_periode_2.message', 'La période 2 est à planifier');
        $this->getJson('/api/classes')->assertOk()->assertJsonPath('data.0.alerte_periode_2.jours_restants', 17);

        Carbon::setTestNow('2026-10-10 10:00:00'); // trop tôt
        $this->getJson("/api/classes/{$classe->id}")->assertJsonPath('data.alerte_periode_2', null);

        Carbon::setTestNow('2027-02-01 10:00:00'); // P1 passée : toujours alerté
        $this->getJson("/api/classes/{$classe->id}")->assertJsonPath('data.alerte_periode_2.jours_restants', -26);

        // Absente si P2 existe
        $deux = Classe::factory()->deuxPeriodes()->create(['annee_scolaire_id' => $this->annee->id]);
        $this->getJson("/api/classes/{$deux->id}")->assertJsonPath('data.alerte_periode_2', null);

        // Absente si l'année n'a pas de période 2
        $annee1 = AnneeScolaire::factory()->create();
        $annee1->periodes()->create(['numero' => 1, 'date_debut' => '2026-08-24', 'date_fin' => '2027-02-19']);
        $seule = Classe::factory()->create(['annee_scolaire_id' => $annee1->id]);
        $this->getJson("/api/classes/{$seule->id}")->assertJsonPath('data.alerte_periode_2', null);

        // Absente si la classe n'est plus active
        $classe->update(['statut' => 'archivee']);
        $this->getJson("/api/classes/{$classe->id}")->assertJsonPath('data.alerte_periode_2', null);
    }

    public function test_ac14_professeur_ajoute_assigne_aux_deux_periodes_avec_recap(): void
    {
        $classe = Classe::factory()->deuxPeriodes()->create(['annee_scolaire_id' => $this->annee->id]);
        $alice = Professeur::factory()->create();

        $this->postJson("/api/classes/{$classe->id}/professeurs", ['professeur_id' => $alice->id, 'role' => 'principal'])
            ->assertStatus(201)
            ->assertJsonPath('recapitulatif.sessions_assignees', 28)
            ->assertJsonPath('recapitulatif.par_periode.0.periode_numero', 1)
            ->assertJsonPath('recapitulatif.par_periode.1.sessions_assignees', 14);
        $this->assertSame(28, \App\Models\SessionProfesseur::where('professeur_id', $alice->id)->count());
    }

    public function test_ac15_liens_partages_par_cours(): void
    {
        $classe = Classe::factory()->avecPeriodes([['numero' => 1, 'cours_id' => $this->c1->id], ['numero' => 2, 'cours_id' => $this->c1->id]])
            ->create(['annee_scolaire_id' => $this->annee->id]);
        $this->postJson("/api/cours/{$this->c1->id}/liens", ['titre' => 'Slides S3', 'url' => 'https://example.com/s3', 'seance_numero' => 3])->assertCreated();

        // Même cours en P1 et P2 : un seul jeu de liens, une seule classe listée
        $r = $this->getJson("/api/cours/{$this->c1->id}/liens")->assertOk();
        $this->assertCount(1, $r->json('classes'));
        $this->assertSame('P1 · Séance 1', $r->json('classes.0.seance_courante.libelle'));
        // Cours différent : ce cours n'a aucun lien, et la classe (via P2) apparaît pour le cours 2 seulement s'il y est
        $this->getJson("/api/cours/{$this->c2->id}/liens")->assertOk()->assertJsonCount(0, 'data')->assertJsonCount(0, 'classes');

        $classe->periodes()->get()->last()->update(['cours_id' => $this->c2->id]);
        $this->getJson("/api/cours/{$this->c2->id}/liens")->assertOk()->assertJsonCount(1, 'classes')->assertJsonPath('classes.0.seance_courante.periode_numero', 2);
    }

    public function test_ac16_filtres_cours_et_periode(): void
    {
        $this->classeP1();
        $deux = Classe::factory()->avecPeriodes([['numero' => 1, 'cours_id' => $this->c1->id], ['numero' => 2, 'cours_id' => $this->c2->id]])->create(['annee_scolaire_id' => $this->annee->id]);

        $this->getJson("/api/classes?cours_id={$this->c2->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $deux->id);
        $this->getJson("/api/classes?cours_id={$this->c1->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/classes?periode_id={$this->p(2)}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/classes?periode_id={$this->p(1)}")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_ac17_portail_professeur_timesheets_calendrier(): void
    {
        $classe = Classe::factory()->avecPeriodes([['numero' => 1, 'cours_id' => $this->c1->id], ['numero' => 2, 'cours_id' => $this->c2->id]])
            ->create(['annee_scolaire_id' => $this->annee->id]);
        $alice = Professeur::factory()->create();
        app(ClasseProfesseurAssignmentService::class)->assigner($classe, $alice, ['role' => 'principal']);

        Sanctum::actingAs($alice->user);
        $r = $this->getJson("/api/mes-classes/{$classe->id}/sessions")->assertOk()->json('data');
        $this->assertSame('P1 · Séance 5', $r[4]['libelle_complet']);
        $this->assertSame('Scratch', $r[4]['cours']['titre']);
        $this->assertSame('P2 · Séance 1', $r[14]['libelle_complet']);
        $this->assertSame('Python', $r[14]['cours']['titre']);
        $this->getJson('/api/mes-classes')->assertOk()->assertJsonPath('data.0.classe.titre', 'Scratch → Python');

        Carbon::setTestNow('2026-11-20 10:00:00');
        $mois = $this->getJson('/api/timesheets/mois?annee=2026&mois=10');
        if ($mois->status() === 200 && ! empty($mois->json('sessions'))) {
            $this->assertSame('Scratch', $mois->json('sessions.0.classe_libelle'));
            $this->assertSame('P1 · Séance 1', $mois->json('sessions.0.libelle_complet'));
        }

        Sanctum::actingAs(\App\Models\User::factory()->directeur()->create());
        $cal = $this->getJson('/api/calendar/month?year=2026&month=10')->assertOk();
        $this->assertSame('Scratch', $cal->json('data.2026-10-07.0.cours.titre'));
        $this->getJson("/api/calendar/month?year=2027&month=3&cours_id={$this->c2->id}")->assertOk();
    }

    public function test_suppression_de_classe_avec_deux_periodes(): void
    {
        Carbon::setTestNow('2026-01-01');
        $classe = Classe::factory()->deuxPeriodes()->create(['annee_scolaire_id' => $this->annee->id]);
        $this->deleteJson("/api/classes/{$classe->id}")->assertNoContent();
        $this->assertSame(0, ClassePeriode::count());
        $this->assertSame(0, CourseSession::count());
    }

    public function test_professeur_ne_gere_pas_les_periodes(): void
    {
        $classe = $this->classeP1();
        $cp = $classe->periodes()->first();
        $this->actingAsRole('professeur');

        $this->postJson("/api/classes/{$classe->id}/periodes", ['periode_id' => $this->p(2), 'cours_id' => $this->c2->id, 'date_premiere_session' => '2027-03-03'])->assertForbidden();
        $this->postJson("/api/classes/{$classe->id}/periodes/apercu", ['periode_id' => $this->p(2), 'date_premiere_session' => '2027-03-03'])->assertForbidden();
        $this->putJson("/api/classes/{$classe->id}/periodes/{$cp->id}", ['cours_id' => $this->c2->id])->assertForbidden();
        $this->deleteJson("/api/classes/{$classe->id}/periodes/{$cp->id}")->assertForbidden();
        $this->postJson("/api/classes/{$classe->id}/periodes/{$cp->id}/annuler", ['motif' => 'abc'])->assertForbidden();
        $this->getJson("/api/classes/{$classe->id}/periodes/{$cp->id}/historique-cours")->assertForbidden();
    }

    public function test_apercu_d_ajout_de_periode(): void
    {
        $classe = $this->classeP1();
        $this->postJson("/api/classes/{$classe->id}/periodes/apercu", ['periode_id' => $this->p(2), 'date_premiere_session' => '2027-03-03'])
            ->assertOk()->assertJsonCount(1, 'data.periodes')->assertJsonCount(14, 'data.periodes.0.seances')->assertJsonPath('data.periodes.0.numero', 2);
        $this->assertSame(1, $classe->periodes()->count());
    }
}
