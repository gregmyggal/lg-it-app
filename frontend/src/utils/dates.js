/**
 * Utilitaires de dates. L'API renvoie des dates « YYYY-MM-DD » (sans fuseau) :
 * on les manipule en date locale pour éviter tout décalage d'un jour.
 */

const JOURS_LONGS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const JOURS_COURTS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
export const MOIS_LONGS = [
  'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
];

/** Jours de la semaine tels que l'API les numérote (1 = lundi … 7 = dimanche). */
export const JOURS_SEMAINE = [
  { value: 1, label: 'Lundi' },
  { value: 2, label: 'Mardi' },
  { value: 3, label: 'Mercredi' },
  { value: 4, label: 'Jeudi' },
  { value: 5, label: 'Vendredi' },
  { value: 6, label: 'Samedi' },
  { value: 7, label: 'Dimanche' },
];

const deuxChiffres = (n) => String(n).padStart(2, '0');

export function parseDate(iso) {
  if (!iso) return null;
  const [y, m, d] = String(iso).slice(0, 10).split('-').map(Number);
  return new Date(y, m - 1, d);
}

export function toISODate(date) {
  return `${date.getFullYear()}-${deuxChiffres(date.getMonth() + 1)}-${deuxChiffres(date.getDate())}`;
}

export function addDays(date, n) {
  const copie = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  copie.setDate(copie.getDate() + n);
  return copie;
}

export function aujourdhuiISO() {
  return toISODate(new Date());
}

/** « 25/11/2026 » */
export function formatDate(iso) {
  const d = parseDate(iso);
  if (!d) return '—';
  return `${deuxChiffres(d.getDate())}/${deuxChiffres(d.getMonth() + 1)}/${d.getFullYear()}`;
}

/** Horodatage ISO 8601 avec fuseau → « 25/11/2026 14:32 » (heure locale du navigateur). */
export function formatDateHeure(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return `${formatDate(toISODate(d))} ${deuxChiffres(d.getHours())}:${deuxChiffres(d.getMinutes())}`;
}

/** « mer. 25/11/2026 » */
export function formatDateCourte(iso) {
  const d = parseDate(iso);
  if (!d) return '—';
  return `${JOURS_COURTS[d.getDay()]} ${formatDate(iso)}`;
}

/** « mercredi 25 novembre 2026 » */
export function formatDateLongue(iso) {
  const d = parseDate(iso);
  if (!d) return '—';
  return `${JOURS_LONGS[d.getDay()]} ${d.getDate()} ${MOIS_LONGS[d.getMonth()]} ${d.getFullYear()}`;
}

/** « 26/10/2026 → 01/11/2026 » ou « 11/11/2026 » si un seul jour. */
export function formatPlage(debut, fin) {
  if (!fin || fin === debut) return formatDate(debut);
  return `${formatDate(debut)} → ${formatDate(fin)}`;
}

/** « 14:00 » → « 14h », « 09:30 » → « 9h30 » */
export function formatHeure(hhmm) {
  if (!hhmm) return '';
  const [h, m] = hhmm.split(':');
  const heure = String(Number(h));
  return m && m !== '00' ? `${heure}h${m}` : `${heure}h`;
}

export function formatHoraire(debut, fin) {
  return `${formatHeure(debut)}–${formatHeure(fin)}`;
}

export function nomJour(jourSemaine) {
  const d = jourSemaine === 7 ? 0 : jourSemaine;
  return JOURS_LONGS[d] || '';
}

/** « mercredi 14h–17h » */
export function libelleCreneau(classe) {
  return `${nomJour(classe.jour_semaine)} ${formatHoraire(classe.heure_debut, classe.heure_fin)}`;
}

/** « React — mercredi 14h–17h » */
export function libelleClasse(classe) {
  const cours = classe.cours?.titre || 'Cours';
  return `${cours} — ${libelleCreneau(classe)}`;
}

/** Lundi de la semaine contenant la date. */
export function debutDeSemaine(date) {
  const decalage = (date.getDay() + 6) % 7;
  return addDays(date, -decalage);
}

/** Semaine ISO 8601 d'une date : { annee, semaine }. */
export function semaineISO(date) {
  const jeudi = addDays(debutDeSemaine(date), 3);
  const premierJanvier = new Date(jeudi.getFullYear(), 0, 1);
  const jours = Math.round((jeudi - premierJanvier) / 86400000);
  return { annee: jeudi.getFullYear(), semaine: Math.floor(jours / 7) + 1 };
}

export function dernierJourDuMois(annee, mois) {
  return new Date(annee, mois, 0);
}
