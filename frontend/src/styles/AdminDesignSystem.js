/**
 * Admin Pages Design System
 * Les couleurs sont des variables CSS (index.css) : clair/nuit sans toucher aux composants.
 * Professional, modern, consistent UI across all admin pages
 */

export const ADMIN_COLORS = {
  // Primary Actions
  primary: 'var(--c-primary)',        // Blue
  primaryHover: 'var(--c-primary-hover)',
  primaryLight: 'var(--c-primary-light)',

  // Semantic
  success: 'var(--c-success)',        // Green
  warning: 'var(--c-warning)',        // Amber
  error: 'var(--c-error)',          // Red
  info: 'var(--c-info)',           // Cyan

  // Backgrounds
  background: 'var(--c-bg)',     // Light gray
  cardBg: 'var(--c-card)',         // White
  hoverBg: 'var(--c-hover)',        // Lighter gray

  // Text
  textPrimary: 'var(--c-text)',    // Dark
  textSecondary: 'var(--c-text-2)',  // Gray
  textTertiary: 'var(--c-text-3)',   // Light gray

  // Borders
  border: 'var(--c-border)',         // Gray-200
  borderLight: 'var(--c-border-light)',    // Gray-100
};

/**
 * Tons sémantiques (fond + texte foncé, contraste AA) pour badges, bandeaux et toasts.
 * Toujours associés à un texte : la couleur ne porte jamais seule l'information.
 */
export const ADMIN_TONES = {
  primary: { bg: 'var(--tone-primary-bg)', fg: 'var(--tone-primary-fg)', border: 'var(--tone-primary-bd)' },
  success: { bg: 'var(--tone-success-bg)', fg: 'var(--tone-success-fg)', border: 'var(--tone-success-bd)' },
  warning: { bg: 'var(--tone-warning-bg)', fg: 'var(--tone-warning-fg)', border: 'var(--tone-warning-bd)' },
  error: { bg: 'var(--tone-error-bg)', fg: 'var(--tone-error-fg)', border: 'var(--tone-error-bd)' },
  info: { bg: 'var(--tone-info-bg)', fg: 'var(--tone-info-fg)', border: 'var(--tone-info-bd)' },
  school: { bg: 'var(--tone-school-bg)', fg: 'var(--tone-school-fg)', border: 'var(--tone-school-bd)' },
  neutral: { bg: 'var(--tone-neutral-bg)', fg: 'var(--tone-neutral-fg)', border: 'var(--tone-neutral-bd)' },
};

export const ADMIN_FOCUS_RING = '0 0 0 3px var(--c-focus)';

export const ADMIN_TYPOGRAPHY = {
  h1: {
    fontSize: '32px',
    fontWeight: 700,
    lineHeight: '1.2',
  },
  h2: {
    fontSize: '24px',
    fontWeight: 700,
    lineHeight: '1.3',
  },
  h3: {
    fontSize: '20px',
    fontWeight: 600,
    lineHeight: '1.4',
  },
  h4: {
    fontSize: '16px',
    fontWeight: 600,
    lineHeight: '1.5',
  },
  body: {
    fontSize: '14px',
    fontWeight: 400,
    lineHeight: '1.6',
  },
  small: {
    fontSize: '13px',
    fontWeight: 400,
    lineHeight: '1.5',
  },
  label: {
    fontSize: '12px',
    fontWeight: 600,
    lineHeight: '1.4',
    textTransform: 'uppercase',
    letterSpacing: '0.5px',
  },
};

export const ADMIN_SPACING = {
  xs: '4px',
  sm: '8px',
  md: '12px',
  lg: '16px',
  xl: '24px',
  '2xl': '32px',
  '3xl': '48px',
};

export const ADMIN_SHADOWS = {
  sm: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
  md: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
  lg: '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
  xl: '0 20px 25px -5px rgba(0, 0, 0, 0.1)',
  hover: '0 4px 12px rgba(0, 0, 0, 0.15)',
};

export const ADMIN_RADIUS = {
  sm: '4px',
  md: '8px',
  lg: '12px',
  xl: '16px',
  full: '9999px',
};

export const ADMIN_TRANSITIONS = {
  fast: 'all 0.15s ease',
  normal: 'all 0.2s ease',
  slow: 'all 0.3s ease',
};

/**
 * Component Style Utilities
 */
