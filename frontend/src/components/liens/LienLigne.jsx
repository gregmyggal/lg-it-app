import AdminButton from '../AdminButton';
import StatutBadge from '../ui/StatutBadge';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { TYPES_LIEN, getStatut } from '../../utils/statuts';
import { formatDateCourte } from '../../utils/dates';

/**
 * Une ligne de lien du cours : titre cliquable (nouvel onglet), description, type, « Modifié par … · date » (professeurs
 * et staff seulement), et les actions autorisées par `lien.can` — modifier, archiver, monter/descendre.
 *
 * @param {object} props
 * @param {object} props.lien lien (`ClasseLienResource`)
 * @param {boolean} props.premier vrai si c'est le premier de sa portée (pas de « Monter »)
 * @param {boolean} props.dernier vrai si c'est le dernier de sa portée (pas de « Descendre »)
 * @param {boolean} props.occupe désactive les actions pendant un appel
 * @param {(lien: object) => void} props.onModifier
 * @param {(lien: object) => void} props.onArchiver
 * @param {(lien: object, delta: -1|1) => void} props.onDeplacer
 */
export default function LienLigne({ lien, premier, dernier, occupe, onModifier, onArchiver, onDeplacer }) {
  const type = lien.type ? getStatut(TYPES_LIEN, lien.type) : null;
  const peutAgir = lien.can?.update || lien.can?.delete;

  return (
    <li
      style={{
        display: 'flex',
        gap: ADMIN_SPACING.lg,
        alignItems: 'flex-start',
        flexWrap: 'wrap',
        padding: ADMIN_SPACING.lg,
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.md,
        background: ADMIN_COLORS.cardBg,
      }}
    >
      <div style={{ flex: '1 1 260px', minWidth: 0 }}>
        <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, alignItems: 'center', flexWrap: 'wrap' }}>
          <a href={lien.url} target="_blank" rel="noopener noreferrer" style={{ fontWeight: 600, color: ADMIN_COLORS.primary, wordBreak: 'break-word' }}>
            {lien.titre}
          </a>
          {type && <StatutBadge label={`${type.icone} ${type.label}`} tone={type.tone} />}
          {lien.hors_programme && <StatutBadge label="Hors programme" tone="warning" />}
        </div>
        {lien.description && <div style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary, marginTop: '2px' }}>{lien.description}</div>}
        <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: '4px', wordBreak: 'break-all' }}>{lien.url}</div>
        {lien.modifie_par && (
          <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: '2px' }}>
            Modifié par {lien.modifie_par.nom}
            {lien.modifie_le ? ` · ${formatDateCourte(lien.modifie_le.slice(0, 10))}` : ''}
          </div>
        )}
      </div>
      {peutAgir && (
        <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
          {lien.can?.update && (
            <>
              <AdminButton size="sm" variant="secondary" disabled={occupe || premier} aria-label={`Monter « ${lien.titre} »`} onClick={() => onDeplacer(lien, -1)}>
                ↑
              </AdminButton>
              <AdminButton size="sm" variant="secondary" disabled={occupe || dernier} aria-label={`Descendre « ${lien.titre} »`} onClick={() => onDeplacer(lien, 1)}>
                ↓
              </AdminButton>
              <AdminButton size="sm" variant="secondary" disabled={occupe} onClick={() => onModifier(lien)}>
                Modifier
              </AdminButton>
            </>
          )}
          {lien.can?.delete && (
            <AdminButton size="sm" variant="secondary" disabled={occupe} onClick={() => onArchiver(lien)}>
              Archiver
            </AdminButton>
          )}
        </div>
      )}
    </li>
  );
}
