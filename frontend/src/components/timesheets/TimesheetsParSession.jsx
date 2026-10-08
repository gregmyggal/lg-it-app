import { useMemo, useState } from 'react';
import AdminButton from '../AdminButton';
import { AdminCheckbox, AdminSelect } from '../AdminFormField';
import { Section } from '../ui/Card';
import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import LinkButton from '../ui/LinkButton';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../ui/DataStates';
import ValiderLotModal from './ValiderLotModal';
import { usePlafondJournalier, useListeSaisies, useSessionsSansHeures } from '../../hooks/useTimesheets';
import { useToast } from '../../hooks/useToast';
import { STATUTS_TIMESHEET, TYPES_ACTIVITE, getStatut } from '../../utils/statuts';
import { formatDateCourte } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';
import { ADMIN_COLORS } from '../../styles/AdminDesignSystem';
import { libelleSession } from '../../utils/classes';

const SEUIL_RETARD_JOURS = 7;
const dernierJour = (annee, mois) => new Date(annee, mois, 0).getDate();

/**
 * Vue directeur « par session » (mock-up 03) : sessions passées sans heures, heures du mois rattachées aux sessions
 * (ou hors séance) et validation en lot avec lissage.
 *
 * @param {object} props
 * @param {string} props.mois `YYYY-MM`
 * @param {() => void} [props.onChange] rechargement de la vue « par professeur » après validation
 */
