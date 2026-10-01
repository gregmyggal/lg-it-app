import { useId, useState } from 'react';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_RADIUS } from '../../styles/AdminDesignSystem';
import AdminButton from '../AdminButton';
import { useMediaQuery } from '../../hooks/useMediaQuery';

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
export function FilterField({ id: idProp, label, value, onChange, options, placeholder, disabled }) {
  const idAuto = useId();
  const id = idProp || idAuto;
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

const styleChamp = {
  padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
  border: `1px solid ${ADMIN_COLORS.border}`,
  borderRadius: ADMIN_RADIUS.md,
  fontSize: '14px',
  fontFamily: 'inherit',
  background: ADMIN_COLORS.cardBg,
  color: ADMIN_COLORS.textPrimary,
  minHeight: '44px',
};

/** Champ de recherche texte : libellé visible associé, la croix native ou Échap efface. */
export function FilterSearch({ id, label = 'Rechercher', value, onChange, placeholder }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: ADMIN_SPACING.xs, minWidth: '200px', flex: '2 1 220px', maxWidth: '360px' }}>
      <label
        htmlFor={id}
        style={{ fontSize: '12px', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.5px', color: ADMIN_COLORS.textPrimary }}
      >
        {label}
      </label>
      <input
        id={id}
        type="search"
        value={value}
        placeholder={placeholder}
        autoComplete="off"
        onChange={(e) => onChange(e.target.value)}
        style={styleChamp}
      />
    </div>
  );
}

/**
 * Barre de filtres commune (staff, professeurs) : Rechercher, filtres en listes, Réinitialiser.
 * Sous 600 px la recherche reste visible ; les autres filtres sont repliés sous « Filtres (N) ».
 *
 * @param {object} props
 * @param {string} props.label  nom de la région de recherche
 * @param {{ id: string, value: string, onChange: (v: string) => void, placeholder?: string }} props.recherche
 * @param {Array<object>} props.filtres  props de FilterField
 * @param {number} props.nbActifs  filtres différents du défaut (hors recherche)
 * @param {() => void} props.onReset
 * @param {boolean} props.resetDisabled
 */
export function FilterToolbar({ label, recherche, filtres, nbActifs, onReset, resetDisabled }) {
  const mobile = useMediaQuery('(max-width: 600px)');
  const [ouvert, setOuvert] = useState(false);
  const idPanneau = useId();
  const afficherFiltres = !mobile || ouvert;

  function reinitialiser() {
    onReset();
    document.getElementById(recherche.id)?.focus();
  }

  const boutonReset = (
    <AdminButton
      variant="secondary"
      onClick={reinitialiser}
      disabled={resetDisabled}
      aria-label="Réinitialiser les filtres"
    >
      ↺ Réinitialiser
    </AdminButton>
  );

  return (
    <FilterBar label={label}>
      <FilterSearch {...recherche} />
      {mobile && (
        <AdminButton
          variant="secondary"
          onClick={() => setOuvert((o) => !o)}
          aria-expanded={ouvert}
          aria-controls={idPanneau}
        >
          Filtres ({nbActifs})
        </AdminButton>
      )}
      <div
        id={idPanneau}
        style={{ display: afficherFiltres ? 'flex' : 'none', flexWrap: 'wrap', gap: ADMIN_SPACING.lg, alignItems: 'flex-end', flex: '1 1 auto' }}
      >
        {filtres.map((f) => <FilterField key={f.id} {...f} />)}
        {!mobile && boutonReset}
      </div>
      {mobile && boutonReset}
    </FilterBar>
  );
}

/** Compteur de résultats annoncé aux lecteurs d'écran. */
export function ResultCount({ children }) {
  return (
    <p role="status" aria-live="polite" style={{ margin: `0 0 ${ADMIN_SPACING.md}`, fontSize: '14px', color: ADMIN_COLORS.textSecondary || '#4b5563' }}>
      {children}
    </p>
  );
}
