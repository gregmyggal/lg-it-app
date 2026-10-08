import { ADMIN_RADIUS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';
import { SOURCES_EMPLOYEUR, TON_EMPLOYEUR } from '../../utils/employeurs';

/**
 * Badge « employeur du mois » (EMP-01) : toujours du texte (la couleur ne porte jamais seule l'information),
 * « hérité » en pointillés, cadenas avec libellé accessible quand le mois est verrouillé.
 *
 * @param {object} props
 * @param {{nom: string, couleur_badge?: string}} props.employeur
 * @param {string} [props.source] `defaut` | `herite` | `explicite` | `migration` | `fige`
 * @param {boolean} [props.verrouille]
 * @param {string|null} [props.raisonVerrou]
 */
export default function EmployeurBadge({ employeur, source, verrouille = false, raisonVerrou = null }) {
  const couleurs = ADMIN_TONES[TON_EMPLOYEUR[employeur?.couleur_badge]] || ADMIN_TONES.neutral;
  const estHerite = source === 'herite' || source === 'defaut';
  return (
    <span
      title={raisonVerrou || undefined}
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
        border: `1px ${estHerite ? 'dashed' : 'solid'} ${couleurs.border}`,
        whiteSpace: 'nowrap',
      }}
    >
      {verrouille && <span role="img" aria-label="Mois verrouillé">🔒</span>}
      {employeur?.nom || '—'}
      {estHerite && <span style={{ fontWeight: 400 }}>· {SOURCES_EMPLOYEUR[source]}</span>}
    </span>
  );
}
