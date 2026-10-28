<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetNote;
use App\Models\TimesheetVersion;
use App\Models\User;
use App\Services\TimesheetNoteService;
use App\Services\TimesheetVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\Concerns\SigneMois;
use Tests\TestCase;

/** TS-06 : notes internes (direction et admin uniquement), historique, rétention et absence totale de fuite vers le professeur. */
class TimesheetNoteTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;
    use SigneMois;

    private const SECRET = 'SECRET-NOTE-INTERNE-ZXQ';

    private Professeur $prof;

    private Timesheet $a;

    private Timesheet $b;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-11-03 10:00:00');
        $this->prof = Professeur::factory()->create(['compte_bancaire' => 'BE68539007547034']);
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);
        $this->a = $this->ligne('2026-10-05', 2.0);
        $this->b = $this->ligne('2026-10-07', 1.5);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ligne(string $date, float $h, string $statut = 'soumis'): Timesheet
    {
        return Timesheet::create(['professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => $h, 'type_activite' => 'animation', 'statut_validation' => $statut]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/professeurs/{$this->prof->id}/timesheets-mois/notes{$suffix}";
    }

    private function ecrire(string $texte = self::SECRET, ?int $timesheetId = null): \Illuminate\Testing\TestResponse
    {
        return $this->putJson($this->url(), ['annee' => 2026, 'mois' => 10, 'timesheet_id' => $timesheetId, 'texte' => $texte]);
    }

    private function detail(): array
    {
        return $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->assertOk()->json('notes_internes');
    }

    public function test_la_direction_ajoute_une_note_de_mois_et_une_note_de_ligne(): void
    {
        $directeur = $this->actingAsRole('directeur');

        $this->ecrire('Heures confirmées par téléphone.')->assertOk();
        $this->ecrire('Vérifié avec Alice.', $this->a->id)->assertOk();

        $d = $this->detail();
        $this->assertSame('Heures confirmées par téléphone.', $d['mois']['texte']);
        $this->assertSame($directeur->id, $d['mois']['auteur']['id']);
        $this->assertSame('Vérifié avec Alice.', $d['lignes'][$this->a->id]['texte']);
        $this->assertArrayNotHasKey($this->b->id, $d['lignes']);
        $this->assertTrue($d['mois']['peut_modifier']);
    }

    public function test_une_seule_note_par_mois_et_par_ligne_et_la_modification_garde_l_historique(): void
    {
        $this->actingAsRole('directeur');
        $this->ecrire('Version 1');

        $this->ecrire('Version 2')->assertOk();
        $this->ecrire('Version 2')->assertOk(); // identique : pas d'entrée d'historique

        $this->assertSame(1, TimesheetNote::count());
        $d = $this->detail();
        $this->assertSame('Version 2', $d['mois']['texte']);
        $this->assertSame(1, $d['mois']['modifications']);
        $h = $this->getJson($this->url("/{$d['mois']['id']}/historique"))->assertOk()->json('data');
        $this->assertSame('Version 1', $h[0]['texte']);
    }

    public function test_texte_de_1_a_1000_caracteres(): void
    {
        $this->actingAsRole('admin');

        $this->ecrire('')->assertStatus(422);
        $this->ecrire(str_repeat('a', 1001))->assertStatus(422);
        $this->ecrire(str_repeat('a', 1000))->assertOk();
    }

    public function test_une_saisie_etrangere_au_mois_est_refusee(): void
    {
        $autre = Professeur::factory()->create();
        $etrangere = Timesheet::create(['professeur_id' => $autre->id, 'date_prestation' => '2026-10-05', 'nombre_heures' => 1, 'type_activite' => 'animation', 'statut_validation' => 'soumis']);
        $this->actingAsRole('admin');

        $this->ecrire('x', $etrangere->id)->assertStatus(422);
        $this->assertSame(0, TimesheetNote::count());
    }

    public function test_un_directeur_ne_modifie_pas_la_note_d_un_autre_mais_l_admin_si(): void
    {
        $this->actingAsRole('directeur');
        $this->ecrire('Note du directeur A');
        $autre = User::factory()->directeur()->create();
        Sanctum::actingAs($autre);

        $this->ecrire('Écrasée')->assertForbidden();
        $this->deleteJson($this->url().'?annee=2026&mois=10')->assertForbidden();
        $this->assertSame('Note du directeur A', TimesheetNote::firstOrFail()->texte);
        $this->assertFalse($this->detail()['mois']['peut_modifier']);

        $this->actingAsRole('admin');
        $this->ecrire('Modérée par l\'admin')->assertOk();
        $this->assertSame('Modérée par l\'admin', TimesheetNote::firstOrFail()->texte);
    }

    public function test_l_auteur_supprime_sa_note_et_l_historique_part_avec(): void
    {
        $this->actingAsRole('directeur');
        $this->ecrire('V1');
        $this->ecrire('V2');

        $this->deleteJson($this->url().'?annee=2026&mois=10')->assertOk();

        $this->assertSame(0, TimesheetNote::count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('timesheet_note_historique')->count());
        $this->deleteJson($this->url().'?annee=2026&mois=10')->assertStatus(404);
    }

    public function test_supprimer_une_saisie_supprime_sa_note(): void
    {
        $this->actingAsRole('admin');
        $this->ecrire('Note de ligne', $this->b->id);

        $this->b->delete();

        $this->assertSame(0, TimesheetNote::count());
    }

    public function test_le_professeur_n_a_acces_a_aucune_route_de_notes(): void
    {
        $this->actingAsRole('directeur');
        $this->ecrire();
        $id = TimesheetNote::firstOrFail()->id;
        Sanctum::actingAs($this->prof->user);

        $this->ecrire('Piratage')->assertForbidden();
        $this->deleteJson($this->url().'?annee=2026&mois=10')->assertForbidden();
        $this->getJson($this->url("/{$id}/historique"))->assertForbidden();
        $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->assertForbidden();
        $this->assertSame(self::SECRET, TimesheetNote::firstOrFail()->texte);
    }

    public function test_anonyme_refuse(): void
    {
        $this->ecrire()->assertUnauthorized();
    }

    /** Aucune route accessible au professeur ne laisse sortir une note (texte, ou clé « notes »). */
    public function test_aucune_fuite_vers_le_professeur(): void
    {
        $this->creerSignature($this->prof->user);
        $directeur = $this->actingAsRole('directeur');
        $this->ecrire(self::SECRET);
        $this->ecrire(self::SECRET.'-LIGNE', $this->a->id);
        $this->a->update(['statut_validation' => 'brouillon']);
        $this->b->update(['statut_validation' => 'brouillon']);
        Sanctum::actingAs($this->prof->user);
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();
        Sanctum::actingAs($directeur);
        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$this->a->id, $this->b->id]])->assertOk();

        Sanctum::actingAs($this->prof->user);
        $reponses = [
            $this->getJson('/api/timesheets')->assertOk()->getContent(),
            $this->getJson("/api/timesheets/{$this->a->id}")->assertOk()->getContent(),
            $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk()->getContent(),
            $this->getJson('/api/timesheets/ma-confirmation?annee=2026&mois=10')->assertOk()->getContent(),
            $this->getJson('/api/timesheets/mes-versions?annee=2026&mois=10')->assertOk()->getContent(),
            $this->getJson('/api/notifications')->assertOk()->getContent(),
            $this->signerMois($this->prof->id)->assertOk()->getContent(),
        ];

        foreach ($reponses as $contenu) {
            $this->assertStringNotContainsString('SECRET-NOTE', $contenu);
            $this->assertStringNotContainsString('notes_internes', $contenu);
        }
        // Notifications stockées (cloche + e-mail) : aucune trace non plus.
        $this->assertStringNotContainsString('SECRET-NOTE', json_encode($this->prof->user->notifications()->pluck('data')->all()));
        // Signature scellée : aucune trace.
        $this->assertStringNotContainsString('SECRET-NOTE', (string) \App\Models\TimesheetSignature::firstOrFail()->payload);
    }

    public function test_une_note_ne_change_ni_les_versions_ni_l_empreinte(): void
    {
        $this->a->update(['statut_validation' => 'brouillon']);
        $this->b->update(['statut_validation' => 'brouillon']);
        Sanctum::actingAs($this->prof->user);
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();
        $this->actingAsRole('directeur');
        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$this->a->id, $this->b->id]])->assertOk();
        $avant = app(TimesheetVersionService::class)->canoniser($this->prof, 2026, 10)['hash'];

        $this->ecrire(self::SECRET, $this->a->id)->assertOk();

        $this->assertSame($avant, app(TimesheetVersionService::class)->canoniser($this->prof, 2026, 10)['hash']);
        $this->assertSame($avant, TimesheetVersion::where('type', 'validee')->firstOrFail()->content_hash);
        $this->assertStringNotContainsString('SECRET-NOTE', TimesheetVersion::where('type', 'validee')->firstOrFail()->lignes);
    }

    public function test_retention_24_mois_apres_la_fin_du_mois(): void
    {
        $this->actingAsRole('admin');
        $this->ecrire('Note de ligne', $this->a->id);
        $this->ecrire('Note du mois');
        $service = app(TimesheetNoteService::class);

        $this->assertSame(0, $service->purger(Carbon::parse('2028-10-15'))); // octobre 2026 : 24 mois pas encore écoulés
        $this->assertSame(2, $service->purger(Carbon::parse('2028-11-15'), simulation: true));
        $this->assertSame(2, $service->purger(Carbon::parse('2028-11-15')));
        $this->assertSame(0, TimesheetNote::count());
        $this->assertSame(0, $service->purger(Carbon::parse('2028-11-15'))); // idempotent
    }

    public function test_la_commande_planifiee_purge_les_notes(): void
    {
        $this->actingAsRole('admin');
        $this->ecrire('Ancienne');
        Carbon::setTestNow('2028-12-01 04:15:00');

        $this->artisan('timesheets:purger-notes')->expectsOutput('1 note(s) supprimée(s).')->assertSuccessful();

        $this->assertSame(0, TimesheetNote::count());
    }

    public function test_la_presence_liste_les_notes_des_professeurs_selectionnes(): void
    {
        $autre = Professeur::factory()->create();
        $sans = Professeur::factory()->create();
        $this->actingAsRole('directeur');
        $this->ecrire('Note du mois');
        $this->ecrire('Note de ligne', $this->a->id);
        $this->putJson("/api/professeurs/{$autre->id}/timesheets-mois/notes", ['annee' => 2026, 'mois' => 10, 'texte' => 'Autre'])->assertOk();

        $r = $this->getJson('/api/timesheets-mois/notes-presence?annee=2026&mois=10&professeur_ids[]='.$this->prof->id.'&professeur_ids[]='.$autre->id.'&professeur_ids[]='.$sans->id)->assertOk();

        $par = collect($r->json('data'))->keyBy('professeur_id');
        $this->assertSame('Note du mois', $par[$this->prof->id]['mois']['texte']);
        $this->assertSame(1, $par[$this->prof->id]['lignes']);
        $this->assertSame('Autre', $par[$autre->id]['mois']['texte']);
        $this->assertNull($par[$sans->id]['mois']);
        $this->assertSame(0, $par[$sans->id]['lignes']);
    }

    public function test_la_presence_est_reservee_au_staff(): void
    {
        $this->actingAsRole('directeur');
        $this->ecrire();
        Sanctum::actingAs($this->prof->user);

        $this->getJson('/api/timesheets-mois/notes-presence?annee=2026&mois=10&professeur_ids[]='.$this->prof->id)->assertForbidden();
    }

    public function test_valider_un_mois_puis_noter_ne_depend_pas_de_la_note(): void
    {
        $this->actingAsRole('directeur');

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$this->a->id, $this->b->id]])->assertOk();

        $this->assertSame(0, TimesheetNote::count());
        $this->ecrire('Heures confirmées par téléphone.')->assertOk();
        $this->assertSame(1, TimesheetNote::count());
    }
}
