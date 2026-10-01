import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from './AuthContext';

export default function ProtectedRoute({ roles, children }) {
  const { user, loading } = useAuth();
  const { pathname } = useLocation();

  if (loading) {
    return <p>Chargement…</p>;
  }

  if (!user) {
    return <Navigate to="/connexion" replace />;
  }

  // Mot de passe provisoire : changement obligatoire avant toute autre page.
  if (user.must_change_password && pathname !== '/mot-de-passe') {
    return <Navigate to="/mot-de-passe" replace />;
  }

  // Rôle insuffisant : retour à l'accueil du portail (et non à la page de connexion).
  if (roles && !roles.includes(user.role)) {
    const accueil = user.role === 'professeur' ? '/mes-classes' : '/admin/classes';
    if (pathname === accueil) {
      return <p role="alert">Votre rôle ne permet pas d'accéder à cette page.</p>;
    }
    return <Navigate to={accueil} replace />;
  }

  return children;
}
