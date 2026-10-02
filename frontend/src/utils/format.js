/** Formats d'affichage des heures et des montants (français, Belgique). */

const EUROS = new Intl.NumberFormat('fr-BE', { style: 'currency', currency: 'EUR' });

/** « 36,00 € » ; `—` si la valeur est absente. */
export function formatEuros(valeur) {
  return valeur === null || valeur === undefined || Number.isNaN(Number(valeur)) ? '—' : EUROS.format(Number(valeur));
}

/** « 3 h », « 2,5 h » (virgule décimale, sans zéros inutiles). */
export function formatHeures(valeur) {
  const n = Number(valeur);
  if (Number.isNaN(n)) return '—';
  return `${String(Math.round(n * 100) / 100).replace('.', ',')} h`;
}

/** Durée en heures décimales → « 1 h 30 », « 2 h », « 45 min » (heures défrayables, durée de séance). */
export function formatDuree(valeur) {
  const n = Number(String(valeur).replace(',', '.'));
  if (!Number.isFinite(n) || n < 0) return '—';
  const minutes = Math.round(n * 60);
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h === 0) return `${m} min`;
  return m === 0 ? `${h} h` : `${h} h ${String(m).padStart(2, '0')}`;
}
