import { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import NotificationsBell from '../components/NotificationsBell';
import ThemeToggle from '../components/ThemeToggle';

const navClass = ({ isActive }) => (isActive ? 'active' : undefined);

export default function PortalLayout() {
  const { user, logout } = useAuth();
  const isStaff = user && (user.role === 'admin' || user.role === 'directeur');
  const [menuOuvert, setMenuOuvert] = useState(false);
  const { pathname } = useLocation();

  // Sur mobile, le menu se referme après chaque navigation.
  useEffect(() => {
    setMenuOuvert(false);
  }, [pathname]);

  return (
    <div className="portal-shell">
      <aside className={`portal-side${menuOuvert ? ' portal-side--open' : ''}`}>
        <div className="portal-side__head">
          <NavLink to="/" className="brand-mark">
            <span className="dot"></span>Logiscool Pays Vert
          </NavLink>
          <button
            type="button"
            className="portal-side__toggle"
            aria-expanded={menuOuvert}
            aria-controls="portal-nav"
            onClick={() => setMenuOuvert((ouvert) => !ouvert)}
          >
            {menuOuvert ? 'Fermer le menu' : 'Menu'}
          </button>
        </div>

        <nav id="portal-nav" aria-label="Navigation principale">
          {!isStaff && (
            <>
              <NavLink to="/mes-classes" className={navClass}>
                Mes classes
              </NavLink>
              <NavLink to="/mes-ressources" className={navClass}>
                Liens de mes cours
              </NavLink>
            </>
          )}
          {!isStaff && (
            <NavLink to="/timesheets" className={navClass}>
              Timesheets
            </NavLink>
          )}

          {isStaff && (
            <>
              <div className="portal-side__group-label">Gestion Opérationnelle</div>
              <NavLink to="/admin/timesheets" end className={navClass}>
                📊 Timesheets
              </NavLink>
              <NavLink to="/admin/timesheets/parametres" className={navClass}>
                ⚙️ Paramètres timesheets
              </NavLink>
              <NavLink to="/admin/professeurs" className={navClass}>
                👨‍🏫 Professeurs & Tarifs
              </NavLink>
              {user?.role === 'admin' && (
                <NavLink to="/admin/staff" className={navClass}>
                  👥 Gestion de l'équipe
                </NavLink>
              )}

              <div className="portal-side__group-label">Scolarité</div>
              <NavLink to="/admin/classes" className={navClass}>
                Classes
              </NavLink>
              <NavLink to="/admin/calendrier" className={navClass}>
                Calendrier
              </NavLink>
              <NavLink to="/admin/annees-scolaires" className={navClass}>
                Années scolaires
              </NavLink>
              <NavLink to="/admin/calendrier-scolaire" className={navClass}>
                Calendrier scolaire
              </NavLink>

              <div className="portal-side__group-label">Contenu</div>
              <NavLink to="/admin/cours" className={navClass}>
                Cours
              </NavLink>
              <NavLink to="/admin/stages" className={navClass}>
                Stages
              </NavLink>
              <NavLink to="/admin/formations" className={navClass}>
                Formations
              </NavLink>
              <NavLink to="/admin/anniversaires" className={navClass}>
                Anniversaires
              </NavLink>
              <NavLink to="/admin/types-formation" className={navClass}>
                Types de formation
              </NavLink>
            </>
          )}
        </nav>

        <div className="portal-side__account">
          <span>
            {user?.name} ({user?.role})
          </span>
          <ThemeToggle />
          <NotificationsBell />
          <button onClick={logout}>Déconnexion</button>
        </div>
      </aside>

      <main className="portal-main">
        <div className="portal-page">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
