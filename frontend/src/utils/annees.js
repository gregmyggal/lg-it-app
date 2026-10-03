/**
 * Utilitaires CLS-03 : cohérence des dates d'une année, lien « Modifier les dates » avec retour,
 * brouillon de formulaire en sessionStorage (saisie conservée pendant un détour par la gestion des années).
 */
import { addDays, formatDate, parseDate, toISODate } from './dates';

const JOUR_MS = 86400000;
const SEUIL_TROU_JOURS = 42; // 6 semaines

export function joursEntre(debutISO, finISO) {
  return Math.round((parseDate(finISO) - parseDate(debutISO)) / JOUR_MS);
}

/**
 * Cohérence en direct de deux périodes (les mêmes règles que le serveur, qui reste la référence).
 * @param {{p1_debut:string,p1_fin:string,p2_debut:string,p2_fin:string}} d
 * @returns {{complet:boolean, bloquants:string[], avertissements:string[], info:string|null, trouJours:number|null, chevauchement:boolean}}
 */
export function verifierPeriodes(d) {
  const complet = Boolean(d.p1_debut && d.p1_fin && d.p2_debut && d.p2_fin);
  const bloquants = [];
  const avertissements = [];
  let info = null;
  let trouJours = null;
  let chevauchement = false;
  if (d.p1_debut && d.p1_fin && d.p1_fin <= d.p1_debut) bloquants.push('La fin de la période 1 doit être postérieure à son début.');
  if (d.p2_debut && d.p2_fin && d.p2_fin <= d.p2_debut) bloquants.push('La fin de la période 2 doit être postérieure à son début.');
  if (d.p1_fin && d.p2_debut && d.p2_debut <= d.p1_fin) {
    chevauchement = true;
    const auPlusTot = toISODate(addDays(parseDate(d.p1_fin), 1));
    bloquants.push(
      `La période 2 doit commencer après la fin de la période 1 (${formatDate(d.p1_fin)}). Au plus tôt le ${formatDate(auPlusTot)}.`,
    );
  }
  if (d.p1_fin && d.p2_debut && !chevauchement) {
    trouJours = joursEntre(d.p1_fin, d.p2_debut) - 1;
    if (trouJours > SEUIL_TROU_JOURS) {
      avertissements.push(`${trouJours} jours sans période : aucune classe ne pourra démarrer dans cet intervalle.`);
    } else {
      info = `${trouJours} jour${trouJours > 1 ? 's' : ''} entre les périodes (vacances).`;
    }
  }
  return { complet, bloquants, avertissements, info, trouJours, chevauchement };
}

/** « fin −14 j », « début +7 j », « inchangée » à partir de l'ancienne et de la nouvelle période. */
export function evolutionPeriode(avant, apres) {
  if (!avant || !apres || !apres.date_debut || !apres.date_fin) return '';
  const parts = [];
  const dd = joursEntre(avant.date_debut, apres.date_debut);
  const df = joursEntre(avant.date_fin, apres.date_fin);
  if (dd) parts.push(`début ${dd > 0 ? '+' : '−'}${Math.abs(dd)} j`);
  if (df) parts.push(`fin ${df > 0 ? '+' : '−'}${Math.abs(df)} j`);
  return parts.length ? parts.join(', ') : 'inchangée';
}

const CHEMIN_RETOUR_VALIDE = /^\/admin\/[A-Za-z0-9\-_/]*(\?[A-Za-z0-9\-_=&%.]*)?$/;

/** N'accepte qu'un chemin interne de l'espace admin (jamais une URL externe). */
export function retourValide(chemin) {
  return typeof chemin === 'string' && !chemin.startsWith('//') && CHEMIN_RETOUR_VALIDE.test(chemin) ? chemin : null;
}

/** Nom lisible de la page d'origine pour la bande « Vous modifiez les dates depuis … ». */
export function libelleRetour(chemin) {
  if (!chemin) return '';
  const base = chemin.split('?')[0];
  if (base === '/admin/classes/nouvelle') return 'Nouvelle classe';
  if (/^\/admin\/classes\/\d+$/.test(base)) return 'la fiche de la classe';
  if (base === '/admin/calendrier-scolaire') return 'Calendrier scolaire';
  if (base === '/admin/annees-scolaires') return 'Années scolaires';
  return 'la page précédente';
}

/** Lien vers l'écran « Modifier les périodes » (avec retour éventuel et période mise en avant). */
export function lienModifierPeriodes(anneeId, { retour, numero } = {}) {
  const q = new URLSearchParams();
  if (retour) q.set('retour', retour);
  if (numero) q.set('periode', String(numero));
  const suffixe = q.toString();
  return `/admin/annees-scolaires/${anneeId}/periodes${suffixe ? `?${suffixe}` : ''}`;
}

/** Lien vers la création d'une année (avec retour éventuel). */
export function lienNouvelleAnnee({ retour } = {}) {
  return `/admin/annees-scolaires/nouvelle${retour ? `?${new URLSearchParams({ retour }).toString()}` : ''}`;
}

const PREFIXE_BROUILLON = 'lgit:brouillon:';
const VALIDITE_BROUILLON_MS = 30 * 60 * 1000;

/** Sauvegarde un brouillon de formulaire (sessionStorage, 30 min). Silencieux si le stockage est indisponible. */
export function sauverBrouillon(cle, donnees) {
  try {
    sessionStorage.setItem(PREFIXE_BROUILLON + cle, JSON.stringify({ at: Date.now(), donnees }));
  } catch {
    /* stockage indisponible : la saisie ne sera simplement pas restaurée */
  }
}

/** Lit le brouillon (sans le supprimer : sûr sous StrictMode) ; null s'il est absent ou périmé. */
export function lireBrouillon(cle) {
  try {
    const brut = sessionStorage.getItem(PREFIXE_BROUILLON + cle);
    if (!brut) return null;
    const { at, donnees } = JSON.parse(brut);
    return Date.now() - at <= VALIDITE_BROUILLON_MS ? donnees : null;
  } catch {
    return null;
  }
}

export function effacerBrouillon(cle) {
  try {
    sessionStorage.removeItem(PREFIXE_BROUILLON + cle);
  } catch {
    /* rien à effacer */
  }
}

/** Extrait le contexte « date hors bornes » d'une erreur API, ou null. */
export function contexteHorsBornes(data) {
  return data?.code === 'date_hors_bornes_periode' && data.contexte ? data.contexte : null;
}
