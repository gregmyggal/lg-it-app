import { Link } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

/** Page « introuvable » (404) : URL inconnue ou supprimée (ex. l'ancien écran « Types de cours », CLS-01 T5). */
export default function NotFoundPage() {
  const { user } = useAuth();
  const accueil = !user ? '/connexion' : user.role === 'professeur' ? '/mes-classes' : '/admin/classes';

  return (
    <main style={{ maxWidth: '520px', margin: '15vh auto', padding: '0 24px', textAlign: 'center' }}>
      <div style={{ fontSize: '48px' }} aria-hidden="true">
        🧭
      </div>
      <h1 style={{ fontSize: '24px' }}>Page introuvable</h1>
      <p>Cette page n'existe pas ou a été supprimée. Vérifiez l'adresse, ou retournez à l'accueil.</p>
      <p>
        <Link to={accueil}>Retour à l'accueil</Link>
      </p>
    </main>
  );
}
