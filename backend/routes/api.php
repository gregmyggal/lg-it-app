<?php

use App\Http\Controllers\AnneeScolaireController;
use App\Http\Controllers\AnniversaireController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendrierScolaireController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\ClasseLienController;
use App\Http\Controllers\ClasseSessionController;
use App\Http\Controllers\ClasseSettingController;
use App\Http\Controllers\CoursController;
use App\Http\Controllers\CourseSessionController;
use App\Http\Controllers\CoursRessourceController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\ProfesseurController;
use App\Http\Controllers\ProfesseurCoursController;
use App\Http\Controllers\ProfesseurTarifController;
use App\Http\Controllers\ShareCodeController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\StageDateController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\TypeCoursController;
use App\Http\Controllers\TypeFormationController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Accès par codes d'accès — non authentifié, lecture seule, statut=publish.
// Pour que les élèves accèdent aux ressources de leurs cours via un code unique.
// ---------------------------------------------------------------------------
Route::middleware('validate.share.code')->group(function () {
    Route::get('/share/{code}', [ShareCodeController::class, 'show']);
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

    // Assignation de cours aux professeurs (professeur-cours pivot)
    Route::post('/professeurs/{professeur}/cours', [ProfesseurCoursController::class, 'assignCoursesToProfesseur']);
    Route::put('/professeurs/{professeur}/cours/{cours}', [ProfesseurCoursController::class, 'updateProfesseurCours']);
    Route::delete('/professeurs/{professeur}/cours/{cours}', [ProfesseurCoursController::class, 'removeProfesseurFromCours']);

    // Gestion des professeurs par cours
    Route::get('/cours/{cours}/professeurs', [ProfesseurCoursController::class, 'listProfesseursByCours']);
    Route::post('/cours/{cours}/professeurs', [ProfesseurCoursController::class, 'assignProfesseursToCours']);
    Route::put('/cours/{cours}/professeurs/{professeur}', [ProfesseurCoursController::class, 'updateCoursProf']);
    Route::delete('/cours/{cours}/professeurs/{professeur}', [ProfesseurCoursController::class, 'removeCourseProf']);

    // CLS-01 T1 : années scolaires, calendrier scolaire, classes, sessions (admin/directeur).
    Route::get('/annees-scolaires', [AnneeScolaireController::class, 'index']);
    Route::post('/annees-scolaires', [AnneeScolaireController::class, 'store']);
    Route::get('/annees-scolaires/{annee}', [AnneeScolaireController::class, 'show']);
    Route::put('/annees-scolaires/{annee}', [AnneeScolaireController::class, 'update']);
    Route::delete('/annees-scolaires/{annee}', [AnneeScolaireController::class, 'destroy']);

    Route::get('/annees-scolaires/{annee}/calendrier', [CalendrierScolaireController::class, 'index']);
    Route::post('/annees-scolaires/{annee}/calendrier', [CalendrierScolaireController::class, 'store']);
    Route::post('/annees-scolaires/{annee}/calendrier/import-fwb', [CalendrierScolaireController::class, 'importFwb']);
    Route::put('/calendrier-scolaire/{entree}', [CalendrierScolaireController::class, 'update']);
    Route::delete('/calendrier-scolaire/{entree}', [CalendrierScolaireController::class, 'destroy']);

    Route::get('/classes', [ClasseController::class, 'index']);
    Route::post('/classes', [ClasseController::class, 'store']);
    Route::post('/classes/apercu', [ClasseController::class, 'apercu']);
    Route::get('/classes/{classe}', [ClasseController::class, 'show']);
    Route::put('/classes/{classe}', [ClasseController::class, 'update']);
    Route::delete('/classes/{classe}', [ClasseController::class, 'destroy']);
    Route::get('/classes/{classe}/sessions', [ClasseSessionController::class, 'index']);
    Route::post('/classes/{classe}/sessions/bis', [ClasseSessionController::class, 'bis']);

    Route::get('/sessions', [CourseSessionController::class, 'index']);
    Route::put('/sessions/{session}', [CourseSessionController::class, 'update']);
    Route::post('/sessions/{session}/cancel', [CourseSessionController::class, 'cancel']);

    // Calendrier des sessions (le calendrier par professeur revient en T2)
    Route::get('/calendar/month', [CalendarController::class, 'month']);
    Route::get('/calendar/week', [CalendarController::class, 'week']);
    Route::get('/calendar/year', [CalendarController::class, 'year']);
    Route::get('/calendar/agenda', [CalendarController::class, 'agenda']);
    Route::get('/calendar/views', [CalendarController::class, 'getViews']);
    Route::post('/calendar/views', [CalendarController::class, 'saveView']);
    Route::delete('/calendar/views/{view}', [CalendarController::class, 'deleteView']);

    // Tarifs horaires des professeurs (admin seulement)
    Route::get('/professeurs/{professeur}/tarifs', [ProfesseurTarifController::class, 'index']);
    Route::post('/professeurs/{professeur}/tarifs', [ProfesseurTarifController::class, 'store']);
    Route::put('/professeurs/{professeur}/tarifs/{professeurTarif}', [ProfesseurTarifController::class, 'update']);
    Route::delete('/professeurs/{professeur}/tarifs/{professeurTarif}', [ProfesseurTarifController::class, 'destroy']);
    Route::post('/professeurs/{professeur}/tarifs/{professeurTarif}/terminate', [ProfesseurTarifController::class, 'terminate']);
    Route::get('/professeurs/{professeur}/tarif-effectif', [ProfesseurTarifController::class, 'effectiveAt']);

    Route::get('/timesheets', [TimesheetController::class, 'index']);
    Route::post('/timesheets', [TimesheetController::class, 'store']);
    Route::get('/timesheets/{timesheet}', [TimesheetController::class, 'show']);
    Route::put('/timesheets/{timesheet}', [TimesheetController::class, 'update']);
    Route::delete('/timesheets/{timesheet}', [TimesheetController::class, 'destroy']);
    Route::post('/timesheets/{timesheet}/submit', [TimesheetController::class, 'submit']);
    Route::post('/timesheets/{timesheet}/validate', [TimesheetController::class, 'validateEntry']);

    // Phase 1B: Lissage, Signature, Aperçu PDF
    Route::get('/timesheets/{timesheet}/propose-lissage', [TimesheetController::class, 'proposeLissage']);
    Route::post('/timesheets/{timesheet}/apply-lissage', [TimesheetController::class, 'applyLissage']);
    Route::post('/timesheets/{timesheet}/sign', [TimesheetController::class, 'sign']);
    Route::get('/timesheets/preview-pdf', [TimesheetController::class, 'previewPdf']);
    Route::post('/timesheets/sign-month', [TimesheetController::class, 'signMonth']);
    Route::get('/timesheets/can-sign-month', [TimesheetController::class, 'canSignMonth']);

    // Phase 2: Génération et téléchargement PDF
    Route::post('/timesheets/generate-pdf', [TimesheetController::class, 'generatePdf']);
    Route::get('/timesheets/download-pdf', [TimesheetController::class, 'downloadPdf']);

    // Liens de classe / réglages — polymorphes, {parentType} ∈ cours|stages|formations|anniversaires.
    Route::get('/{parentType}/{parentId}/liens', [ClasseLienController::class, 'index']);
    Route::post('/{parentType}/{parentId}/liens', [ClasseLienController::class, 'store']);
    Route::put('/liens/{lien}', [ClasseLienController::class, 'update']);
    Route::delete('/liens/{lien}', [ClasseLienController::class, 'destroy']);

    Route::get('/{parentType}/{parentId}/settings', [ClasseSettingController::class, 'show']);
    Route::put('/{parentType}/{parentId}/settings', [ClasseSettingController::class, 'update']);
});
