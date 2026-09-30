import client from '../api/client';
import { useApiQuery } from './useApiQuery';
import { aujourdhuiISO } from '../utils/dates';

/** Toutes les années scolaires (la plus récente d'abord), avec leurs périodes et `can`. */
export function useAnneesScolaires() {
  return useApiQuery(() => client.get('/annees-scolaires').then((res) => res.data.data), []);
}

export function creerAnneeScolaire(payload) {
  return client.post('/annees-scolaires', payload).then((res) => res.data.data);
}

export function importerCalendrierFwb(anneeId) {
  return client.post(`/annees-scolaires/${anneeId}/calendrier/import-fwb`).then((res) => res.data);
}

/** Année proposée par défaut : celle qui couvre aujourd'hui, sinon la plus récente (l'API les trie ainsi). */
export function anneeParDefaut(annees) {
  return annees.find((a) => a.date_debut <= aujourdhuiISO() && aujourdhuiISO() <= a.date_fin) || annees[0] || null;
}
