import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

const navClass = ({ isActive }) => (isActive ? 'active' : undefined);

export default function PortalLayout() {
  const { user, logout } = useAuth();
  const isStaff = user && (user.role === 'admin' || user.role === 'directeur');

  return (
    <div className="portal-shell">
      <aside className="portal-side">
        <NavLink to="/" className="brand-mark">
          <span className="dot"></span>LG-IT
        </NavLink>

        <nav>
          <NavLink to="/mes-cours" className={navClass}>
            Mes cours
          </NavLink>
          <NavLink to="/timesheets" className={navClass}>
            Timesheets
          </NavLink>

          {isStaff && (
            <>
              <div className="portal-side__group-label">Administration</div>
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
              <NavLink to="/admin/types-cours" className={navClass}>
                Types de cours
              </NavLink>
              <NavLink to="/admin/types-formation" className={navClass}>
                Types de formation
              </NavLink>
              <NavLink to="/admin/professeurs" className={navClass}>
                Professeurs
              </NavLink>
            </>
          )}
        </nav>

        <div className="portal-side__account">
          <span>
            {user?.name} ({user?.role})
          </span>
          <button onClick={logout}>Déconnexion</button>
        </div>
      </aside>

      <main className="portal-main">
        <Outlet />
      </main>
    </div>
  );
}
