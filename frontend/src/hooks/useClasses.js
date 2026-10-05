import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/** Liste des classes filtrée (année, période, cours, jour). Attend une année pour charger. */
export function useClasses(filtres, { enabled = true } = {}) {
  const params = Object.fromEntries(
    Object.entries(filtres).filter(([, v]) => v !== '' && v !== null && v !== undefined),
  );
  return useApiQuery(
    () => client.get('/classes', { params: { per_page: 100, ...params } }).then((res) => res.data.data),
    [JSON.stringify(params)],
    { enabled },
  );
}

export function useClasse(id) {
  return useApiQuery(() => client.get(`/classes/${id}`).then((res) => res.data.data), [id]);
}

/** Toutes les sessions d'une classe (bis et annulées incluses). */
export function useClasseSessions(id) {
  return useApiQuery(() => client.get(`/classes/${id}/sessions`).then((res) => res.data.data), [id]);
}

export function apercuClasse(payload) {
  return client.post('/classes/apercu', payload).then((res) => res.data.data);
}

export function creerClasse(payload) {
  return client.post('/classes', payload).then((res) => res.data.data);
}

/** CLS-07 : formulaire « Nouvelle classe » pré-rempli depuis une classe existante (dates transposées sur `jour_semaine`). */
export function propositionDuplication(classeId, params = {}) {
  return client.get(`/classes/${classeId}/duplication`, { params }).then((res) => res.data.data);
}

export function modifierClasse(id, payload) {
  return client.put(`/classes/${id}`, payload).then((res) => res.data.data);
}

export function supprimerClasse(id) {
  return client.delete(`/classes/${id}`);
}

/** Session renvoyée + `avertissements` (non bloquants, ex. « Cette date est après la fin de la période 1 »). */
function avecAvertissements(res) {
  return { ...res.data.data, avertissements: res.data.avertissements || res.data.data?.avertissements || [] };
}

export function deplacerSession(sessionId, payload) {
  return client.put(`/sessions/${sessionId}`, payload).then((res) => ({ ...avecAvertissements(res), replanification: res.data.replanification || null }));
}

export function apercuDeplacement(sessionId, payload) {
  return client.post(`/sessions/${sessionId}/deplacement/apercu`, payload).then((res) => res.data.data);
}

export function annulerSession(sessionId, motif) {
  return client
    .post(`/sessions/${sessionId}/cancel`, { motif_annulation: motif })
    .then((res) => res.data.data);
}

export function creerBis(classeId, payload) {
  return client.post(`/classes/${classeId}/sessions/bis`, payload).then(avecAvertissements);
}

/** Périodes d'une classe (CLS-02) : toutes les réponses d'écriture renvoient la `Classe` complète. */
export function apercuPeriode(classeId, payload) {
  return client.post(`/classes/${classeId}/periodes/apercu`, payload).then((res) => res.data.data);
}

export function ajouterPeriode(classeId, payload) {
  return client.post(`/classes/${classeId}/periodes`, payload).then((res) => res.data.data);
}

export function changerCoursPeriode(classeId, classePeriodeId, coursId) {
  return client.put(`/classes/${classeId}/periodes/${classePeriodeId}`, { cours_id: coursId }).then((res) => res.data.data);
}

export function supprimerPeriode(classeId, classePeriodeId) {
  return client.delete(`/classes/${classeId}/periodes/${classePeriodeId}`);
}

export function annulerPeriode(classeId, classePeriodeId, motif) {
  return client.post(`/classes/${classeId}/periodes/${classePeriodeId}/annuler`, { motif }).then((res) => res.data.data);
}

/** Historique des changements de cours d'une période (du plus récent au plus ancien). */
export function useHistoriqueCoursPeriode(classeId, classePeriodeId) {
  return useApiQuery(
    () => client.get(`/classes/${classeId}/periodes/${classePeriodeId}/historique-cours`).then((res) => res.data.data),
    [classeId, classePeriodeId],
  );
}
