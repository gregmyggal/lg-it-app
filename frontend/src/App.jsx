import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import ProtectedRoute from './auth/ProtectedRoute';
import Layout from './layouts/Layout';
import PortalLayout from './layouts/PortalLayout';
import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';
import CoursListPage from './pages/CoursListPage';
import CoursDetailPage from './pages/CoursDetailPage';
import StagesListPage from './pages/StagesListPage';
import StagesDetailPage from './pages/StagesDetailPage';
import FormationsListPage from './pages/FormationsListPage';
import FormationsDetailPage from './pages/FormationsDetailPage';
import AnniversairesDetailPage from './pages/AnniversairesDetailPage';
import ContactPage from './pages/ContactPage';
import FondersPage from './pages/FondersPage';
import AboutPage from './pages/AboutPage';
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
import StagesAdminPage from './pages/admin/StagesAdminPage';
import TypesCoursAdminPage from './pages/admin/TypesCoursAdminPage';
import TypesFormationAdminPage from './pages/admin/TypesFormationAdminPage';

const STAFF = ['admin', 'directeur'];
const PORTAL = ['professeur', 'directeur', 'admin'];

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route element={<Layout />}>
            <Route index element={<HomePage />} />
            <Route path="connexion" element={<LoginPage />} />
            <Route path="cours" element={<CoursListPage />} />
            <Route path="cours/:slug" element={<CoursDetailPage />} />
            <Route path="stages" element={<StagesListPage />} />
            <Route path="stages/:slug" element={<StagesDetailPage />} />
            <Route path="formations" element={<FormationsListPage />} />
            <Route path="formations/:slug" element={<FormationsDetailPage />} />
            <Route path="anniversaires/:slug" element={<AnniversairesDetailPage />} />
            <Route path="contact" element={<ContactPage />} />
            <Route path="fondateurs" element={<FondersPage />} />
            <Route path="apropos" element={<AboutPage />} />
          </Route>

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
              path="admin/professeurs"
              element={
                <ProtectedRoute roles={STAFF}>
                  <ProfesseursAdminPage />
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
