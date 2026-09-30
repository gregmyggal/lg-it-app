import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';
import AdminButton from '../AdminButton';
import Banner from './Banner';

/** Squelette de chargement (texte pour lecteur d'écran). Animation désactivée si prefers-reduced-motion. */
export function LoadingBlock({ message, lignes = 5 }) {
  return (
    <div
      role="status"
      aria-busy="true"
      style={{
        background: ADMIN_COLORS.cardBg,
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.lg,
        padding: ADMIN_SPACING.lg,
      }}
    >
      <span className="sr-only">{message}</span>
      {Array.from({ length: lignes }, (_, i) => (
        <span
          key={i}
          aria-hidden="true"
          className="skeleton"
          style={{ display: 'block', height: '18px', margin: '14px 0', width: `${100 - i * 7}%` }}
        />
      ))}
    </div>
  );
}

/** Erreur de chargement : message métier en français + bouton « Réessayer ». */
export function ErrorBlock({ message, onRetry }) {
  return (
    <Banner
      tone="error"
      actions={
        onRetry && (
          <AdminButton variant="secondary" size="sm" onClick={onRetry}>
            Réessayer
          </AdminButton>
        )
      }
    >
      <strong>{message}</strong>
    </Banner>
  );
}

/** État vide : explique pourquoi c'est vide et propose l'action pour y remédier. */
export function EmptyBlock({ icon, title, children, actions }) {
  return (
    <div
      style={{
        background: ADMIN_COLORS.cardBg,
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.lg,
        padding: `${ADMIN_SPACING['3xl']} ${ADMIN_SPACING.xl}`,
        textAlign: 'center',
      }}
    >
      {icon && (
        <div aria-hidden="true" style={{ fontSize: '40px', marginBottom: ADMIN_SPACING.md }}>
          {icon}
        </div>
      )}
      <h2 style={{ margin: `0 0 ${ADMIN_SPACING.sm}`, fontSize: '18px', color: ADMIN_COLORS.textPrimary }}>{title}</h2>
      <p
        style={{
          margin: '0 auto',
          maxWidth: '560px',
          color: ADMIN_COLORS.textSecondary,
          fontSize: '14px',
        }}
      >
        {children}
      </p>
      {actions && (
        <div
          style={{
            display: 'flex',
            gap: ADMIN_SPACING.md,
            justifyContent: 'center',
            flexWrap: 'wrap',
            marginTop: ADMIN_SPACING.lg,
          }}
        >
          {actions}
        </div>
      )}
    </div>
  );
}
