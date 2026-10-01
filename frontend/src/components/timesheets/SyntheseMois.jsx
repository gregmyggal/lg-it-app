import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';
import { formatDateCourte } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';

/**
 * Total du mois (mock-up 02, section 3) : heures, montant en euros (les siens, Q22), jours encodés et
 * dépassements du plafond journalier (le lissage se fait à la validation, pas ici).
 *
 * @param {object} props
 * @param {{heures: number, montant: number, jours: number, depassements: object[]}} props.synthese
 * @param {number} props.heuresEnAttente heures des sessions cochées pas encore enregistrées
 */
export default function SyntheseMois({ synthese, heuresEnAttente }) {
  const cases = [
    { label: 'Total heures', valeur: formatHeures(synthese.heures + heuresEnAttente) },
    { label: 'Total montant', valeur: formatEuros(synthese.montant), note: heuresEnAttente > 0 ? 'hors sessions à enregistrer' : null },
    { label: 'Jours encodés', valeur: String(synthese.jours) },
  ];

  return (
    <div>
      <dl style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: ADMIN_SPACING.lg, margin: 0 }}>
        {cases.map((c) => (
          <div key={c.label} style={{ border: `1px solid ${ADMIN_COLORS.border}`, borderRadius: ADMIN_RADIUS.md, padding: ADMIN_SPACING.lg }}>
            <dt style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, fontWeight: 600 }}>{c.label}</dt>
            <dd style={{ margin: `${ADMIN_SPACING.xs} 0 0`, fontSize: '20px', fontWeight: 700 }}>{c.valeur}</dd>
            {c.note && <dd style={{ margin: 0, fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>{c.note}</dd>}
          </div>
        ))}
      </dl>
      {synthese.depassements?.length > 0 && (
        <div
          role="status"
          style={{
            marginTop: ADMIN_SPACING.lg,
            background: ADMIN_TONES.warning.bg,
            color: ADMIN_TONES.warning.fg,
            border: `1px solid ${ADMIN_TONES.warning.border}`,
            borderRadius: ADMIN_RADIUS.md,
            padding: ADMIN_SPACING.lg,
            fontSize: '14px',
          }}
        >
          <strong>Plafond journalier dépassé</strong> ({synthese.depassements.map((d) => `${formatDateCourte(d.date)} : ${formatEuros(d.montant_total)}`).join(' · ')}). Rien à faire de
          votre côté : la direction proposera un lissage au moment de valider vos heures.
        </div>
      )}
    </div>
  );
}
