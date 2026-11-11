import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/** DEP-01 : réalisations d'enfants déposées sur un stage (stage entier ou séance). */

/** `{ data: fichiers, meta: { taille_max_octets, quota_espace_octets, extensions, seances } }` */
export function useDepot(classeId) {
  return useApiQuery(() => client.get(`/classes/${classeId}/depot`).then((res) => res.data), [classeId], { enabled: Boolean(classeId) });
}

/**
 * Dépose un lot ; chaque fichier est contrôlé séparément côté serveur.
 * Renvoie `{ data: déposés, refuses: [{ nom, message }] }` (même en 422 si tout est refusé : l'erreur porte `response.data.refuses`).
 */
export function deposerFichiers(classeId, { fichiers, libelle, courseSessionId }) {
  const formData = new FormData();
  fichiers.forEach((f) => formData.append('fichiers[]', f));
  if (libelle) formData.append('libelle', libelle);
  if (courseSessionId) formData.append('course_session_id', courseSessionId);
  return client.post(`/classes/${classeId}/depot/fichiers`, formData).then((res) => res.data);
}

export function supprimerFichier(classeId, fichierId) {
  return client.delete(`/classes/${classeId}/depot/fichiers/${fichierId}`);
}

/** Téléchargement authentifié (le lien direct n'enverrait pas le jeton) puis enregistrement via un lien temporaire. */
export async function telechargerFichier(classeId, fichier) {
  const res = await client.get(`/classes/${classeId}/depot/fichiers/${fichier.id}/telecharger`, { responseType: 'blob' });
  const url = URL.createObjectURL(res.data);
  const a = document.createElement('a');
  a.href = url;
  a.download = fichier.nom;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

/** Liens parents du stage : `{ data: [{ id, libelle, statut, expire_le, nb_fichiers, nb_ouvertures, … }] }` (sans le lien en clair). */
export function usePartages(classeId) {
  return useApiQuery(() => client.get(`/classes/${classeId}/depot/partages`).then((res) => res.data.data), [classeId], { enabled: Boolean(classeId) });
}

/** Crée un lien pour une sélection de fichiers. Renvoie `{ data: partage, lien }` — le lien n'est affiché qu'ici ou via `lienPartage`. */
export function creerPartage(classeId, fichierIds) {
  return client.post(`/classes/${classeId}/depot/partages`, { fichier_ids: fichierIds }).then((res) => res.data);
}

/** Nouveau lien (7 jours), mêmes fichiers ; l'ancien cesse de fonctionner. */
export function regenererPartage(classeId, partageId) {
  return client.post(`/classes/${classeId}/depot/partages/${partageId}/regenerer`).then((res) => res.data);
}

export function revoquerPartage(classeId, partageId) {
  return client.post(`/classes/${classeId}/depot/partages/${partageId}/revoquer`).then((res) => res.data);
}

/** Lien d'un partage actif (410 s'il est expiré ou révoqué). */
export function lienPartage(classeId, partageId) {
  return client.get(`/classes/${classeId}/depot/partages/${partageId}/lien`).then((res) => res.data.lien);
}

/** Page parent (publique) : `{ libelle, expire_le, fichiers: [{ id, nom, taille }] }` ; 410 si le lien n'est plus valable. */
export function chargerDepotParent(token) {
  return client.get(`/public/depot/${token}`).then((res) => res.data.data);
}

export async function telechargerFichierParent(token, fichier) {
  const res = await client.get(`/public/depot/${token}/fichiers/${fichier.id}`, { responseType: 'blob' });
  const url = URL.createObjectURL(res.data);
  const a = document.createElement('a');
  a.href = url;
  a.download = fichier.nom;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
