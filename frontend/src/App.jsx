import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import ProtectedRoute from './auth/ProtectedRoute';
import PortalLayout from './layouts/PortalLayout';
import LoginPage from './pages/LoginPage';
import ChangerMotDePassePage from './pages/ChangerMotDePassePage';
import MotDePasseOubliePage from './pages/MotDePasseOubliePage';
import DefinirMotDePassePage from './pages/DefinirMotDePassePage';
import MesCoursPage from './pages/MesCoursPage';
import CoursLiensPage from './pages/CoursLiensPage';
import NotFoundPage from './pages/NotFoundPage';
import CoursLiensHistoriquePage from './pages/CoursLiensHistoriquePage';
import MesClassesPage from './pages/MesClassesPage';
import MesClasseSessionsPage from './pages/MesClasseSessionsPage';
import TimesheetsPage from './pages/TimesheetsPage';
import AnniversairesAdminPage from './pages/admin/AnniversairesAdminPage';
import AnniversairesEditContentPage from './pages/admin/AnniversairesEditContentPage';
import CoursAdminPage from './pages/admin/CoursAdminPage';
import CoursEditContentPage from './pages/admin/CoursEditContentPage';
import FormationsAdminPage from './pages/admin/FormationsAdminPage';
import FormationsEditContentPage from './pages/admin/FormationsEditContentPage';
import StagesEditContentPage from './pages/admin/StagesEditContentPage';
import AdminProfesseursPage from './pages/AdminProfesseursPage';
import AdminProfesseurDetail from './components/AdminProfesseurDetail';
import AdminTimesheetsPage from './pages/AdminTimesheetsPage';
import ProfesseurTariffPage from './pages/ProfesseurTariffPage';
import AdminCalendarPage from './pages/AdminCalendarPage';
import ToastProvider from './components/toast/ToastProvider';
import ClassesAdminPage from './pages/admin/ClassesAdminPage';
import ClasseCreatePage from './pages/admin/ClasseCreatePage';
import ClasseDetailPage from './pages/admin/ClasseDetailPage';
import CalendrierScolaireAdminPage from './pages/admin/CalendrierScolaireAdminPage';
import AnneesScolairesPage from './pages/admin/AnneesScolairesPage';
import AnneeCreatePage from './pages/admin/AnneeCreatePage';
import AnneePeriodesPage from './pages/admin/AnneePeriodesPage';
import StagesAdminPage from './pages/admin/StagesAdminPage';
import TypesFormationAdminPage from './pages/admin/TypesFormationAdminPage';
import SharePage from './pages/SharePage';
import StaffAdminPage from './pages/admin/StaffAdminPage';
import TimesheetParametresPage from './pages/admin/TimesheetParametresPage';

const STAFF = ['admin', 'directeur'];
const PORTAL = ['professeur', 'directeur', 'admin'];

/** Ancienne route Sprint 2 « sessions d'un cours » → liste des classes filtrée par cours. */
function RedirectCoursSessions() {
  const { coursId } = useParams();
  return <Navigate to={`/admin/classes?cours_id=${encodeURIComponent(coursId)}`} replace />;
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <ToastProvider>
        <Routes>
          <Route path="/" element={<LoginPage />} />
          <Route path="/connexion" element={<LoginPage />} />
          <Route path="/mot-de-passe-oublie" element={<MotDePasseOubliePage />} />
          <Route path="/definir-mot-de-passe" element={<DefinirMotDePassePage />} />
          <Route path="/share/:code" element={<SharePage />} />
          <Route
            path="/mot-de-passe"
            element={
              <ProtectedRoute>
                <ChangerMotDePassePage />
              </ProtectedRoute>
            }
          />

          <Route element={<PortalLayout />}>
            <Route path="mes-cours" element={<Navigate to="/mes-classes" replace />} />
            <Route
              path="mes-classes"
              element={
                <ProtectedRoute roles={PORTAL}>
                  <MesClassesPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="mes-classes/:id"
              element={
                <ProtectedRoute roles={PORTAL}>
                  <MesClasseSessionsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="mes-ressources"
              element={
                <ProtectedRoute roles={PORTAL}>
                  <MesCoursPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="mes-ressources/:coursId"
              element={
                <ProtectedRoute roles={PORTAL}>
                  <CoursLiensPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="mes-ressources/:coursId/historique"
              element={
                <ProtectedRoute roles={PORTAL}>
                  <CoursLiensHistoriquePage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/cours/:coursId/liens"
              element={
                <ProtectedRoute roles={STAFF}>
                  <CoursLiensPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/cours/:coursId/liens/historique"
              element={
                <ProtectedRoute roles={STAFF}>
                  <CoursLiensHistoriquePage />
                </ProtectedRoute>
              }
            />
            <Route
              path="timesheets"
              element={
                <ProtectedRoute roles={PORTAL}>
                  <TimesheetsPage />
                </ProtectedRoute>
              }
            />

            <Route
              path="admin/cours"
              element={
                <ProtectedRoute roles={STAFF}>
                  <CoursAdminPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/cours/:id/contenu"
              element={
                <ProtectedRoute roles={STAFF}>
                  <CoursEditContentPage />
                </ProtectedRoute>
              }
            />
            <Route path="admin/cours/:coursId/sessions" element={<RedirectCoursSessions />} />
            <Route
              path="admin/classes"
              element={
                <ProtectedRoute roles={STAFF}>
                  <ClassesAdminPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/classes/nouvelle"
              element={
                <ProtectedRoute roles={STAFF}>
                  <ClasseCreatePage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/classes/:id"
              element={
                <ProtectedRoute roles={STAFF}>
                  <ClasseDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/annees-scolaires"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AnneesScolairesPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/annees-scolaires/nouvelle"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AnneeCreatePage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/annees-scolaires/:id/periodes"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AnneePeriodesPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/calendrier-scolaire"
              element={
                <ProtectedRoute roles={STAFF}>
                  <CalendrierScolaireAdminPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/calendrier"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AdminCalendarPage />
                </ProtectedRoute>
              }
            />
            <Route path="admin/calendar" element={<Navigate to="/admin/calendrier" replace />} />
            <Route
              path="admin/stages"
              element={
                <ProtectedRoute roles={STAFF}>
                  <StagesAdminPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/stages/:id/contenu"
              element={
                <ProtectedRoute roles={STAFF}>
                  <StagesEditContentPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/formations"
              element={
                <ProtectedRoute roles={STAFF}>
                  <FormationsAdminPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/formations/:id/contenu"
              element={
                <ProtectedRoute roles={STAFF}>
                  <FormationsEditContentPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/anniversaires"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AnniversairesAdminPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/anniversaires/:id/contenu"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AnniversairesEditContentPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/timesheets"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AdminTimesheetsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/timesheets/parametres"
              element={
                <ProtectedRoute roles={STAFF}>
                  <TimesheetParametresPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/professeurs"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AdminProfesseursPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/professeurs/:id"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AdminProfesseurDetail />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/professeurs/:id/tarifs"
              element={
                <ProtectedRoute roles={STAFF}>
                  <ProfesseurTariffPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/types-formation"
              element={
                <ProtectedRoute roles={STAFF}>
                  <TypesFormationAdminPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/staff"
              element={
                <ProtectedRoute roles={['admin']}>
                  <StaffAdminPage />
                </ProtectedRoute>
              }
            />
          </Route>
          <Route path="*" element={<NotFoundPage />} />
        </Routes>
        </ToastProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}