export const AdminStyles = {
  // Page Layout
  pageContainer: {
    minHeight: '100vh',
    background: ADMIN_COLORS.background,
  },

  pageHeader: {
    background: 'white',
    borderBottom: `1px solid ${ADMIN_COLORS.border}`,
    padding: `${ADMIN_SPACING.lg} ${ADMIN_SPACING.xl}`,
    position: 'sticky',
    top: 0,
    zIndex: 100,
    boxShadow: ADMIN_SHADOWS.sm,
  },

  pageContent: {
    maxWidth: 'var(--page-max, 1760px)',
    margin: '0 auto',
    padding: `${ADMIN_SPACING.xl}`,
  },

  // Cards
  card: {
    background: ADMIN_COLORS.cardBg,
    border: `1px solid ${ADMIN_COLORS.border}`,
    borderRadius: ADMIN_RADIUS.lg,
    boxShadow: ADMIN_SHADOWS.sm,
    transition: ADMIN_TRANSITIONS.normal,
    padding: ADMIN_SPACING.lg,
  },

  cardHover: {
    boxShadow: ADMIN_SHADOWS.hover,
    transform: 'translateY(-2px)',
  },

  // Buttons
  buttonBase: {
    padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
    borderRadius: ADMIN_RADIUS.md,
    border: 'none',
    cursor: 'pointer',
    fontWeight: 600,
    fontSize: '14px',
    transition: ADMIN_TRANSITIONS.normal,
    display: 'inline-flex',
    alignItems: 'center',
    gap: ADMIN_SPACING.md,
  },

  buttonPrimary: {
    background: ADMIN_COLORS.primary,
    color: 'white',
    '&:hover': {
      background: ADMIN_COLORS.primaryHover,
      boxShadow: ADMIN_SHADOWS.md,
    },
  },

  buttonSecondary: {
    background: ADMIN_COLORS.background,
    color: ADMIN_COLORS.textPrimary,
    border: `1px solid ${ADMIN_COLORS.border}`,
    '&:hover': {
      background: ADMIN_COLORS.hoverBg,
      borderColor: ADMIN_COLORS.textSecondary,
    },
  },

  buttonDanger: {
    background: ADMIN_COLORS.error,
    color: 'white',
    '&:hover': {
      background: '#dc2626',
      boxShadow: ADMIN_SHADOWS.md,
    },
  },

  // Forms
  formField: {
    marginBottom: ADMIN_SPACING.lg,
  },

  label: {
    display: 'block',
    fontSize: '12px',
    fontWeight: 600,
    color: ADMIN_COLORS.textPrimary,
    marginBottom: ADMIN_SPACING.sm,
    textTransform: 'uppercase',
    letterSpacing: '0.5px',
  },

  input: {
    width: '100%',
    padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
    border: `1px solid ${ADMIN_COLORS.border}`,
    borderRadius: ADMIN_RADIUS.md,
    fontSize: '14px',
    transition: ADMIN_TRANSITIONS.normal,
    '&:focus': {
      borderColor: ADMIN_COLORS.primary,
      boxShadow: `0 0 0 3px ${ADMIN_COLORS.primaryLight}`,
      outline: 'none',
    },
  },

  // Modal
  modal: {
    position: 'fixed',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 1000,
  },

  modalContent: {
    background: ADMIN_COLORS.cardBg,
    borderRadius: ADMIN_RADIUS.xl,
    boxShadow: ADMIN_SHADOWS.xl,
    maxWidth: '500px',
    width: '90%',
    maxHeight: '90vh',
    overflow: 'auto',
    animation: 'slideUp 0.3s ease',
  },

  modalHeader: {
    padding: ADMIN_SPACING.xl,
    borderBottom: `1px solid ${ADMIN_COLORS.border}`,
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },

  modalBody: {
    padding: ADMIN_SPACING.xl,
  },

  modalFooter: {
    padding: ADMIN_SPACING.xl,
    borderTop: `1px solid ${ADMIN_COLORS.border}`,
    display: 'flex',
    gap: ADMIN_SPACING.lg,
    justifyContent: 'flex-end',
  },

  // Table
  tableContainer: {
    overflowX: 'auto',
    borderRadius: ADMIN_RADIUS.lg,
    border: `1px solid ${ADMIN_COLORS.border}`,
  },

  tableHeader: {
    background: ADMIN_COLORS.background,
    borderBottom: `1px solid ${ADMIN_COLORS.border}`,
  },

  tableCell: {
    padding: ADMIN_SPACING.lg,
    borderBottom: `1px solid ${ADMIN_COLORS.border}`,
    fontSize: '14px',
    color: ADMIN_COLORS.textPrimary,
  },

  tableRow: {
    '&:hover': {
      background: ADMIN_COLORS.background,
    },
  },

  // Badge/Tag
  badge: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: ADMIN_SPACING.sm,
    padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
    borderRadius: ADMIN_RADIUS.full,
    fontSize: '12px',
    fontWeight: 600,
  },

  badgePrimary: {
    background: ADMIN_COLORS.primaryLight,
    color: ADMIN_COLORS.primary,
  },

  badgeSuccess: {
    background: '#d1fae5',
    color: ADMIN_COLORS.success,
  },

  badgeWarning: {
    background: '#fef3c7',
    color: ADMIN_COLORS.warning,
  },

  badgeError: {
    background: '#fee2e2',
    color: ADMIN_COLORS.error,
  },

  // Grid
  gridContainer: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
    gap: ADMIN_SPACING.xl,
  },

  // Flex utilities
  flexBetween: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },

  flexCenter: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
  },

  // Loading State
  skeleton: {
    background: `linear-gradient(90deg, ${ADMIN_COLORS.background} 25%, ${ADMIN_COLORS.hoverBg} 50%, ${ADMIN_COLORS.background} 75%)`,
    backgroundSize: '200% 100%',
    animation: 'loading 1.5s infinite',
  },
};

/**
 * Icon Color Utilities
 */
export const ICON_COLORS = {
  primary: ADMIN_COLORS.primary,
  success: ADMIN_COLORS.success,
  warning: ADMIN_COLORS.warning,
  error: ADMIN_COLORS.error,
  info: ADMIN_COLORS.info,
  muted: ADMIN_COLORS.textSecondary,
};

/**
 * Common Action Icons & Colors
 */
export const ACTION_ICONS = {
  create: { icon: '➕', color: ADMIN_COLORS.success },
  edit: { icon: '✏️', color: ADMIN_COLORS.primary },
  delete: { icon: '🗑️', color: ADMIN_COLORS.error },
  view: { icon: '👁️', color: ADMIN_COLORS.info },
  download: { icon: '⬇️', color: ADMIN_COLORS.primary },
  export: { icon: '📤', color: ADMIN_COLORS.primary },
  search: { icon: '🔍', color: ADMIN_COLORS.textSecondary },
  filter: { icon: '⚙️', color: ADMIN_COLORS.textSecondary },
  settings: { icon: '⚙️', color: ADMIN_COLORS.textSecondary },
};
