import { TYPES_LIEN, getStatut } from '../../utils/statuts';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

import { liensDeSession, resumeLiens } from '../../utils/liens';

function Ligne({ lien }) {
  const type = lien.type ? getStatut(TYPES_LIEN, lien.type) : null;
  return (
    <li style={{ marginBottom: '4px' }}>
      {type ? `${type.icone} ` : ''}
      <a href={lien.url} target="_blank" rel="noopener noreferrer">
        {lien.titre}
      </a>
      {lien.description && <span style={{ color: ADMIN_COLORS.textSecondary, fontSize: '13px' }}> — {lien.description}</span>}
    </li>
  );
}

/**
 * Résumé dépliable « Liens : 3 généraux + 2 de la séance 6 » d'une session (portail professeur, mock-up 03) : un tap pour
 * voir et ouvrir les liens généraux puis ceux de la séance.
 *
 * @param {object} props
 * @param {object[]} props.liens liens actifs du cours (`GET /cours/{id}/liens`)
 * @param {number} props.seanceNumero numéro de séance de la session
 */
export default function LiensSession({ liens, seanceNumero }) {
  const l = liensDeSession(liens, seanceNumero);
  const vide = l.generaux.length + l.seance.length === 0;

  return (
    <details style={{ marginTop: ADMIN_SPACING.sm }}>
      <summary style={{ cursor: 'pointer', fontSize: '13px' }}>Liens : {resumeLiens(l, seanceNumero)}</summary>
      {vide ? (
        <p style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary, margin: '6px 0 0' }}>Aucun lien pour la séance {seanceNumero}.</p>
      ) : (
        <div style={{ fontSize: '14px', marginTop: '6px' }}>
          {l.seance.length > 0 && (
            <>
              <strong style={{ fontSize: '12px' }}>Séance {seanceNumero}</strong>
              <ul style={{ margin: '4px 0 8px', paddingLeft: '18px' }}>{l.seance.map((x) => <Ligne key={x.id} lien={x} />)}</ul>
            </>
          )}
          {l.generaux.length > 0 && (
            <>
              <strong style={{ fontSize: '12px' }}>Pour tout le cours</strong>
              <ul style={{ margin: '4px 0 0', paddingLeft: '18px' }}>{l.generaux.map((x) => <Ligne key={x.id} lien={x} />)}</ul>
            </>
          )}
        </div>
      )}
    </details>
  );
}
