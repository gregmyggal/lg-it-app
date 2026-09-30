import { ADMIN_TONES, ADMIN_SPACING, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';
import { getStatut } from '../../utils/statuts';

/**
 * Badge texte + couleur piloté par `utils/statuts.js`.
 * Valeur inconnue : badge neutre avec la valeur brute.
 *
 * @param {object} props
 * @param {Record<string, {label: string, tone: string}>} [props.table] table de statuts (ex. STATUTS_SESSION)
 * @param {string} [props.valeur] valeur brute de l'API
 * @param {string} [props.label] libellé imposé (sans table)
 * @param {string} [props.tone] ton imposé (sans table)
 */
export default function StatutBadge({ table, valeur, label, tone }) {
  const statut = table ? getStatut(table, valeur) : { label, tone: tone || 'neutral' };
  const couleurs = ADMIN_TONES[statut.tone] || ADMIN_TONES.neutral;
  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: ADMIN_SPACING.xs,
        padding: `2px ${ADMIN_SPACING.md}`,
        borderRadius: ADMIN_RADIUS.full,
        fontSize: '12px',
        fontWeight: 700,
        background: couleurs.bg,
        color: couleurs.fg,
        whiteSpace: 'nowrap',
      }}
    >
      {statut.label}
    </span>
  );
}
