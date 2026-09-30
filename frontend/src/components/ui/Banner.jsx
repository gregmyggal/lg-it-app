import { ADMIN_TONES, ADMIN_SPACING, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';

/**
 * Bandeau d'information / d'alerte.
 * Ton `error` : `role="alert"` ; sinon `role="status"` (surchargeable).
 *
 * @param {object} props
 * @param {'info'|'success'|'warning'|'error'|'school'} [props.tone]
 * @param {React.ReactNode} props.children
 * @param {React.ReactNode} [props.actions]
 * @param {string} [props.role]
 */
export default function Banner({ tone = 'info', children, actions, role, style, id }) {
  const couleurs = ADMIN_TONES[tone] || ADMIN_TONES.info;
  return (
    <div
      id={id}
      role={role || (tone === 'error' ? 'alert' : 'status')}
      style={{
        display: 'flex',
        gap: ADMIN_SPACING.lg,
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
        marginBottom: ADMIN_SPACING.lg,
        borderRadius: ADMIN_RADIUS.md,
        background: couleurs.bg,
        color: couleurs.fg,
        border: `1px solid ${couleurs.border}`,
        fontSize: '14px',
        ...style,
      }}
    >
      <div style={{ flex: '1 1 260px' }}>{children}</div>
      {actions && <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap' }}>{actions}</div>}
    </div>
  );
}
