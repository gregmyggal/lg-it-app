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

/** Dates proposées pour une nouvelle année (FWB si importé, sinon par défaut). `libelle` optionnel. */
export function proposerAnnee(libelle) {
  return client
    .get('/annees-scolaires/proposition', { params: libelle ? { libelle } : {} })
    .then((res) => res.data.data);
}

/** Modifie une année : `version` (= `updated_at` lu) obligatoire dès que dates/périodes/libellé sont envoyés. */
export function modifierAnnee(anneeId, payload) {
  return client.put(`/annees-scolaires/${anneeId}`, payload).then((res) => res.data);
}

/** Aperçu d'impact (lecture seule) de nouvelles dates de périodes : [{numero, date_debut, date_fin}]. */
export function apercuImpactAnnee(anneeId, periodes) {
  return client.post(`/annees-scolaires/${anneeId}/apercu-impact`, { periodes }).then((res) => res.data.data);
}

export function archiverAnnee(anneeId) {
  return client.post(`/annees-scolaires/${anneeId}/archiver`).then((res) => res.data.data ?? res.data);
}

export function reactiverAnnee(anneeId) {
  return client.post(`/annees-scolaires/${anneeId}/reactiver`).then((res) => res.data.data ?? res.data);
}

export function activerAnnee(anneeId) {
  return client.put(`/annees-scolaires/${anneeId}`, { statut: 'active' }).then((res) => res.data.data ?? res.data);
}

export function supprimerAnnee(anneeId) {
  return client.delete(`/annees-scolaires/${anneeId}`);
}

export function importerCalendrierFwb(anneeId) {
  return client.post(`/annees-scolaires/${anneeId}/calendrier/import-fwb`).then((res) => res.data);
}

/** Année proposée par défaut : celle qui couvre aujourd'hui, sinon la plus récente (l'API les trie ainsi). */
export function anneeParDefaut(annees) {
  return annees.find((a) => a.date_debut <= aujourdhuiISO() && aujourdhuiISO() <= a.date_fin) || annees[0] || null;
}
