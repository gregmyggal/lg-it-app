import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import ProtectedRoute from './auth/ProtectedRoute';
import PortalLayout from './layouts/PortalLayout';
import LoginPage from './pages/LoginPage';
import MesCoursPage from './pages/MesCoursPage';
import TimesheetsPage from './pages/TimesheetsPage';
import AnniversairesAdminPage from './pages/admin/AnniversairesAdminPage';
import AnniversairesEditContentPage from './pages/admin/AnniversairesEditContentPage';
import CoursAdminPage from './pages/admin/CoursAdminPage';
import CoursEditContentPage from './pages/admin/CoursEditContentPage';
import FormationsAdminPage from './pages/admin/FormationsAdminPage';
import FormationsEditContentPage from './pages/admin/FormationsEditContentPage';
import StagesEditContentPage from './pages/admin/StagesEditContentPage';
import ProfesseursAdminPage from './pages/admin/ProfesseursAdminPage';
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
import StagesAdminPage from './pages/admin/StagesAdminPage';
import TypesCoursAdminPage from './pages/admin/TypesCoursAdminPage';
import TypesFormationAdminPage from './pages/admin/TypesFormationAdminPage';
import SharePage from './pages/SharePage';

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
          <Route path="/share/:code" element={<SharePage />} />

          <Route element={<PortalLayout />}>
            <Route
              path="mes-cours"
              element={
                <ProtectedRoute roles={PORTAL}>
                  <MesCoursPage />
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
              path="admin/types-cours"
              element={
                <ProtectedRoute roles={STAFF}>
                  <TypesCoursAdminPage />
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
          </Route>
        </Routes>
        </ToastProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}
