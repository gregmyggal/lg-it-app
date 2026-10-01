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
