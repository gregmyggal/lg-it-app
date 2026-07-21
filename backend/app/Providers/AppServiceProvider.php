<?php

namespace App\Providers;

use App\Models\Anniversaire;
use App\Models\Cours;
use App\Models\Formation;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Alias courts pour parent_type (classe_liens/classe_settings) au lieu du nom de
        // classe PHP complet — évite de coupler la BDD au namespace App\Models.
        // morphMap() (pas enforceMorphMap()) : Sanctum utilise aussi une relation
        // polymorphe interne (tokenable) sur User, qui doit rester non contrainte.
        Relation::morphMap([
            'cours' => Cours::class,
            'stage' => Stage::class,
            'formation' => Formation::class,
            'anniversaire' => Anniversaire::class,
        ]);
    }
}
