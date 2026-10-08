<?php

use App\Http\Controllers\AccesController;
use App\Http\Controllers\AnneeScolaireController;
use App\Http\Controllers\AnniversaireController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\EnsureCompteActif;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendrierScolaireController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\ClassePeriodeController;
use App\Http\Controllers\ClasseLienController;
use App\Http\Controllers\ClasseProfesseurController;
use App\Http\Controllers\ClasseSessionController;
use App\Http\Controllers\ClasseSettingController;
use App\Http\Controllers\CoursController;
use App\Http\Controllers\CourseSessionController;
use App\Http\Controllers\CoursLienController;
use App\Http\Controllers\CoursRessourceController;
use App\Http\Controllers\EmployeurController;
use App\Http\Controllers\EmployeurMoisController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\HeuresDefrayablesController;
use App\Http\Controllers\LienVersionController;
use App\Http\Controllers\MesClassesController;
use App\Http\Controllers\ProfesseurClasseController;
use App\Http\Controllers\ProfesseurController;
use App\Http\Controllers\ProfesseurTarifController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TimesheetConfirmationController;
use App\Http\Controllers\TimesheetDetailMoisController;
use App\Http\Controllers\TimesheetLissageController;
use App\Http\Controllers\TimesheetParametreController;
use App\Http\Controllers\TimesheetPdfController;
use App\Http\Controllers\TimesheetSyntheseMoisController;
use App\Http\Controllers\SessionProfesseurController;
use App\Http\Controllers\ShareCodeController;
use App\Http\Controllers\SignatureController;
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

// SIG-01 : vérification publique d'une signature imprimée sur une fiche (identifiant aléatoire, données minimales).
Route::get('/signatures/{publicId}/verification', [SignatureController::class, 'verifier'])->middleware('throttle:30,1');

