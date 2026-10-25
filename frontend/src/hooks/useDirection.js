import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/** UI-M6 : « Aujourd'hui » direction, fiche séance, candidats, remplacements (unitaire et en lot atomique). */

export function useAccueilDirection() {
  return useApiQuery(() => client.get('/accueil-direction').then((res) => res.data), []);
}

export function useSeance(id) {
  return useApiQuery(() => client.get(`/sessions/${id}`).then((res) => res.data.data), [id]);
}

/** Professeurs actifs pouvant intervenir sur la séance, avec leurs conflits d'horaire. */
export function useCandidats(sessionId) {
  return useApiQuery(() => client.get(`/sessions/${sessionId}/candidats`).then((res) => res.data.data), [sessionId]);
}

export function remplacerSurSeance(sessionId, remplaceId, remplacantId) {
  return client
    .post(`/sessions/${sessionId}/remplacer`, { professeur_remplace_id: remplaceId, professeur_remplacant_id: remplacantId })
    .then((res) => res.data);
}

export function annulerRemplacement(sessionId, professeurId) {
  return client.delete(`/sessions/${sessionId}/remplacements/${professeurId}`).then((res) => res.data);
}

export function ajouterASeance(sessionId, professeurId) {
  return client.post(`/sessions/${sessionId}/professeurs`, { professeur_id: professeurId }).then((res) => res.data);
}

export function retirerDeSeance(sessionId, professeurId) {
  return client.delete(`/sessions/${sessionId}/professeurs/${professeurId}`).then((res) => res.data);
}

export function modifierSeance(sessionId, payload) {
  return client.put(`/sessions/${sessionId}`, payload).then((res) => res.data.data);
}

export function annulerSeance(sessionId, motif) {
  return client.post(`/sessions/${sessionId}/cancel`, { motif_annulation: motif }).then((res) => res.data.data);
}

export function chargerSeancesProfesseur(professeurId, du, au) {
  return client.get(`/professeurs/${professeurId}/seances`, { params: { du, au } }).then((res) => res.data.data);
}

export function apercuRemplacementLot(professeurId, sessionIds, remplacantId) {
  return client.post(`/professeurs/${professeurId}/remplacements/apercu`, { session_ids: sessionIds, remplacant_id: remplacantId }).then((res) => res.data.data);
}

export function appliquerRemplacementLot(professeurId, sessionIds, remplacantId) {
  return client.post(`/professeurs/${professeurId}/remplacements`, { session_ids: sessionIds, remplacant_id: remplacantId }).then((res) => res.data.data);
}
