import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_RADIUS, ADMIN_TRANSITIONS } from '../styles/AdminDesignSystem';

export function AdminFormField({
  label,
  description,
  error,
  required,
  children,
  htmlFor,
}) {
  return (
    <div style={{ marginBottom: ADMIN_SPACING.lg }}>
      {label && (
        <label
          htmlFor={htmlFor}
          style={{
            display: 'block',
            fontSize: '12px',
            fontWeight: 600,
            color: ADMIN_COLORS.textPrimary,
            marginBottom: ADMIN_SPACING.sm,
            textTransform: 'uppercase',
            letterSpacing: '0.5px',
          }}
        >
          {label}
          {required && <span style={{ color: ADMIN_COLORS.error, marginLeft: '4px' }}>*</span>}
        </label>
      )}

      {description && (
        <p
          style={{
            fontSize: '12px',
            color: ADMIN_COLORS.textSecondary,
            marginBottom: ADMIN_SPACING.sm,
            margin: 0,
          }}
        >
          {description}
        </p>
      )}

      <div>{children}</div>

      {error && (
        <div
          style={{
            marginTop: ADMIN_SPACING.sm,
            fontSize: '12px',
            color: ADMIN_COLORS.error,
            display: 'flex',
            alignItems: 'center',
            gap: ADMIN_SPACING.sm,
          }}
        >
          <span>⚠️</span>
          {error}
        </div>
      )}
    </div>
  );
}

/**
 * AdminInput - Text input with styling
 */
export function AdminInput({
  type = 'text',
  placeholder,
  value,
  onChange,
  disabled,
  error,
  required,
  ...props
}) {
  return (
    <input
      type={type}
      placeholder={placeholder}
      value={value}
      onChange={onChange}
      disabled={disabled}
      required={required}
      style={{
        width: '100%',
        padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
        border: `1px solid ${error ? ADMIN_COLORS.error : ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.md,
        fontSize: '14px',
        transition: ADMIN_TRANSITIONS.normal,
        fontFamily: 'inherit',
        backgroundColor: disabled ? ADMIN_COLORS.background : 'white',
        color: ADMIN_COLORS.textPrimary,
        boxSizing: 'border-box',
      }}
      onFocus={(e) => {
        if (!error) {
          e.target.style.borderColor = ADMIN_COLORS.primary;
          e.target.style.boxShadow = `0 0 0 3px ${ADMIN_COLORS.primaryLight}`;
        }
      }}
      onBlur={(e) => {
        e.target.style.borderColor = ADMIN_COLORS.border;
        e.target.style.boxShadow = 'none';
      }}
      {...props}
    />
  );
}

/**
 * AdminTextarea - Multi-line input
 */
export function AdminTextarea({
  placeholder,
  value,
  onChange,
  disabled,
  error,
  required,
  rows = 4,
  ...props
}) {
  return (
    <textarea
      placeholder={placeholder}
      value={value}
      onChange={onChange}
      disabled={disabled}
      required={required}
      rows={rows}
      style={{
        width: '100%',
        padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
        border: `1px solid ${error ? ADMIN_COLORS.error : ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.md,
        fontSize: '14px',
        fontFamily: 'inherit',
        transition: ADMIN_TRANSITIONS.normal,
        backgroundColor: disabled ? ADMIN_COLORS.background : 'white',
        color: ADMIN_COLORS.textPrimary,
        boxSizing: 'border-box',
        resize: 'vertical',
      }}
      onFocus={(e) => {
        if (!error) {
          e.target.style.borderColor = ADMIN_COLORS.primary;
          e.target.style.boxShadow = `0 0 0 3px ${ADMIN_COLORS.primaryLight}`;
        }
      }}
      onBlur={(e) => {
        e.target.style.borderColor = ADMIN_COLORS.border;
        e.target.style.boxShadow = 'none';
      }}
      {...props}
    />
  );
}

/**
 * AdminSelect - Dropdown input
 */
export function AdminSelect({
  placeholder,
  value,
  onChange,
  options,
  disabled,
  error,
  required,
  ...props
}) {
  return (
    <select
      value={value}
      onChange={onChange}
      disabled={disabled}
      required={required}
      style={{
        width: '100%',
        padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
        border: `1px solid ${error ? ADMIN_COLORS.error : ADMIN_COLORS.border}`,
        borderRadius: ADMIN_RADIUS.md,
        fontSize: '14px',
        fontFamily: 'inherit',
        transition: ADMIN_TRANSITIONS.normal,
        backgroundColor: disabled ? ADMIN_COLORS.background : 'white',
        color: ADMIN_COLORS.textPrimary,
        cursor: disabled ? 'not-allowed' : 'pointer',
        boxSizing: 'border-box',
      }}
      {...props}
    >
      {placeholder && <option value="">{placeholder}</option>}
      {options?.map((option) => (
        <option
          key={typeof option === 'string' ? option : option.value}
          value={typeof option === 'string' ? option : option.value}
        >
          {typeof option === 'string' ? option : option.label}
        </option>
      ))}
    </select>
  );
}

/**
 * AdminCheckbox - Single checkbox
 */
export function AdminCheckbox({
  label,
  checked,
  onChange,
  disabled,
  ...props
}) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: ADMIN_SPACING.md }}>
      <input
        type="checkbox"
        checked={checked}
        onChange={onChange}
        disabled={disabled}
        style={{
          width: '18px',
          height: '18px',
          cursor: disabled ? 'not-allowed' : 'pointer',
          accentColor: ADMIN_COLORS.primary,
        }}
        {...props}
      />
      {label && (
        <label
          style={{
            fontSize: '14px',
            color: ADMIN_COLORS.textPrimary,
            cursor: disabled ? 'not-allowed' : 'pointer',
          }}
        >
          {label}
        </label>
      )}
    </div>
  );
}

/**
 * AdminCheckboxGroup - Multiple checkboxes
 */
export function AdminCheckboxGroup({
  options,
  selected = [],
  onChange,
  disabled,
}) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: ADMIN_SPACING.md }}>
      {options?.map((option) => {
        const value = typeof option === 'string' ? option : option.value;
        const label = typeof option === 'string' ? option : option.label;
        return (
          <AdminCheckbox
            key={value}
            label={label}
            checked={selected.includes(value)}
            onChange={(e) => {
              if (e.target.checked) {
                onChange([...selected, value]);
              } else {
                onChange(selected.filter((v) => v !== value));
              }
            }}
            disabled={disabled}
          />
        );
      })}
    </div>
  );
}
