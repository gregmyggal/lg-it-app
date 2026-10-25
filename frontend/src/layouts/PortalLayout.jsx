import { useEffect, useState } from 'react';
import { useMediaQuery } from '../hooks/useMediaQuery';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import NotificationsBell from '../components/NotificationsBell';
import ThemeToggle from '../components/ThemeToggle';
import AdminModal from '../components/AdminModal';
import MaSignatureModal from '../components/signature/MaSignatureModal';
import { NavIcon, estActive, navigationPour, ongletActif } from './navigation';

const navClass = ({ isActive }) => (isActive ? 'active' : undefined);

export default function PortalLayout() {
  const { user, logout } = useAuth();
  const [feuilleOuverte, setFeuilleOuverte] = useState(false);
  const [maSignature, setMaSignature] = useState(false);
  const { pathname } = useLocation();
  // Sous 860 px : barre du haut + onglets en bas (UI-M2). Au-delà : barre latérale, bâtie sur la même configuration.
  const mobile = useMediaQuery('(max-width: 860px)');
  const nav = navigationPour(user);

  // La feuille « Plus / Moi » se referme après chaque navigation.
  useEffect(() => {
    setFeuilleOuverte(false);
  }, [pathname]);

  const actif = ongletActif(nav, pathname);

  return (
    <div className="portal-shell">
      {mobile ? (
        <header className="portal-top">
          <NavLink to="/" className="brand-mark">
            <span className="dot"></span>Logiscool Pays Vert
          </NavLink>
          <NotificationsBell />
        </header>
      ) : (
        <aside className="portal-side">
          <div className="portal-side__head">
            <NavLink to="/" className="brand-mark">
              <span className="dot"></span>Logiscool Pays Vert
            </NavLink>
          </div>

          <nav id="portal-nav" aria-label="Navigation principale">
            {nav.principal.map((e) => (
              <NavLink key={e.to} to={e.to} end={e.end} className={navClass}>
                <NavIcon nom={e.icone} />
                {e.label}
              </NavLink>
            ))}
            {nav.groupes.map((g) => (
              <div key={g.label} className="portal-side__group">
                <div className="portal-side__group-label">{g.label}</div>
                {g.items.map((e) => (
                  <NavLink key={e.to} to={e.to} className={navClass}>
                    {e.label}
                  </NavLink>
                ))}
              </div>
            ))}
          </nav>

          <div className="portal-side__account">
            <span>
              {user?.name} ({user?.role})
            </span>
            <ThemeToggle />
            <NotificationsBell />
            {user?.role === 'professeur' && <button onClick={() => setMaSignature(true)}>Ma signature</button>}
            <button onClick={logout}>Déconnexion</button>
          </div>
        </aside>
      )}

      <main className="portal-main">
        <div className="portal-page">
          <Outlet />
        </div>
      </main>

      {mobile && (
        <nav className="portal-tabs" aria-label="Navigation principale">
          {nav.principal.map((e) => (
            <NavLink key={e.to} to={e.to} end={e.end} className={() => (actif === e ? 'portal-tab active' : 'portal-tab')} aria-current={actif === e ? 'page' : undefined}>
              <NavIcon nom={e.icone} />
              <span>{e.label}</span>
            </NavLink>
          ))}
          <button
            type="button"
            className={`portal-tab${!actif && nav.groupes.some((g) => g.items.some((e) => estActive(e, pathname))) ? ' active' : ''}`}
            aria-haspopup="dialog"
            onClick={() => setFeuilleOuverte(true)}
          >
            <NavIcon nom={nav.dernierOnglet === 'Moi' ? 'moi' : 'plus'} />
            <span>{nav.dernierOnglet}</span>
          </button>
        </nav>
      )}

      <AdminModal isOpen={mobile && feuilleOuverte} title={nav.dernierOnglet} onClose={() => setFeuilleOuverte(false)} size="sm">
        <div className="sheet-nav">
          {nav.groupes.map((g) => (
            <div key={g.label}>
              <div className="sheet-nav__label">{g.label}</div>
              <ul className="sheet-nav__list">
                {g.items.map((e) => (
                  <li key={e.to}>
                    <NavLink to={e.to} className={navClass}>
                      {e.label}
                    </NavLink>
                  </li>
                ))}
              </ul>
            </div>
          ))}
          <div className="sheet-nav__label">Compte</div>
          <div className="sheet-nav__compte">
            <strong>{user?.name}</strong> <span>({user?.role})</span>
          </div>
          <ThemeToggle />
          <ul className="sheet-nav__list">
            {user?.role === 'professeur' && (
              <li>
                <button
                  type="button"
                  onClick={() => {
                    setFeuilleOuverte(false);
                    setMaSignature(true);
                  }}
                >
                  Ma signature
                </button>
              </li>
            )}
            <li>
              <button type="button" onClick={logout}>
                Déconnexion
              </button>
            </li>
          </ul>
        </div>
      </AdminModal>

      {maSignature && <MaSignatureModal nom={user?.name} onClose={() => setMaSignature(false)} />}
    </div>
  );
}
