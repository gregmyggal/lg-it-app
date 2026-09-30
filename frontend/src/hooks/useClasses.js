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

export function modifierClasse(id, payload) {
  return client.put(`/classes/${id}`, payload).then((res) => res.data.data);
}

export function supprimerClasse(id) {
  return client.delete(`/classes/${id}`);
}

export function deplacerSession(sessionId, payload) {
  return client.put(`/sessions/${sessionId}`, payload).then((res) => res.data.data);
}

export function annulerSession(sessionId, motif) {
  return client
    .post(`/sessions/${sessionId}/cancel`, { motif_annulation: motif })
    .then((res) => res.data.data);
}

export function creerBis(classeId, payload) {
  return client.post(`/classes/${classeId}/sessions/bis`, payload).then((res) => res.data.data);
}
