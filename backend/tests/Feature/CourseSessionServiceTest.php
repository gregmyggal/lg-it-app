<?php

namespace Tests\Feature;

use App\Exceptions\RegleMetierException;
use App\Models\CalendrierScolaire;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Services\CalendrierScolaireService;
use App\Services\CourseSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

class CourseSessionServiceTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    private CourseSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->service = app(CourseSessionService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(Classe $classe, int $numero, int $bis = 0): CourseSession
    {
        return $classe->sessions()->where('seance_numero', $numero)->where('bis_rang', $bis)->firstOrFail();
    }

    private function assertRegle(int $status, string $message, callable $action): void
    {
        try {
            $action();
            $this->fail('Une RegleMetierException était attendue.');
        } catch (RegleMetierException $e) {
            $this->assertSame($status, $e->status);
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_deplacer_change_date_et_heures(): void
    {
        $classe = $this->classeAvecSessions($this->annee());

        $session = $this->service->deplacer($this->seance($classe, 3), [
            'date' => '2026-10-22', 'heure_debut' => '15:00', 'heure_fin' => '18:00',
        ]);

        $this->assertSame('2026-10-22', $session->date->toDateString());
        $this->assertSame('15:00:00', $session->heure_debut);
        $this->assertSame(3, $session->seance_numero); // aucune renumérotation
    }

    public function test_deplacer_apres_la_fin_de_la_periode_est_accepte_hors_periode(): void
    {
        $classe = $this->classeAvecSessions($this->annee());

        $session = $this->service->deplacer($this->seance($classe, 3), ['date' => '2027-02-19']);
        $this->assertFalse($session->load('classePeriode.periode')->isHorsPeriode()); // borne incluse

        $session = $this->service->deplacer($this->seance($classe, 3), ['date' => '2027-02-20']);
        $this->assertSame('2027-02-20', $session->date->toDateString());
        $this->assertTrue($session->load('classePeriode.periode')->isHorsPeriode());
        $this->assertSame(['Cette date est après la fin de la période 1'], $session->avertissements());
    }

    public function test_deplacer_une_session_passee_est_refuse_409(): void
    {
        $classe = $this->classeAvecSessions($this->annee());
        Carbon::setTestNow('2026-10-20 10:00:00'); // séances 1 (07/10) et 2 (14/10) sont passées

        $this->assertRegle(409, 'Une session passée ne peut pas être déplacée.', fn () => $this->service->deplacer(
            $this->seance($classe, 2), ['date' => '2026-10-30']
        ));
        $this->service->deplacer($this->seance($classe, 3), ['date' => '2026-10-23']); // 21/10 : à venir
        $this->assertSame('2026-10-23', $this->seance($classe, 3)->date->toDateString());
    }

    public function test_deplacer_une_session_annulee_est_refuse_409(): void
    {
        $classe = $this->classeAvecSessions($this->annee());
        $this->service->annuler($this->seance($classe, 4), 'Jour de grève');

        $this->assertRegle(409, 'Une session annulée ne peut pas être déplacée.', fn () => $this->service->deplacer(
            $this->seance($classe, 4), ['date' => '2026-11-06']
        ));
    }

    public function test_annuler_conserve_le_numero_de_seance_et_les_autres(): void
    {
        $classe = $this->classeAvecSessions($this->annee());

        $session = $this->service->annuler($this->seance($classe, 5), 'Professeur malade');

        $this->assertSame('annulee', $session->statut);
        $this->assertSame('Professeur malade', $session->motif_annulation);
        $this->assertNotNull($session->cancelled_at);
        $this->assertSame(5, $session->seance_numero);
        $this->assertSame(range(1, 14), $classe->sessions()->pluck('seance_numero')->all());
        $this->assertSame(13, $classe->sessionsActives()->count());

        $this->assertRegle(409, 'Seule une session planifiée ou en cours peut être annulée.', fn () => $this->service->annuler($session, 'Encore'));
    }

    public function test_bis_remplace_une_session_annulee_sans_confirmation(): void
    {
        $classe = $this->classeAvecSessions($this->annee());
        $annulee = $this->service->annuler($this->seance($classe, 5), 'Professeur malade');

        $bis = $this->service->ajouterBis($classe, ['seance_numero' => 5, 'date' => '2027-01-20']);

        $this->assertSame(5, $bis->seance_numero);
        $this->assertSame(1, $bis->bis_rang);
        $this->assertSame($annulee->id, $bis->remplace_session_id);
        $this->assertSame('14:00:00', $bis->heure_debut); // héritées de la classe
        $this->assertSame('Séance 5 bis', $bis->libelle());
        $this->assertSame(14, $classe->sessionsActives()->count()); // 13 + 1 : pas de dépassement
        $this->assertSame(15, $classe->sessions()->count());
    }

    public function test_bis_au_dela_de_14_sessions_demande_confirmation_409(): void
    {
        $classe = $this->classeAvecSessions($this->annee());

        try {
            $this->service->ajouterBis($classe, ['seance_numero' => 3, 'date' => '2027-01-20']);
            $this->fail('Une RegleMetierException 409 était attendue.');
        } catch (RegleMetierException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('Cette période passera à 15 sessions', $e->getMessage());
            $this->assertSame(15, $e->extra['nb_sessions']);
        }
        $this->assertSame(14, $classe->sessions()->count());

        $bis = $this->service->ajouterBis($classe, ['seance_numero' => 3, 'date' => '2027-01-20'], confirmerDepassement: true);
        $this->assertSame(1, $bis->bis_rang);
        $this->assertNull($bis->remplace_session_id); // aucune session annulée à remplacer
        $this->assertSame(15, $classe->sessions()->count());

        // Bis du bis : rang suivant ; le remplacement cible la session annulée sans remplaçante.
        $this->service->annuler($bis, 'Salle indisponible');
        $bis2 = $this->service->ajouterBis($classe, ['seance_numero' => 3, 'date' => '2027-01-27'], confirmerDepassement: true);
        $this->assertSame(2, $bis2->bis_rang);
        $this->assertSame($bis->id, $bis2->remplace_session_id);
    }

    public function test_bis_refuse_seance_inconnue_et_date_hors_periode(): void
    {
        $classe = $this->classeAvecSessions($this->annee());

        $this->assertRegle(422, "La séance 9 n'existe pas dans cette période : un bis doit se rattacher à l'une de ses séances.", function () use ($classe) {
            $classe->sessions()->where('seance_numero', 9)->delete();
            $this->service->ajouterBis($classe, ['seance_numero' => 9, 'date' => '2027-01-20']);
        });
        $bis = $this->service->ajouterBis($classe, ['seance_numero' => 2, 'date' => '2027-02-22'], confirmerDepassement: true);
        $this->assertTrue($bis->load('classePeriode.periode')->isHorsPeriode());
    }

    public function test_alerte_calendrier_apres_ajout_dune_fermeture(): void
    {
        $annee = $this->annee();
        $classe = $this->classeAvecSessions($annee);
        $calendrier = app(CalendrierScolaireService::class);

        $entree = CalendrierScolaire::factory()->create([
            'annee_scolaire_id' => $annee->id, 'type' => 'fermeture', 'libelle' => 'Fermeture exceptionnelle',
            'date_debut' => '2026-10-14', 'date_fin' => '2026-10-14',
        ]);

        $sessions = $classe->sessions()->get();
        $calendrier->attachAlerts($sessions);

        $this->assertSame(['libelle' => 'Fermeture exceptionnelle', 'type' => 'fermeture'], $sessions[1]->alerte_calendrier);
        $this->assertNull($sessions[0]->alerte_calendrier);
        $this->assertSame('2026-10-14', $sessions[1]->date->toDateString(), 'Jamais de déplacement automatique.');

        // Une entrée masquée ne déclenche plus d'alerte.
        $entree->update(['masque' => true]);
        $sessions = $classe->sessions()->get();
        $calendrier->attachAlerts($sessions);
        $this->assertNull($sessions[1]->alerte_calendrier);
    }
}
