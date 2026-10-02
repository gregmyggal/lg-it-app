/** Thème du portail (UI-01 T5) : « auto » suit le système, « light » / « dark » le forcent. Mémorisé sur ce navigateur. */
export const THEME_KEY = 'lgit_theme';
export const THEME_MODES = ['auto', 'light', 'dark'];

export function lireTheme() {
  try {
    const t = localStorage.getItem(THEME_KEY);
    return t === 'light' || t === 'dark' ? t : 'auto';
  } catch {
    return 'auto';
  }
}

export function appliquerTheme(mode) {
  const racine = document.documentElement;
  if (mode === 'light' || mode === 'dark') racine.setAttribute('data-theme', mode);
  else racine.removeAttribute('data-theme');
  try {
    if (mode === 'auto') localStorage.removeItem(THEME_KEY);
    else localStorage.setItem(THEME_KEY, mode);
  } catch {
    /* stockage indisponible (navigation privée) : le choix vaut pour cette page seulement */
  }
}
