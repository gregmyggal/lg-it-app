<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\SessionProfesseur;
use App\Notifications\NotificationRemplacement;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** UI-M6 : candidats avec conflits, séances d'un professeur, remplacement atomique en lot et mail au remplaçant. */
class RemplacementLotTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Classe $classe;

    private Professeur $alice;

    private Professeur $carol;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->classe = $this->classeAvecSessions($this->annee());
        $this->alice = Professeur::factory()->create();
        $this->carol = Professeur::factory()->create();
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

    private function remplace(CourseSession $s, Professeur $p): bool
    {
        return SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $p->id)->value('remplace') === true;
    }

    public function test_remplacement_en_lot_remplace_tout_et_ne_previent_le_remplacant_qu_une_fois(): void
    {
        Notification::fake();
        $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$this->alice->id}/remplacements", [
            'session_ids' => [$this->seance(1)->id, $this->seance(2)->id],
            'remplacant_id' => $this->carol->id,
        ])->assertOk()->assertJsonPath('data.seances', 2);

        $this->assertTrue($this->remplace($this->seance(1), $this->alice));
        $this->assertTrue($this->remplace($this->seance(2), $this->alice));
        $this->assertSame(2, SessionProfesseur::where('professeur_id', $this->carol->id)->where('origine', 'remplacement')->count());
        Notification::assertSentToTimes($this->carol->user, NotificationRemplacement::class, 1);
    }

    public function test_remplacement_en_lot_atomique_un_refus_n_en_modifie_aucune(): void
    {
        Notification::fake();
        $this->seance(2)->update(['statut' => CourseSession::STATUT_ANNULEE]);
        $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$this->alice->id}/remplacements", [
            'session_ids' => [$this->seance(1)->id, $this->seance(2)->id],
            'remplacant_id' => $this->carol->id,
        ])->assertStatus(409);

        $this->assertFalse($this->remplace($this->seance(1), $this->alice));
        $this->assertSame(0, SessionProfesseur::where('professeur_id', $this->carol->id)->count());
        Notification::assertNothingSent();
    }

    public function test_remplacement_en_lot_refuse_si_le_remplace_n_est_pas_sur_une_seance(): void
    {
        $this->actingAsRole('directeur');
        $autre = Professeur::factory()->create();

        $this->postJson("/api/professeurs/{$autre->id}/remplacements", [
            'session_ids' => [$this->seance(1)->id],
            'remplacant_id' => $this->carol->id,
        ])->assertStatus(422);

        $this->assertSame(0, SessionProfesseur::where('professeur_id', $this->carol->id)->count());
    }

    public function test_apercu_n_ecrit_rien(): void
    {
        Notification::fake();
        $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$this->alice->id}/remplacements/apercu", [
            'session_ids' => [$this->seance(1)->id, $this->seance(2)->id],
            'remplacant_id' => $this->carol->id,
        ])->assertOk()->assertJsonPath('data.seances', 2);

        $this->assertFalse($this->remplace($this->seance(1), $this->alice));
        $this->assertSame(0, SessionProfesseur::where('professeur_id', $this->carol->id)->count());
        Notification::assertNothingSent();
    }

    public function test_remplacement_unitaire_previent_aussi_le_remplacant(): void
    {
        Notification::fake();
        $this->actingAsRole('directeur');

        $this->postJson("/api/sessions/{$this->seance(1)->id}/remplacer", [
            'professeur_remplace_id' => $this->alice->id,
            'professeur_remplacant_id' => $this->carol->id,
        ])->assertOk();

        Notification::assertSentToTimes($this->carol->user, NotificationRemplacement::class, 1);
    }

    public function test_un_echec_d_envoi_n_annule_pas_le_remplacement(): void
    {
        $this->mock(Dispatcher::class, fn ($m) => $m->shouldReceive('send')->andThrow(new \RuntimeException('smtp')));
        $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$this->alice->id}/remplacements", [
            'session_ids' => [$this->seance(1)->id],
            'remplacant_id' => $this->carol->id,
        ])->assertOk();

        $this->assertTrue($this->remplace($this->seance(1), $this->alice));
    }

    public function test_candidats_excluent_les_presents_et_les_archives_et_signalent_les_conflits(): void
    {
        $archive = Professeur::factory()->create(['statut' => 'inactif']);
        $occupe = Professeur::factory()->create();
        $autreClasse = $this->classeAvecSessions($this->annee2());
        app(ClasseProfesseurAssignmentService::class)->assigner($autreClasse, $occupe);
        $this->actingAsRole('directeur');

        $r = $this->getJson("/api/sessions/{$this->seance(1)->id}/candidats")->assertOk();

        $ids = collect($r->json('data'))->pluck('id');
        $this->assertNotContains($this->alice->id, $ids->all());
        $this->assertNotContains($archive->id, $ids->all());
        $this->assertContains($this->carol->id, $ids->all());
        $this->assertSame([], collect($r->json('data'))->firstWhere('id', $this->carol->id)['conflits']);
        $this->assertNotEmpty(collect($r->json('data'))->firstWhere('id', $occupe->id)['conflits']);
    }

    public function test_seances_d_un_professeur_dans_une_periode(): void
    {
        $this->actingAsRole('directeur');
        $d1 = $this->seance(1)->date->toDateString();
        $this->seance(2)->update(['statut' => CourseSession::STATUT_ANNULEE]);

        $r = $this->getJson("/api/professeurs/{$this->alice->id}/seances?du={$d1}&au={$this->seance(3)->date->toDateString()}")->assertOk();

        $ids = collect($r->json('data'))->pluck('id')->all();
        $this->assertContains($this->seance(1)->id, $ids);
        $this->assertNotContains($this->seance(2)->id, $ids); // annulée
        $this->assertFalse($r->json('data.0.heures_encodees'));
    }

    public function test_acces_professeur_403_et_sans_jeton_401(): void
    {
        $url = "/api/professeurs/{$this->alice->id}/remplacements";
        $this->postJson($url, [])->assertUnauthorized();

        Sanctum::actingAs($this->alice->user);
        $this->postJson($url, ['session_ids' => [$this->seance(1)->id], 'remplacant_id' => $this->carol->id])->assertForbidden();
        $this->getJson("/api/sessions/{$this->seance(1)->id}/candidats")->assertForbidden();
        $this->getJson("/api/professeurs/{$this->alice->id}/seances")->assertForbidden();
    }

    /** Seconde année scolaire, même calendrier : pour une classe en conflit d'horaire avec la première. */
    private function annee2()
    {
        return $this->classe->anneeScolaire;
    }
}
