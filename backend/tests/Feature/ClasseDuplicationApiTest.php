<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\Cours;
use App\Models\Professeur;
use App\Models\SessionProfesseur;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\CourseSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/**
 * CLS-07 — Dupliquer une classe. Source S : Scratch (P1) → Python (P2), mercredi 14h–17h, Salle A,
 * P1 démarrée le 07/10/2026, P2 le 10/03/2027. Calendrier FWB 2026-2027 ; P1 24/08 → 19/02, P2 22/02 → 02/07.
 */
class ClasseDuplicationApiTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    private AnneeScolaire $annee;

    private Classe $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-04 10:00:00');
        $this->annee = $this->annee();
        $this->importFwb($this->annee);
        $this->source = $this->creer('2026-10-07', '2027-03-10');
    }

    private function creer(string $dateP1, ?string $dateP2 = null, array $forceesP1 = []): Classe
    {
        $periodes = [[
            'periode_id' => $this->annee->periodes->firstWhere('numero', 1)->id,
            'cours_id' => Cours::factory()->create(['titre' => 'Scratch'])->id,
            'date_premiere_session' => $dateP1,
            'dates_forcees' => $forceesP1,
        ]];
        if ($dateP2) {
            $periodes[] = [
                'periode_id' => $this->annee->periodes->firstWhere('numero', 2)->id,
                'cours_id' => Cours::factory()->create(['titre' => 'Python'])->id,
                'date_premiere_session' => $dateP2,
            ];
        }

        return $this->classeAvecSessions($this->annee, ['periodes' => $periodes]);
    }

    /** Payload de création de la copie à partir de la proposition. */
    private function payload(array $proposition, array $surcharge = []): array
    {
        return array_merge([
            'annee_scolaire_id' => $proposition['annee_scolaire_id'],
            'jour_semaine' => $proposition['jour_semaine'],
            'heure_debut' => $proposition['heure_debut'],
            'heure_fin' => $proposition['heure_fin'],
            'lieu' => $proposition['lieu'],
            'source_classe_id' => $proposition['source']['id'],
            'periodes' => array_map(fn ($p) => [
                'periode_id' => $p['periode_id'], 'cours_id' => $p['cours_id'], 'date_premiere_session' => $p['date_premiere_session'],
            ], $proposition['periodes']),
        ], $surcharge);
    }

    private function dm(array $dates): array
    {
        return array_map(fn ($d) => substr($d, 8, 2).'/'.substr($d, 5, 2), $dates);
    }

    public function test_ac2_proposition_sans_changement_de_jour_et_sans_professeur(): void
    {
        $this->actingAsRole('directeur');

        $this->getJson("/api/classes/{$this->source->id}/duplication")
            ->assertOk()
            ->assertJsonPath('data.source.libelle', 'Scratch → Python — mercredi 14h–17h')
            ->assertJsonPath('data.annee_scolaire_id', $this->annee->id)
            ->assertJsonPath('data.jour_semaine', 3)
            ->assertJsonPath('data.heure_debut', '14:00')
            ->assertJsonPath('data.lieu', 'Salle A')
            ->assertJsonPath('data.periodes.0.date_premiere_session', '2026-10-07')
            ->assertJsonPath('data.periodes.1.date_premiere_session', '2027-03-10')
            ->assertJsonMissingPath('data.professeurs');
        $this->assertSame(1, Classe::count());
    }

    public function test_ac3_ac4_jeudi_meme_semaine_puis_apercu_avec_comparaison(): void
    {
        $this->actingAsRole('directeur');
        $proposition = $this->getJson("/api/classes/{$this->source->id}/duplication?jour_semaine=4")
            ->assertOk()
            ->assertJsonPath('data.periodes.0.date_premiere_session', '2026-10-08')
            ->assertJsonPath('data.periodes.0.raison', 'meme_semaine')
            ->assertJsonPath('data.periodes.1.date_premiere_session', '2027-03-11')
            ->json('data');

        $apercu = $this->postJson('/api/classes/apercu', $this->payload($proposition))->assertOk()->json('data');

        [$p1, $p2] = $apercu['periodes'];
        $this->assertSame(['08/10', '15/10', '05/11', '12/11', '19/11', '26/11', '03/12', '10/12', '17/12', '07/01', '14/01', '21/01', '28/01', '04/02'], $this->dm(array_column($p1['seances'], 'date')));
        $this->assertSame(['22/10', '29/10', '24/12', '31/12'], $this->dm(array_column($p1['dates_sautees'], 'date')));
        $this->assertSame(['11/03', '18/03', '25/03', '01/04', '08/04', '15/04', '22/04', '13/05', '20/05', '27/05', '03/06', '10/06', '17/06', '24/06'], $this->dm(array_column($p2['seances'], 'date')));
        $this->assertSame(0, $apercu['seances_passees']);
        $this->assertSame([], $apercu['doublons']);

        $this->assertSame(['date_source' => '2026-10-07', 'etat_source' => 'normale', 'date_copie' => '2026-10-08', 'ecart_jours' => 1],
            array_intersect_key($p1['comparaison'][0], array_flip(['date_source', 'etat_source', 'date_copie', 'ecart_jours'])));
        $this->assertSame(-6, $p1['comparaison'][3]['ecart_jours']); // P1 · 4 : source 18/11 (Armistice sauté le mercredi), copie 12/11
    }

    public function test_ac6_source_demarree_dans_le_passe_avertit_sans_bloquer(): void
    {
        $this->actingAsRole('directeur');
        $this->travelTo('2026-10-20 10:00:00');
        $proposition = $this->getJson("/api/classes/{$this->source->id}/duplication?jour_semaine=4")->json('data');
        $this->assertSame('2026-10-08', $proposition['periodes'][0]['date_premiere_session']);

        $apercu = $this->postJson('/api/classes/apercu', $this->payload($proposition))->assertOk()->json('data');
        $this->assertSame(2, $apercu['seances_passees']); // 08/10 et 15/10
        $this->assertSame('demarrage_passe', $apercu['avertissements'][0]['code']);
        $this->assertTrue($apercu['periodes'][0]['comparaison'][0]['passee']);

        $this->postJson('/api/classes', $this->payload($proposition))->assertStatus(201)->assertJsonPath('data.nb_sessions', 28);
    }

    public function test_rg3_la_transposition_part_de_la_seance_1_reelle_de_la_source(): void
    {
        $this->actingAsRole('directeur');
        $seance1 = $this->source->sessions()->where('seance_numero', 1)->where('bis_rang', 0)
            ->whereHas('classePeriode.periode', fn ($q) => $q->where('numero', 1))->first();
        app(CourseSessionService::class)->deplacer($seance1, ['date' => '2026-10-14']);

        $this->getJson("/api/classes/{$this->source->id}/duplication?jour_semaine=4")
            ->assertOk()
            ->assertJsonPath('data.periodes.0.date_premiere_session', '2026-10-15');
    }

    public function test_ac7_date_hors_bornes_recalee_dans_la_periode(): void
    {
        $this->actingAsRole('directeur');
        $tardive = $this->creer('2027-02-17'); // P1 se termine le vendredi 19/02

        $this->getJson("/api/classes/{$tardive->id}/duplication?jour_semaine=6")
            ->assertOk()
            ->assertJsonPath('data.periodes.0.date_premiere_session', '2027-02-13')
            ->assertJsonPath('data.periodes.0.raison', 'borne_periode');
    }

    public function test_ac9_doublon_probable_avertit_sans_bloquer(): void
    {
        $this->actingAsRole('directeur');
        $proposition = $this->getJson("/api/classes/{$this->source->id}/duplication")->json('data');

        $apercu = $this->postJson('/api/classes/apercu', $this->payload($proposition, ['heure_debut' => '16:00', 'heure_fin' => '18:00']))->assertOk()->json('data');
        $this->assertSame([[
            'classe_id' => $this->source->id,
            'libelle' => 'Scratch → Python — mercredi 14h–17h',
            'cours_communs' => ['Scratch', 'Python'],
        ]], $apercu['doublons']);
        $this->assertContains('doublon', array_column($apercu['avertissements'], 'code'));

        $apercu = $this->postJson('/api/classes/apercu', $this->payload($proposition, ['heure_debut' => '17:00', 'heure_fin' => '18:30']))->json('data');
        $this->assertSame([], $apercu['doublons']);

        $this->postJson('/api/classes', $this->payload($proposition, ['heure_debut' => '16:00', 'heure_fin' => '18:00']))->assertStatus(201);
    }

    public function test_ac10_creation_sans_professeur_avec_trace_de_la_source(): void
    {
        $this->actingAsRole('directeur');
        app(ClasseProfesseurAssignmentService::class)->assigner($this->source, Professeur::factory()->create());
        $avant = SessionProfesseur::count();
        $proposition = $this->getJson("/api/classes/{$this->source->id}/duplication?jour_semaine=4")->json('data');

        $id = $this->postJson('/api/classes', $this->payload($proposition))
            ->assertStatus(201)
            ->assertJsonPath('data.source.id', $this->source->id)
            ->assertJsonPath('data.source.libelle', 'Scratch → Python — mercredi 14h–17h')
            ->json('data.id');

        $copie = Classe::find($id);
        $this->assertSame($this->source->id, $copie->source_classe_id);
        $this->assertSame(0, $copie->assignationsActives()->count());
        $this->assertSame($avant, SessionProfesseur::count());
        $this->assertSame(28, $this->source->sessions()->count());
    }

    public function test_ac11_comparaison_signale_deplacee_annulee_et_bis_sans_les_copier(): void
    {
        $this->actingAsRole('directeur');
        $service = app(CourseSessionService::class);
        $base = fn (int $n) => $this->source->sessions()->where('seance_numero', $n)->where('bis_rang', 0)
            ->whereHas('classePeriode.periode', fn ($q) => $q->where('numero', 1))->first();
        $service->deplacer($base(2), ['date' => '2026-10-15']);
        $service->annuler($base(6), 'Grève');
        $service->ajouterBis($this->source, ['seance_numero' => 6, 'periode_numero' => 1, 'date' => '2026-12-05']);
        $proposition = $this->getJson("/api/classes/{$this->source->id}/duplication?jour_semaine=4")->json('data');

        $p1 = $this->postJson('/api/classes/apercu', $this->payload($proposition))->assertOk()->json('data.periodes.0');

        $this->assertSame(['deplacee', '2026-10-14'], [$p1['comparaison'][1]['etat_source'], $p1['comparaison'][1]['date_source_prevue']]);
        $this->assertSame(['annulee', '2026-12-05'], [$p1['comparaison'][5]['etat_source'], $p1['comparaison'][5]['bis_source']]);
        $this->assertCount(14, $p1['seances']);
    }

    public function test_ac12_conge_force_dans_la_source_signale_mais_non_coche(): void
    {
        $this->actingAsRole('directeur');
        $s3 = $this->creer('2026-10-07', null, ['2026-11-11']); // P1 · 4 tenue le 11/11 (Armistice)
        $proposition = $this->getJson("/api/classes/{$s3->id}/duplication?heure_debut=17:00&heure_fin=18:30")->json('data');

        $p1 = $this->postJson('/api/classes/apercu', $this->payload($proposition))->assertOk()->json('data.periodes.0');

        $sautees = collect($p1['dates_sautees'])->keyBy('date');
        $this->assertTrue($sautees['2026-11-11']['forcee_dans_source']);
        $this->assertFalse($sautees['2026-10-21']['forcee_dans_source']);
        $this->assertSame(['pendant_conge', 'Armistice', '2026-11-18'], [$p1['comparaison'][3]['etat_source'], $p1['comparaison'][3]['conge_source'], $p1['comparaison'][3]['date_copie']]);
    }

    public function test_ac15_periode_annulee_non_reprise(): void
    {
        $this->actingAsRole('directeur');
        $this->source->periodes()->whereHas('periode', fn ($q) => $q->where('numero', 2))->update(['statut' => ClassePeriode::STATUT_ANNULEE]);

        $this->getJson("/api/classes/{$this->source->id}/duplication")
            ->assertOk()
            ->assertJsonCount(1, 'data.periodes')
            ->assertJsonPath('data.periodes_non_reprises.0.numero', 2)
            ->assertJsonPath('data.periodes_non_reprises.0.motif', "La période 2 de la classe source est annulée : elle n'est pas reprise.");
    }

    public function test_rg8_annee_source_archivee_dates_videes(): void
    {
        $this->actingAsRole('admin');
        $this->annee->update(['statut' => AnneeScolaire::STATUT_ARCHIVEE]);

        $this->getJson("/api/classes/{$this->source->id}/duplication")
            ->assertOk()
            ->assertJsonPath('data.annee_scolaire_id', null)
            ->assertJsonPath('data.annee_source_archivee', true)
            ->assertJsonPath('data.periodes.0.date_premiere_session', null);
    }

    public function test_n1_copie_demarree_dans_le_passe_professeur_assigne_aussi_aux_seances_passees(): void
    {
        $this->actingAsRole('directeur');
        $this->travelTo('2026-10-20 10:00:00');
        $proposition = $this->getJson("/api/classes/{$this->source->id}/duplication?jour_semaine=4")->json('data');
        $copie = Classe::find($this->postJson('/api/classes', $this->payload($proposition))->json('data.id'));

        $r = app(ClasseProfesseurAssignmentService::class)->assigner($copie, Professeur::factory()->create());

        $this->assertSame(28, $r['recapitulatif']['sessions_assignees']);
        $this->assertSame(0, $r['recapitulatif']['sessions_passees_ignorees']);
    }

    public function test_ac18_ac19_permissions_et_source_inexistante(): void
    {
        $this->getJson("/api/classes/{$this->source->id}/duplication")->assertStatus(401);

        $this->actingAsRole('professeur');
        $this->getJson("/api/classes/{$this->source->id}/duplication")->assertStatus(403);

        $this->actingAsRole('directeur');
        $this->getJson('/api/classes/99999/duplication')->assertStatus(404);
    }
}
