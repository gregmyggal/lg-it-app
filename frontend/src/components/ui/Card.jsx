import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_RADIUS, ADMIN_SHADOWS } from '../../styles/AdminDesignSystem';

/** Section de page (titre, sous-titre, actions, contenu) — pattern « fiche » de l'admin. */
export function Section({ title, subtitle, actions, children, headingLevel = 2, bodyPadding = true, style }) {
  const Titre = `h${headingLevel}`;
  return (
    <section
      style={{
        background: ADMIN_COLORS.cardBg,
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.lg,
        boxShadow: ADMIN_SHADOWS.sm,
        marginBottom: ADMIN_SPACING.xl,
        overflow: 'hidden',
        ...style,
      }}
    >
      {(title || actions) && (
        <header
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'flex-start',
            gap: ADMIN_SPACING.lg,
            flexWrap: 'wrap',
            padding: `${ADMIN_SPACING.lg} ${ADMIN_SPACING.xl}`,
            borderBottom: `1px solid ${ADMIN_COLORS.border}`,
          }}
        >
          <div>
            <Titre style={{ margin: 0, fontSize: '18px', color: ADMIN_COLORS.textPrimary }}>{title}</Titre>
            {subtitle && (
              <p style={{ margin: `${ADMIN_SPACING.xs} 0 0`, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
                {subtitle}
              </p>
            )}
          </div>
          {actions && <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap' }}>{actions}</div>}
        </header>
      )}
      <div style={bodyPadding ? { padding: ADMIN_SPACING.xl } : undefined}>{children}</div>
    </section>
  );
}
