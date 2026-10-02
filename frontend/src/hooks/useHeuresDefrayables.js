import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/**
 * DEF-01 : durée de séance par défaut et heures défrayables applicables à un cours pour une année civile
 * (`source` : « cours » si le cours a sa valeur, « defaut » sinon). Réservé à la direction et à l'admin.
 */
export function useHeuresDefrayables(coursId, annee) {
  return useApiQuery(
    () => client.get('/heures-defrayables', { params: { annee, cours_id: coursId || undefined } }).then((res) => res.data.data),
    [coursId, annee],
  );
}

/** Simule l'effet d'une valeur sur le plafond journalier, sans rien enregistrer (`portee` : « global » ou « cours »). */
export function simulerImpactDefrayables(payload) {
  return client.post('/heures-defrayables/impact', payload).then((res) => res.data.data);
}
