<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** UI-M7 : aperçu (sans écriture) de l'impact d'une modification de créneau/lieu d'une classe. */
class ClasseApercuModificationTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

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

    private function total(): int
    {
        return $this->classe->sessions()->where('statut', '!=', CourseSession::STATUT_ANNULEE)->count();
    }

    public function test_toutes_les_seances_a_venir_suivent_le_nouveau_lieu(): void
    {
        $this->actingAsRole('directeur');

        $r = $this->postJson("/api/classes/{$this->classe->id}/apercu-modification", ['lieu' => 'Salle B'])->assertOk();

        $this->assertSame($this->total(), $r->json('data.seances_a_mettre_a_jour'));
        $this->assertSame(0, $r->json('data.seances_inchangees'));
    }

    public function test_seance_ajustee_a_la_main_passee_ou_annulee_reste_inchangee(): void
    {
        $this->actingAsRole('directeur');
        $sessions = $this->classe->sessions()->orderBy('date')->get();
        $sessions[1]->update(['lieu' => 'Ailleurs']);               // ajustée à la main
        $sessions[2]->update(['statut' => CourseSession::STATUT_ANNULEE]); // annulée : ni comptée ni modifiée
        Carbon::setTestNow($sessions[0]->date->copy()->addDay()->setTime(10, 0)); // la première est passée

        $r = $this->postJson("/api/classes/{$this->classe->id}/apercu-modification", ['lieu' => 'Salle B'])->assertOk();

        $this->assertSame($this->total() - 2, $r->json('data.seances_a_mettre_a_jour'));
        $this->assertSame(2, $r->json('data.seances_inchangees'));
    }

    public function test_heure_modifiee_ou_aucun_changement(): void
    {
        $this->actingAsRole('directeur');
        $url = "/api/classes/{$this->classe->id}/apercu-modification";

        $this->postJson($url, ['heure_debut' => '15:00', 'heure_fin' => '17:00'])->assertOk()
            ->assertJsonPath('data.seances_a_mettre_a_jour', $this->total());
        // Valeurs identiques à celles de la classe : rien à mettre à jour.
        $this->postJson($url, ['heure_debut' => '14:00', 'heure_fin' => '17:00', 'lieu' => 'Salle A'])->assertOk()
            ->assertJsonPath('data.seances_a_mettre_a_jour', 0);
    }

    public function test_l_apercu_n_ecrit_rien(): void
    {
        $this->actingAsRole('admin');

        $this->postJson("/api/classes/{$this->classe->id}/apercu-modification", ['lieu' => 'Salle B', 'heure_debut' => '15:00'])->assertOk();

        $this->assertSame('Salle A', $this->classe->fresh()->lieu);
        $this->assertSame(0, $this->classe->sessions()->where('lieu', 'Salle B')->count());
    }

    public function test_fin_avant_debut_refusee(): void
    {
        $this->actingAsRole('directeur');

        $this->postJson("/api/classes/{$this->classe->id}/apercu-modification", ['heure_debut' => '18:00', 'heure_fin' => '17:00'])->assertStatus(422);
    }

    public function test_professeur_403_et_sans_jeton_401(): void
    {
        $url = "/api/classes/{$this->classe->id}/apercu-modification";
        $this->postJson($url, ['lieu' => 'X'])->assertUnauthorized();

        Sanctum::actingAs(Professeur::factory()->create()->user);
        $this->postJson($url, ['lieu' => 'X'])->assertForbidden();
    }
}
