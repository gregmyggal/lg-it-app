import { flushSync } from 'react-dom';

/** Le navigateur sait animer un changement d'écran (View Transitions) et l'utilisateur ne demande pas de réduire les animations. */
export function transitionsPossibles() {
  return typeof document !== 'undefined'
    && typeof document.startViewTransition === 'function'
    && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Exécute une navigation avec une transition d'écran native (UI-M8) ; sans support ou si les animations sont réduites,
 * la navigation est immédiate comme avant. `flushSync` garantit que le nouvel écran est dessiné dans la transition.
 */
export function avecTransition(naviguer) {
  if (!transitionsPossibles()) {
    naviguer();
    return;
  }
  const transition = document.startViewTransition(() => {
    flushSync(naviguer);
  });
  // Une transition interrompue par une autre navigation rapide n'est pas une erreur : on évite le rejet non géré.
  transition.ready.catch(() => {});
  transition.finished.catch(() => {});
}
