import { useRef } from 'react';
import StatutBadge from '../ui/StatutBadge';
import EmployeurBadge from '../employeurs/EmployeurBadge';
import { STATUTS_MOIS_PROF } from '../../utils/statuts';
import { formatEuros, formatHeures } from '../../utils/format';

const DELAI_APPUI_LONG_MS = 450;
const TOLERANCE_PX = 8;

/**
 * Un professeur dans la synthèse du mois, sur smartphone (UI-M5) : nom, employeur, statut, alertes, montant.
 * Tap = ouvrir le détail ; en mode sélection, tap = cocher. L'appui long active la sélection (le bouton « Sélectionner »
 * de la barre d'outils reste l'alternative accessible).
 *
 * @param {object} props
 * @param {object} props.ligne une entrée de `professeurs` de `GET /timesheets/mois-synthese`
 * @param {boolean} props.modeSelection
 * @param {boolean} props.selectionnee
 * @param {(id: number) => void} props.onOuvrir
 * @param {(id: number) => void} props.onBasculer
 * @param {(id: number) => void} props.onAppuiLong
 */
export default function CarteProfesseurMois({ ligne: l, modeSelection, selectionnee, onOuvrir, onBasculer, onAppuiLong }) {
  const minuteur = useRef(null);
  const depart = useRef(null);
  const declenche = useRef(false);

  const annuler = () => {
    clearTimeout(minuteur.current);
    minuteur.current = null;
  };

  function debut(e) {
    declenche.current = false;
    depart.current = { x: e.clientX, y: e.clientY };
    annuler();
    minuteur.current = setTimeout(() => {
      declenche.current = true;
      navigator.vibrate?.(15);
      onAppuiLong(l.professeur_id);
    }, DELAI_APPUI_LONG_MS);
  }

  // Un défilement ou un déplacement du doigt annule l'appui long.
  function deplacement(e) {
    if (!depart.current) return;
    if (Math.abs(e.clientX - depart.current.x) > TOLERANCE_PX || Math.abs(e.clientY - depart.current.y) > TOLERANCE_PX) annuler();
  }

  function clic() {
    if (declenche.current) {
      declenche.current = false;
      return;
    }
    if (modeSelection) onBasculer(l.professeur_id);
    else onOuvrir(l.professeur_id);
  }

  return (
    <li className={`pm-carte${selectionnee ? ' pm-carte--selection' : ''}`}>
      <button
        type="button"
        className="pm-carte__corps"
        aria-pressed={modeSelection ? selectionnee : undefined}
        aria-label={modeSelection ? `Sélectionner ${l.professeur}` : `Ouvrir ${l.professeur}`}
        onPointerDown={debut}
        onPointerMove={deplacement}
        onPointerUp={annuler}
        onPointerLeave={annuler}
        onPointerCancel={annuler}
        onContextMenu={(e) => e.preventDefault()}
        onClick={clic}
      >
        {modeSelection && (
          <span className="pm-coche" aria-hidden="true">
            {selectionnee ? '✓' : ''}
          </span>
        )}
        <span className="pm-carte__texte">
          <span className="pm-carte__ligne">
            <strong className="pm-carte__nom">{l.professeur}</strong>
            <strong className="pm-carte__montant">{l.lignes ? formatEuros(l.total_eur) : '—'}</strong>
          </span>
          {l.employeur && (
            <span className="pm-carte__employeur">
              <EmployeurBadge employeur={l.employeur.employeur} source={l.employeur.source} verrouille={l.employeur.verrouille} raisonVerrou={l.employeur.raison_verrou} />
            </span>
          )}
          <span className="pm-carte__badges">
            <StatutBadge table={STATUTS_MOIS_PROF} valeur={l.statut_mois} />
            {l.heures_animation > 0 && <span className="pm-heures">{formatHeures(Number(l.heures_animation) + Number(l.heures_preparation || 0))}</span>}
            {l.lignes_ajustees > 0 && <StatutBadge label={`${l.lignes_ajustees} ajustée${l.lignes_ajustees > 1 ? 's' : ''}`} tone="info" />}
            {l.alertes.map((a) => (
              <StatutBadge key={a.code} label={`⚠ ${a.libelle}`} tone="warning" />
            ))}
          </span>
        </span>
      </button>
    </li>
  );
}
