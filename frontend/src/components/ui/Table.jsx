import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';

/** Tableau du design system : conteneur défilant horizontalement (lisible sur tablette et mobile). */
export function Table({ children, caption, minWidth = '720px', maxHeight }) {
  return (
    <div
      style={{
        overflow: 'auto',
        maxHeight,
        background: ADMIN_COLORS.cardBg,
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.lg,
      }}
    >
      <table style={{ width: '100%', borderCollapse: 'collapse', minWidth, fontSize: '14px' }}>
        {caption && <caption className="sr-only">{caption}</caption>}
        {children}
      </table>
    </div>
  );
}

export function Th({ children, srOnly }) {
  return (
    <th
      scope="col"
      style={{
        textAlign: 'left',
        padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
        background: ADMIN_COLORS.background,
        borderBottom: `1px solid ${ADMIN_COLORS.border}`,
        fontSize: '12px',
        textTransform: 'uppercase',
        letterSpacing: '0.5px',
        color: ADMIN_COLORS.textSecondary,
        position: 'sticky',
        top: 0,
        whiteSpace: 'nowrap',
      }}
    >
      {srOnly ? <span className="sr-only">{children}</span> : children}
    </th>
  );
}

export function Td({ children, style, ...props }) {
  return (
    <td
      style={{
        padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
        borderBottom: `1px solid ${ADMIN_COLORS.borderLight}`,
        verticalAlign: 'top',
        color: ADMIN_COLORS.textPrimary,
        ...style,
      }}
      {...props}
    >
      {children}
    </td>
  );
}

/** Ligne de tableau ; `fond` colore la ligne à signaler (le texte porte toujours l'information). */
export function Tr({ children, fond }) {
  return <tr style={{ background: fond }}>{children}</tr>;
}
