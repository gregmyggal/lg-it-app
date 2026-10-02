export function FormField({ label, error, required, children, helperText }) {
  return (
    <div className="form-field" style={{ marginBottom: '16px' }}>
      {label && (
        <label style={{ display: 'block', marginBottom: '8px', fontWeight: '500' }}>
          {label}
          {required && <span style={{ color: 'red' }}> *</span>}
        </label>
      )}
      {children}
      {error && (
        <div style={{ color: 'var(--tone-error-fg)', fontSize: '12px', marginTop: '4px' }}>
          {Array.isArray(error) ? error.join(', ') : error}
        </div>
      )}
      {helperText && (
        <div style={{ color: 'var(--c-text-2)', fontSize: '12px', marginTop: '4px' }}>
          {helperText}
        </div>
      )}
    </div>
  );
}
