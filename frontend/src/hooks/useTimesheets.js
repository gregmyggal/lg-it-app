import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/** Accès API de la tranche T3 : heures liées aux sessions, écran mensuel, vue directeur, validation en lot. */

/** Écran « Encoder mon mois » : sessions commencées du mois, heures libres, synthèse. */
export function useMonMois(annee, mois) {
  return useApiQuery(
    () => client.get('/timesheets/mon-mois', { params: { annee, mois } }).then((res) => res.data),
    [annee, mois],
  );
}

/** Crée une saisie (liée à une session ou libre). `professeur_id` n'est jamais envoyé : le serveur utilise l'utilisateur connecté. */
export function creerSaisie(payload) {
  return client.post('/timesheets', payload).then((res) => res.data);
}

export function modifierSaisie(id, payload) {
  return client.put(`/timesheets/${id}`, payload).then((res) => res.data);
}

export function supprimerSaisie(id) {
  return client.delete(`/timesheets/${id}`);
}

/** Soumet le mois : crée les sessions incluses puis passe tous les brouillons du mois en « soumis » (atomique). */
export function soumettreMois(payload) {
  return client.post('/timesheets/soumettre-mois', payload).then((res) => res.data);
}

/** Liste des saisies (staff : toutes, filtrables ; professeur : les siennes). */
export function useListeSaisies(filtres, { enabled = true } = {}) {
  const params = Object.fromEntries(Object.entries(filtres).filter(([, v]) => v !== '' && v !== null && v !== undefined));
  return useApiQuery(() => client.get('/timesheets', { params }).then((res) => res.data), [JSON.stringify(params)], { enabled });
}

/** Vue directeur : sessions passées où un professeur attendu n'a encodé aucune heure. */
export function useSessionsSansHeures(filtres, { enabled = true } = {}) {
  const params = Object.fromEntries(Object.entries(filtres).filter(([, v]) => v !== '' && v !== null && v !== undefined));
  return useApiQuery(() => client.get('/timesheets/sessions-sans-heures', { params }).then((res) => res.data.data), [JSON.stringify(params)], { enabled });
}

/** Proposition de lissage (existante) pour une saisie en dépassement du plafond journalier. */
export function proposerLissage(id, annee, mois) {
  return client.get(`/timesheets/${id}/propose-lissage`, { params: { year: annee, month: mois } }).then((res) => res.data);
}

/** Validation en lot : applique les lissages demandés puis confirme, en une transaction. */
export function validerLot(payload) {
  return client.post('/timesheets/valider-lot', payload).then((res) => res.data);
}

/** Soumet une saisie en brouillon (brouillon → soumis). */
export function soumettreSaisie(id) {
  return client.post(`/timesheets/${id}/submit`).then((res) => res.data);
}

/** TS-01 T1 : adaptation d'une saisie par le staff (motif obligatoire) et historique. */
export function adapterSaisie(id, payload) {
  return client.post(`/timesheets/${id}/adapter`, payload).then((res) => res.data);
}

export function chargerHistoriqueSaisie(id) {
  return client.get(`/timesheets/${id}/historique`).then((res) => res.data.data);
}

/** TS-01 T2 : synthèse du mois par professeur (statut calculé, totaux, alertes). */
export function useSyntheseMois(annee, mois) {
  return useApiQuery(
    () => client.get('/timesheets/mois-synthese', { params: { annee, mois } }).then((res) => res.data),
    [annee, mois],
  );
}

/** Plafond journalier (€) paramétré pour l'année civile (TS-00) ; `null` tant qu'il n'est pas chargé. */
export function usePlafondJournalier(annee) {
  const q = useApiQuery(() => client.get(`/timesheet-parametres/${annee}`).then((res) => res.data.data.plafond_journalier_eur), [annee]);
  return q.data ?? null;
}

/** TS-01 T3 : détail d'un professeur pour un mois (saisies, jauge par jour, historique). */
export function useDetailMois(professeurId, annee, mois) {
  return useApiQuery(
    () => client.get(`/professeurs/${professeurId}/timesheets-mois`, { params: { annee, mois } }).then((res) => res.data),
    [professeurId, annee, mois],
  );
}

/** Aperçu avant/après d'un lissage : `mode` « auto » (proposition) ou « manuel » (déplacements fournis). */
export function apercuLissage(professeurId, payload) {
  return client.post(`/professeurs/${professeurId}/timesheets-mois/lissage/apercu`, payload).then((res) => res.data);
}

export function appliquerLissage(professeurId, payload) {
  return client.post(`/professeurs/${professeurId}/timesheets-mois/lissage`, payload).then((res) => res.data);
}

/** TS-01 T4 : reconfirmation du mois par le professeur (ajustements, contestation, signature possible). */
export function useMaConfirmation(annee, mois) {
  return useApiQuery(
    () => client.get('/timesheets/ma-confirmation', { params: { annee, mois } }).then((res) => res.data),
    [annee, mois],
  );
}

export function signerMois(professeurId, annee, mois) {
  return client.post('/timesheets/sign-month', { professeur_id: professeurId, year: annee, month: mois }).then((res) => res.data);
}

export function contesterMois(payload) {
  return client.post('/timesheets/contester-mois', payload).then((res) => res.data);
}

/** Staff : répond à la contestation (les saisies contestées repassent en revue). */
export function traiterContestation(professeurId, payload) {
  return client.post(`/professeurs/${professeurId}/timesheets-mois/traiter-contestation`, payload).then((res) => res.data);
}

/**
 * TS-01 T5 : fiche de défraiement PDF. Les réponses binaires (`blob`) renvoient leurs erreurs JSON sous forme de Blob :
 * on les relit pour que `getErrorMessage` / `getErrorData` fonctionnent comme pour les autres appels.
 */
async function appelBlob(requete) {
  try {
    return await requete();
  } catch (err) {
    if (err.response?.data instanceof Blob) {
      try {
        err.response.data = JSON.parse(await err.response.data.text());
      } catch {
        /* réponse non JSON : on garde l'erreur telle quelle */
      }
    }
    throw err;
  }
}

export function genererPdf(professeurId, annee, mois) {
  return client.post(`/professeurs/${professeurId}/timesheet-pdfs`, { annee, mois }).then((res) => res.data.data);
}

export function apercuPdf(professeurId, annee, mois) {
  return appelBlob(() => client.get(`/professeurs/${professeurId}/timesheet-pdfs/apercu`, { params: { annee, mois }, responseType: 'blob' })).then((res) => res.data);
}

export function telechargerPdf(id) {
  return appelBlob(() => client.get(`/timesheet-pdfs/${id}/telecharger`, { responseType: 'blob' })).then((res) => res.data);
}

/** Lot : renvoie le zip des fiches ; tout ou rien (422 + `bloquants` si un professeur n'est pas prêt). */
export function genererPdfLot(professeurIds, annee, mois) {
  return appelBlob(() => client.post('/timesheets-mois/pdf-lot', { annee, mois, professeur_ids: professeurIds }, { responseType: 'blob' })).then((res) => res.data);
}

/** Admin : rouvre un mois généré (motif obligatoire) ; la génération suivante crée une nouvelle version. */
export function deverrouillerMois(professeurId, payload) {
  return client.post(`/professeurs/${professeurId}/timesheets-mois/deverrouiller`, payload).then((res) => res.data);
}
