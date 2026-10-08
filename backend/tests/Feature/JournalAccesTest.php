<?php

namespace Tests\Feature;

use App\Models\AccesDonneeSensible;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** RGPD-01 : journal append-only des accès à l'IBAN et aux fiches (ids seulement), purge à 12 mois. */
class JournalAccesTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Professeur $prof;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->prof = Professeur::factory()->create(['compte_bancaire' => 'BE68539007547034']);
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 11.75, 'date_debut' => '2026-01-01']);
        Timesheet::create(['professeur_id' => $this->prof->id, 'date_prestation' => '2026-10-01', 'nombre_heures' => 3, 'type_activite' => 'animation', 'statut_validation' => 'confirme', 'signature_professeur' => now()]);
    }

    private function actions(): array
    {
        return AccesDonneeSensible::orderBy('id')->pluck('action')->all();
    }

    public function test_show_et_update_journalisent_la_lecture_de_l_iban(): void
    {
        $user = $this->actingAsRole('directeur');

        $this->getJson("/api/professeurs/{$this->prof->id}")->assertOk();
        $this->putJson("/api/professeurs/{$this->prof->id}", ['telephone' => '0470'])->assertOk();

        $this->assertSame(['lecture_iban', 'lecture_iban'], $this->actions());
        $trace = AccesDonneeSensible::first();
        $this->assertSame($user->id, $trace->user_id);
        $this->assertSame($this->prof->id, $trace->professeur_id);
        $this->assertNotNull($trace->created_at);
    }

    public function test_la_liste_et_la_lecture_par_le_titulaire_ne_journalisent_pas(): void
    {
        $this->actingAsRole('admin');
        $this->getJson('/api/professeurs')->assertOk();
        $this->assertSame(0, AccesDonneeSensible::count());

        Sanctum::actingAs($this->prof->user);
        $this->getJson("/api/professeurs/{$this->prof->id}")->assertOk();
        $this->getJson('/api/me')->assertOk();
        $this->assertSame(0, AccesDonneeSensible::count());
    }

    public function test_pdf_apercu_telechargement_et_zip_sont_journalises(): void
    {
        $directeur = $this->actingAsRole('directeur');

        $this->get("/api/professeurs/{$this->prof->id}/timesheet-pdfs/apercu?annee=2026&mois=10")->assertOk();
        $this->assertSame(['telechargement_pdf'], $this->actions());

        $id = $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertCreated()->json('data.id');
        $this->assertCount(1, $this->actions(), 'La génération seule ne lit rien.');
        $this->get("/api/timesheet-pdfs/{$id}/telecharger")->assertOk();
        $this->assertSame(['telechargement_pdf', 'telechargement_pdf'], $this->actions());

        // Zip : un autre professeur prêt (le premier est déjà généré).
        $b = Professeur::factory()->create(['compte_bancaire' => 'BE71096123456769']);
        ProfesseurTarif::create(['professeur_id' => $b->id, 'tarif_horaire_eur' => 10, 'date_debut' => '2026-01-01']);
        Timesheet::create(['professeur_id' => $b->id, 'date_prestation' => '2026-10-01', 'nombre_heures' => 1, 'type_activite' => 'animation', 'statut_validation' => 'confirme', 'signature_professeur' => now()]);
        $this->post('/api/timesheets-mois/pdf-lot', ['annee' => 2026, 'mois' => 10, 'professeur_ids' => [$b->id]])->assertOk();
        $zip = AccesDonneeSensible::where('action', 'export_zip')->get();
        $this->assertCount(1, $zip);
        $this->assertSame([$directeur->id, $b->id], [$zip[0]->user_id, $zip[0]->professeur_id]);

        // Le titulaire télécharge sa propre fiche : pas de trace.
        $avant = AccesDonneeSensible::count();
        Sanctum::actingAs($this->prof->user);
        $this->get("/api/timesheet-pdfs/{$id}/telecharger")->assertOk();
        $this->assertSame($avant, AccesDonneeSensible::count());
    }

    public function test_le_journal_ne_contient_que_des_identifiants(): void
    {
        $this->assertSame(['id', 'user_id', 'professeur_id', 'action', 'created_at'], Schema::getColumnListing('acces_donnees_sensibles'));
    }

    public function test_le_journal_est_en_ajout_seul_et_la_commande_purge_a_12_mois(): void
    {
        $user = $this->actingAsRole('admin');
        $recente = AccesDonneeSensible::create(['user_id' => $user->id, 'professeur_id' => $this->prof->id, 'action' => 'lecture_iban']);
        $ancienne = AccesDonneeSensible::create(['user_id' => $user->id, 'professeur_id' => $this->prof->id, 'action' => 'export_zip']);
        AccesDonneeSensible::whereKey($ancienne->id)->toBase()->update(['created_at' => now()->subMonths(13)]);
        $limite = AccesDonneeSensible::create(['user_id' => $user->id, 'professeur_id' => $this->prof->id, 'action' => 'lecture_iban']);
        AccesDonneeSensible::whereKey($limite->id)->toBase()->update(['created_at' => now()->subMonths(11)]);

        try {
            $recente->update(['action' => 'x']);
            $this->fail('Modification autorisée.');
        } catch (LogicException) {
        }
        try {
            $recente->delete();
            $this->fail('Suppression autorisée.');
        } catch (LogicException) {
        }

        $this->artisan('acces:purger-journal')->expectsOutputToContain('1 trace(s) purgée(s)')->assertSuccessful();
        $this->assertEqualsCanonicalizing([$recente->id, $limite->id], AccesDonneeSensible::pluck('id')->all());
    }
}
