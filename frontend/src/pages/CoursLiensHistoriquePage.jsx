import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import HistoriqueLiens from '../components/liens/HistoriqueLiens';
import { useLiensCours } from '../hooks/useLiens';

/** Historique des liens d'un cours (CLS-01 T4, mock-up 02) : staff et professeurs ayant (ou ayant eu) une classe du cours. */
export default function CoursLiensHistoriquePage() {
  const { coursId } = useParams();
  const { user } = useAuth();
  const staff = user.role === 'admin' || user.role === 'directeur';
  const base = staff ? `/admin/cours/${coursId}/liens` : `/mes-ressources/${coursId}`;
  const liens = useLiensCours(coursId);
  const titre = liens.data?.cours?.titre;

  return (
    <>
      <AdminPageHeader
        icon="🕘"
        title={titre ? `Historique des liens — ${titre}` : 'Historique des liens'}
        breadcrumb={
          <>
            <Link to={staff ? '/admin/cours' : '/mes-ressources'}>{staff ? 'Cours' : 'Liens de mes cours'}</Link> › <Link to={base}>{titre || 'Liens'}</Link> › Historique
          </>
        }
      />
      <AdminPageContent>
        <HistoriqueLiens coursId={coursId} />
      </AdminPageContent>
    </>
  );
}
