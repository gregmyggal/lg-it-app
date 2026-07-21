import { useState, useEffect } from 'react';

export default function JSONEditor({ value, onChange, label, section }) {
  const [isValid, setIsValid] = useState(true);
  const [error, setError] = useState(null);
  const [isExpanded, setIsExpanded] = useState(false);
  const [lines, setLines] = useState([]);

  useEffect(() => {
    try {
      JSON.parse(value);
      setIsValid(true);
      setError(null);
    } catch (err) {
      setIsValid(false);
      setError(err.message);
    }
    updateLines();
  }, [value]);

  function updateLines() {
    const lineCount = value.split('\n').length;
    setLines(Array.from({ length: lineCount }, (_, i) => i + 1));
  }

  function handleChange(e) {
    onChange(e.target.value);
  }

  function handleFormat() {
    try {
      const parsed = JSON.parse(value);
      const formatted = JSON.stringify(parsed, null, 2);
      onChange(formatted);
      setIsValid(true);
      setError(null);
    } catch (err) {
      setError('JSON invalide : ' + err.message);
    }
  }

  function handleMinify() {
    try {
      const parsed = JSON.parse(value);
      const minified = JSON.stringify(parsed);
      onChange(minified);
      setIsValid(true);
      setError(null);
    } catch (err) {
      setError('JSON invalide : ' + err.message);
    }
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
        <label style={{ fontSize: '14px', fontWeight: 600, color: '#111827' }}>
          {label}
          <span style={{ fontSize: '12px', color: '#6b7280', fontWeight: 400, marginLeft: '8px' }}>
            (JSON)
          </span>
        </label>
        <div style={{ display: 'flex', gap: '8px' }}>
          <button
            type="button"
            onClick={handleFormat}
            disabled={!isValid}
            style={{
              padding: '6px 12px',
              background: '#f3f4f6',
              color: '#374151',
              border: '1px solid #e5e7eb',
              borderRadius: '6px',
              cursor: !isValid ? 'not-allowed' : 'pointer',
              fontSize: '12px',
              fontWeight: 500,
              opacity: !isValid ? 0.5 : 1,
              transition: 'all 0.2s ease',
            }}
            onMouseEnter={(e) => {
              if (isValid) {
                e.target.style.background = '#e5e7eb';
                e.target.style.borderColor = '#d1d5db';
              }
            }}
            onMouseLeave={(e) => {
              if (isValid) {
                e.target.style.background = '#f3f4f6';
                e.target.style.borderColor = '#e5e7eb';
              }
            }}
          >
            🔄 Formater
          </button>
          <button
            type="button"
            onClick={handleMinify}
            disabled={!isValid}
            style={{
              padding: '6px 12px',
              background: '#f3f4f6',
              color: '#374151',
              border: '1px solid #e5e7eb',
              borderRadius: '6px',
              cursor: !isValid ? 'not-allowed' : 'pointer',
              fontSize: '12px',
              fontWeight: 500,
              opacity: !isValid ? 0.5 : 1,
              transition: 'all 0.2s ease',
            }}
            onMouseEnter={(e) => {
              if (isValid) {
                e.target.style.background = '#e5e7eb';
                e.target.style.borderColor = '#d1d5db';
              }
            }}
            onMouseLeave={(e) => {
              if (isValid) {
                e.target.style.background = '#f3f4f6';
                e.target.style.borderColor = '#e5e7eb';
              }
            }}
          >
            ⚡ Minifier
          </button>
          <button
            type="button"
            onClick={() => setIsExpanded(!isExpanded)}
            style={{
              padding: '6px 12px',
              background: isExpanded ? '#dbeafe' : '#f3f4f6',
              color: isExpanded ? '#1e40af' : '#374151',
              border: `1px solid ${isExpanded ? '#bfdbfe' : '#e5e7eb'}`,
              borderRadius: '6px',
              cursor: 'pointer',
              fontSize: '12px',
              fontWeight: 500,
              transition: 'all 0.2s ease',
            }}
            onMouseEnter={(e) => {
              e.target.style.background = isExpanded ? '#bfdbfe' : '#e5e7eb';
            }}
            onMouseLeave={(e) => {
              e.target.style.background = isExpanded ? '#dbeafe' : '#f3f4f6';
            }}
          >
            {isExpanded ? '⬜ Réduire' : '⬛ Agrandir'}
          </button>
        </div>
      </div>

      {error && (
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '6px', padding: '12px', marginBottom: '12px', display: 'flex', gap: '8px', alignItems: 'flex-start' }}>
          <span style={{ fontSize: '16px', flex: '0 0 auto' }}>⚠️</span>
          <div style={{ flex: 1 }}>
            <p style={{ margin: '0 0 4px 0', fontSize: '13px', fontWeight: 500, color: '#991b1b' }}>JSON invalide</p>
            <p style={{ margin: 0, fontSize: '12px', color: '#7f1d1d', fontFamily: 'monospace' }}>{error}</p>
          </div>
        </div>
      )}

      {isValid && !error && (
        <div style={{ background: '#ecfdf5', border: '1px solid #d1fae5', borderRadius: '6px', padding: '12px', marginBottom: '12px', display: 'flex', gap: '8px', alignItems: 'center' }}>
          <span style={{ fontSize: '16px' }}>✓</span>
          <p style={{ margin: 0, fontSize: '13px', fontWeight: 500, color: '#047857' }}>JSON valide</p>
        </div>
      )}

      <div
        style={{
          background: '#1e1e2e',
          borderRadius: '8px',
          overflow: 'hidden',
          border: `2px solid ${isValid ? '#10b981' : '#ef4444'}`,
          transition: 'border-color 0.2s ease',
          height: isExpanded ? '600px' : '300px',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        <div style={{ display: 'flex', height: '100%', overflow: 'hidden' }}>
          {/* Line numbers */}
          <div
            style={{
              background: '#2a2a3e',
              color: '#7c8696',
              padding: '12px 8px',
              fontSize: '12px',
              lineHeight: '1.5',
              textAlign: 'right',
              minWidth: '40px',
              borderRight: '1px solid #3c3c52',
              userSelect: 'none',
              fontFamily: 'monospace',
              overflowY: 'auto',
            }}
          >
            {lines.map((num) => (
              <div key={num}>{num}</div>
            ))}
          </div>

          {/* Editor */}
          <textarea
            value={value}
            onChange={handleChange}
            style={{
              flex: 1,
              padding: '12px 16px',
              background: '#1e1e2e',
              color: '#e0e0e0',
              border: 'none',
              fontFamily: 'Fira Code, Monaco, monospace',
              fontSize: '13px',
              lineHeight: '1.5',
              resize: 'none',
              outline: 'none',
              overflowY: 'auto',
            }}
            spellCheck="false"
          />
        </div>
      </div>

      {/* Stats */}
      <div style={{ display: 'flex', gap: '16px', marginTop: '12px', fontSize: '12px', color: '#6b7280' }}>
        <span>📏 {value.length} caractères</span>
        <span>📋 {lines.length} lignes</span>
      </div>
    </div>
  );
}
