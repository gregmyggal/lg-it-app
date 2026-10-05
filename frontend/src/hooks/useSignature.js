import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/**
 * SIG-01 : signature du professeur (image réutilisable). `specimen` = null tant qu'aucune n'est enregistrée
 * (enveloppée : pour useApiQuery, `data === null` signifie « en cours de chargement »).
 */
export function useMaSignature() {
  const q = useApiQuery(() => client.get('/ma-signature').then((res) => ({ specimen: res.data.data })), []);
  return { ...q, specimen: q.data?.specimen ?? null };
}

export function enregistrerMaSignature(payload) {
  return client.put('/ma-signature', payload).then((res) => res.data.data);
}

/** Public : vérifie un identifiant « SIG-… » imprimé sur une fiche (404 = introuvable). */
export function verifierSignature(publicId) {
  return client.get(`/signatures/${encodeURIComponent(publicId)}/verification`).then((res) => res.data.data);
}

export function useSignatureParametres() {
  return useApiQuery(() => client.get('/signature-parametres').then((res) => res.data.data), []);
}

export function enregistrerSignatureParametres(payload) {
  return client.put('/signature-parametres', payload).then((res) => res.data.data);
}
