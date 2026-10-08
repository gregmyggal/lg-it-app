import { AdminInput } from '../AdminFormField';

const arrondi = (n) => Math.round(n * 2) / 2;

/**
 * Saisie d'heures : champ numérique (clavier décimal) encadré de boutons − / + par pas de 0,5 h.
 * Les boutons ne s'affichent que sur mobile (classe `.heures-stepper__btn`), où ils offrent de grandes cibles tactiles.
 */
export default function HeuresStepper({ value, onChange, disabled, min = 0.5, max = 24, step = 0.5, 'aria-label': ariaLabel }) {
  function bouger(delta) {
    const base = Number(value) || 0;
    const suivant = Math.min(max, Math.max(min, arrondi(base + delta)));
    onChange(String(suivant));
  }

  return (
    <div className="heures-stepper">
      <button
        type="button"
        className="heures-stepper__btn"
        aria-label="Diminuer d'une demi-heure"
        disabled={disabled || (Number(value) || 0) <= min}
        onClick={() => bouger(-step)}
      >
        −
      </button>
      <AdminInput
        type="number"
        inputMode="decimal"
        step={step}
        min={min}
        max={max}
        aria-label={ariaLabel}
        value={value}
        disabled={disabled}
        onChange={(e) => onChange(e.target.value)}
      />
      <button
        type="button"
        className="heures-stepper__btn"
        aria-label="Augmenter d'une demi-heure"
        disabled={disabled || (Number(value) || 0) >= max}
        onClick={() => bouger(step)}
      >
        +
      </button>
    </div>
  );
}
