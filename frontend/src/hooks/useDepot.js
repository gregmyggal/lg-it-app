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
