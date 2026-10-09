import { useCallback, useMemo, useRef, useState } from 'react';
import { ADMIN_TONES, ADMIN_SPACING, ADMIN_RADIUS, ADMIN_SHADOWS } from '../../styles/AdminDesignSystem';
import { ToastContext } from './ToastContext';

/**
 * Toasts de confirmation/erreur. La région est toujours montée et annoncée
 * (`aria-live="polite"`) ; chaque toast porte `role="status"` (ou `alert` pour une erreur ; `warning` = avertissement non bloquant).
 */
export default function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const compteur = useRef(0);

  const retirer = useCallback((id) => setToasts((prev) => prev.filter((t) => t.id !== id)), []);

  const afficher = useCallback(
    (tone, message, duree) => {
      compteur.current += 1;
      const id = compteur.current;
      setToasts((prev) => [...prev, { id, tone, message }]);
      setTimeout(() => retirer(id), duree);
    },
    [retirer],
  );

  const api = useMemo(
    () => ({
      success: (message) => afficher('success', message, 8000),
      warning: (message) => afficher('warning', message, 10000),
      error: (message) => afficher('error', message, 10000),
    }),
    [afficher],
  );

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div
        className="toast-zone"
        aria-live="polite"
        style={{
          position: 'fixed',
          right: ADMIN_SPACING.lg,
          bottom: ADMIN_SPACING.lg,
          left: ADMIN_SPACING.lg,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'flex-end',
          gap: ADMIN_SPACING.sm,
          zIndex: 2000,
          pointerEvents: 'none',
        }}
      >
        {toasts.map((t) => {
          const couleurs = ADMIN_TONES[t.tone];
          return (
            <div
              key={t.id}
              role={t.tone === 'error' ? 'alert' : 'status'}
              style={{
                pointerEvents: 'auto',
                maxWidth: '520px',
                display: 'flex',
                gap: ADMIN_SPACING.md,
                alignItems: 'flex-start',
                padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
                background: couleurs.bg,
                color: couleurs.fg,
                border: `1px solid ${couleurs.border}`,
                borderRadius: ADMIN_RADIUS.md,
                boxShadow: ADMIN_SHADOWS.lg,
                fontSize: '14px',
                fontWeight: 600,
              }}
            >
              <span aria-hidden="true">{t.tone === 'success' ? '✔' : '⚠'}</span>
              <span style={{ flex: 1 }}>{t.message}</span>
              <button
                type="button"
                onClick={() => retirer(t.id)}
                aria-label="Fermer la notification"
                style={{ background: 'transparent', border: 'none', cursor: 'pointer', color: 'inherit', fontSize: '16px' }}
              >
                ✕
              </button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}
