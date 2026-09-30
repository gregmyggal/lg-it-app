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

  // Rôle insuffisant : retour à l'accueil du portail (et non à la page de connexion).
  if (roles && !roles.includes(user.role)) {
    if (pathname === '/mes-cours') {
      return <p role="alert">Votre rôle ne permet pas d'accéder à cette page.</p>;
    }
    return <Navigate to="/mes-cours" replace />;
  }

  return children;
}
