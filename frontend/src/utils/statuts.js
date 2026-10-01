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

/** Rôle d'un professeur dans une classe (indicatif : aucun effet sur la rémunération ni les heures). */
export const ROLES_PROFESSEUR = {
  principal: { label: 'Principal', tone: 'primary' },
  co_enseignant: { label: 'Co-enseignant', tone: 'info' },
  remplacant: { label: 'Remplaçant', tone: 'warning' },
};

/** Origine d'une ligne professeur d'une session : héritée de la classe ou remplacement ponctuel. */
export const ORIGINES_SESSION = {
  classe: { label: 'Assigné par la classe', tone: 'neutral' },
  remplacement: { label: 'Remplacement ponctuel', tone: 'warning' },
};

/** Assignation d'un professeur à une classe : active ou terminée (champ booléen `actif` de l'API). */
export const STATUTS_ASSIGNATION = {
  actif: { label: 'Active', tone: 'success' },
  termine: { label: 'Terminée', tone: 'neutral' },
};

/** Valeur de STATUTS_ASSIGNATION correspondant au booléen `actif`. */
export function statutAssignation(actif) {
  return actif ? 'actif' : 'termine';
}

/** « Ma situation » d'un professeur sur une session (portail « Mes classes »). */
export const SITUATIONS_SESSION = {
  assignee: { label: 'Assigné', tone: 'success' },
  remplace_par: { label: 'Remplacé', tone: 'warning' },
  remplacant_de: { label: 'Remplaçant', tone: 'info' },
};

/** Texte complet de la situation : « Vous êtes remplacé par Bob », « Vous remplacez Alice »… */
export function phraseSituation(situation) {
  const nom = situation?.professeur?.nom;
  if (situation?.type === 'remplace_par') return nom ? `Vous êtes remplacé par ${nom}` : 'Vous êtes remplacé';
  if (situation?.type === 'remplacant_de') return nom ? `Vous remplacez ${nom}` : 'Vous êtes remplaçant';
  return 'Vous êtes assigné à cette session';
}

/**
 * Statuts d'une saisie d'heures (`statut_validation`, sans accent côté API) : brouillon → soumis → confirmé → généré.
 * Le professeur modifie ses brouillons ; le staff confirme ; la génération du PDF clôt le mois.
 */
export const STATUTS_TIMESHEET = {
  brouillon: { label: 'Brouillon', tone: 'warning' },
  soumis: { label: 'Soumis', tone: 'info' },
  confirme: { label: 'Confirmé', tone: 'primary' },
  genere: { label: 'Généré', tone: 'success' },
};

/** État d'encodage CALCULÉ d'une session pour un professeur (pas un statut stocké) ; `a_encoder` = aucune saisie. */
export const ETATS_ENCODAGE = {
  a_encoder: { label: 'À encoder', tone: 'warning' },
  ...STATUTS_TIMESHEET,
};

/** Type d'activité d'une saisie. */
export const TYPES_ACTIVITE = {
  animation: { label: 'Animation', tone: 'primary' },
  preparation: { label: 'Préparation', tone: 'info' },
};

/** Une saisie n'est modifiable par le professeur que tant qu'elle est en brouillon. */
export function estBrouillon(statut) {
  return statut === 'brouillon';
}

/** Type d'un lien de cours (ancien « thème » ; facultatif) — icône + libellé. */
export const TYPES_LIEN = {
  document: { label: 'Document', icone: '📄', tone: 'info' },
  video: { label: 'Vidéo', icone: '🎥', tone: 'primary' },
  outil: { label: 'Outil', icone: '🛠️', tone: 'neutral' },
  jeu: { label: 'Jeu', icone: '🎮', tone: 'success' },
};

/** Actions de l'historique des liens (une version par action ; une restauration est elle-même une version). */
export const ACTIONS_VERSION = {
  creation: { label: 'Création', tone: 'success' },
  modification: { label: 'Modification', tone: 'info' },
  portee: { label: 'Changement de portée', tone: 'info' },
  archivage: { label: 'Archivage', tone: 'warning' },
  ordre: { label: 'Réordonnancement', tone: 'neutral' },
  restauration: { label: 'Restauration', tone: 'primary' },
};

/** Libellé d'une portée : « Liens généraux » ou « Séance 6 » (au-delà de 14 : « Hors programme »). */
export function libellePortee(seanceNumero) {
  if (seanceNumero === null || seanceNumero === undefined) return 'Liens généraux';
  return seanceNumero > 14 ? `Hors programme (séance ${seanceNumero})` : `Séance ${seanceNumero}`;
}
