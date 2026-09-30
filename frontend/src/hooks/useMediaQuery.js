import { useSyncExternalStore } from 'react';

/** Vrai tant que la requête média correspond (ex. '(max-width: 700px)'). */
export function useMediaQuery(requete) {
  return useSyncExternalStore(
    (notifier) => {
      const mql = window.matchMedia(requete);
      mql.addEventListener('change', notifier);
      return () => mql.removeEventListener('change', notifier);
    },
    () => window.matchMedia(requete).matches,
    () => false,
  );
}
