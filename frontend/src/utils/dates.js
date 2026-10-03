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

/** Titre d'une classe : `titre` de l'API (« Cours P1 → Cours P2 »), sinon dérivé de ses périodes. */
export function titreClasse(classe) {
  if (classe.titre) return classe.titre;
  const cours = (classe.periodes || []).map((p) => p.cours?.titre).filter(Boolean);
  if (cours.length === 0) return classe.cours?.titre || 'Cours';
  return cours[0] === cours[cours.length - 1] ? cours[0] : cours.join(' → ');
}

/** « Scratch → Python — mercredi 14h–17h » */
export function libelleClasse(classe) {
  return `${titreClasse(classe)} — ${libelleCreneau(classe)}`;
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

/** « 14:00 » + 1,5 h → « 15:30 » (plafonné à 23:59 : une séance ne passe pas minuit). */
export function ajouterHeures(hhmm, heures) {
  const [h, m] = String(hhmm).split(':').map(Number);
  if (!Number.isFinite(h) || !Number.isFinite(m)) return hhmm;
  const total = Math.min(23 * 60 + 59, Math.round(h * 60 + m + Number(heures) * 60));
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

/** Durée entre deux heures « HH:MM » en heures décimales (0 si la fin n'est pas après le début). */
export function heuresEntre(debut, fin) {
  const [h1, m1] = String(debut).split(':').map(Number);
  const [h2, m2] = String(fin).split(':').map(Number);
  const minutes = h2 * 60 + m2 - (h1 * 60 + m1);
  return Number.isFinite(minutes) && minutes > 0 ? minutes / 60 : 0;
}

/** Premier jour de classe strictement après `apres` (ISO) et au plus tôt `minimum` (ISO), pour le jour ISO `jour` (1 = lundi). */
export function jourDeClasseApres(apres, minimum, jour) {
  let d = addDays(parseDate(apres), 1);
  const min = minimum ? parseDate(minimum) : null;
  if (min && d < min) d = min;
  for (let i = 0; i < 7; i += 1) {
    const iso = d.getDay() === 0 ? 7 : d.getDay();
    if (iso === jour) return toISODate(d);
    d = addDays(d, 1);
  }
  return '';
}