// ADMIN-02 : « Mot de passe oublié » et définition du mot de passe via un lien (publics, tous rôles).
Route::post('/mot-de-passe/oublie', [AccesController::class, 'oubli'])->middleware('throttle:acces-oubli');
Route::post('/mot-de-passe/verifier', [AccesController::class, 'verifier'])->middleware('throttle:acces-lien');
Route::post('/mot-de-passe/definir', [AccesController::class, 'definir'])->middleware('throttle:acces-lien');

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
    Route::post('/professeurs/envoyer-invitations', [ProfesseurController::class, 'envoyerInvitations']);
    Route::apiResource('professeurs', ProfesseurController::class);
    // PROF-01 : cycle de vie du professeur et de son compte.
    Route::get('/professeurs/{professeur}/impact-desactivation', [ProfesseurController::class, 'impact']);
    Route::get('/professeurs/{professeur}/impact-suppression', [ProfesseurController::class, 'impactSuppression']);
    Route::post('/professeurs/{professeur}/desactiver', [ProfesseurController::class, 'desactiver']);
    Route::post('/professeurs/{professeur}/reactiver', [ProfesseurController::class, 'reactiver']);
    // ADMIN-03 : invitation / lien de réinitialisation envoyés par email (même contrat que le staff).
    Route::post('/professeurs/{professeur}/envoyer-lien', [ProfesseurController::class, 'envoyerLien']);
    Route::post('/professeurs/{professeur}/generer-lien', [ProfesseurController::class, 'genererLien']);
    Route::put('/professeurs/{professeur}/compte', [ProfesseurController::class, 'changerEmailConnexion']);

    // ADMIN-01 : gestion des comptes admin/directeur (staff).
    Route::post('/staff/envoyer-invitations', [StaffController::class, 'envoyerInvitations']);
    Route::apiResource('staff', StaffController::class);
    Route::get('/staff/{staff}/impact-info', [StaffController::class, 'impactInfo']);
    Route::post('/staff/{staff}/desactiver', [StaffController::class, 'desactiver']);
    Route::post('/staff/{staff}/reactiver', [StaffController::class, 'reactiver']);
    // ADMIN-02 : invitation / lien de réinitialisation envoyés par email.
    Route::post('/staff/{staff}/envoyer-lien', [StaffController::class, 'envoyerLien']);
    Route::post('/staff/{staff}/generer-lien', [StaffController::class, 'genererLien']);

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
    Route::post('/sessions/{session}/professeurs', [SessionProfesseurController::class, 'ajouter']);
    Route::delete('/sessions/{session}/professeurs/{professeur}', [SessionProfesseurController::class, 'retirer']);
    Route::post('/sessions/{session}/remplacer', [SessionProfesseurController::class, 'remplacer']);
    Route::delete('/sessions/{session}/remplacements/{professeur}', [SessionProfesseurController::class, 'annulerRemplacement']);

    Route::get('/mes-classes', [MesClassesController::class, 'index']);
    Route::get('/mes-classes/{classe}/sessions', [MesClassesController::class, 'sessions']);

    // CLS-01 T1 : années scolaires, calendrier scolaire, classes, sessions (admin/directeur).
    Route::get('/annees-scolaires', [AnneeScolaireController::class, 'index']);
    Route::post('/annees-scolaires', [AnneeScolaireController::class, 'store']);
    Route::get('/annees-scolaires/proposition', [AnneeScolaireController::class, 'proposition']);
    Route::get('/annees-scolaires/{annee}', [AnneeScolaireController::class, 'show']);
    Route::post('/annees-scolaires/{annee}/apercu-impact', [AnneeScolaireController::class, 'apercuImpact']);
    Route::post('/annees-scolaires/{annee}/archiver', [AnneeScolaireController::class, 'archiver']);
    Route::post('/annees-scolaires/{annee}/reactiver', [AnneeScolaireController::class, 'reactiver']);
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
    Route::get('/classes/{classe}/duplication', [ClasseController::class, 'duplication']);
    Route::put('/classes/{classe}', [ClasseController::class, 'update']);
    Route::delete('/classes/{classe}', [ClasseController::class, 'destroy']);
    // CLS-02 : périodes d'une classe
    Route::post('/classes/{classe}/periodes/apercu', [ClassePeriodeController::class, 'apercu']);
    Route::post('/classes/{classe}/periodes', [ClassePeriodeController::class, 'store']);
    Route::put('/classes/{classe}/periodes/{classePeriode}', [ClassePeriodeController::class, 'update']);
    Route::delete('/classes/{classe}/periodes/{classePeriode}', [ClassePeriodeController::class, 'destroy']);
    Route::get('/classes/{classe}/periodes/{classePeriode}/historique-cours', [ClassePeriodeController::class, 'historiqueCours']);
    Route::post('/classes/{classe}/periodes/{classePeriode}/annuler', [ClassePeriodeController::class, 'annuler']);
    Route::get('/classes/{classe}/sessions', [ClasseSessionController::class, 'index']);
    Route::post('/classes/{classe}/sessions/bis', [ClasseSessionController::class, 'bis']);

    Route::get('/sessions', [CourseSessionController::class, 'index']);
    Route::put('/sessions/{session}', [CourseSessionController::class, 'update']);
    Route::post('/sessions/{session}/deplacement/apercu', [CourseSessionController::class, 'apercuDeplacement']);
    Route::post('/sessions/{session}/cancel', [CourseSessionController::class, 'cancel']);

    // Calendrier des sessions (le calendrier par professeur revient en T2)
    Route::get('/calendar/month', [CalendarController::class, 'month']);
    Route::get('/calendar/week', [CalendarController::class, 'week']);
    Route::get('/calendar/year', [CalendarController::class, 'year']);
    Route::get('/calendar/agenda', [CalendarController::class, 'agenda']);
    Route::get('/calendar/views', [CalendarController::class, 'getViews']);
    Route::post('/calendar/views', [CalendarController::class, 'saveView']);
    Route::delete('/calendar/views/{view}', [CalendarController::class, 'deleteView']);

    // TS-00 : plafonds de défraiement par année civile (directeur et admin)
    Route::get('/heures-defrayables', [HeuresDefrayablesController::class, 'show']);
    Route::post('/heures-defrayables/impact', [HeuresDefrayablesController::class, 'impact']);
    Route::get('/timesheet-parametres/{annee}', [TimesheetParametreController::class, 'show'])->whereNumber('annee');
    Route::put('/timesheet-parametres/{annee}', [TimesheetParametreController::class, 'update'])->whereNumber('annee');

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
    Route::get('/professeurs/{professeur}/timesheets-mois', [TimesheetDetailMoisController::class, 'show']);
    Route::post('/professeurs/{professeur}/timesheets-mois/lissage/apercu', [TimesheetLissageController::class, 'apercu']);
    Route::post('/professeurs/{professeur}/timesheets-mois/lissage', [TimesheetLissageController::class, 'appliquer']);
    Route::post('/professeurs/{professeur}/timesheet-pdfs', [TimesheetPdfController::class, 'generer']);
    Route::get('/professeurs/{professeur}/timesheet-pdfs', [TimesheetPdfController::class, 'index']);
    Route::get('/professeurs/{professeur}/timesheet-pdfs/apercu', [TimesheetPdfController::class, 'apercu']);
    Route::post('/professeurs/{professeur}/timesheets-mois/deverrouiller', [TimesheetPdfController::class, 'deverrouiller']);
    Route::post('/timesheets-mois/pdf-lot', [TimesheetPdfController::class, 'lot']);
    Route::get('/timesheet-pdfs/{pdf}/telecharger', [TimesheetPdfController::class, 'telecharger']);
    Route::get('/timesheets/ma-confirmation', [TimesheetConfirmationController::class, 'etat']);
    Route::post('/timesheets/contester-mois', [TimesheetConfirmationController::class, 'contester']);
    Route::post('/professeurs/{professeur}/timesheets-mois/remettre-en-brouillon', [TimesheetConfirmationController::class, 'remettreEnBrouillon']);
    Route::post('/professeurs/{professeur}/timesheets-mois/traiter-contestation', [TimesheetConfirmationController::class, 'traiter']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/lues', [NotificationController::class, 'toutesLues']);
    Route::post('/notifications/{id}/lue', [NotificationController::class, 'lue']);
    // EMP-01 : employeur d'un animateur, mois par mois (entités, frise, édition, lot, historique).
    Route::get('/employeurs', [EmployeurController::class, 'index']);
    Route::post('/employeurs', [EmployeurController::class, 'store']);
    Route::put('/employeurs/{employeur}', [EmployeurController::class, 'update']);
    Route::get('/professeurs/{professeur}/employeurs-mois', [EmployeurMoisController::class, 'index']);
    Route::get('/professeurs/{professeur}/employeurs-mois/historique', [EmployeurMoisController::class, 'historique']);
    Route::put('/professeurs/{professeur}/employeurs-mois/{annee}/{mois}', [EmployeurMoisController::class, 'update'])->whereNumber(['annee', 'mois']);
    Route::post('/employeurs-mois/lot', [EmployeurMoisController::class, 'lot']);
    Route::get('/timesheets/mois-synthese', [TimesheetSyntheseMoisController::class, 'show']);
    Route::post('/timesheets/valider-lot', [TimesheetValidationController::class, 'validerLot']);

    Route::get('/timesheets', [TimesheetController::class, 'index']);
    Route::post('/timesheets', [TimesheetController::class, 'store']);
    Route::get('/timesheets/{timesheet}', [TimesheetController::class, 'show']);
    Route::put('/timesheets/{timesheet}', [TimesheetController::class, 'update']);
    Route::delete('/timesheets/{timesheet}', [TimesheetController::class, 'destroy']);
    Route::post('/timesheets/{timesheet}/submit', [TimesheetController::class, 'submit']);
    Route::post('/timesheets/{timesheet}/adapter', [TimesheetController::class, 'adapter']);
    Route::get('/timesheets/{timesheet}/historique', [TimesheetController::class, 'historique']);
    Route::post('/timesheets/{timesheet}/validate', [TimesheetController::class, 'validateEntry']);

    // Phase 1B: Lissage, Signature, Aperçu PDF
    Route::get('/timesheets/{timesheet}/propose-lissage', [TimesheetController::class, 'proposeLissage']);
    Route::post('/timesheets/{timesheet}/apply-lissage', [TimesheetController::class, 'applyLissage']);
    Route::get('/timesheets/preview-pdf', [TimesheetController::class, 'previewPdf']);
    Route::post('/timesheets/sign-month', [TimesheetController::class, 'signMonth']);
    Route::get('/ma-signature', [SignatureController::class, 'show']);
    Route::put('/ma-signature', [SignatureController::class, 'update']);
    Route::get('/signature-parametres', [SignatureController::class, 'parametres']);
    Route::put('/signature-parametres', [SignatureController::class, 'enregistrerParametres']);
    Route::get('/timesheets/can-sign-month', [TimesheetController::class, 'canSignMonth']);


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
