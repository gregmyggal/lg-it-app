import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';
import { formatDate, parseDate } from '../../utils/dates';

const JOUR_MS = 86400000;

/**
 * Frise d'une année : P1 (bleu), P2 (violet), trou hachuré, chevauchement rouge.
 * La période est toujours nommée par du texte (« P1 », « P2 ») ; `aria-label` résume les dates.
 *
 * @param {object} props
 * @param {{numero:number,date_debut:string,date_fin:string}[]} props.periodes les 2 périodes (dates éventuellement vides)
 * @param {string} [props.titre] libellé de la frise (ex. « Après »)
 * @param {boolean} [props.attenuee] rendu grisé (état « avant »)
 */
export default function FriseAnnee({ periodes, titre, attenuee = false }) {
  const [p1, p2] = [1, 2].map((n) => periodes.find((p) => p.numero === n));
  const valides = [p1, p2].every((p) => p?.date_debut && p?.date_fin);
  const resume = [p1, p2]
    .map((p, i) => `Période ${i + 1} : ${p?.date_debut && p?.date_fin ? `${formatDate(p.date_debut)} au ${formatDate(p.date_fin)}` : 'dates incomplètes'}`)
    .join('. ');
  if (!valides) {
    return (
      <div role="img" aria-label={`${titre ? `${titre}. ` : ''}${resume}`} style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
        {titre && <strong>{titre} · </strong>}Renseignez les quatre dates pour voir la frise.
      </div>
    );
  }
  const t = (iso) => parseDate(iso).getTime();
  const min = Math.min(t(p1.date_debut), t(p2.date_debut));
  const max = Math.max(t(p1.date_fin), t(p2.date_fin));
  const total = Math.max(JOUR_MS, max - min + JOUR_MS);
  const pct = (ms) => `${((ms / total) * 100).toFixed(3)}%`;
  const segment = (debut, fin) => ({ left: pct(t(debut) - min), width: pct(Math.max(JOUR_MS, t(fin) - t(debut) + JOUR_MS)) });

  const finP1 = t(p1.date_fin);
  const debutP2 = t(p2.date_debut);
  const chevauche = debutP2 <= finP1;
  const trou = !chevauche && debutP2 - finP1 > JOUR_MS;

  const barre = (tone, debut, fin, texte) => (
    <div
      style={{
        position: 'absolute',
        top: 0,
        height: '100%',
        background: tone.bg,
        color: tone.fg,
        border: `1px solid ${tone.border}`,
        borderRadius: ADMIN_RADIUS.sm,
        boxSizing: 'border-box',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '12px',
        fontWeight: 700,
        overflow: 'hidden',
        ...segment(debut, fin),
      }}
    >
      {texte}
    </div>
  );

  return (
    <div style={{ opacity: attenuee ? 0.7 : 1 }}>
      {titre && <div style={{ fontSize: '12px', fontWeight: 600, marginBottom: ADMIN_SPACING.xs }}>{titre}</div>}
      <div role="img" aria-label={`${titre ? `${titre}. ` : ''}${resume}${chevauche ? '. Les deux périodes se chevauchent.' : ''}`}>
        <div style={{ position: 'relative', height: '28px', background: ADMIN_COLORS.background, borderRadius: ADMIN_RADIUS.sm }}>
          {trou && (
            <div
              aria-hidden="true"
              style={{
                position: 'absolute',
                top: '4px',
                height: 'calc(100% - 8px)',
                left: pct(finP1 - min + JOUR_MS),
                width: pct(debutP2 - finP1 - JOUR_MS),
                background: `repeating-linear-gradient(45deg, transparent, transparent 4px, ${ADMIN_COLORS.border} 4px, ${ADMIN_COLORS.border} 8px)`,
              }}
            />
          )}
          {barre(ADMIN_TONES.primary, p1.date_debut, p1.date_fin, 'P1')}
          {barre(ADMIN_TONES.school, p2.date_debut, p2.date_fin, 'P2')}
          {chevauche && (
            <div
              aria-hidden="true"
              style={{
                position: 'absolute',
                top: 0,
                height: '100%',
                background: ADMIN_TONES.error.bg,
                border: `2px solid ${ADMIN_TONES.error.fg}`,
                boxSizing: 'border-box',
                ...segment(p2.date_debut, p1.date_fin),
              }}
            />
          )}
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: ADMIN_SPACING.xs }}>
          <span>{formatDate(p1.date_debut)}</span>
          <span>{formatDate(p2.date_fin)}</span>
        </div>
      </div>
    </div>
  );
}
