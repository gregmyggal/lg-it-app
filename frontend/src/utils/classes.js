/** Aides d'affichage des classes à deux périodes (CLS-02). */

/** « P1 · Séance 3 » (+ « bis ») : libellé unique d'une session ; retombe sur `libelle` si l'API ne le fournit pas. */
export function libelleSession(session) {
  return session.libelle_complet || session.libelle;
}

/** Même libellé en minuscules pour les phrases (« la p1 · séance 3 » n'étant pas lisible, on garde « P1 · séance 3 »). */
export function libelleSessionPhrase(session) {
  const l = libelleSession(session);
  return l.replace(/(Séance)/, 'séance');
}

/** Période de classe (`classe.periodes[]`) portant ce numéro de période, ou `null`. */
export function periodeDeClasse(classe, numero) {
  return (classe.periodes || []).find((p) => p.numero === numero) || null;
}

/** Périodes actives de la classe (les périodes annulées restent visibles mais ne comptent plus pour les ajouts). */
export function numerosPresents(classe) {
  return (classe.periodes || []).map((p) => p.numero);
}

/** « P1 Scratch · P2 Python » */
export function resumePeriodes(classe) {
  return (classe.periodes || []).map((p) => `P${p.numero} ${p.cours?.titre || ''}`.trim()).join(' · ');
}

/** Cours d'une session : celui de sa période (`session.cours`), repli sur l'ancien emplacement `classe.cours`. */
export function coursDeSession(session) {
  return session.cours || session.classe?.cours || null;
}

/** Texte d'avertissement « hors période » d'une session (null si elle est dans sa période). */
export function texteHorsPeriode(session) {
  return session.hors_periode ? `Hors période${session.periode_numero ? ` ${session.periode_numero}` : ''}` : null;
}
