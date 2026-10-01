import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/** Accès API de la tranche T4 : liens d'un cours (généraux / par séance), historique, restauration. */

/** `{ cours, classes, data: liens, peut_modifier, peut_voir_historique, anciennes_ressources }` */
export function useLiensCours(coursId) {
  // Attend l'identifiant du cours (ex. chargé avec la classe) avant d'interroger l'API.
  return useApiQuery(() => client.get(`/cours/${coursId}/liens`).then((res) => res.data), [coursId], { enabled: Boolean(coursId) });
}

export function creerLien(coursId, payload) {
  return client.post(`/cours/${coursId}/liens`, payload).then((res) => res.data.data);
}

/** `payload.version` est obligatoire : le serveur répond 409 (+ `lien` actuel) si elle est dépassée. */
export function modifierLien(id, payload) {
  return client.put(`/liens/${id}`, payload).then((res) => res.data.data);
}

/** « Supprimer » = archiver. Renvoie `{ historique_id }` (sert à annuler). */
export function archiverLien(id) {
  return client.delete(`/liens/${id}`).then((res) => res.data);
}

/** Réordonne UNE portée : `seanceNumero` null = généraux ; `ids` = tous les liens de la portée dans le nouvel ordre. */
export function ordonnerLiens(coursId, seanceNumero, ids) {
  return client.put(`/cours/${coursId}/liens/ordre`, { seance_numero: seanceNumero, ids }).then((res) => res.data.data);
}

/** Versions des 6 derniers mois, filtrables (`portee`: `generaux` ou n° de séance, `auteur`, `action`). */
export function useHistoriqueLiens(coursId, filtres, page = 1) {
  const params = { page, ...Object.fromEntries(Object.entries(filtres).filter(([, v]) => v !== '' && v !== null && v !== undefined)) };
  return useApiQuery(
    () => client.get(`/cours/${coursId}/liens/historique`, { params }).then((res) => res.data),
    [coursId, JSON.stringify(params)],
  );
}

/** Restaure l'état précédant une version ; 409 `modifie_depuis` → renvoyer avec `confirmer: true` ; 410 si purgée. */
export function restaurerVersion(versionId, confirmer = false) {
  return client.post(`/liens-versions/${versionId}/restaurer`, { confirmer }).then((res) => res.data.data);
}

export function reprendreRessources(coursId) {
  return client.post(`/cours/${coursId}/liens/reprendre-ressources`).then((res) => res.data);
}
