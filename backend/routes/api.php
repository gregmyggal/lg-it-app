<?php

use App\Http\Controllers\AnniversaireController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClasseLienController;
use App\Http\Controllers\ClasseSettingController;
use App\Http\Controllers\CoursController;
use App\Http\Controllers\CoursRessourceController;
use App\Http\Controllers\CourseRecurrenceController;
use App\Http\Controllers\CourseSessionController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\ProfesseurController;
use App\Http\Controllers\ProfesseurCoursController;
use App\Http\Controllers\ProfesseurTarifController;
use App\Http\Controllers\SessionProfessorController;
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

    // Sprint 2: Récurrences de cours
    Route::get('/cours/{cours}/recurrences', [CourseRecurrenceController::class, 'index']);
    Route::post('/cours/{cours}/recurrences', [CourseRecurrenceController::class, 'store']);
    Route::get('/cours/{cours}/recurrences/{recurrence}', [CourseRecurrenceController::class, 'show']);
    Route::put('/cours/{cours}/recurrences/{recurrence}', [CourseRecurrenceController::class, 'update']);
    Route::delete('/cours/{cours}/recurrences/{recurrence}', [CourseRecurrenceController::class, 'destroy']);
    Route::post('/cours/{cours}/recurrences/{recurrence}/generate-sessions', [CourseRecurrenceController::class, 'generateSessions']);

    // Sprint 2: Sessions de cours
    Route::get('/sessions', [CourseSessionController::class, 'index']);
    Route::post('/sessions', [CourseSessionController::class, 'store']);
    Route::get('/sessions/{session}', [CourseSessionController::class, 'show']);
    Route::put('/sessions/{session}', [CourseSessionController::class, 'update']);
    Route::delete('/sessions/{session}', [CourseSessionController::class, 'destroy']);
    Route::post('/sessions/{session}/cancel', [CourseSessionController::class, 'cancel']);
    Route::post('/sessions/{session}/in-progress', [CourseSessionController::class, 'markInProgress']);
    Route::post('/sessions/{session}/complete', [CourseSessionController::class, 'markCompleted']);
    Route::get('/cours/{cours}/sessions', [CourseSessionController::class, 'indexByCourse']);

    // Sprint 2: Assignation de professeurs aux sessions
    Route::get('/sessions/{session}/professors', [SessionProfessorController::class, 'indexBySession']);
    Route::post('/sessions/{session}/professors', [SessionProfessorController::class, 'store']);
    Route::put('/sessions/{session}/professors/{assignment}', [SessionProfessorController::class, 'update']);
    Route::delete('/sessions/{session}/professors/{assignment}', [SessionProfessorController::class, 'destroy']);
    Route::post('/sessions/{session}/professors/bulk', [SessionProfessorController::class, 'bulk']);
    Route::post('/sessions/{session}/professors/{assignment}/present', [SessionProfessorController::class, 'markPresent']);
    Route::post('/sessions/{session}/professors/{assignment}/absent', [SessionProfessorController::class, 'markAbsent']);

    // Sprint 2: Calendrier
    Route::get('/calendar/month', [CalendarController::class, 'month']);
    Route::get('/calendar/week', [CalendarController::class, 'week']);
    Route::get('/calendar/year', [CalendarController::class, 'year']);
    Route::get('/calendar/agenda', [CalendarController::class, 'agenda']);
    Route::get('/calendar/professor/{professeur}', [CalendarController::class, 'professorCalendar']);
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
