import { Table, Th, Td, Tr } from '../ui/Table';
import { liensDeSession, resumeLiens } from '../../utils/liens';
import StatutBadge from '../ui/StatutBadge';
import AdminButton from '../AdminButton';
import { ADMIN_COLORS, ADMIN_TONES } from '../../styles/AdminDesignSystem';
import { STATUTS_SESSION, TYPES_CALENDRIER, estSessionBarree, getStatut } from '../../utils/statuts';
import { formatDate, formatDateCourte, formatHoraire } from '../../utils/dates';
import { formatDuree } from '../../utils/format';
import { libelleSession, libelleSessionPhrase } from '../../utils/classes';

/**
 * Liste des sessions d'une classe (séances, bis, annulées) avec les actions d'ajustement.
 * Les actions affichées viennent de `session.can` (jamais d'un test sur le statut).
 *
 * @param {object} props
 * @param {object[]} props.sessions CourseSessionResource[] triées par séance puis bis
 * @param {(session: object, mode: 'deplacer'|'annuler'|'bis') => void} props.onAjuster
 * @param {object[]} [props.liens] liens actifs du cours : colonne « Liens » (généraux + ceux de la séance)
 * @param {string} [props.caption]
 * @param {string} [props.legendeId] id de la légende décrivant le verrou 🔒
 * @param {(session: object) => void} [props.onProfesseurs] ouvre la gestion des professeurs de la session : ajout, retrait, remplacement (staff)
 */
export default function ClasseSessionsTable({ sessions, onAjuster, onProfesseurs, liens, caption = 'Sessions de la classe', legendeId = 'legende-sessions' }) {
  const parId = new Map(sessions.map((s) => [s.id, s]));
  const remplacantDe = new Map(sessions.filter((s) => s.remplace_session_id).map((s) => [s.remplace_session_id, s]));

  return (
    <Table caption={caption} minWidth="760px">
      <thead>
        <tr>
          <Th>Séance</Th>
          <Th>Date</Th>
          <Th>Horaire</Th>
          <Th>Défrayé</Th>
          <Th>Professeurs</Th>
          {liens && <Th>Liens</Th>}
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
                <strong>{libelleSession(s)}</strong>
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
                    remplace la {libelleSessionPhrase(origine)} du {formatDate(origine.date)}
                  </div>
                )}
                {s.hors_periode && (
                  <div style={{ fontSize: '13px' }}>
                    <StatutBadge label="⚠ Hors période · rattrapage" tone="warning" />
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
                {barree || !s.heures_defrayables ? (
                  <span style={{ color: ADMIN_COLORS.textSecondary }}>—</span>
                ) : (
                  <span title={s.heures_defrayables.source === 'cours' ? 'Valeur définie sur le cours' : 'Défaut global'}>
                    <StatutBadge label={formatDuree(s.heures_defrayables.valeur)} tone="primary" />
                  </span>
                )}
              </Td>
              <Td>
                {(s.professeurs || []).length === 0 ? (
                  <span style={{ color: ADMIN_COLORS.textSecondary }}>—</span>
                ) : (
                  <ul style={{ listStyle: 'none', margin: 0, padding: 0, fontSize: '13px' }}>
                    {s.professeurs.map((p) => (
                      <li key={p.id} style={{ textDecoration: p.remplace ? 'line-through' : undefined, color: p.remplace ? ADMIN_COLORS.textSecondary : undefined }}>
                        {p.nom}
                        {p.origine === 'ajout' && !p.remplace && ' (ajouté)'}
                        {p.role === 'remplacant' && !p.remplace && ' (remplaçant)'}
                        {p.remplace && ' (remplacé)'}
                      </li>
                    ))}
                  </ul>
                )}
              </Td>
              {liens && (
                <Td>
                  <span style={{ fontSize: '13px' }}>{resumeLiens(liensDeSession(liens, s.seance_numero), s.seance_numero)}</span>
                </Td>
              )}
              <Td>
                <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                  <StatutBadge table={STATUTS_SESSION} valeur={s.statut} />
                  {alerte && <StatutBadge label="⚠ Date en conflit" tone="warning" />}
                  {barree && (remplacee ? <StatutBadge label="Remplacée par un bis" tone="success" /> : <StatutBadge label="À remplacer" tone="warning" />)}
                </div>
              </Td>
              <Td>
                {onProfesseurs && !barree && (
                  <AdminButton size="sm" variant="secondary" onClick={() => onProfesseurs(s)} style={{ marginBottom: '6px' }}>
                    Professeurs
                  </AdminButton>
                )}
                {barree ? (
                  remplacee ? (
                    <span style={{ fontSize: '13px' }}>
                      Bis : {libelleSession(remplacee)} · {formatDateCourte(remplacee.date)}
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
                    aria-describedby={legendeId}
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
