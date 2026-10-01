import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import LinkButton from '../components/ui/LinkButton';
import LiensDuCours from '../components/liens/LiensDuCours';

/**
 * Écran « Liens du cours » (CLS-01 T4, mock-up 01), un seul composant pour les deux rôles : `/admin/cours/:coursId/liens`
 * (admin, directeur) et `/mes-ressources/:coursId` (professeur). Seuls le fil d'Ariane et le lien « Historique » changent.
 */
export default function CoursLiensPage() {
  const { coursId } = useParams();
  const { user } = useAuth();
  const staff = user.role === 'admin' || user.role === 'directeur';
  const racine = staff ? '/admin/cours' : '/mes-ressources';
  const base = staff ? `/admin/cours/${coursId}/liens` : `/mes-ressources/${coursId}`;
  const [infos, setInfos] = useState(null);
  const titre = infos?.cours?.titre;

  return (
    <>
      <AdminPageHeader
        icon="🔗"
        title={titre ? `Liens du cours ${titre}` : 'Liens du cours'}
        description="Les liens partagés par toutes les classes du cours : généraux, ou propres à une séance (1 à 14). Les professeurs du cours les adaptent pour tous."
        breadcrumb={
          <>
            {staff ? 'Catalogue › ' : ''}
            <Link to={racine}>{staff ? 'Cours' : 'Liens de mes cours'}</Link> › {titre || 'Liens'}
          </>
        }
        action={infos?.peut_voir_historique && <LinkButton to={`${base}/historique`}>🕘 Historique</LinkButton>}
      />
      <AdminPageContent>
        <LiensDuCours coursId={coursId} onChargé={setInfos} />
      </AdminPageContent>
    </>
  );
}
