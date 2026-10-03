import { Link } from 'react-router-dom';
import { lienModifierPeriodes } from '../../utils/annees';

/**
 * Lien contextuel « Modifier les dates » vers l'écran de modification des périodes d'une année.
 * Affiché seulement si l'utilisateur peut modifier l'année (`can.update`) et qu'elle n'est pas archivée.
 *
 * @param {object} props
 * @param {{id:number,libelle:string,statut?:string,can?:{update?:boolean}}} props.annee
 * @param {number} props.numero période concernée (1 ou 2)
 * @param {string} props.retour chemin de la page d'origine (retour automatique après enregistrement)
 * @param {() => void} [props.avantNavigation] appelé au clic (ex. sauvegarde du brouillon)
 * @param {React.ReactNode} [props.children] texte du lien
 */
export default function LienModifierDates({ annee, numero, retour, avantNavigation, children = 'Modifier les dates' }) {
  if (!annee?.can?.update || annee.statut === 'archivee') return null;
  return (
    <Link
      to={lienModifierPeriodes(annee.id, { retour, numero })}
      onClick={() => avantNavigation?.()}
      aria-label={`Modifier les dates de la période ${numero} (${annee.libelle})`}
    >
      {children}
    </Link>
  );
}
