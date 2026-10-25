import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/** Page « Aujourd'hui » du professeur : prochaine séance, séances à encoder, progression du mois (une seule requête). */
export function useMonAccueil() {
  return useApiQuery(() => client.get('/mon-accueil').then((res) => res.data), []);
}
