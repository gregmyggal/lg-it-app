export function DetailSection({ title, children, icon = null }) {
  return (
    <div style={{ marginBottom: '60px' }}>
      {icon && <div style={{ color: 'var(--teal)', marginBottom: '16px' }}>{icon}</div>}
      <h2 style={{ marginBottom: '24px' }}>{title}</h2>
      {children}
    </div>
  );
}

export function DetailCheckList({ items, color = 'var(--teal)' }) {
  return (
    <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '16px' }}>
      {items.map((item, idx) => (
        <li key={idx} style={{ display: 'flex', gap: '12px' }}>
          <span style={{ color, fontWeight: '700', flex: '0 0 auto' }}>✓</span>
          <span>{typeof item === 'string' ? item : item}</span>
        </li>
      ))}
    </ul>
  );
}

export function DetailCards({ items }) {
  return (
    <div style={{ display: 'grid', gap: '16px' }}>
      {items.map((item, idx) => (
        <div key={idx} style={{ padding: '20px', background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: '12px' }}>
          <h3 style={{ margin: '0 0 8px 0', fontSize: '16px' }}>{item.titre || item.title}</h3>
          <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px', lineHeight: '1.6' }}>
            {item.contenu || item.desc || item.description || item.content}
          </p>
        </div>
      ))}
    </div>
  );
}

export function SidebarBox({ title, children }) {
  return (
    <div style={{ padding: '32px', background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: '12px', marginBottom: '24px' }}>
      <h3 style={{ margin: '0 0 24px 0', fontSize: '18px' }}>{title}</h3>
      {children}
    </div>
  );
}
