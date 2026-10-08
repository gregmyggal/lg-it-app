<?php

namespace Database\Seeders;

use App\Models\Cours;
use App\Models\Professeur;
use App\Models\User;
use App\Support\ContentDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Test data: 1 admin, 1 directeur, 2 professeurs (assignés à des classes de démonstration).
     * All test passwords: 'password'
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $directeur = User::create([
            'name' => 'Directrice',
            'email' => 'directeur@test.com',
            'password' => Hash::make('password'),
            'role' => 'directeur',
        ]);

        $userA = User::create([
            'name' => 'Alice Prof',
            'email' => 'alice@test.com',
            'password' => Hash::make('password'),
            'role' => 'professeur',
        ]);
        $profA = Professeur::create([
            'user_id' => $userA->id,
            'prenom' => 'Alice',
            'nom' => 'Prof',
            'email' => 'alice@test.com',
            'statut' => 'actif',
            'date_entree' => '2025-09-01',
        ]);

        $userB = User::create([
            'name' => 'Bob Prof',
            'email' => 'bob@test.com',
            'password' => Hash::make('password'),
            'role' => 'professeur',
        ]);
        $profB = Professeur::create([
            'user_id' => $userB->id,
            'prenom' => 'Bob',
            'nom' => 'Prof',
            'email' => 'bob@test.com',
            'statut' => 'actif',
            'date_entree' => '2025-09-01',
        ]);

        $coursScratch = Cours::create(array_merge([
            'titre' => 'Scratch Junior',
            'slug' => 'scratch-junior',
            'statut' => 'publish',
        ], ContentDefaults::coursDefaults()));

        $coursPython = Cours::create(array_merge([
            'titre' => 'Python Ado',
            'slug' => 'python-ado',
            'statut' => 'publish',
        ], ContentDefaults::coursDefaults()));

        // CLS-01 T1 : année scolaire 2026-2027 (+ calendrier FWB) puis classes React (14 sessions chacune)
        $this->call([
            EmployeurSeeder::class,
            AnneeScolaireSeeder::class,
            ClasseSeeder::class,
            ProfesseurClasseSeeder::class,
        ]);
    }
}
