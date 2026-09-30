/**
 * Lecture des erreurs renvoyées par l'API ({ message, errors }).
 * Les messages métier viennent du serveur (en français) et sont affichés tels quels.
 */

export function getStatus(err) {
  return err?.response?.status ?? null;
}

export function getErrorMessage(err, fallback = 'Une erreur est survenue. Réessayez dans un instant.') {
  if (!err?.response) {
    return 'Impossible de joindre le serveur. Vérifiez votre connexion puis réessayez.';
  }
  const { status, data } = err.response;
  if (status >= 500) {
    return 'Le serveur a rencontré un problème. Réessayez dans un instant ; si cela persiste, contactez un administrateur.';
  }
  return data?.message || fallback;
}

/** Erreurs de validation par champ : { date: 'Cette date est…' } (premier message de chaque champ). */
export function getFieldErrors(err) {
  const errors = err?.response?.data?.errors;
  if (!errors || typeof errors !== 'object') return {};
  return Object.fromEntries(
    Object.entries(errors).map(([champ, messages]) => [
      champ,
      Array.isArray(messages) ? messages[0] : String(messages),
    ]),
  );
}

export function getErrorData(err) {
  return err?.response?.data ?? {};
}
