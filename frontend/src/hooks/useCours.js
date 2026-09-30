import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/** Catalogue des cours (pour les sélecteurs). */
export function useCours() {
  return useApiQuery(() => client.get('/cours').then((res) => res.data), []);
}
