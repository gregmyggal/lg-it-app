import { Link } from 'react-router-dom';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';

/**
 * Lien de navigation stylé comme un AdminButton (primary | secondary).
 * Un vrai lien reste ouvrable dans un nouvel onglet, contrairement à un bouton qui navigue.
 */
export default function LinkButton({ to, children, variant = 'secondary', size = 'md' }) {
  const primaire = variant === 'primary';
  return (
    <Link
      to={to}
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: ADMIN_SPACING.sm,
        padding: size === 'sm' ? `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}` : `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
        fontSize: size === 'sm' ? '13px' : '14px',
        fontWeight: 600,
        borderRadius: ADMIN_RADIUS.md,
        textDecoration: 'none',
        background: primaire ? ADMIN_COLORS.primary : ADMIN_COLORS.background,
        color: primaire ? ADMIN_COLORS.cardBg : ADMIN_COLORS.textPrimary,
        border: primaire ? 'none' : `1px solid ${ADMIN_COLORS.border}`,
        whiteSpace: 'nowrap',
      }}
    >
      {children}
    </Link>
  );
}
