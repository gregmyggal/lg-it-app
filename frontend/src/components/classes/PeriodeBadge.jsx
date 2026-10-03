import { ADMIN_TONES, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';

/**
 * Pastille de période « P1 » / « P2 » : identifiée par le texte, la couleur (bleu / violet) n'est qu'un renfort.
 *
 * @param {object} props
 * @param {number} props.numero numéro de période (1 ou 2)
 * @param {string} [props.children] texte (défaut « P{numero} »)
 */
export default function PeriodeBadge({ numero, children }) {
  const c = numero === 2 ? ADMIN_TONES.school : ADMIN_TONES.primary;
  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        padding: '2px 8px',
        borderRadius: ADMIN_RADIUS.full,
        fontSize: '12px',
        fontWeight: 700,
        background: c.bg,
        color: c.fg,
        border: `1px solid ${c.border}`,
        whiteSpace: 'nowrap',
      }}
    >
      {children || `P${numero}`}
    </span>
  );
}
