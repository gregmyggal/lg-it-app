import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import AdminButton from '../AdminButton';
import { ADMIN_COLORS, ADMIN_TONES } from '../../styles/AdminDesignSystem';
import { STATUTS_SESSION, TYPES_CALENDRIER, estSessionBarree, getStatut } from '../../utils/statuts';
import { formatDate, formatDateCourte, formatHoraire } from '../../utils/dates';

/**
 * Liste des sessions d'une classe (séances, bis, annulées) avec les actions d'ajustement.
 * Les actions affichées viennent de `session.can` (jamais d'un test sur le statut).
 *
 * @param {object} props
 * @param {object[]} props.sessions CourseSessionResource[] triées par séance puis bis
 * @param {(session: object, mode: 'deplacer'|'annuler'|'bis') => void} props.onAjuster
 */
export default function ClasseSessionsTable({ sessions, onAjuster }) {
  const parId = new Map(sessions.map((s) => [s.id, s]));
  const remplacantDe = new Map(sessions.filter((s) => s.remplace_session_id).map((s) => [s.remplace_session_id, s]));

  return (
    <Table caption="Sessions de la classe" minWidth="760px">
      <thead>
        <tr>
          <Th>Séance</Th>
          <Th>Date</Th>
          <Th>Horaire</Th>
          <Th>Statut</Th>
          <Th>Actions</Th>
        </tr>
      </thead>
      <tbody>
        {sessions.map((s) => {
          const barree = estSessionBarree(s.statut);
          const remplacee = remplacantDe.get(s.id);
          const origine = s.remplace_session_id ? parId.get(s.remplace_session_id) : null;
          const alerte = s.alerte_calendrier;
          const peutAjuster = s.can?.update || s.can?.cancel;
          return (
            <Tr
              key={s.id}
              fond={alerte ? ADMIN_TONES.warning.bg : barree ? ADMIN_COLORS.background : undefined}
            >
              <Td>
                <strong>{s.libelle}</strong>
              </Td>
              <Td>
                <span
                  style={{
                    textDecoration: barree ? 'line-through' : undefined,
                    color: barree ? ADMIN_COLORS.textSecondary : undefined,
                  }}
                >
                  {formatDateCourte(s.date)}
                </span>
                {barree && s.motif_annulation && (
                  <div style={{ fontSize: '13px' }}>Motif : {s.motif_annulation}</div>
                )}
                {origine && (
                  <div style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
                    remplace la {origine.libelle.toLowerCase()} du {formatDate(origine.date)}
                  </div>
                )}
                {alerte && (
                  <div style={{ fontSize: '13px', color: ADMIN_TONES.school.fg }}>
                    {getStatut(TYPES_CALENDRIER, alerte.type).label} · {alerte.libelle}
                  </div>
                )}
              </Td>
              <Td>{formatHoraire(s.heure_debut, s.heure_fin)}</Td>
              <Td>
                <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                  <StatutBadge table={STATUTS_SESSION} valeur={s.statut} />
                  {alerte && <StatutBadge label="⚠ Date en conflit" tone="warning" />}
                  {barree && (remplacee ? <StatutBadge label="Remplacée par un bis" tone="success" /> : <StatutBadge label="À remplacer" tone="warning" />)}
                </div>
              </Td>
              <Td>
                {barree ? (
                  remplacee ? (
                    <span style={{ fontSize: '13px' }}>
                      Bis : {remplacee.libelle} · {formatDateCourte(remplacee.date)}
                    </span>
                  ) : (
                    s.can?.bis && (
                      <AdminButton size="sm" variant="secondary" onClick={() => onAjuster(s, 'bis')}>
                        Remplacer cette session
                      </AdminButton>
                    )
                  )
                ) : peutAjuster ? (
                  <AdminButton size="sm" variant="secondary" onClick={() => onAjuster(s, alerte ? 'deplacer' : undefined)}>
                    Ajuster
                  </AdminButton>
                ) : (
                  <AdminButton
                    size="sm"
                    variant="secondary"
                    disabled
                    title="Session passée ou terminée : elle ne peut plus être déplacée ni annulée."
                    aria-describedby="legende-sessions"
                  >
                    🔒 Ajuster
                  </AdminButton>
                )}
              </Td>
            </Tr>
          );
        })}
      </tbody>
    </Table>
  );
}
