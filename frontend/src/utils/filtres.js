/** Utilitaires communs aux barres de filtres des listes (staff, professeurs). */

/** Minuscules sans accents : « Éloïse » correspond à « eloise ». */
export function normaliser(texte) {
  return String(texte ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

/** Tous les mots saisis doivent se retrouver dans l'un des champs (ET, insensible à la casse et aux accents). */
export function correspondRecherche(champs, saisie) {
  const mots = normaliser(saisie).split(/\s+/).filter(Boolean);
  if (mots.length === 0) return true;
  const cible = normaliser(champs.filter(Boolean).join(' '));
  return mots.every((mot) => cible.includes(mot));
}

/** Statut : défaut « Actifs » sur toutes les listes ; « Tous » n'est jamais le défaut. */
export const STATUT_PAR_DEFAUT = 'actif';
export const OPTIONS_STATUT = [
  { value: 'actif', label: 'Actifs' },
  { value: 'inactif', label: 'Désactivés' },
  { value: 'tous', label: 'Tous' },
];

/** PROF-02 : un professeur inactif est « archivé » (les comptes staff restent « désactivés »). */
export const OPTIONS_STATUT_PROFESSEUR = [
  { value: 'actif', label: 'Actifs' },
  { value: 'inactif', label: 'Archivés' },
  { value: 'tous', label: 'Tous' },
];

/** Valeur d'API du filtre Statut (« tous » = pas de filtre serveur). */
export const statutPourApi = (statut) => (statut === 'tous' ? '' : statut);

/** Accès : mêmes libellés et valeurs sur toutes les listes (filtre appliqué côté client). */
export const OPTIONS_ACCES = [
  { value: 'relancer', label: 'À relancer' },
  { value: 'non_envoye', label: 'Accès non envoyé' },
  { value: 'invitation_en_attente', label: 'Invitation en attente' },
  { value: 'mot_de_passe_provisoire', label: 'Mot de passe provisoire' },
  { value: 'mot_de_passe_defini', label: 'Mot de passe défini' },
];

export function correspondAcces(acces, filtre) {
  if (!filtre) return true;
  if (filtre === 'relancer') return Boolean(acces?.a_relancer);
  if (filtre === 'non_envoye') return acces?.statut === 'invitation_non_envoyee' && acces.motif !== 'echec';
  return acces?.statut === filtre;
}

export const OPTIONS_CONTRAT = [
  { value: 'salarie', label: 'Salarié' },
  { value: 'freelance', label: 'Freelance' },
  { value: 'prestataire', label: 'Prestataire' },
  { value: 'non_renseigne', label: 'Non renseigné' },
];

export function correspondContrat(typeContrat, filtre) {
  if (!filtre) return true;
  return (typeContrat || 'non_renseigne') === filtre;
}

/** « 2 professeurs sur 5 » / « 5 professeurs » (singulier/pluriel corrects). */
export function libelleResultats(nombre, total, [singulier, pluriel]) {
  const nom = nombre > 1 ? pluriel : singulier;
  return nombre === total ? `${nombre} ${nom}` : `${nombre} ${nom} sur ${total}`;
}
