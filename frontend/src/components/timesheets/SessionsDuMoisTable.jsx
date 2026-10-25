import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import AdminButton from '../AdminButton';
import HeuresStepper from './HeuresStepper';
import { ADMIN_COLORS } from '../../styles/AdminDesignSystem';
import { ETATS_ENCODAGE, TYPES_ACTIVITE, getStatut } from '../../utils/statuts';
import { formatDateCourte, formatHoraire } from '../../utils/dates';
import { formatDuree, formatEuros, formatHeures } from '../../utils/format';
import { libelleSession, libelleSessionPhrase } from '../../utils/classes';

/** Une heure saisissable pour une séance : entre 0,5 et 24 h. */
export const heuresValides = (valeur) => Number(valeur) >= 0.5 && Number(valeur) <= 24;

/**
 * Séances du mois, passées et à venir (mock-up 02 et TS-03, section 1). Chaque séance s'encode par son propre bouton « Encoder » : elle
 * devient un brouillon. Une fois encodée, elle affiche ses heures et ses actions. Il n'y a pas de case à cocher : la
 * soumission du mois prend automatiquement toutes les heures encodées.
 *
 * @param {object} props
 * @param {object[]} props.sessions sessions de `GET /timesheets/mon-mois`
 * @param {Record<number, string>} props.heuresParSession heures proposées pour chaque séance à encoder
 * @param {(id: number, heures: string) => void} props.onHeures
 * @param {(session: object) => void} props.onEncoder
 * @param {(saisie: object) => void} props.onModifier
 * @param {(saisie: object) => void} props.onSupprimer
 * @param {boolean} [props.verrouille] mois soumis : plus aucune action d'encodage
 */
export default function SessionsDuMoisTable({ sessions, heuresParSession, onHeures, onEncoder, onModifier, onSupprimer, verrouille = false }) {
  return (
    <Table caption="Mes sessions du mois" minWidth="760px" cards>
      <thead>
        <tr>
          <Th>Date</Th>
          <Th>Classe · Séance</Th>
          <Th>Activité</Th>
          <Th>Heures défrayées (h)</Th>
          <Th>Montant</Th>
          <Th>État</Th>
        </tr>
      </thead>
      <tbody>
        {sessions.map((s) => {
          const saisies = s.mes_timesheets;
          const aEncoder = s.encodage === 'a_encoder' && s.peut_encoder && !verrouille;
          const heures = heuresParSession[s.id] ?? String(s.duree_par_defaut);
          const montant = saisies.reduce((t, x) => t + (Number(x.montant_brut) || 0), 0);

          return (
            <Tr key={s.id} fond={s.annulee ? ADMIN_COLORS.background : undefined}>
              <Td label="Date">
                <span style={{ textDecoration: s.annulee ? 'line-through' : undefined }}>{formatDateCourte(s.date)}</span>
                {s.est_a_venir && !s.annulee && (
                  <span className="badge" style={{ marginLeft: '6px', fontSize: '11px', background: 'var(--tone-primary-bg)', color: 'var(--tone-primary-fg)', borderRadius: '9999px', padding: '1px 8px' }}>
                    À venir
                  </span>
                )}
                <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>{formatHoraire(s.heure_debut, s.heure_fin)}</div>
              </Td>
              <Td label="Classe · Séance">
                <strong>{s.classe_libelle}</strong> · {libelleSession(s)}
                {s.remplace_par && (
                  <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>Remplacée par {s.remplace_par.nom}</div>
                )}
                {s.annulee && <div style={{ fontSize: '12px' }}>Annulée : aucune heure à encoder</div>}
              </Td>
              <Td label="Activité">
                {saisies.length > 0
                  ? saisies.map((x) => <div key={x.id}>{getStatut(TYPES_ACTIVITE, x.type_activite).label}</div>)
                  : aEncoder
                    ? getStatut(TYPES_ACTIVITE, 'animation').label
                    : '—'}
              </Td>
              <Td label="Heures défrayées">
                {saisies.length > 0 ? (
                  saisies.map((x) => (
                    <div key={x.id} style={{ display: 'flex', gap: '6px', alignItems: 'center', flexWrap: 'wrap' }}>
                      {formatHeures(x.nombre_heures)}
                      {x.can?.update && !verrouille && (
                        <AdminButton size="sm" variant="secondary" onClick={() => onModifier(x)}>
                          Modifier
                        </AdminButton>
                      )}
                      {x.can?.delete && !verrouille && (
                        <AdminButton size="sm" variant="secondary" onClick={() => onSupprimer(x)}>
                          Supprimer
                        </AdminButton>
                      )}
                    </div>
                  ))
                ) : aEncoder ? (
                  <>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px', flexWrap: 'wrap' }}>
                      <HeuresStepper
                        aria-label={`Heures défrayées pour la ${libelleSessionPhrase(s)}`}
                        value={heures}
                        onChange={(valeur) => onHeures(s.id, valeur)}
                      />
                      {s.duree_seance != null && (
                        <span
                          role="img"
                          tabIndex={0}
                          aria-label={`${formatDuree(s.duree_seance)} de séance + préparation`}
                          title={`${formatDuree(s.duree_seance)} de séance + préparation`}
                          style={{ cursor: 'help' }}
                        >
                          ⓘ
                        </span>
                      )}
                      <AdminButton size="sm" onClick={() => onEncoder(s, heures)} disabled={!heuresValides(heures)}>
                        Encoder
                      </AdminButton>
                    </div>
                    {Number(heures) !== Number(s.duree_par_defaut) && (
                      <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>Valeur standard : {formatDuree(s.duree_par_defaut)}</div>
                    )}
                  </>
                ) : (
                  '—'
                )}
              </Td>
              <Td label="Montant">{saisies.length > 0 && montant > 0 ? formatEuros(montant) : '—'}</Td>
              <Td label="État">
                <StatutBadge table={ETATS_ENCODAGE} valeur={s.encodage} />
              </Td>
            </Tr>
          );
        })}
      </tbody>
    </Table>
  );
}
