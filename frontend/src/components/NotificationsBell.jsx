import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../api/client';
import { formatDateHeure } from '../utils/dates';

const INTERVALLE_MS = 60000;

/**
 * Cloche de notifications (TS-01 T4) : compteur de non lues, liste déroulante, clic = marque lue + ouvre l'écran concerné.
 * Rafraîchie toutes les minutes ; une erreur réseau laisse simplement l'état précédent (la cloche n'est jamais bloquante).
 */
export default function NotificationsBell() {
  const navigate = useNavigate();
  const [etat, setEtat] = useState({ non_lues: 0, data: [] });
  const [ouvert, setOuvert] = useState(false);
  const conteneur = useRef(null);

  const charger = useCallback(() => {
    client.get('/notifications').then((res) => setEtat(res.data)).catch(() => {});
  }, []);

  useEffect(() => {
    charger();
    const timer = setInterval(charger, INTERVALLE_MS);
    return () => clearInterval(timer);
  }, [charger]);

  useEffect(() => {
    if (!ouvert) return undefined;
    const fermer = (e) => {
      if (e.type === 'keydown' ? e.key === 'Escape' : !conteneur.current?.contains(e.target)) setOuvert(false);
    };
    document.addEventListener('pointerdown', fermer);
    document.addEventListener('keydown', fermer);
    return () => {
      document.removeEventListener('pointerdown', fermer);
      document.removeEventListener('keydown', fermer);
    };
  }, [ouvert]);

  async function ouvrir(n) {
    setOuvert(false);
    if (!n.lue) await client.post(`/notifications/${n.id}/lue`).catch(() => {});
    charger();
    if (n.url) navigate(n.url);
  }

  async function toutesLues() {
    await client.post('/notifications/lues').catch(() => {});
    charger();
  }

  return (
    <div ref={conteneur} style={{ position: 'relative' }}>
      <button
        type="button"
        className="notif-btn"
        onClick={() => setOuvert((o) => !o)}
        aria-expanded={ouvert}
        aria-label={`Notifications${etat.non_lues ? ` (${etat.non_lues} non lues)` : ''}`}
        style={{ cursor: 'pointer' }}
      >
        🔔{etat.non_lues > 0 && <strong style={{ marginLeft: 4, color: 'var(--tone-error-fg)' }}>{etat.non_lues}</strong>}
      </button>
      {ouvert && (
        <div
          role="region"
          className="notif-pop"
          aria-label="Notifications"
          style={{ position: 'absolute', bottom: '110%', left: 0, width: 300, maxHeight: 360, overflow: 'auto', background: 'var(--c-card)', color: 'var(--c-text)', border: '1px solid var(--c-border)', borderRadius: 8, boxShadow: '0 8px 24px var(--c-shadow)', zIndex: 50 }}
        >
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 12px', borderBottom: '1px solid var(--c-border)' }}>
            <strong>Notifications</strong>
            {etat.non_lues > 0 && <button type="button" onClick={toutesLues} style={{ cursor: 'pointer' }}>Tout marquer comme lu</button>}
          </div>
          {etat.data.length === 0 && <p style={{ padding: 12, margin: 0, color: 'var(--c-text-2)' }}>Aucune notification.</p>}
          {etat.data.map((n) => (
            <button
              key={n.id}
              type="button"
              onClick={() => ouvrir(n)}
              style={{ display: 'block', width: '100%', textAlign: 'left', padding: '8px 12px', border: 0, borderBottom: '1px solid var(--c-border-light)', background: n.lue ? 'var(--c-card)' : 'var(--c-primary-light)', color: 'var(--c-text)', cursor: 'pointer' }}
            >
              <div style={{ fontWeight: n.lue ? 400 : 700 }}>{n.titre}</div>
              <div style={{ fontSize: 12, color: 'var(--c-text-muted)' }}>{n.message}</div>
              <div style={{ fontSize: 11, color: 'var(--c-text-2)' }}>{formatDateHeure(n.created_at)}</div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
