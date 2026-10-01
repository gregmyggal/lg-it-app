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
