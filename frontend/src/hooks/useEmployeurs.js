import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/** EMP-01 : employeur d'un animateur, mois par mois (entités, frise, édition, lot, historique). Le serveur reste l'autorité. */

/** Entités employeurs (staff) ; `can.update` = gestion réservée à l'admin. */
export function useEmployeurs() {
  return useApiQuery(() => client.get('/employeurs').then((res) => res.data.data), []);
}

export function creerEmployeur(payload) {
  return client.post('/employeurs', payload).then((res) => res.data.data);
}

export function modifierEmployeur(id, payload) {
  return client.put(`/employeurs/${id}`, payload).then((res) => res.data.data);
}

/** 12 mois d'une année civile pour un animateur : `{ mois: [{ mois, employeur, source, verrouille, modifiable, version… }] }`. */
export function useEmployeursMois(professeurId, annee) {
  return useApiQuery(
    () => client.get(`/professeurs/${professeurId}/employeurs-mois`, { params: { annee } }).then((res) => res.data),
    [professeurId, annee],
  );
}

export function useHistoriqueEmployeurs(professeurId, annee) {
  return useApiQuery(
    () => client.get(`/professeurs/${professeurId}/employeurs-mois/historique`, { params: { annee } }).then((res) => res.data.data),
    [professeurId, annee],
  );
}

/** `version` = celle lue par l'écran (verrou optimiste, 409 si périmée). */
export function definirEmployeurMois(professeurId, annee, mois, payload) {
  return client.put(`/professeurs/${professeurId}/employeurs-mois/${annee}/${mois}`, payload).then((res) => res.data.data);
}

/** Lot : `{ annee, mois, professeur_ids, employeur_id | reprendre_precedent: true, motif }` → `{ appliques, ignores }`. */
export function definirEmployeurLot(payload) {
  return client.post('/employeurs-mois/lot', payload).then((res) => res.data);
}
