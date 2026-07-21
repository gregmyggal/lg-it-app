import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_RADIUS, ADMIN_TRANSITIONS } from '../styles/AdminDesignSystem';

/**
 * AdminButton - Professional button component
 * Variants: primary, secondary, danger, ghost
 * Sizes: sm, md, lg
 */
export default function AdminButton({
  children,
  onClick,
  disabled = false,
  loading = false,
  variant = 'primary',
  size = 'md',
  icon,
  fullWidth = false,
  type = 'button',
  className,
  style,
  ...props
}) {
  const sizeMap = {
    sm: {
      padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
      fontSize: '13px',
    },
    md: {
      padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
      fontSize: '14px',
    },
    lg: {
      padding: `${ADMIN_SPACING.lg} ${ADMIN_SPACING.xl}`,
      fontSize: '15px',
    },
  };

  const variantMap = {
    primary: {
      background: ADMIN_COLORS.primary,
      color: 'white',
      border: 'none',
      hoverBg: ADMIN_COLORS.primaryHover,
      hoverShadow: '0 4px 12px rgba(37, 99, 235, 0.3)',
    },
    secondary: {
      background: ADMIN_COLORS.background,
      color: ADMIN_COLORS.textPrimary,
      border: `1px solid ${ADMIN_COLORS.border}`,
      hoverBg: ADMIN_COLORS.hoverBg,
      hoverShadow: '0 2px 8px rgba(0, 0, 0, 0.08)',
    },
    danger: {
      background: ADMIN_COLORS.error,
      color: 'white',
      border: 'none',
      hoverBg: '#dc2626',
      hoverShadow: '0 4px 12px rgba(239, 68, 68, 0.3)',
    },
    success: {
      background: ADMIN_COLORS.success,
      color: 'white',
      border: 'none',
      hoverBg: '#059669',
      hoverShadow: '0 4px 12px rgba(16, 185, 129, 0.3)',
    },
    ghost: {
      background: 'transparent',
      color: ADMIN_COLORS.textSecondary,
      border: 'none',
      hoverBg: ADMIN_COLORS.background,
      hoverShadow: 'none',
    },
  };

  const selectedVariant = variantMap[variant] || variantMap.primary;
  const selectedSize = sizeMap[size] || sizeMap.md;

  const buttonStyle = {
    ...selectedSize,
    background: disabled ? ADMIN_COLORS.background : selectedVariant.background,
    color: disabled ? ADMIN_COLORS.textTertiary : selectedVariant.color,
    border: selectedVariant.border,
    borderRadius: ADMIN_RADIUS.md,
    fontWeight: 600,
    cursor: disabled ? 'not-allowed' : 'pointer',
    transition: ADMIN_TRANSITIONS.normal,
    display: 'inline-flex',
    alignItems: 'center',
    gap: ADMIN_SPACING.md,
    width: fullWidth ? '100%' : 'auto',
    justifyContent: fullWidth ? 'center' : 'flex-start',
    opacity: disabled ? 0.6 : 1,
    boxShadow: '0 1px 2px rgba(0, 0, 0, 0.05)',
    ...style,
  };

  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled || loading}
      style={buttonStyle}
      onMouseEnter={(e) => {
        if (!disabled && !loading) {
          e.currentTarget.style.background = selectedVariant.hoverBg;
          e.currentTarget.style.transform = 'translateY(-1px)';
          if (selectedVariant.hoverShadow !== 'none') {
            e.currentTarget.style.boxShadow = selectedVariant.hoverShadow;
          }
        }
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.background = disabled ? ADMIN_COLORS.background : selectedVariant.background;
        e.currentTarget.style.transform = 'translateY(0)';
        e.currentTarget.style.boxShadow = '0 1px 2px rgba(0, 0, 0, 0.05)';
      }}
      className={className}
      {...props}
    >
      {loading && <span>⏳</span>}
      {icon && !loading && <span>{icon}</span>}
      {children}
    </button>
  );
}

/**
 * ButtonGroup - Group related buttons together
 */
export function AdminButtonGroup({ children, direction = 'row', gap = ADMIN_SPACING.md, ...props }) {
  return (
    <div
      style={{
        display: 'flex',
        flexDirection: direction === 'column' ? 'column' : 'row',
        gap,
        ...props.style,
      }}
      {...props}
    >
      {children}
    </div>
  );
}

/**
 * IconButton - Small button for actions (edit, delete, etc)
 */
export function AdminIconButton({
  icon,
  onClick,
  title,
  variant = 'secondary',
  disabled = false,
  size = 'sm',
  ...props
}) {
  const variantMap = {
    primary: { bg: ADMIN_COLORS.primaryLight, color: ADMIN_COLORS.primary },
    secondary: { bg: ADMIN_COLORS.background, color: ADMIN_COLORS.textSecondary },
    danger: { bg: '#fee2e2', color: ADMIN_COLORS.error },
    success: { bg: '#d1fae5', color: ADMIN_COLORS.success },
  };

  const selected = variantMap[variant] || variantMap.secondary;

  return (
    <button
      onClick={onClick}
      title={title}
      disabled={disabled}
      style={{
        width: '32px',
        height: '32px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        borderRadius: ADMIN_RADIUS.md,
        border: 'none',
        background: disabled ? ADMIN_COLORS.background : selected.bg,
        color: disabled ? ADMIN_COLORS.textTertiary : selected.color,
        cursor: disabled ? 'not-allowed' : 'pointer',
        fontSize: '16px',
        transition: ADMIN_TRANSITIONS.normal,
        padding: 0,
      }}
      onMouseEnter={(e) => {
        if (!disabled) {
          e.currentTarget.style.transform = 'scale(1.1)';
          e.currentTarget.style.boxShadow = '0 2px 6px rgba(0, 0, 0, 0.1)';
        }
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.transform = 'scale(1)';
        e.currentTarget.style.boxShadow = 'none';
      }}
      {...props}
    >
      {icon}
    </button>
  );
}

/**
 * LoadingButton - Button with loading state
 */
export function AdminLoadingButton({
  children,
  isLoading,
  loadingText = 'Chargement...',
  ...props
}) {
  return (
    <AdminButton {...props} disabled={isLoading || props.disabled} loading={isLoading}>
      {isLoading ? loadingText : children}
    </AdminButton>
  );
}
