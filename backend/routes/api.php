<?php

use App\Http\Controllers\AnniversaireController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClasseLienController;
use App\Http\Controllers\ClasseSettingController;
use App\Http\Controllers\CoursController;
use App\Http\Controllers\CoursRessourceController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\ProfesseurController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\StageDateController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\TypeCoursController;
use App\Http\Controllers\TypeFormationController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Catalogue public — non authentifié, lecture seule, statut=publish uniquement.
// Pour les pages marketing (accueil, catalogue cours/stages/formations/anniversaires).
// ---------------------------------------------------------------------------
Route::prefix('public')->group(function () {
    Route::get('/cours', [CoursController::class, 'publicIndex']);
    Route::get('/cours/{cours:slug}', [CoursController::class, 'publicShow']);
    Route::get('/stages', [StageController::class, 'publicIndex']);
    Route::get('/stages/{stage:slug}', [StageController::class, 'publicShow']);
    Route::get('/formations', [FormationController::class, 'publicIndex']);
    Route::get('/formations/{formation:slug}', [FormationController::class, 'publicShow']);
    Route::get('/anniversaires', [AnniversaireController::class, 'publicIndex']);
    Route::get('/anniversaires/{anniversaire:slug}', [AnniversaireController::class, 'publicShow']);
});

Route::post('/login', [AuthController::class, 'login']);

// ---------------------------------------------------------------------------
// Back-office — authentifié (Sanctum). Isolation par professeur et verrous
// appliqués via les Policies (cf. app/Policies).
// ---------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // "cours" est invariable en français : sans ce override, Laravel le singularise
    // (règle anglaise) en "cour" et casse le binding implicite vers Cours $cours.
    Route::apiResource('cours', CoursController::class)->parameters(['cours' => 'cours']);
    Route::get('/cours/{cours}/ressources', [CoursRessourceController::class, 'index']);
    Route::post('/cours/{cours}/ressources', [CoursRessourceController::class, 'store']);
    Route::put('/ressources/{coursRessource}', [CoursRessourceController::class, 'update']);
    Route::delete('/ressources/{coursRessource}', [CoursRessourceController::class, 'destroy']);

    Route::apiResource('stages', StageController::class);
    Route::post('/stages/{stage}/dates', [StageDateController::class, 'store']);
    Route::delete('/stages/{stage}/dates/{stageDate}', [StageDateController::class, 'destroy']);

    Route::apiResource('formations', FormationController::class);
    Route::apiResource('anniversaires', AnniversaireController::class);
    // Pas de Route::apiResource ici : le tiret dans "types-cours"/"types-formation"
    // casserait le nom de paramètre attendu par le binding implicite ($typeCours).
    Route::get('/types-cours', [TypeCoursController::class, 'index']);
    Route::post('/types-cours', [TypeCoursController::class, 'store']);
    Route::get('/types-cours/{typeCours}', [TypeCoursController::class, 'show']);
    Route::put('/types-cours/{typeCours}', [TypeCoursController::class, 'update']);
    Route::delete('/types-cours/{typeCours}', [TypeCoursController::class, 'destroy']);

    Route::get('/types-formation', [TypeFormationController::class, 'index']);
    Route::post('/types-formation', [TypeFormationController::class, 'store']);
    Route::get('/types-formation/{typeFormation}', [TypeFormationController::class, 'show']);
    Route::put('/types-formation/{typeFormation}', [TypeFormationController::class, 'update']);
    Route::delete('/types-formation/{typeFormation}', [TypeFormationController::class, 'destroy']);
    Route::apiResource('professeurs', ProfesseurController::class);

    Route::get('/timesheets', [TimesheetController::class, 'index']);
    Route::post('/timesheets', [TimesheetController::class, 'store']);
    Route::get('/timesheets/{timesheet}', [TimesheetController::class, 'show']);
    Route::put('/timesheets/{timesheet}', [TimesheetController::class, 'update']);
    Route::delete('/timesheets/{timesheet}', [TimesheetController::class, 'destroy']);
    Route::post('/timesheets/{timesheet}/submit', [TimesheetController::class, 'submit']);
    Route::post('/timesheets/{timesheet}/validate', [TimesheetController::class, 'validateEntry']);

    // Liens de classe / réglages — polymorphes, {parentType} ∈ cours|stages|formations|anniversaires.
    Route::get('/{parentType}/{parentId}/liens', [ClasseLienController::class, 'index']);
    Route::post('/{parentType}/{parentId}/liens', [ClasseLienController::class, 'store']);
    Route::put('/liens/{lien}', [ClasseLienController::class, 'update']);
    Route::delete('/liens/{lien}', [ClasseLienController::class, 'destroy']);

    Route::get('/{parentType}/{parentId}/settings', [ClasseSettingController::class, 'show']);
    Route::put('/{parentType}/{parentId}/settings', [ClasseSettingController::class, 'update']);
});
