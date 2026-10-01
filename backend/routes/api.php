<?php

use App\Http\Controllers\AnneeScolaireController;
use App\Http\Controllers\AnniversaireController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\EnsureCompteActif;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendrierScolaireController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\ClasseLienController;
use App\Http\Controllers\ClasseProfesseurController;
use App\Http\Controllers\ClasseSessionController;
use App\Http\Controllers\ClasseSettingController;
use App\Http\Controllers\CoursController;
use App\Http\Controllers\CourseSessionController;
use App\Http\Controllers\CoursLienController;
use App\Http\Controllers\CoursRessourceController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\LienVersionController;
use App\Http\Controllers\MesClassesController;
use App\Http\Controllers\ProfesseurClasseController;
use App\Http\Controllers\ProfesseurController;
use App\Http\Controllers\ProfesseurTarifController;
use App\Http\Controllers\SessionProfesseurController;
use App\Http\Controllers\ShareCodeController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\StageDateController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\TimesheetMoisController;
use App\Http\Controllers\TimesheetValidationController;
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
Route::middleware(['auth:sanctum', EnsureCompteActif::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/mot-de-passe', [AuthController::class, 'changerMotDePasse']);

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
    // Pas de Route::apiResource ici : le tiret dans "types-formation"
    // casserait le nom de paramètre attendu par le binding implicite ($typeFormation).
    Route::get('/types-formation', [TypeFormationController::class, 'index']);
    Route::post('/types-formation', [TypeFormationController::class, 'store']);
    Route::get('/types-formation/{typeFormation}', [TypeFormationController::class, 'show']);
    Route::put('/types-formation/{typeFormation}', [TypeFormationController::class, 'update']);
    Route::delete('/types-formation/{typeFormation}', [TypeFormationController::class, 'destroy']);
    Route::apiResource('professeurs', ProfesseurController::class);
    // PROF-01 : cycle de vie du professeur et de son compte.
    Route::get('/professeurs/{professeur}/impact-desactivation', [ProfesseurController::class, 'impact']);
    Route::post('/professeurs/{professeur}/desactiver', [ProfesseurController::class, 'desactiver']);
    Route::post('/professeurs/{professeur}/reactiver', [ProfesseurController::class, 'reactiver']);
    Route::post('/professeurs/{professeur}/reinitialiser-mot-de-passe', [ProfesseurController::class, 'reinitialiserMotDePasse']);
    Route::put('/professeurs/{professeur}/compte', [ProfesseurController::class, 'changerEmailConnexion']);

    // ADMIN-01 : gestion des comptes admin/directeur (staff).
    Route::apiResource('staff', StaffController::class);
    Route::get('/staff/{staff}/impact-info', [StaffController::class, 'impactInfo']);
    Route::post('/staff/{staff}/desactiver', [StaffController::class, 'desactiver']);
    Route::post('/staff/{staff}/reactiver', [StaffController::class, 'reactiver']);
    Route::post('/staff/{staff}/reinitialiser-mot-de-passe', [StaffController::class, 'reinitialiserMotDePasse']);

    // CLS-01 T2 : assignation des professeurs aux classes (mêmes services dans les deux sens),
    // remplacement ponctuel sur une session, portail « Mes classes ».
    Route::get('/classes/{classe}/professeurs', [ClasseProfesseurController::class, 'index']);
    Route::post('/classes/{classe}/professeurs/apercu', [ClasseProfesseurController::class, 'apercu']);
    Route::post('/classes/{classe}/professeurs', [ClasseProfesseurController::class, 'store']);
    Route::put('/classes/{classe}/professeurs/{professeur}', [ClasseProfesseurController::class, 'update']);
    Route::delete('/classes/{classe}/professeurs/{professeur}', [ClasseProfesseurController::class, 'destroy']);

    Route::get('/professeurs/{professeur}/classes', [ProfesseurClasseController::class, 'index']);
    Route::post('/professeurs/{professeur}/classes/apercu', [ProfesseurClasseController::class, 'apercu']);
    Route::post('/professeurs/{professeur}/classes', [ProfesseurClasseController::class, 'store']);
    Route::put('/professeurs/{professeur}/classes/{classe}', [ProfesseurClasseController::class, 'update']);
    Route::delete('/professeurs/{professeur}/classes/{classe}', [ProfesseurClasseController::class, 'destroy']);

    Route::get('/sessions/{session}/professeurs', [SessionProfesseurController::class, 'index']);
    Route::post('/sessions/{session}/remplacer', [SessionProfesseurController::class, 'remplacer']);
    Route::delete('/sessions/{session}/remplacements/{professeur}', [SessionProfesseurController::class, 'annulerRemplacement']);

    Route::get('/mes-classes', [MesClassesController::class, 'index']);
    Route::get('/mes-classes/{classe}/sessions', [MesClassesController::class, 'sessions']);

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

    // CLS-01 T3 : écran mensuel, vue directeur par session, validation en lot (déclarées AVANT /timesheets/{timesheet}).
    Route::get('/timesheets/mon-mois', [TimesheetMoisController::class, 'monMois']);
    Route::post('/timesheets/soumettre-mois', [TimesheetMoisController::class, 'soumettreMois']);
    Route::get('/timesheets/sessions-sans-heures', [TimesheetValidationController::class, 'sessionsSansHeures']);
    Route::post('/timesheets/valider-lot', [TimesheetValidationController::class, 'validerLot']);

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
    // CLS-01 T4 : liens d'un COURS (versionnés), déclarés AVANT les routes génériques par type de parent.
    Route::get('/cours/{cours}/liens', [CoursLienController::class, 'index']);
    Route::post('/cours/{cours}/liens', [CoursLienController::class, 'store']);
    Route::put('/cours/{cours}/liens/ordre', [CoursLienController::class, 'ordre']);
    Route::get('/cours/{cours}/liens/historique', [CoursLienController::class, 'historique']);
    Route::post('/cours/{cours}/liens/reprendre-ressources', [CoursLienController::class, 'reprendre']);
    Route::post('/liens-versions/{version}/restaurer', [LienVersionController::class, 'restaurer']);

    Route::get('/{parentType}/{parentId}/liens', [ClasseLienController::class, 'index']);
    Route::post('/{parentType}/{parentId}/liens', [ClasseLienController::class, 'store']);
    Route::put('/liens/{lien}', [ClasseLienController::class, 'update']);
    Route::delete('/liens/{lien}', [ClasseLienController::class, 'destroy']);

    Route::get('/{parentType}/{parentId}/settings', [ClasseSettingController::class, 'show']);
    Route::put('/{parentType}/{parentId}/settings', [ClasseSettingController::class, 'update']);
});
