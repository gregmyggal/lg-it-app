import { useState } from 'react';
import { appliquerTheme, lireTheme } from '../utils/theme';

const CHOIX = [
  { mode: 'auto', label: 'Auto', aide: 'Suivre le réglage du système' },
  { mode: 'light', label: '☀ Clair', aide: 'Thème clair' },
  { mode: 'dark', label: '☾ Nuit', aide: 'Thème nuit' },
];

/** Bascule Auto / Clair / Nuit du portail, mémorisée sur ce navigateur. */
export default function ThemeToggle() {
  const [mode, setMode] = useState(lireTheme);

  function choisir(m) {
    appliquerTheme(m);
    setMode(m);
  }

  return (
    <div className="theme-toggle" role="group" aria-label="Thème d'affichage">
      {CHOIX.map((c) => (
        <button key={c.mode} type="button" aria-pressed={mode === c.mode} title={c.aide} onClick={() => choisir(c.mode)}>
          {c.label}
        </button>
      ))}
    </div>
  );
}
