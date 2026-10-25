<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\SessionProfesseur;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\SessionReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** « Aujourd'hui » de la direction (UI-M6) : GET /api/accueil-direction et GET /api/sessions/{id}. */
class AccueilDirectionTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Classe $classe;

    private Professeur $alice;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->classe = $this->classeAvecSessions($this->annee());
        $this->alice = Professeur::factory()->create();
        app(ClasseProfesseurAssignmentService::class)->assigner($this->classe, $this->alice);
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

    private function le(int $n, string $heure): void
    {
        Carbon::setTestNow($this->seance($n)->date->copy()->setTimeFromTimeString($heure));
    }

    public function test_seances_du_jour_avec_leurs_professeurs_non_remplaces(): void
    {
        $carol = Professeur::factory()->create();
        app(SessionReplacementService::class)->remplacer($this->seance(2), $this->alice->id, $carol->id);
        $this->le(2, '08:00'); // 10:00 à Bruxelles, séance 14h–17h plus tard
        $this->actingAsRole('directeur');

        $r = $this->getJson('/api/accueil-direction')->assertOk();

        $s = collect($r->json('seances'))->firstWhere('id', $this->seance(2)->id);
        $this->assertSame([$carol->id], collect($s['professeurs'])->pluck('id')->all());
        $this->assertTrue($s['est_aujourdhui']);
        $this->assertFalse($s['en_cours']);
        $this->assertSame(0, $r->json('alertes.sans_professeur'));
    }

    public function test_seance_annulee_absente_et_seance_sans_professeur_comptee(): void
    {
        $this->le(2, '08:00');
        SessionProfesseur::where('course_session_id', $this->seance(2)->id)->delete();
        $this->actingAsRole('admin');

        $this->getJson('/api/accueil-direction')->assertOk()->assertJsonPath('alertes.sans_professeur', 1);

        $this->seance(2)->update(['statut' => CourseSession::STATUT_ANNULEE]);
        $r = $this->getJson('/api/accueil-direction')->assertOk();
        $this->assertNotContains($this->seance(2)->id, collect($r->json('seances'))->pluck('id')->all());
        $this->assertSame(0, $r->json('alertes.sans_professeur'));
    }

    public function test_fiche_seance_staff_avec_telephone_et_refus_professeur(): void
    {
        $this->alice->update(['telephone' => '0470 11 22 33']);
        $this->actingAsRole('directeur');

        $r = $this->getJson("/api/sessions/{$this->seance(1)->id}")->assertOk();
        $this->assertSame('0470 11 22 33', $r->json('data.professeurs.0.telephone'));

        Sanctum::actingAs(Professeur::factory()->create()->user); // professeur étranger à la séance
        $this->getJson("/api/sessions/{$this->seance(1)->id}")->assertForbidden();
    }

    public function test_acces_professeur_403_et_sans_jeton_401(): void
    {
        $this->getJson('/api/accueil-direction')->assertUnauthorized();

        Sanctum::actingAs($this->alice->user);
        $this->getJson('/api/accueil-direction')->assertForbidden();
    }
}
