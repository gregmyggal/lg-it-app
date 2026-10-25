import { Link, NavLink, useNavigate } from 'react-router-dom';
import { avecTransition } from '../../utils/transition';

/** Clic « simple » : laisse le navigateur gérer ouverture dans un nouvel onglet, clic milieu, touches modificatrices. */
const clicSimple = (e) => e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey;

function useClic(to, onClick) {
  const navigate = useNavigate();
  return (e) => {
    onClick?.(e);
    if (e.defaultPrevented || !clicSimple(e)) return;
    e.preventDefault();
    avecTransition(() => navigate(to));
  };
}

/** `Link` avec transition d'écran native (UI-M8). */
export function LienTransition({ to, onClick, ...props }) {
  return <Link to={to} onClick={useClic(to, onClick)} {...props} />;
}

/** `NavLink` avec transition d'écran native (UI-M8). */
export function NavLienTransition({ to, onClick, ...props }) {
  return <NavLink to={to} onClick={useClic(to, onClick)} {...props} />;
}
