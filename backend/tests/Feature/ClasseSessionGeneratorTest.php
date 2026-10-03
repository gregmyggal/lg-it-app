<?php

namespace Tests\Feature;

use App\Exceptions\RegleMetierException;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Services\ClasseSessionGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

class ClasseSessionGeneratorTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    public function test_genere_14_seances_hebdomadaires_sans_calendrier(): void
    {
        $classe = $this->classeAvecSessions($this->annee());

        $sessions = $classe->sessions()->get();
        $this->assertCount(14, $sessions);
        $this->assertSame(range(1, 14), $sessions->pluck('seance_numero')->all());
        $this->assertSame([0], $sessions->pluck('bis_rang')->unique()->values()->all());
        $this->assertSame('2026-10-07', $sessions->first()->date->toDateString());
        $this->assertSame('2027-01-06', $sessions->last()->date->toDateString());
        $this->assertSame('14:00:00', $sessions->first()->heure_debut);
        $this->assertSame('planifiee', $sessions->first()->statut);
    }

    public function test_saute_vacances_et_feries_du_calendrier_fwb(): void
    {
        $annee = $this->annee();
        $this->importFwb($annee);

        $classe = $this->classeAvecSessions($annee);

        $dates = $classe->sessions()->pluck('date')->map->toDateString()->all();
        $this->assertCount(14, $dates);
        $this->assertSame('2026-10-07', $dates[0]);
        $this->assertSame('2026-10-14', $dates[1]);
        $this->assertSame('2026-11-04', $dates[2]); // 21/10 et 28/10 : vacances d'automne
        $this->assertSame('2026-11-18', $dates[3]); // 11/11 : Armistice
        $this->assertSame('2027-02-10', $dates[13]); // 23/12 et 30/12 : vacances d'hiver
        $this->assertNotContains('2026-12-23', $dates);
    }

    public function test_apercu_liste_les_dates_sautees_avec_libelle_sans_persister(): void
    {
        $annee = $this->annee();
        $this->importFwb($annee);

        $apercu = app(ClasseSessionGenerator::class)->preview($this->donneesClasse($annee));

        $plan = $apercu['periodes'][0];
        $this->assertCount(14, $plan['seances']);
        $this->assertNull($plan['blocage']);
        $sautees = collect($plan['dates_sautees'])->keyBy('date');
        $this->assertSame(['2026-10-21', '2026-10-28', '2026-11-11', '2026-12-23', '2026-12-30'], $sautees->keys()->all());
        $this->assertSame('Armistice', $sautees['2026-11-11']['libelle']);
        $this->assertSame('ferie', $sautees['2026-11-11']['type']);
        $this->assertSame(0, Classe::count());
        $this->assertSame(0, CourseSession::count());
    }

    public function test_entree_masquee_nest_pas_sautee(): void
    {
        $annee = $this->annee();
        CalendrierScolaire::factory()->create([
            'annee_scolaire_id' => $annee->id,
            'date_debut' => '2026-10-14', 'date_fin' => '2026-10-14', 'masque' => true,
        ]);

        $classe = $this->classeAvecSessions($annee);

        $this->assertSame('2026-10-14', $classe->sessions()->get()[1]->date->toDateString());
    }

    public function test_recale_la_premiere_date_sur_le_jour_de_la_classe(): void
    {
        $annee = $this->annee();

        // 2026-10-06 est un mardi ; la classe a lieu le mercredi (3).
        $classe = $this->classeAvecSessions($annee, ['date_premiere_session' => '2026-10-06']);

        $this->assertSame('2026-10-07', $classe->periodes()->first()->date_premiere_session->toDateString());
        $this->assertSame('2026-10-07', $classe->sessions()->first()->date->toDateString());

        $apercu = app(ClasseSessionGenerator::class)->preview($this->donneesClasse($annee, ['date_premiere_session' => '2026-10-06']));
        $this->assertTrue($apercu['periodes'][0]['recale']);
        $this->assertSame('2026-10-07', $apercu['periodes'][0]['date_premiere_session']);
    }

    public function test_la_14e_seance_hors_periode_n_est_pas_bloquante_et_avertit(): void
    {
        $annee = $this->annee(); // P1 se termine le 2027-02-19

        $classe = $this->classeAvecSessions($annee, ['date_premiere_session' => '2027-01-13']);

        $this->assertSame(14, $classe->sessions()->count());
        $this->assertTrue($classe->sessions()->get()->last()->load('classePeriode.periode')->isHorsPeriode());

        $apercu = app(ClasseSessionGenerator::class)->preview($this->donneesClasse($annee, ['date_premiere_session' => '2027-01-13']));
        $this->assertNull($apercu['periodes'][0]['blocage']);
        $this->assertStringContainsString('La séance 14 dépasse la fin de la période 1', $apercu['periodes'][0]['avertissements'][0]);
    }

    public function test_refuse_une_periode_dune_autre_annee(): void
    {
        $annee = $this->annee();
        $autre = AnneeScolaire::factory()->avecPeriodes()->create();

        $this->expectException(RegleMetierException::class);

        $this->classeAvecSessions($annee, ['periode_id' => $autre->periodes()->first()->id]);
    }

    public function test_generation_idempotente_ne_recree_que_les_seances_manquantes(): void
    {
        $generator = app(ClasseSessionGenerator::class);
        $classe = $this->classeAvecSessions($this->annee());

        $this->assertSame(0, $generator->generateSessions($classe->periodes()->first()));
        $this->assertSame(14, $classe->sessions()->count());

        // Une séance déplacée à la main et une séance supprimée.
        $classe->sessions()->where('seance_numero', 2)->update(['date' => '2026-10-15']);
        $classe->sessions()->where('seance_numero', 5)->delete();

        $this->assertSame(1, $generator->generateSessions($classe->periodes()->first()));
        $this->assertSame(14, $classe->sessions()->count());
        $this->assertSame('2026-10-15', $classe->sessions()->where('seance_numero', 2)->first()->date->toDateString());
        $this->assertSame('2026-11-04', $classe->sessions()->where('seance_numero', 5)->first()->date->toDateString());
    }
}
