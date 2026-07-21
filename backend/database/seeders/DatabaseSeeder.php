<?php

namespace Database\Seeders;

use App\Models\Cours;
use App\Models\Professeur;
use App\Models\TypeCours;
use App\Models\User;
use App\Support\ContentDefaults;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Jeu de données minimal pour vérifier le RBAC (isolation par professeur,
     * verrouillage des timesheets) : 1 admin, 1 directeur, 2 professeurs sur
     * des types_cours distincts, 2 cours correspondants.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@lgit.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $directeur = User::create([
            'name' => 'Directrice',
            'email' => 'directeur@lgit.test',
            'password' => 'password',
            'role' => 'directeur',
        ]);

        $scratch = TypeCours::create(['nom' => 'Scratch Junior', 'slug' => 'scratch-junior']);
        $python = TypeCours::create(['nom' => 'Python Ado', 'slug' => 'python-ado']);

        $userA = User::create([
            'name' => 'Alice Prof',
            'email' => 'alice@lgit.test',
            'password' => 'password',
            'role' => 'professeur',
        ]);
        $profA = Professeur::create([
            'user_id' => $userA->id,
            'prenom' => 'Alice',
            'nom' => 'Prof',
            'email' => 'alice@lgit.test',
            'statut' => 'actif',
            'date_entree' => '2025-09-01',
        ]);
        $profA->typesCours()->attach($scratch->id);

        $userB = User::create([
            'name' => 'Bob Prof',
            'email' => 'bob@lgit.test',
            'password' => 'password',
            'role' => 'professeur',
        ]);
        $profB = Professeur::create([
            'user_id' => $userB->id,
            'prenom' => 'Bob',
            'nom' => 'Prof',
            'email' => 'bob@lgit.test',
            'statut' => 'actif',
            'date_entree' => '2025-09-01',
        ]);
        $profB->typesCours()->attach($python->id);

        $coursScratch = Cours::create(array_merge([
            'titre' => 'Scratch Junior',
            'slug' => 'scratch-junior',
            'statut' => 'publish',
        ], ContentDefaults::coursDefaults()));
        $coursScratch->typesCours()->attach($scratch->id);

        $coursPython = Cours::create(array_merge([
            'titre' => 'Python Ado',
            'slug' => 'python-ado',
            'statut' => 'publish',
        ], ContentDefaults::coursDefaults()));
        $coursPython->typesCours()->attach($python->id);
    }
}
