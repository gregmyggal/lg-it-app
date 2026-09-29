import { BrowserRouter, Route, Routes } from 'react-router-dom';
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
import AdminCoursSessionsPage from './pages/AdminCoursSessionsPage';
import StagesAdminPage from './pages/admin/StagesAdminPage';
import TypesCoursAdminPage from './pages/admin/TypesCoursAdminPage';
import TypesFormationAdminPage from './pages/admin/TypesFormationAdminPage';
import SharePage from './pages/SharePage';

const STAFF = ['admin', 'directeur'];
const PORTAL = ['professeur', 'directeur', 'admin'];

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
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
            <Route
              path="admin/cours/:coursId/sessions"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AdminCoursSessionsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="admin/calendar"
              element={
                <ProtectedRoute roles={STAFF}>
                  <AdminCalendarPage />
                </ProtectedRoute>
              }
            />
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
      </AuthProvider>
    </BrowserRouter>
  );
}
