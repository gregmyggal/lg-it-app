import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/**
 * Accès API de la tranche T2 : assignation des professeurs aux classes, remplacement ponctuel, « Mes classes ».
 * Un « contexte » désigne une assignation : { classeId, professeurId, cote } où `cote` indique par quelle
 * famille de routes passer (`classe` ou `professeur`) ; les deux appellent les mêmes services côté serveur.
 */

function racine({ classeId, professeurId, cote }) {
  return cote === 'professeur' ? `/professeurs/${professeurId}/classes` : `/classes/${classeId}/professeurs`;
}

function cible(ctx) {
  return `${racine(ctx)}/${ctx.cote === 'professeur' ? ctx.classeId : ctx.professeurId}`;
}

export function useAssignationsClasse(classeId) {
  return useApiQuery(() => client.get(`/classes/${classeId}/professeurs`).then((res) => res.data.data), [classeId]);
}

export function useAssignationsProfesseur(professeurId) {
  return useApiQuery(() => client.get(`/professeurs/${professeurId}/classes`).then((res) => res.data.data), [professeurId]);
}

/** Liste des professeurs pour les sélecteurs (la liste est servie telle quelle, sans enveloppe `data`). */
export function useProfesseursListe() {
  return useApiQuery(
    () =>
      client.get('/professeurs').then((res) => {
        const liste = Array.isArray(res.data) ? res.data : res.data.data;
        return liste.map((p) => ({ id: p.id, nom: `${p.prenom || ''} ${p.nom || ''}`.trim(), statut: p.statut }));
      }),
    [],
  );
}

/** Aperçu de propagation : n'écrit rien. Renvoie { sessions_assignees, ..., conflits }. */
export function apercuAssignation(ctx, payload) {
  return client.post(`${racine(ctx)}/apercu`, payload).then((res) => res.data.data);
}

/** Création / réactivation. Renvoie { data, recapitulatif }. */
export function assignerProfesseur(ctx, payload) {
  return client.post(racine(ctx), payload).then((res) => res.data);
}

export function modifierAssignation(ctx, payload) {
  return client.put(cible(ctx), payload).then((res) => res.data);
}

/** Termine l'assignation (la ligne reste pour l'historique). */
export function terminerAssignation(ctx, dateFin) {
  return client.delete(cible(ctx), { data: dateFin ? { date_fin: dateFin } : {} }).then((res) => res.data);
}

/** Lignes professeurs d'une session (remplacé, remplaçant, origine). */
export function lignesSession(sessionId) {
  return client.get(`/sessions/${sessionId}/professeurs`).then((res) => res.data.data);
}

/** Ajout ponctuel d'un professeur à une session (même passée). Renvoie { data: session, avertissements: [{ date, message }] }. */
export function ajouterProfesseurSession(sessionId, payload) {
  return client.post(`/sessions/${sessionId}/professeurs`, payload).then((res) => res.data);
}

/** Retire un professeur ajouté ponctuellement (409 si ses heures sont déjà encodées). */
export function retirerProfesseurSession(sessionId, professeurId) {
  return client.delete(`/sessions/${sessionId}/professeurs/${professeurId}`).then((res) => res.data.data);
}

/** Renvoie { data: session, avertissements: [{ date, message }] }. */
export function remplacerProfesseur(sessionId, payload) {
  return client.post(`/sessions/${sessionId}/remplacer`, payload).then((res) => res.data);
}

export function annulerRemplacement(sessionId, professeurRemplaceId) {
  return client.delete(`/sessions/${sessionId}/remplacements/${professeurRemplaceId}`).then((res) => res.data.data);
}

/** Portail professeur : { data: assignations, remplacements: sessions à venir }. */
export function useMesClasses(inclureTerminees) {
  return useApiQuery(
    () =>
      client
        .get('/mes-classes', { params: inclureTerminees ? { inclure_terminees: 1 } : {} })
        .then((res) => ({ classes: res.data.data, remplacements: res.data.remplacements || [] })),
    [inclureTerminees],
  );
}

export function useMesSessions(classeId) {
  return useApiQuery(() => client.get(`/mes-classes/${classeId}/sessions`).then((res) => res.data.data), [classeId]);
}
