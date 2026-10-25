/**
 * Illustrations d'états vides (UI-M8) : SVG en ligne, sans texte, aux couleurs du thème (clair et sombre).
 * Décoratives (`aria-hidden`) : le titre et le texte de l'état vide portent le sens.
 */
const DESSINS = {
  ok: (
    <>
      <circle cx="48" cy="48" r="30" className="ill-fond" />
      <circle cx="48" cy="48" r="30" className="ill-trait" />
      <path d="m34 49 10 10 19-21" className="ill-accent" />
    </>
  ),
  calendrier: (
    <>
      <rect x="18" y="24" width="60" height="52" rx="8" className="ill-fond" />
      <rect x="18" y="24" width="60" height="52" rx="8" className="ill-trait" />
      <path d="M18 40h60M34 16v14M62 16v14" className="ill-trait" />
      <circle cx="36" cy="54" r="3.5" className="ill-plein" />
      <circle cx="48" cy="54" r="3.5" className="ill-plein" />
      <circle cx="60" cy="54" r="3.5" className="ill-accent-plein" />
    </>
  ),
  classes: (
    <>
      <path d="M12 38 48 20l36 18-36 18z" className="ill-fond" />
      <path d="M12 38 48 20l36 18-36 18zM28 48v14c12 9 28 9 40 0V48" className="ill-trait" />
      <path d="M84 38v18" className="ill-accent" />
    </>
  ),
  personnes: (
    <>
      <circle cx="38" cy="38" r="12" className="ill-fond" />
      <circle cx="38" cy="38" r="12" className="ill-trait" />
      <path d="M14 76c2-14 11-20 24-20s22 6 24 20" className="ill-trait" />
      <path d="M62 28a11 11 0 0 1 0 22M70 58c8 3 12 9 13 18" className="ill-accent" />
    </>
  ),
  livre: (
    <>
      <path d="M20 22h26c4 0 6 2 6 6v46c0-4-2-6-6-6H20z" className="ill-fond" />
      <path d="M76 22H50c-4 0-6 2-6 6v46c0-4 2-6 6-6h26z" className="ill-fond" />
      <path d="M20 22h26c4 0 6 2 6 6v46c0-4-2-6-6-6H20zM76 22H50" className="ill-trait" />
      <path d="M52 28c0-4 2-6 6-6M76 22v46H58" className="ill-trait" />
      <path d="M30 36h14M30 46h14" className="ill-accent" />
    </>
  ),
  boite: (
    <>
      <path d="M16 52 26 28h44l10 24v18a6 6 0 0 1-6 6H22a6 6 0 0 1-6-6z" className="ill-fond" />
      <path d="M16 52 26 28h44l10 24v18a6 6 0 0 1-6 6H22a6 6 0 0 1-6-6zM16 52h22l4 8h12l4-8h22" className="ill-trait" />
      <path d="M48 12v10M36 16l4 8M60 16l-4 8" className="ill-accent" />
    </>
  ),
  recherche: (
    <>
      <circle cx="42" cy="42" r="22" className="ill-fond" />
      <circle cx="42" cy="42" r="22" className="ill-trait" />
      <path d="m58 58 20 20" className="ill-accent" />
      <path d="M32 42h20" className="ill-trait" />
    </>
  ),
  lien: (
    <>
      <path d="M40 56a14 14 0 0 0 20 0l12-12a14 14 0 0 0-20-20l-4 4" className="ill-trait" />
      <path d="M56 40a14 14 0 0 0-20 0L24 52a14 14 0 0 0 20 20l4-4" className="ill-accent" />
    </>
  ),
  horloge: (
    <>
      <circle cx="48" cy="48" r="30" className="ill-fond" />
      <circle cx="48" cy="48" r="30" className="ill-trait" />
      <path d="M48 30v19l12 8" className="ill-accent" />
    </>
  ),
};

export default function Illustration({ nom, taille = 88 }) {
  if (!DESSINS[nom]) return null;
  return (
    <svg className="illustration" viewBox="0 0 96 96" width={taille} height={taille} aria-hidden="true" focusable="false">
      {DESSINS[nom]}
    </svg>
  );
}
