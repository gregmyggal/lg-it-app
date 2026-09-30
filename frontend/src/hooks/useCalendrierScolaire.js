import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/**
 * Entrées du calendrier scolaire d'une année (les entrées FWB masquées sont exclues par l'API).
 * @param {number|string|null} anneeId
 * @param {{type?: string, source?: string}} [filtres]
 */
export function useCalendrierScolaire(anneeId, filtres = {}) {
  const params = Object.fromEntries(Object.entries(filtres).filter(([, v]) => v));
  return useApiQuery(
    () =>
      client
        .get(`/annees-scolaires/${anneeId}/calendrier`, { params })
        .then((res) => res.data.data),
    [anneeId, JSON.stringify(params)],
    { enabled: Boolean(anneeId) },
  );
}

export function creerEntreeCalendrier(anneeId, payload) {
  return client.post(`/annees-scolaires/${anneeId}/calendrier`, payload).then((res) => res.data.data);
}

export function modifierEntreeCalendrier(id, payload) {
  return client.put(`/calendrier-scolaire/${id}`, payload).then((res) => res.data.data);
}

/** FWB : masquée ; École : supprimée. Le message de l'API dit ce qui s'est passé. */
export function supprimerEntreeCalendrier(id) {
  return client.delete(`/calendrier-scolaire/${id}`).then((res) => res.data);
}
