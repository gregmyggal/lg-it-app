<?php

namespace App\Providers;

use App\Models\Anniversaire;
use App\Models\Cours;
use App\Models\Formation;
use App\Models\ProfesseurEmployeurMois;
use App\Models\Stage;
use App\Models\User;
use App\Policies\EmployeurMoisPolicy;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // ADMIN-01 : enregistrer la Policy pour User
        Gate::policy(User::class, UserPolicy::class);

        // EMP-01 : l'employeur d'un mois a sa propre Policy (le modèle d'entité, Employeur, est découvert automatiquement).
        Gate::policy(ProfesseurEmployeurMois::class, EmployeurMoisPolicy::class);

        // ADMIN-02 : routes publiques de mot de passe.
        RateLimiter::for('acces-oubli', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('acces-lien', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
