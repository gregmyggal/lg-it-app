import { formatDateHeure } from './dates';

const DETAIL = {
  mot_de_passe_defini: (a) => `Mot de passe défini le ${formatDateHeure(a.mot_de_passe_defini_le)}`,
  invitation_en_attente: (a) => `Invitation envoyée le ${formatDateHeure(a.invitation_envoyee_le)} (expire le ${formatDateHeure(a.invitation_expire_le)})`,
  invitation_expiree: (a) => `Invitation expirée le ${formatDateHeure(a.invitation_expire_le)}`,
  invitation_non_envoyee: (a) => (a.motif === 'echec'
    ? 'L\u2019envoi de l\u2019invitation a échoué'
    : 'Compte créé, aucun email d\u2019invitation envoyé'),
  mot_de_passe_provisoire: () => 'Mot de passe provisoire (jamais changé)',
};

/** Phrase détaillée de l'état d'accès d'un compte (résumé `acces` calculé par le backend). */
export function detailAcces(acces) {
  return DETAIL[acces?.statut]?.(acces) ?? '';
}

/** Libellé du bouton d'envoi : l'invitation tant que le mot de passe n'a jamais été défini. */
export function libelleEnvoiLien(acces) {
  if (estAccesNonEnvoye(acces)) return 'Envoyer l\u2019invitation';
  return acces?.statut?.startsWith('invitation_') ? 'Renvoyer l\u2019invitation' : 'Envoyer un lien de réinitialisation';
}

/** Accès créé sans envoi d'invitation (volontaire, ADMIN-05) : ni alerte ni « à relancer ». */
export function estAccesNonEnvoye(acces) {
  return acces?.statut === 'invitation_non_envoyee' && acces.motif !== 'echec';
}

/** Clé du libellé de pastille : distingue « accès non envoyé » (volontaire) de « invitation en échec ». */
export function cleStatutAcces(acces) {
  if (acces?.statut === 'invitation_non_envoyee' && acces.motif === 'echec') return 'invitation_echec';
  return acces?.statut;
}

/** Relance rapide proposée : invitation expirée ou envoi en échec (« Renvoyer »). */
export function relanceRapide(acces) {
  return acces?.statut === 'invitation_expiree' || (acces?.statut === 'invitation_non_envoyee' && acces.motif === 'echec');
}

/** Éligible à l'envoi en lot : accès non envoyé (volontaire ou en échec). */
export function eligibleLot(acces) {
  return acces?.statut === 'invitation_non_envoyee';
}

/** Plafond d'un envoi en lot (aligné sur AccesCompteService::LOT_MAX). */
export const LOT_MAX = 25;

/** Le compte n'a pas encore défini son mot de passe : aucun email de notification ne lui est envoyé. */
export function sansMotDePasseDefini(acces) {
  return Boolean(acces?.statut) && acces.statut !== 'mot_de_passe_defini';
}
