<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Cours;
use App\Services\ClasseSessionGenerator;
use App\Support\ContentDefaults;
use Illuminate\Database\Seeder;

/** Cours « React » : classes du mercredi 14h–17h et du samedi 9h–12h en période 1 (14 sessions chacune). */
class ClasseSeeder extends Seeder
{
    public function run(ClasseSessionGenerator $generator): void
    {
        $annee = AnneeScolaire::with('periodes')->where('libelle', '2026-2027')->first();
        if (! $annee) {
            $this->command?->warn('Année 2026-2027 introuvable : lancer AnneeScolaireSeeder.');

            return;
        }

        $react = Cours::firstOrCreate(
            ['slug' => 'react'],
            array_merge(['titre' => 'React', 'statut' => 'publish'], ContentDefaults::coursDefaults())
        );
        $periode1 = $annee->periodes->firstWhere('numero', 1);

        $definitions = [
            ['jour_semaine' => 3, 'heure_debut' => '14:00', 'heure_fin' => '17:00', 'date_premiere_session' => '2026-10-07'], // mercredi
            ['jour_semaine' => 6, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'date_premiere_session' => '2026-10-10'], // samedi
        ];

        foreach ($definitions as $def) {
            $existe = Classe::where([
                'cours_id' => $react->id,
                'annee_scolaire_id' => $annee->id,
                'periode_id' => $periode1->id,
                'jour_semaine' => $def['jour_semaine'],
            ])->exists();

            if (! $existe) {
                $generator->create($def + [
                    'cours_id' => $react->id,
                    'annee_scolaire_id' => $annee->id,
                    'periode_id' => $periode1->id,
                    'lieu' => null,
                ]);
            }
        }
    }
}
