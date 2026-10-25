<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\User;
use App\Notifications\NotificationTimesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\Concerns\SigneMois;
use Tests\TestCase;

/** Notifications (cloche + email) : le professeur soumet/signe → direction et admin ; la direction agit → le professeur. */
class TimesheetNotificationMailTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;
    use SigneMois;

    private Professeur $prof;

    private User $directeur;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 10:00:00');
        Storage::fake('local');
        $this->prof = Professeur::factory()->create(['compte_bancaire' => 'BE68539007547034']);
        $this->creerSignature($this->prof->user);
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);
        $this->directeur = User::factory()->directeur()->create();
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ligne(string $statut, string $date = '2026-10-10'): Timesheet
    {
        return Timesheet::create([
            'professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => 1, 'type_activite' => 'animation',
            'statut_validation' => $statut,
        ]);
    }

    private function codes(User $user): array
    {
        return $user->notifications()->get()->pluck('data.code')->all();
    }

    public function test_la_soumission_du_mois_previent_admin_et_direction_par_mail_et_cloche(): void
    {
        $this->ligne('brouillon');
        $this->ligne('brouillon', '2026-10-31'); // futur, dans le mois courant
        Sanctum::actingAs($this->prof->user);

        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk()->assertJsonPath('soumises', 2);

        foreach ([$this->directeur, $this->admin] as $u) {
            $this->assertSame([NotificationTimesheet::MOIS_SOUMIS], $this->codes($u));
        }
        $this->assertSame([], $this->codes($this->prof->user));
    }

    public function test_le_mail_part_pour_la_direction_quand_son_compte_est_active(): void
    {
        $this->directeur->forceFill(['mot_de_passe_defini_le' => now()])->save();
        Notification::fake();
        $this->ligne('brouillon');
        Sanctum::actingAs($this->prof->user);

        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();

        Notification::assertSentTo($this->directeur, NotificationTimesheet::class, fn ($n, array $canaux) => in_array('mail', $canaux, true) && $n->code === 'mois_soumis');
    }

    public function test_une_soumission_refusee_ne_notifie_personne(): void
    {
        Sanctum::actingAs($this->prof->user);

        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertStatus(422);

        $this->assertSame([], $this->codes($this->directeur));
    }

    public function test_la_signature_du_mois_previent_admin_et_direction(): void
    {
        $this->ligne('confirme');
        Sanctum::actingAs($this->prof->user);

        $this->signerMois($this->prof->id)->assertOk();

        foreach ([$this->directeur, $this->admin] as $u) {
            $this->assertSame([NotificationTimesheet::MOIS_SIGNE], $this->codes($u));
        }
    }

    public function test_une_validation_partielle_previent_quand_meme_le_professeur(): void
    {
        $a = $this->ligne('soumis');
        $this->ligne('soumis', '2026-10-11');
        Sanctum::actingAs($this->directeur);

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$a->id]])->assertOk();

        $this->assertSame([NotificationTimesheet::HEURES_TRAITEES], $this->codes($this->prof->user));
    }

    public function test_la_generation_du_pdf_previent_le_professeur(): void
    {
        $this->ligne('confirme');
        Sanctum::actingAs($this->prof->user);
        $this->signerMois($this->prof->id)->assertOk();
        Sanctum::actingAs($this->directeur);

        $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertCreated();

        $this->assertContains(NotificationTimesheet::PDF_GENERE, $this->codes($this->prof->user));
    }
}
