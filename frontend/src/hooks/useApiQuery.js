import { useCallback, useEffect, useRef, useState } from 'react';
import { getErrorMessage } from '../api/errors';

/**
 * Chargement de données avec les états de l'écran : chargement, erreur, données.
 * `fetcher` doit renvoyer une promesse (déjà « dépliée » : les données, pas la réponse axios).
 * Recharge quand `deps` change ou via `reload()`.
 *
 * @param {() => Promise<*>} fetcher
 * @param {Array} deps
 * @param {{enabled?: boolean, errorMessage?: string}} [options]
 */
export function useApiQuery(fetcher, deps, { enabled = true, errorMessage } = {}) {
  const [state, setState] = useState({ data: null, loading: enabled, error: null });
  const [tick, setTick] = useState(0);
  const fetcherRef = useRef(fetcher);
  fetcherRef.current = fetcher;

  useEffect(() => {
    if (!enabled) {
      setState({ data: null, loading: false, error: null });
      return undefined;
    }
    let annule = false;
    setState((prev) => ({ ...prev, loading: true, error: null }));
    fetcherRef
      .current()
      .then((data) => {
        if (!annule) setState({ data, loading: false, error: null });
      })
      .catch((err) => {
        if (!annule) {
          setState({ data: null, loading: false, error: errorMessage || getErrorMessage(err) });
        }
      });
    return () => {
      annule = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, enabled, tick]);

  const reload = useCallback(() => setTick((t) => t + 1), []);

  // Avant le premier chargement (ex. requête activée après coup), on se considère déjà « en chargement ».
  const enAttente = enabled && state.data === null && state.error === null;
  return { ...state, loading: state.loading || enAttente, reload };
}
