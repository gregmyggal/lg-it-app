/** Aides d'affichage des liens d'un cours par session (CLS-01 T4). */

/** Liens d'une session : les liens généraux + ceux de SON numéro de séance (fixe ; vrai aussi pour un bis ou une session annulée). */
export function liensDeSession(liens, seanceNumero) {
  return {
    generaux: liens.filter((l) => l.seance_numero === null),
    seance: liens.filter((l) => l.seance_numero === seanceNumero),
  };
}

/** « 3 généraux + 2 de la séance 6 » (ou « Aucun lien »). */
export function resumeLiens({ generaux, seance }, seanceNumero) {
  if (generaux.length + seance.length === 0) return 'Aucun lien';
  const parts = [];
  if (generaux.length) parts.push(`${generaux.length} généra${generaux.length > 1 ? 'ux' : 'l'}`);
  if (seance.length) parts.push(`${seance.length} de la séance ${seanceNumero}`);
  return parts.join(' + ');
}

