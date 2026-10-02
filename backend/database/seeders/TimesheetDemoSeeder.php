<?php

namespace Database\Seeders;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\User;
use App\Services\TimesheetNotifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de données de DÉMONSTRATION de la validation mensuelle (TS-01) : un professeur par état du mois, pour le mois
 * courant. À lancer sur une base jetable après DatabaseSeeder (voir scripts/demo-timesheets.sh) ; jamais sur la base de dev.
 * Mot de passe de tous les comptes : « password ».
 */
class TimesheetDemoSeeder extends Seeder
{
    private const IBAN = 'BE68539007547034';

    private Carbon $mois;

    public function run(): void
    {
        $this->mois = Carbon::now()->startOfMonth();
        $directeur = User::where('role', 'directeur')->firstOrFail();

        // Alice (existante) : à valider, sans dépassement.
        $alice = $this->prof('alice@test.com', 'Alice', 'Prof', 20, self::IBAN);
        $this->ligne($alice, 1, 'animation', 2, 'soumis');
        $this->ligne($alice, 3, 'preparation', 1, 'soumis');
        $this->ligne($alice, 8, 'cours', 2, 'soumis');

        // Bob (existant) : à valider, avec un jour au-delà du plafond (3 h × 20 € = 60 €) -> tester le lissage.
        $bob = $this->prof('bob@test.com', 'Bob', 'Prof', 20, self::IBAN);
        $this->ligne($bob, 8, 'animation', 3, 'soumis');
        $this->ligne($bob, 9, 'animation', 1, 'soumis');

        // Chloé : mois validé puis ajusté par la direction -> « Attente professeur ».
        $chloe = $this->prof('chloe@test.com', 'Chloé', 'Dubois', 20, self::IBAN);
        $this->ligne($chloe, 2, 'animation', 2, 'confirme', true);
        $ajustee = $this->ligne($chloe, 5, 'preparation', 2, 'confirme');
        TimesheetAudit::create([
            'timesheet_id' => $ajustee->id, 'professeur_id' => $chloe->id, 'user_id' => $directeur->id, 'action' => 'adaptation',
            'avant' => ['nombre_heures' => 1.0], 'apres' => ['nombre_heures' => 2.0], 'motif' => 'Réunion pédagogique confirmée',
        ]);

        // David : a contesté ses heures -> « Contesté ».
        $david = $this->prof('david@test.com', 'David', 'Petit', 20, self::IBAN);
        $contestee = $this->ligne($david, 4, 'animation', 2, 'conteste');
        TimesheetAudit::create([
            'timesheet_id' => $contestee->id, 'professeur_id' => $david->id, 'user_id' => $david->user_id, 'action' => 'contestation',
            'avant' => ['statut' => 'confirme'], 'apres' => ['statut' => 'conteste'], 'motif' => 'Il manque une heure de préparation le 4',
        ]);

        // Emma : confirmé et signé, IBAN présent -> « Prêt PDF » (tarif et lignes de la fiche modèle).
        $emma = $this->prof('emma@test.com', 'Emma', 'Roux', 11.75, self::IBAN);
        $this->ligne($emma, 3, 'animation', 3, 'confirme', true);
        $this->ligne($emma, 4, 'animation', 1.5, 'confirme', true);
        $this->ligne($emma, 10, 'cours', 2, 'confirme', true);
        $this->ligne($emma, 24, 'deplacement', 1, 'confirme', true);

        // Félix : brouillon, sans compte bancaire -> « Brouillon » + alerte.
        $felix = $this->prof('felix@test.com', 'Félix', 'Moreau', 20, null);
        $this->ligne($felix, 2, 'preparation', 1, 'brouillon');

        // Notifications cohérentes avec les états (cloche + journal d'emails).
        $notifier = app(TimesheetNotifier::class);
        $notifier->siMoisAConfirmer($chloe, $this->mois->year, $this->mois->month);
        $notifier->contestation($david, $this->mois->year, $this->mois->month, 'Il manque une heure de préparation le 4');
    }

    private function prof(string $email, string $prenom, string $nom, float $tarif, ?string $iban): Professeur
    {
        $prof = Professeur::where('email', $email)->first();
        if (! $prof) {
            $user = User::create(['name' => "{$prenom} {$nom}", 'email' => $email, 'password' => Hash::make('password'), 'role' => 'professeur']);
            $prof = Professeur::create(['user_id' => $user->id, 'prenom' => $prenom, 'nom' => $nom, 'email' => $email, 'statut' => 'actif', 'date_entree' => '2025-09-01']);
        }
        $prof->update(['compte_bancaire' => $iban]);
        ProfesseurTarif::updateOrCreate(['professeur_id' => $prof->id, 'date_debut' => '2025-01-01'], ['tarif_horaire_eur' => $tarif]);

        return $prof;
    }

    private function ligne(Professeur $prof, int $jour, string $type, float $nombre, string $statut, bool $signee = false): Timesheet
    {
        return Timesheet::create([
            'professeur_id' => $prof->id,
            'date_prestation' => $this->mois->copy()->day($jour)->toDateString(),
            'nombre_heures' => $nombre,
            'type_activite' => $type,
            'statut_validation' => $statut,
            'signature_professeur' => $signee ? now() : null,
            'validated_at' => in_array($statut, ['confirme', 'conteste'], true) ? now() : null,
        ]);
    }
}
