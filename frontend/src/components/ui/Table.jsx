import { ADMIN_COLORS, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';

/**
 * Tableau du design system (UI-01 T4). La largeur minimale (`minWidth`) ne s'applique que sous 1280 px (défilement
 * horizontal sur tablette et mobile) ; au-delà, le tableau occupe la largeur disponible, sans défilement inutile.
 * Cellules compactes dès 1280 px. Avec `cards`, chaque ligne devient une carte empilée sous 640 px : les `Td` portent
 * alors un `label` (repris de l'en-tête de colonne) et les colonnes secondaires restent masquées. Les colonnes secondaires (`xl` sur Th et Td) ne s'affichent qu'à partir de 1440 px.
 */
export function Table({ children, caption, minWidth = '720px', maxHeight, cards = false }) {
  return (
    <div
      className={cards ? 'ui-table-wrap ui-table-wrap--cards' : 'ui-table-wrap'}
      style={{
        overflow: 'auto',
        maxHeight,
        background: ADMIN_COLORS.cardBg,
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.lg,
      }}
    >
      <table className="ui-table" style={{ '--table-min': minWidth }}>
        {caption && <caption className="sr-only">{caption}</caption>}
        {children}
      </table>
    </div>
  );
}

const classes = (...c) => c.filter(Boolean).join(' ');

export function Th({ children, srOnly, xl }) {
  return (
    <th
      scope="col"
      className={classes('ui-th', xl && 'col-xl')}
      style={{
        background: ADMIN_COLORS.background,
        borderBottom: `1px solid ${ADMIN_COLORS.border}`,
        color: ADMIN_COLORS.textSecondary,
        position: 'sticky',
        top: 0,
      }}
    >
      {srOnly ? <span className="sr-only">{children}</span> : children}
    </th>
  );
}

export function Td({ children, style, xl, className, label, ...props }) {
  return (
    <td
      className={classes('ui-td', xl && 'col-xl', className)}
      data-label={label}
      style={{
        borderBottom: `1px solid ${ADMIN_COLORS.borderLight}`,
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
