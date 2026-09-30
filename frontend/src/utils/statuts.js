/**
 * Table unique des libellés et tons (couleurs) par valeur d'énumération de l'API.
 * Aucune page ne doit comparer un statut à une chaîne en dur : elle passe par ce fichier.
 * Les valeurs sont celles de l'API (sans accent) ; les libellés sont affichés tels quels.
 * Un statut inconnu s'affiche en badge neutre avec sa valeur brute (jamais un badge vide).
 */

export const STATUTS_SESSION = {
  planifiee: { label: 'Planifiée', tone: 'primary' },
  en_cours: { label: 'En cours', tone: 'info' },
  terminee: { label: 'Terminée', tone: 'success' },
  annulee: { label: 'Annulée', tone: 'neutral', barree: true },
};

export const STATUTS_CLASSE = {
  active: { label: 'Active', tone: 'success' },
  terminee: { label: 'Terminée', tone: 'neutral' },
  archivee: { label: 'Archivée', tone: 'neutral', archive: true },
};

export const STATUTS_ANNEE = {
  brouillon: { label: 'Brouillon', tone: 'neutral' },
  active: { label: 'Active', tone: 'success', discret: true },
  archivee: { label: 'Archivée', tone: 'neutral' },
};

export const TYPES_CALENDRIER = {
  vacances: { label: 'Vacances', tone: 'info' },
  ferie: { label: 'Férié', tone: 'warning' },
  fermeture: { label: 'Fermeture école', tone: 'school' },
};

export const SOURCES_CALENDRIER = {
  // Une entrée FWB supprimée est masquée (et le reste aux imports suivants) ; une entrée École est supprimée.
  fwb: { label: 'FWB', tone: 'primary', masquable: true },
  ecole: { label: 'École', tone: 'school' },
};

/**
 * @param {Record<string, {label: string, tone: string}>} table
 * @param {string|null|undefined} valeur valeur brute de l'API
 * @returns {{label: string, tone: string, barree: boolean, connu: boolean, valeur: *}}
 */
export function getStatut(table, valeur) {
  const entree = table[valeur];
  if (entree) {
    return { barree: false, ...entree, connu: true, valeur };
  }
  return {
    label: valeur === null || valeur === undefined || valeur === '' ? '—' : String(valeur),
    tone: 'neutral',
    barree: false,
    connu: false,
    valeur,
  };
}

/** Options de liste déroulante { value, label } pour une table de statuts. */
export function optionsStatut(table) {
  return Object.entries(table).map(([value, { label }]) => ({ value, label }));
}

/** Vrai si le statut de session s'affiche barré (date barrée, ligne grisée). */
export function estSessionBarree(statut) {
  return getStatut(STATUTS_SESSION, statut).barree;
}

/** « 2026-2027 » (année active) ou « 2025-2026 (archivée) » pour un sélecteur d'année. */
export function libelleAnneeListe(annee) {
  const statut = getStatut(STATUTS_ANNEE, annee.statut);
  return statut.discret ? annee.libelle : `${annee.libelle} (${statut.label.toLowerCase()})`;
}

/** Vrai si la classe est archivée (plus d'action « Archiver »). */
export function estClasseArchivee(statut) {
  return getStatut(STATUTS_CLASSE, statut).archive === true;
}

/** Valeur d'API à envoyer pour archiver une classe. */
export const STATUT_CLASSE_ARCHIVEE = 'archivee';

/** Vrai si supprimer une entrée de cette source la masque au lieu de l'effacer (règle FWB, cf. API). */
export function estSourceMasquable(source) {
  return getStatut(SOURCES_CALENDRIER, source).masquable === true;
}
