/**
 * Admin Page Layout Component
 * Standardized layout for all admin pages with header, content, and messaging
 */

export function AdminPageHeader({ icon, title, description, badge, action }) {
  return (
    <div
      style={{
        background: 'white',
        borderBottom: '1px solid #e5e7eb',
        padding: '32px 24px',
        position: 'sticky',
        top: 0,
        zIndex: 100,
      }}
    >
      <div
        style={{
          maxWidth: '1400px',
          margin: '0 auto',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
        }}
      >
        <div style={{ flex: 1 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '8px' }}>
            {icon && <span style={{ fontSize: '28px' }}>{icon}</span>}
            <h1 style={{ margin: 0, fontSize: '32px', fontWeight: 700, color: '#111827' }}>
              {title}
            </h1>
            {badge && (
              <div
                style={{
                  background: '#dbeafe',
                  color: '#2563eb',
                  padding: '4px 12px',
                  borderRadius: '9999px',
                  fontSize: '12px',
                  fontWeight: 600,
                }}
              >
                {badge}
              </div>
            )}
          </div>
          {description && (
            <p style={{ margin: '0 0 0 40px', fontSize: '14px', color: '#6b7280' }}>
              {description}
            </p>
          )}
        </div>
        {action && <div>{action}</div>}
      </div>
    </div>
  );
}

export function AdminPageContent({ children }) {
  return (
    <div
      style={{
        maxWidth: '1400px',
        margin: '0 auto',
        padding: '32px 24px',
        minHeight: 'calc(100vh - 200px)',
      }}
    >
      {children}
    </div>
  );
}

export function AdminCardGrid({ children, emptyMessage }) {
  const items = Array.isArray(children) ? children : [children];
  const nonEmptyItems = items.filter(Boolean);

  if (nonEmptyItems.length === 0) {
    return (
      <div
        style={{
          textAlign: 'center',
          padding: '60px 24px',
          color: '#9ca3af',
        }}
      >
        <div style={{ fontSize: '48px', marginBottom: '12px' }}>📭</div>
        <p style={{ fontSize: '14px' }}>{emptyMessage || 'Aucun élément trouvé'}</p>
      </div>
    );
  }

  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
        gap: '24px',
      }}
    >
      {nonEmptyItems}
    </div>
  );
}

export function AdminCard({ children, onClick, isActive }) {
  return (
    <div
      onClick={onClick}
      style={{
        background: 'white',
        border: isActive ? '2px solid #2563eb' : '1px solid #e5e7eb',
        borderRadius: '12px',
        padding: '20px',
        boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
        cursor: onClick ? 'pointer' : 'default',
        transition: 'all 0.2s ease',
      }}
      onMouseEnter={(e) => {
        if (onClick) {
          e.currentTarget.style.boxShadow = '0 10px 15px rgba(0,0,0,0.1)';
          e.currentTarget.style.transform = 'translateY(-2px)';
        }
      }}
      onMouseLeave={(e) => {
        if (onClick) {
          e.currentTarget.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
          e.currentTarget.style.transform = 'translateY(0)';
        }
      }}
    >
      {children}
    </div>
  );
}

export function AdminCardHeader({ title, subtitle, actions }) {
  return (
    <div style={{ marginBottom: '16px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '12px' }}>
        <div>
          <h3 style={{ margin: '0 0 4px 0', fontSize: '16px', fontWeight: 600, color: '#111827' }}>
            {title}
          </h3>
          {subtitle && (
            <p style={{ margin: 0, fontSize: '13px', color: '#6b7280' }}>{subtitle}</p>
          )}
        </div>
        {actions && <div style={{ display: 'flex', gap: '8px' }}>{actions}</div>}
      </div>
    </div>
  );
}

export function AdminCardBody({ children }) {
  return <div style={{ fontSize: '14px', color: '#111827', lineHeight: '1.6' }}>{children}</div>;
}

export function AdminCardFooter({ children }) {
  return (
    <div
      style={{
        marginTop: '16px',
        paddingTop: '16px',
        borderTop: '1px solid #e5e7eb',
        display: 'flex',
        gap: '8px',
        justifyContent: 'flex-end',
      }}
    >
      {children}
    </div>
  );
}

export function AdminBadge({ label, color = 'blue', icon }) {
  const colorMap = {
    blue: { bg: '#dbeafe', text: '#2563eb' },
    green: { bg: '#d1fae5', text: '#10b981' },
    red: { bg: '#fee2e2', text: '#ef4444' },
    amber: { bg: '#fef3c7', text: '#f59e0b' },
    purple: { bg: '#e9d5ff', text: '#7c3aed' },
  };

  const { bg, text } = colorMap[color] || colorMap.blue;

  return (
    <div
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: '6px',
        background: bg,
        color: text,
        padding: '4px 12px',
        borderRadius: '6px',
        fontSize: '12px',
        fontWeight: 600,
      }}
    >
      {icon && <span>{icon}</span>}
      {label}
    </div>
  );
}

export function AdminStat({ label, value, color = 'blue' }) {
  const colorMap = {
    blue: '#2563eb',
    green: '#10b981',
    purple: '#7c3aed',
    orange: '#f97316',
  };

  return (
    <div style={{ textAlign: 'center', padding: '12px' }}>
      <div
        style={{
          fontSize: '24px',
          fontWeight: 700,
          color: colorMap[color],
          marginBottom: '4px',
        }}
      >
        {value}
      </div>
      <div style={{ fontSize: '12px', color: '#6b7280', fontWeight: 600 }}>
        {label}
      </div>
    </div>
  );
}
