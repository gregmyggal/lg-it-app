import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';

/** Barre de filtres (région de recherche, retour à la ligne automatique). */
export function FilterBar({ label, children }) {
  return (
    <div
      role="search"
      aria-label={label}
      style={{
        display: 'flex',
        flexWrap: 'wrap',
        gap: ADMIN_SPACING.lg,
        alignItems: 'flex-end',
        background: ADMIN_COLORS.cardBg,
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.lg,
        padding: ADMIN_SPACING.lg,
        marginBottom: ADMIN_SPACING.xl,
      }}
    >
      {children}
    </div>
  );
}

/** Champ de filtre : libellé visible associé à une liste déroulante. */
export function FilterField({ id, label, value, onChange, options, placeholder, disabled }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: ADMIN_SPACING.xs, minWidth: '160px', flex: '1 1 160px', maxWidth: '260px' }}>
      <label
        htmlFor={id}
        style={{
          fontSize: '12px',
          fontWeight: 600,
          textTransform: 'uppercase',
          letterSpacing: '0.5px',
          color: ADMIN_COLORS.textPrimary,
        }}
      >
        {label}
      </label>
      <select
        id={id}
        value={value}
        disabled={disabled}
        onChange={(e) => onChange(e.target.value)}
        style={{
          padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
          border: `1px solid ${ADMIN_COLORS.border}`,
          borderRadius: ADMIN_RADIUS.md,
          fontSize: '14px',
          fontFamily: 'inherit',
          background: ADMIN_COLORS.cardBg,
          color: ADMIN_COLORS.textPrimary,
          minHeight: '44px',
        }}
      >
        {placeholder !== undefined && <option value="">{placeholder}</option>}
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </div>
  );
}