export default function TimesheetsParSession({ mois, onChange }) {
  const toast = useToast();
  const [annee, moisNum] = mois.split('-').map(Number);
  const dates = { date_from: `${mois}-01`, date_to: `${mois}-${String(dernierJour(annee, moisNum)).padStart(2, '0')}` };
  const plafond = usePlafondJournalier(annee);
  const saisies = useListeSaisies(dates);
  const sansHeures = useSessionsSansHeures(dates);
  const [classeId, setClasseId] = useState('');
  const [selection, setSelection] = useState(new Set());
  const [lot, setLot] = useState(false);

  const classes = useMemo(() => {
    const m = new Map();
    (saisies.data || []).forEach((t) => t.session && m.set(t.session.classe_id, t.session.classe_libelle?.split(' — ')[0] || `Classe ${t.session.classe_id}`));
    (sansHeures.data || []).forEach((s) => m.set(s.classe_id, s.classe_libelle));
    return [...m.entries()].map(([value, label]) => ({ value: String(value), label }));
  }, [saisies.data, sansHeures.data]);

  const visibles = (saisies.data || []).filter((t) => !classeId || String(t.session?.classe_id) === classeId);
  const validables = visibles.filter((t) => t.can?.validate && t.statut_validation === 'soumis');
  const choisies = visibles.filter((t) => selection.has(t.id));

  function basculer(id) {
    setSelection((prev) => {
      const n = new Set(prev);
      if (n.has(id)) n.delete(id);
      else n.add(id);
      return n;
    });
  }

  function apresValidation(message) {
    setLot(false);
    setSelection(new Set());
    toast.success(message);
    saisies.reload();
    sansHeures.reload();
    onChange?.();
  }

  return (
    <>
      <Section
        title="Sessions passées sans heures"
        subtitle="Une session terminée où au moins un professeur attendu n'a encodé aucune heure."
        bodyPadding={false}
      >
        {sansHeures.loading && <LoadingBlock message="Chargement des sessions…" lignes={2} />}
        {sansHeures.error && <ErrorBlock message={sansHeures.error} onRetry={sansHeures.reload} />}
        {sansHeures.data?.length === 0 && (
          <EmptyBlock icon="✅" title="Aucune session sans heures">
            Tous les professeurs attendus ont encodé leurs heures pour les sessions de ce mois.
          </EmptyBlock>
        )}
        {sansHeures.data?.length > 0 && (
          <Table caption="Sessions passées sans heures" minWidth="640px" cards>
            <thead>
              <tr>
                <Th>Session</Th>
                <Th>Date</Th>
                <Th>Professeurs sans heures</Th>
                <Th>Retard</Th>
                <Th>Actions</Th>
              </tr>
            </thead>
            <tbody>
              {sansHeures.data
                .filter((s) => !classeId || String(s.classe_id) === classeId)
                .map((s) => (
                  <Tr key={s.session_id}>
                    <Td label="Session">
                      <strong>{s.classe_libelle}</strong> · {libelleSession(s)}
                    </Td>
                    <Td label="Date">{formatDateCourte(s.date)}</Td>
                    <Td label="Professeurs sans heures">{s.professeurs_sans_heures.map((p) => p.nom).join(', ')}</Td>
                    <Td label="Retard">
                      <StatutBadge
                        label={s.jours_de_retard === 0 ? "Aujourd'hui" : `${s.jours_de_retard} j`}
                        tone={s.jours_de_retard > SEUIL_RETARD_JOURS ? 'warning' : 'neutral'}
                      />
                    </Td>
                    <Td label="Actions">
                      <LinkButton to={`/admin/classes/${s.classe_id}`} size="sm">
                        Voir la classe
                      </LinkButton>
                    </Td>
                  </Tr>
                ))}
            </tbody>
          </Table>
        )}
      </Section>

      <Section
        title="Heures du mois"
        subtitle="Les heures de chaque professeur, rattachées à leur session ou encodées librement. Les saisies soumises peuvent être validées en lot, avec lissage."
        bodyPadding={false}
        actions={
          <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' }}>
            <AdminSelect aria-label="Filtrer par classe" value={classeId} placeholder="Toutes les classes" options={classes} onChange={(e) => setClasseId(e.target.value)} />
            <AdminButton size="sm" variant="secondary" onClick={() => setSelection(new Set(validables.map((t) => t.id)))} disabled={validables.length === 0}>
              Tout sélectionner ({validables.length})
            </AdminButton>
            <AdminButton size="sm" onClick={() => setLot(true)} disabled={choisies.length === 0}>
              Valider la sélection ({choisies.length})
            </AdminButton>
          </div>
        }
      >
        {saisies.loading && <LoadingBlock message="Chargement des heures…" lignes={4} />}
        {saisies.error && <ErrorBlock message={saisies.error} onRetry={saisies.reload} />}
        {saisies.data && visibles.length === 0 && (
          <EmptyBlock icon="🗓️" title="Aucune heure ce mois-ci">
            Aucun professeur n'a encore encodé d'heures pour cette période.
          </EmptyBlock>
        )}
        {visibles.length > 0 && (
          <Table caption="Heures du mois par session" minWidth="860px" cards>
            <thead>
              <tr>
                <Th srOnly>Sélection</Th>
                <Th>Session</Th>
                <Th>Date</Th>
                <Th>Professeur</Th>
                <Th>Activité</Th>
                <Th>Durée</Th>
                <Th>Montant</Th>
                <Th>Statut</Th>
              </tr>
            </thead>
            <tbody>
              {visibles.map((t) => {
                const valid = t.can?.validate && t.statut_validation === 'soumis';
                return (
                  <Tr key={t.id}>
                    <Td label="Sélection">
                      {valid && (
                        <AdminCheckbox
                          id={`sel-${t.id}`}
                          label=""
                          aria-label={`Sélectionner les heures de ${t.professeur?.prenom} ${t.professeur?.nom} du ${formatDateCourte(t.date_prestation.slice(0, 10))}`}
                          checked={selection.has(t.id)}
                          onChange={() => basculer(t.id)}
                        />
                      )}
                    </Td>
                    <Td label="Session">
                      {t.session ? (
                        <>
                          <strong>{t.session.classe_libelle?.split(' — ')[0]}</strong> · {libelleSession(t.session)}
                        </>
                      ) : (
                        <span style={{ color: ADMIN_COLORS.textSecondary }}>Heures hors séance</span>
                      )}
                    </Td>
                    <Td label="Date">{formatDateCourte(t.date_prestation.slice(0, 10))}</Td>
                    <Td label="Professeur">
                      {t.professeur?.prenom} {t.professeur?.nom}
                    </Td>
                    <Td label="Activité">{getStatut(TYPES_ACTIVITE, t.type_activite).label}</Td>
                    <Td label="Durée">{formatHeures(t.nombre_heures)}</Td>
                    <Td label="Montant">{formatEuros(t.montant_brut)}</Td>
                    <Td label="Statut">
                      <StatutBadge table={STATUTS_TIMESHEET} valeur={t.statut_validation} />
                    </Td>
                  </Tr>
                );
              })}
            </tbody>
          </Table>
        )}
      </Section>

      {lot && plafond != null && (
        <ValiderLotModal
          plafond={plafond}
          selection={choisies}
          toutesLesSaisies={saisies.data || []}
          annee={annee}
          mois={moisNum}
          onClose={() => setLot(false)}
          onDone={apresValidation}
        />
      )}
    </>
  );
}
