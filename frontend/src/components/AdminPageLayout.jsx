/**
 * Admin Page Layout Component
 * Standardized layout for all admin pages with header, content, and messaging
 */

export function AdminPageHeader({ icon, title, description, badge, action, breadcrumb }) {
  return (
    <div
      className="adm-page-head"
      style={{
        background: 'var(--c-card)',
        borderBottom: '1px solid var(--c-border)',
        padding: '24px 24px',
        position: 'relative',
      }}
    >
      <div
        style={{
          maxWidth: 'var(--page-max, 1760px)',
          margin: '0 auto',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          flexWrap: 'wrap',
          gap: '16px',
        }}
      >
        <div style={{ flex: '1 1 420px', minWidth: 0 }}>
          {breadcrumb && (
            <nav aria-label="Fil d'Ariane" style={{ fontSize: '13px', color: 'var(--c-text-muted)', marginBottom: '8px' }}>
              {breadcrumb}
            </nav>
          )}
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '8px' }}>
            {icon && <span aria-hidden="true" style={{ fontSize: '28px', flex: '0 0 auto' }}>{icon}</span>}
            <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '4px 12px', flex: 1, minWidth: 0 }}>
              <h1 style={{ margin: 0, fontSize: 'clamp(22px, 5vw, 32px)', fontWeight: 700, color: 'var(--c-text)' }}>
                {title}
              </h1>
              {badge && (
                <div
                  style={{
                    background: 'var(--tone-primary-bg)',
                    color: 'var(--tone-primary-fg)',
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
          </div>
          {description && (
            <p style={{ margin: icon ? '0 0 0 40px' : 0, fontSize: '14px', color: 'var(--c-text-muted)' }}>
              {description}
            </p>
          )}
        </div>
        {action && <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>{action}</div>}
      </div>
    </div>
  );
}

export function AdminPageContent({ children }) {
  return (
    <div
      className="adm-page-content"
      style={{
        maxWidth: 'var(--page-max, 1760px)',
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
          color: 'var(--c-text-3)',
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
        background: 'var(--c-card)',
        border: isActive ? '2px solid var(--c-primary)' : '1px solid var(--c-border)',
        borderRadius: '12px',
        padding: '20px',
        boxShadow: '0 1px 3px var(--c-shadow)',
        cursor: onClick ? 'pointer' : 'default',
        transition: 'all 0.2s ease',
      }}
      onMouseEnter={(e) => {
        if (onClick) {
          e.currentTarget.style.boxShadow = '0 10px 15px var(--c-shadow)';
          e.currentTarget.style.transform = 'translateY(-2px)';
        }
      }}
      onMouseLeave={(e) => {
        if (onClick) {
          e.currentTarget.style.boxShadow = '0 1px 3px var(--c-shadow)';
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
          <h3 style={{ margin: '0 0 4px 0', fontSize: '16px', fontWeight: 600, color: 'var(--c-text)' }}>
            {title}
          </h3>
          {subtitle && (
            <p style={{ margin: 0, fontSize: '13px', color: 'var(--c-text-2)' }}>{subtitle}</p>
          )}
        </div>
        {actions && <div style={{ display: 'flex', gap: '8px' }}>{actions}</div>}
      </div>
    </div>
  );
}

export function AdminCardBody({ children }) {
  return <div style={{ fontSize: '14px', color: 'var(--c-text)', lineHeight: '1.6' }}>{children}</div>;
}

export function AdminCardFooter({ children }) {
  return (
    <div
      style={{
        marginTop: '16px',
        paddingTop: '16px',
        borderTop: '1px solid var(--c-border)',
        display: 'flex',
        gap: '8px',
        justifyContent: 'flex-end',
      }}
    >
      {children}
    </div>
  );
}

export function AdminBadge({ label, color = 'blue', icon, style }) {
  const colorMap = {
    blue: { bg: 'var(--b-blue-bg)', text: 'var(--b-blue-fg)' },
    green: { bg: 'var(--b-green-bg)', text: 'var(--b-green-fg)' },
    red: { bg: 'var(--b-red-bg)', text: 'var(--b-red-fg)' },
    amber: { bg: 'var(--b-amber-bg)', text: 'var(--b-amber-fg)' },
    purple: { bg: 'var(--b-purple-bg)', text: 'var(--b-purple-fg)' },
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
        ...style,
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
      <div style={{ fontSize: '12px', color: 'var(--c-text-2)', fontWeight: 600 }}>
        {label}
      </div>
    </div>
  );
}
