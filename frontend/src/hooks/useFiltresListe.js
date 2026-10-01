import { useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';

/**
 * Filtres d'une liste conservés dans l'URL (`replace` : pas d'empilement d'historique), comme l'écran Classes.
 * Une valeur égale au défaut n'apparaît pas dans l'URL ; une valeur hors liste autorisée est ignorée.
 *
 * @param {Record<string, string>} defauts  ex. { q: '', statut: 'actif', acces: '' }
 * @param {Record<string, string[]>} [autorisees]  valeurs permises par filtre (les autres filtres sont libres)
 */
export function useFiltresListe(defauts, autorisees = {}) {
  const [params, setParams] = useSearchParams();

  const valeurs = Object.fromEntries(Object.keys(defauts).map((cle) => {
    const brute = params.get(cle);
    const permise = brute !== null && (!autorisees[cle] || autorisees[cle].includes(brute));
    return [cle, permise ? brute : defauts[cle]];
  }));

  const majFiltre = useCallback((cle, valeur) => {
    setParams((precedent) => {
      const suivant = new URLSearchParams(precedent);
      if (valeur === defauts[cle] || valeur === '' || valeur == null) suivant.delete(cle);
      else suivant.set(cle, valeur);
      return suivant;
    }, { replace: true });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [setParams, JSON.stringify(defauts)]);

  const reinitialiser = useCallback(() => {
    setParams((precedent) => {
      const suivant = new URLSearchParams(precedent);
      Object.keys(defauts).forEach((cle) => suivant.delete(cle));
      return suivant;
    }, { replace: true });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [setParams, JSON.stringify(defauts)]);

  const nbActifs = Object.keys(defauts).filter((cle) => cle !== 'q' && valeurs[cle] !== defauts[cle]).length;
  const estParDefaut = Object.keys(defauts).every((cle) => valeurs[cle] === defauts[cle]);

  return { valeurs, majFiltre, reinitialiser, nbActifs, estParDefaut };
}
