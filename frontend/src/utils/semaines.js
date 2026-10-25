/** Regroupement des cartes de « Mon mois » par semaine (UI-M4). */

const MOIS_COURTS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const deuxChiffres = (n) => String(n).padStart(2, '0');

/** Lundi (ISO `YYYY-MM-DD`) de la semaine d'une date ISO, sans passer par un fuseau. */
function lundiDe(iso) {
  const [a, m, j] = iso.split('-').map(Number);
  const d = new Date(a, m - 1, j);
  d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
  return `${d.getFullYear()}-${deuxChiffres(d.getMonth() + 1)}-${deuxChiffres(d.getDate())}`;
}

export function titreSemaine(lundi) {
  const [, m, j] = lundi.split('-').map(Number);
  return `Semaine du ${j} ${MOIS_COURTS[m - 1]}`;
}

/**
 * Séances et heures libres du mois dans l'ordre chronologique, regroupées par semaine.
 *
 * @param {object[]} sessions `sessions` de `GET /timesheets/mon-mois`
 * @param {object[]} libres `libres` de `GET /timesheets/mon-mois`
 * @returns {{lundi: string, cartes: {cle: string, type: 'session'|'libre', date: string, donnees: object}[]}[]}
 */
export function grouperParSemaine(sessions, libres) {
  const cartes = [
    ...sessions.map((s) => ({ cle: `s${s.id}`, type: 'session', date: s.date, heure: s.heure_debut, donnees: s })),
    ...libres.map((x) => ({ cle: `l${x.id}`, type: 'libre', date: x.date_prestation.slice(0, 10), heure: '99:99', donnees: x })),
  ].sort((a, b) => (a.date + a.heure).localeCompare(b.date + b.heure));

  const semaines = [];
  cartes.forEach((c) => {
    const lundi = lundiDe(c.date);
    let semaine = semaines[semaines.length - 1];
    if (!semaine || semaine.lundi !== lundi) {
      semaine = { lundi, cartes: [] };
      semaines.push(semaine);
    }
    semaine.cartes.push(c);
  });
  return semaines;
}
