import { useMediaQuery } from '../hooks/useMediaQuery';

/**
 * Section repliable sur smartphone (UI-M7) : `<details>` natif avec un titre, ouvert ou non par défaut.
 * Sur ordinateur, le contenu s'affiche tel quel (ses propres titres suffisent) : même contenu, autre conteneur.
 */
export default function Pliable({ titre, ouvert = false, children }) {
  const mobile = useMediaQuery('(max-width: 860px)');
  if (!mobile) return children;
  return (
    <details className="pliable" open={ouvert}>
      <summary>{titre}</summary>
      <div className="pliable__corps">{children}</div>
    </details>
  );
}
