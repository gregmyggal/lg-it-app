<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\SessionProfesseur;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\SessionReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** PROF-02 : archivage et suppression (forçable) d'un professeur, nettoyage de toutes ses séances à venir. */
class ProfesseurArchivageSuppressionTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Classe $mercredi;

    private Classe $lundi;

    private Professeur $alice;

    private Professeur $bob;

    private Professeur $carol;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $annee = $this->annee();
        $this->mercredi = $this->classeAvecSessions($annee);
        $this->lundi = $this->classeAvecSessions($annee, ['jour_semaine' => 1, 'date_premiere_session' => '2026-10-05', 'heure_debut' => '09:00', 'heure_fin' => '11:00']);
        $this->alice = Professeur::factory()->create(['prenom' => 'Élodie', 'nom' => 'Martin']);
        $this->bob = Professeur::factory()->create();
        $this->carol = Professeur::factory()->create();

        $assignations = app(ClasseProfesseurAssignmentService::class);
        $assignations->assigner($this->mercredi, $this->alice);
        $assignations->assigner($this->lundi, $this->bob);

        // Mercredi 07/10 et 14/10 passées, 21/10 et suivantes à venir.
        Carbon::setTestNow('2026-10-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(Classe $classe, int $numero): CourseSession
    {
        return $classe->sessions()->where('seance_numero', $numero)->where('bis_rang', 0)->firstOrFail();
    }

    private function ligne(CourseSession $s, Professeur $p): ?SessionProfesseur
    {
        return SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $p->id)->first();
    }

    private function heure(Professeur $p, ?CourseSession $s = null, string $statut = 'brouillon'): Timesheet
    {
        return Timesheet::create([
            'professeur_id' => $p->id, 'course_session_id' => $s?->id, 'date_prestation' => ($s?->date ?? now())->toDateString(),
            'nombre_heures' => 3, 'statut_validation' => $statut,
        ]);
    }

    /** Alice : ses séances de classe, un ajout ponctuel le lundi 26/10, remplace Bob le 02/11, remplacée par Carol le 28/10. */
    private function scenarioComplet(): array
    {
        $remplacements = app(SessionReplacementService::class);
        $ajout = $this->seance($this->lundi, 4);       // 26/10
        $remplaceBob = $this->seance($this->lundi, 5); // 02/11
        $remplaceeParCarol = $this->seance($this->mercredi, 4); // 28/10
        $remplacements->ajouter($ajout, $this->alice->id);
        $remplacements->remplacer($remplaceBob, $this->bob->id, $this->alice->id);
        $remplacements->remplacer($remplaceeParCarol, $this->alice->id, $this->carol->id);

        return compact('ajout', 'remplaceBob', 'remplaceeParCarol');
    }

    public function test_archivage_libere_toutes_les_seances_a_venir_et_garde_le_passe(): void
    {
        ['ajout' => $ajout, 'remplaceBob' => $remplaceBob, 'remplaceeParCarol' => $remplaceeParCarol] = $this->scenarioComplet();
        $avecHeure = $this->seance($this->mercredi, 5); // 04/11 : heure déjà encodée → conservée
        $this->heure($this->alice, $avecHeure);
        $passee = $this->seance($this->mercredi, 1);
        $nbSeances = CourseSession::count();
        $this->actingAsRole('directeur');

        $attendues = $this->getJson("/api/professeurs/{$this->alice->id}/impact-desactivation")->json('data.seances_a_venir');
        $this->assertGreaterThan(3, $attendues);

        $this->postJson("/api/professeurs/{$this->alice->id}/desactiver")->assertOk()
            ->assertJsonPath('data.statut', 'inactif')->assertJsonPath('assignations_terminees', 1)
            ->assertJsonPath('seances_liberees', $attendues);

        $futures = SessionProfesseur::where('professeur_id', $this->alice->id)
            ->whereHas('session', fn ($q) => $q->where('date', '>=', '2026-10-20'))->pluck('course_session_id')->all();
        $this->assertSame([$avecHeure->id], $futures);
        $this->assertNotNull($this->ligne($passee, $this->alice));
        $this->assertNull($this->ligne($ajout, $this->alice));

        // Elle remplaçait Bob : Bob reste remplacé, sans remplaçant.
        $bob = $this->ligne($remplaceBob, $this->bob);
        $this->assertTrue($bob->remplace);
        $this->assertNull($bob->remplace_par_professeur_id);
        // Carol la remplaçait : Carol reste, en ajout ponctuel.
        $this->assertSame(SessionProfesseur::ORIGINE_AJOUT, $this->ligne($remplaceeParCarol, $this->carol)->origine);

        $this->assertSame($nbSeances, CourseSession::count());
        $this->assertSame(CourseSession::STATUT_PLANIFIEE, $remplaceBob->fresh()->statut);
        $this->getJson("/api/professeurs/{$this->alice->id}/classes")->assertOk()->assertJsonPath('data.0.actif', false);
    }

    public function test_impact_detaille_les_seances_par_type_et_celles_sans_professeur(): void
    {
        $this->scenarioComplet();
        $this->actingAsRole('admin');

        $r = $this->getJson("/api/professeurs/{$this->alice->id}/impact-desactivation")->assertOk()
            ->assertJsonPath('data.classes_actives', 1)
            ->assertJsonPath('data.detail_seances.ajout', 1)
            ->assertJsonPath('data.detail_seances.remplacement', 1)
            ->assertJsonPath('data.detail_seances.deja_remplace', 1);

        $classe = $r->json('data.detail_seances.classe');
        $this->assertGreaterThan(0, $classe);
        $this->assertSame($classe + 3, $r->json('data.seances_a_venir'));
        // Sans professeur : ses séances de classe (hors celle reprise par Carol) + celle de Bob ; pas l'ajout du lundi (Bob y reste).
        $this->assertSame($classe + 1, $r->json('data.seances_sans_professeur'));
        $this->assertSame($this->mercredi->periodes->first()->cours->titre ?? null, $r->json('data.seances_sans_professeur_liste.0.classe'));
    }

    public function test_un_remplace_sans_remplacant_peut_recevoir_un_nouveau_remplacant(): void
    {
        ['remplaceBob' => $remplaceBob] = $this->scenarioComplet();
        $this->actingAsRole('directeur');
        $this->postJson("/api/professeurs/{$this->alice->id}/desactiver")->assertOk();

        $this->postJson("/api/sessions/{$remplaceBob->id}/remplacer", [
            'professeur_remplace_id' => $this->bob->id, 'professeur_remplacant_id' => $this->carol->id,
        ])->assertSuccessful();

        $this->assertSame($this->carol->id, $this->ligne($remplaceBob, $this->bob)->remplace_par_professeur_id);
    }

    public function test_suppression_sans_heure_libere_les_seances_et_supprime_le_compte(): void
    {
        ['remplaceBob' => $remplaceBob] = $this->scenarioComplet();
        $userId = $this->alice->user_id;
        $nbSeances = CourseSession::count();
        $this->actingAsRole('directeur');

        $this->getJson("/api/professeurs/{$this->alice->id}/impact-suppression")->assertOk()
            ->assertJsonPath('data.forcage_requis', false)->assertJsonPath('data.impact.detail_seances.remplacement', 1);

        $this->deleteJson("/api/professeurs/{$this->alice->id}")->assertNoContent();

        $this->assertDatabaseMissing('professeurs', ['id' => $this->alice->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertSame($nbSeances, CourseSession::count());
        $this->assertTrue($this->ligne($remplaceBob, $this->bob)->remplace);
    }

    public function test_suppression_avec_heures_exige_un_forcage_valide(): void
    {
        $this->heure($this->alice, $this->seance($this->mercredi, 1), 'confirme');
        $this->heure($this->alice, $this->seance($this->mercredi, 2));
        $this->actingAsRole('directeur');

        $this->deleteJson("/api/professeurs/{$this->alice->id}")->assertStatus(409)
            ->assertJsonPath('forcable', true)->assertJsonPath('resume.heures.nb', 2)->assertJsonPath('resume.heures.total', 6);

        $this->deleteJson("/api/professeurs/{$this->alice->id}", ['force' => true, 'motif' => 'Doublon', 'confirmation_nom' => 'Élodie Martin'])
            ->assertUnprocessable()->assertJsonValidationErrors('motif');
        $this->deleteJson("/api/professeurs/{$this->alice->id}", ['force' => true, 'motif' => 'Créée en double par erreur', 'confirmation_nom' => 'Alice Martin'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmation_nom');
        $this->assertDatabaseHas('professeurs', ['id' => $this->alice->id]);

        $this->deleteJson("/api/professeurs/{$this->alice->id}", ['force' => true, 'motif' => 'Créée en double par erreur', 'confirmation_nom' => '  elodie   MARTIN '])
            ->assertNoContent();

        $this->assertDatabaseMissing('professeurs', ['id' => $this->alice->id]);
        $this->assertSame(0, Timesheet::where('professeur_id', $this->alice->id)->count());
        $this->assertSame(0, SessionProfesseur::where('professeur_id', $this->alice->id)->count());
    }

    public function test_heures_sur_fiche_generee_forcage_reserve_a_l_admin(): void
    {
        $this->heure($this->alice, $this->seance($this->mercredi, 1), 'genere');
        $payload = ['force' => true, 'motif' => 'Départ, données à purger', 'confirmation_nom' => 'Élodie Martin'];

        $this->actingAsRole('directeur');
        $this->getJson("/api/professeurs/{$this->alice->id}/impact-suppression")->assertOk()
            ->assertJsonPath('data.forcable', false)->assertJsonPath('data.heures_generees', 1);
        $this->deleteJson("/api/professeurs/{$this->alice->id}", $payload)->assertStatus(409)->assertJsonPath('forcable', false);
        $this->assertDatabaseHas('professeurs', ['id' => $this->alice->id]);

        $this->actingAsRole('admin');
        $this->getJson("/api/professeurs/{$this->alice->id}/impact-suppression")->assertOk()->assertJsonPath('data.forcable', true);
        $this->deleteJson("/api/professeurs/{$this->alice->id}", $payload)->assertNoContent();
        $this->assertDatabaseMissing('professeurs', ['id' => $this->alice->id]);
    }

    public function test_refus_pour_un_compte_non_professeur_et_pour_un_professeur(): void
    {
        $directeur = User::factory()->create(['role' => 'directeur']);
        $doubleCasquette = Professeur::factory()->create(['user_id' => $directeur->id]);
        $this->actingAsRole('admin');
        $this->deleteJson("/api/professeurs/{$doubleCasquette->id}")->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $directeur->id]);

        Sanctum::actingAs($this->bob->user);
        $this->deleteJson("/api/professeurs/{$this->alice->id}")->assertForbidden();
        $this->getJson("/api/professeurs/{$this->alice->id}/impact-suppression")->assertForbidden();
    }
}
