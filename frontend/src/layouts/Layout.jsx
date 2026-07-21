import { Link, Outlet } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

export default function Layout() {
  const { user, logout } = useAuth();

  return (
    <div className="app-shell">
      <header className="app-nav">
        <Link to="/" className="brand-mark">
          <span className="dot"></span>LG-IT
        </Link>
        <nav>
          <Link to="/">Accueil</Link>
          <Link to="/cours">Cours</Link>
          <Link to="/stages">Stages</Link>
          <Link to="/formations">Formations</Link>
          <Link to="/apropos">L'esprit LG-IT</Link>
          <Link to="/contact">Contact</Link>
        </nav>
        <div className="app-nav__account">
          {user ? (
            <>
              <Link to="/mes-cours" className="btn">
                Mon espace
              </Link>
              <button onClick={logout}>Déconnexion</button>
            </>
          ) : (
            <Link to="/connexion" className="btn">
              Connexion
            </Link>
          )}
        </div>
      </header>
      <main className="app-main">
        <Outlet />
      </main>
    </div>
  );
}
