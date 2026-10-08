<?php

namespace Database\Seeders;

use App\Models\Employeur;
use Illuminate\Database\Seeder;

/** EMP-01 : entités employeurs d'amorçage (ASBL par défaut, L-IT Solutions « À COMPLÉTER »). Idempotent, n'écrase rien. */
class EmployeurSeeder extends Seeder
{
    public function run(): void
    {
        Employeur::amorcer();
    }
}
