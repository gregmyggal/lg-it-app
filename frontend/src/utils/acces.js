import { formatDateHeure } from './dates';

const DETAIL = {
  mot_de_passe_defini: (a) => `Mot de passe défini le ${formatDateHeure(a.mot_de_passe_defini_le)}`,
  invitation_en_attente: (a) => `Invitation envoyée le ${formatDateHeure(a.invitation_envoyee_le)} (expire le ${formatDateHeure(a.invitation_expire_le)})`,
  invitation_expiree: (a) => `Invitation expirée le ${formatDateHeure(a.invitation_expire_le)}`,
  invitation_non_envoyee: () => 'Invitation non envoyée',
  mot_de_passe_provisoire: () => 'Mot de passe provisoire (jamais changé)',
};

/** Phrase détaillée de l'état d'accès d'un compte (résumé `acces` calculé par le backend). */
export function detailAcces(acces) {
  return DETAIL[acces?.statut]?.(acces) ?? '';
}

/** Libellé du bouton d'envoi : l'invitation tant que le mot de passe n'a jamais été défini. */
export function libelleEnvoiLien(acces) {
  return acces?.statut?.startsWith('invitation_') ? 'Renvoyer l\u2019invitation' : 'Envoyer un lien de réinitialisation';
}

/** Statuts pour lesquels la relance rapide est proposée. */
export const A_RELANCER_RAPIDE = ['invitation_expiree', 'invitation_non_envoyee'];
