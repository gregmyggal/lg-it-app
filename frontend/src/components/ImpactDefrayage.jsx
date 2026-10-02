import { useEffect, useState } from 'react';
import Banner from './ui/Banner';
import { simulerImpactDefrayables } from '../hooks/useHeuresDefrayables';
import { formatDate } from '../utils/dates';
import { formatDuree, formatEuros } from '../utils/format';

const DELAI_MS = 500;

/**
 * Avertissement non bloquant (DEF-01 T4) : avec cette valeur, combien de jours professeur dépasseraient le plafond
 * journalier ? Calculé par le serveur sur les séances non encore encodées de l'année ; rien n'est enregistré.
 *
 * @param {object} props
 * @param {number} props.annee année civile concernée
 * @param {'global'|'cours'} props.portee
 * @param {number|string} [props.coursId] requis pour la portée « cours »
 * @param {string|number} props.heures valeur saisie (virgule décimale acceptée) ; vide ou invalide : rien n'est affiché
 */
export default function ImpactDefrayage({ annee, portee, coursId, heures }) {
  const [impact, setImpact] = useState(null);
  const valeur = Number(String(heures).replace(',', '.'));
  const valide = String(heures).trim() !== '' && Number.isFinite(valeur) && valeur >= 0.5 && valeur <= 8 && (portee === 'global' || Boolean(coursId));

  useEffect(() => {
    if (!valide) {
      setImpact(null);
      return undefined;
    }
    let annule = false;
    const minuteur = setTimeout(() => {
      simulerImpactDefrayables({ annee, portee, cours_id: portee === 'cours' ? Number(coursId) : undefined, heures: valeur })
        .then((r) => !annule && setImpact(r))
        .catch(() => !annule && setImpact(null)); // l'avertissement est facultatif : jamais bloquant
    }, DELAI_MS);
    return () => {
      annule = true;
      clearTimeout(minuteur);
    };
  }, [annee, portee, coursId, valeur, valide]);

  if (!impact || impact.nouveaux === 0) return null;

  return (
    <Banner tone="warning">
      <strong>
        Avec {formatDuree(valeur)} par séance, {impact.nouveaux} jour{impact.nouveaux > 1 ? 's' : ''} de plus dépasserai{impact.nouveaux > 1 ? 'ent' : 't'} le plafond
        de {formatEuros(impact.plafond_journalier_eur)} par jour
      </strong>{' '}
      (aujourd’hui : {impact.avant}, ensuite : {impact.apres}). Rien n’est bloqué : le lissage permet de répartir ces montants.
      {impact.exemples.length > 0 && (
        <ul style={{ margin: '6px 0 0', paddingLeft: 18, fontSize: 13 }}>
          {impact.exemples.map((e) => (
            <li key={`${e.professeur}-${e.date}`}>{e.professeur}, le {formatDate(e.date)} : {formatEuros(e.montant)}</li>
          ))}
        </ul>
      )}
    </Banner>
  );
}
