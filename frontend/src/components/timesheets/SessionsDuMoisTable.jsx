import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import AdminButton from '../AdminButton';
import { AdminCheckbox, AdminInput } from '../AdminFormField';
import { ADMIN_COLORS } from '../../styles/AdminDesignSystem';
import { ETATS_ENCODAGE, TYPES_ACTIVITE, getStatut } from '../../utils/statuts';
import { formatDateCourte, formatHoraire } from '../../utils/dates';
import { formatDuree, formatEuros, formatHeures } from '../../utils/format';

/**
 * Sessions commencées du mois (mock-up 02, section 1). Une session « à encoder » est préremplie (durée de la
 * session, modifiable) et peut être incluse ou non ; une session déjà saisie affiche ses heures et ses actions ;
 * une session remplacée ou annulée l'indique, sans aucune alerte.
 *
 * @param {object} props
 * @param {object[]} props.sessions sessions de `GET /timesheets/mon-mois`
 * @param {Record<number, {inclure: boolean, heures: string}>} props.saisiesLocales état local des sessions à encoder
 * @param {(id: number, patch: object) => void} props.onChange
 * @param {(saisie: object) => void} props.onModifier
 * @param {(saisie: object) => void} props.onSupprimer
 */
export default function SessionsDuMoisTable({ sessions, saisiesLocales, onChange, onModifier, onSupprimer }) {
  return (
    <Table caption="Mes sessions du mois" minWidth="760px">
      <thead>
        <tr>
          <Th>Inclure</Th>
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
          const local = saisiesLocales[s.id];
          const saisies = s.mes_timesheets;
          const aEncoder = s.encodage === 'a_encoder' && s.peut_encoder;
          const montant = saisies.reduce((t, x) => t + (Number(x.montant_brut) || 0), 0);

          return (
            <Tr key={s.id} fond={s.annulee ? ADMIN_COLORS.background : undefined}>
              <Td>
                {aEncoder && local && (
                  <AdminCheckbox
                    id={`inclure-${s.id}`}
                    label=""
                    aria-label={`Inclure la ${s.libelle.toLowerCase()} du ${formatDateCourte(s.date)}`}
                    checked={local.inclure}
                    onChange={(e) => onChange(s.id, { inclure: e.target.checked })}
                  />
                )}
              </Td>
              <Td>
                <span style={{ textDecoration: s.annulee ? 'line-through' : undefined }}>{formatDateCourte(s.date)}</span>
                <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>{formatHoraire(s.heure_debut, s.heure_fin)}</div>
              </Td>
              <Td>
                <strong>{s.classe_libelle}</strong> · {s.libelle}
                {s.remplace_par && (
                  <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>Remplacée par {s.remplace_par.nom}</div>
                )}
                {s.annulee && <div style={{ fontSize: '12px' }}>Annulée : aucune heure à encoder</div>}
              </Td>
              <Td>
                {saisies.length > 0
                  ? saisies.map((x) => <div key={x.id}>{getStatut(TYPES_ACTIVITE, x.type_activite).label}</div>)
                  : aEncoder
                    ? getStatut(TYPES_ACTIVITE, 'animation').label
                    : '—'}
              </Td>
              <Td>
                {saisies.length > 0 ? (
                  saisies.map((x) => (
                    <div key={x.id} style={{ display: 'flex', gap: '6px', alignItems: 'center', flexWrap: 'wrap' }}>
                      {formatHeures(x.nombre_heures)}
                      {x.can?.update && (
                        <AdminButton size="sm" variant="secondary" onClick={() => onModifier(x)}>
                          Modifier
                        </AdminButton>
                      )}
                      {x.can?.delete && (
                        <AdminButton size="sm" variant="secondary" onClick={() => onSupprimer(x)}>
                          Supprimer
                        </AdminButton>
                      )}
                    </div>
                  ))
                ) : aEncoder && local ? (
                  <>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                      <AdminInput
                        type="number"
                        step="0.5"
                        min="0.5"
                        max="24"
                        aria-label={`Heures défrayées pour la ${s.libelle.toLowerCase()}`}
                        value={local.heures}
                        disabled={!local.inclure}
                        onChange={(e) => onChange(s.id, { heures: e.target.value })}
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
                    </div>
                    {Number(local.heures) !== Number(s.duree_par_defaut) && (
                      <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>Valeur standard : {formatDuree(s.duree_par_defaut)}</div>
                    )}
                  </>
                ) : (
                  '—'
                )}
              </Td>
              <Td>{saisies.length > 0 && montant > 0 ? formatEuros(montant) : '—'}</Td>
              <Td>
                <StatutBadge table={ETATS_ENCODAGE} valeur={s.encodage} />
              </Td>
            </Tr>
          );
        })}
      </tbody>
    </Table>
  );
}
