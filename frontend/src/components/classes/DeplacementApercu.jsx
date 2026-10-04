import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import Banner from '../ui/Banner';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';
import { formatDate, formatDateCourte } from '../../utils/dates';

const MOTIFS = { heures_validees: 'heures soumises ou validées' };

/**
 * Aperçu « avant → après » du déplacement d'une séance avec décalage des suivantes (CLS-06,
 * réponse de `POST /sessions/{id}/deplacement/apercu`). Les dates sautées portent une case « Forcer ».
 *
 * @param {object} props
 * @param {object|null} props.apercu
 * @param {boolean} props.chargement
 * @param {string[]} props.datesForcees
 * @param {(date: string, forcer: boolean) => void} props.onForcer
 */
export default function DeplacementApercu({ apercu, chargement, datesForcees, onForcer }) {
  if (!apercu) {
    return (
      <div role="status" aria-busy={chargement} style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary, margin: `${ADMIN_SPACING.sm} 0` }}>
        {chargement ? '⏳ Calcul des nouvelles dates…' : ''}
      </div>
    );
  }

  return (
    <div aria-busy={chargement} style={{ opacity: chargement ? 0.6 : 1, margin: `${ADMIN_SPACING.sm} 0 ${ADMIN_SPACING.lg}` }}>
      {apercu.periodes.map((bloc) => (
        <BlocPeriode key={bloc.classe_periode_id} bloc={bloc} datesForcees={datesForcees} onForcer={onForcer} />
      ))}
      {apercu.avertissements.length > 0 && (
        <Banner tone="warning" role="status">
          <strong>Avertissements (non bloquants) :</strong>
          <ul style={{ margin: `${ADMIN_SPACING.xs} 0 0`, paddingLeft: '18px' }}>
            {apercu.avertissements.map((a) => (
              <li key={a.message}>⚠ {a.message}</li>
            ))}
          </ul>
        </Banner>
      )}
    </div>
  );
}

function BlocPeriode({ bloc, datesForcees, onForcer }) {
  const visibles = bloc.lignes.filter((l) => l.etat !== 'inchangee');
  const premiereInchangee = bloc.lignes.find((l) => l.etat === 'inchangee' && !bloc.arret);
  const bougees = bloc.lignes.filter((l) => l.etat === 'deplacee' || l.etat === 'decalee');
  const derniere = bougees.at(-1);
  const verrou = bloc.lignes.find((l) => l.etat === 'verrouillee');
  const lignes = [
    ...visibles.map((l) => ({ ...l, type: 'seance', tri: l.date_apres })),
    ...bloc.dates_sautees.map((d) => ({ ...d, type: 'sautee', tri: d.date })),
  ].sort((a, b) => (a.tri < b.tri ? -1 : a.tri > b.tri ? 1 : 0));

  const resume = bloc.arret
    ? `${bougees.length} séance${bougees.length > 1 ? 's' : ''} déplacée${bougees.length > 1 ? 's' : ''} · arrêt à la ${bloc.arret.seance} (${MOTIFS[bloc.arret.motif]}) : elle et les suivantes sont inchangées.`
    : bougees.length === 0
      ? 'Aucune séance ne change de date.'
      : `${bougees.length} séance${bougees.length > 1 ? 's' : ''} déplacée${bougees.length > 1 ? 's' : ''}${derniere ? ` · dernière le ${formatDate(derniere.date_apres)}` : ''}.`;

  return (
    <div style={{ marginBottom: ADMIN_SPACING.md }}>
      <div style={{ fontWeight: 600, fontSize: '14px', marginBottom: ADMIN_SPACING.xs }}>
        Nouvelles dates — Période {bloc.numero}
        {bloc.cascade ? ' (décalée en cascade)' : ''}
      </div>
      {bloc.declencheur && (
        <div style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary, marginBottom: ADMIN_SPACING.xs }}>
          {bloc.declencheur} : la période {bloc.numero} est décalée à partir de la semaine suivante.
        </div>
      )}
      <div role="status" aria-live="polite" style={{ fontSize: '13px', marginBottom: ADMIN_SPACING.sm }}>
        {resume}
        {premiereInchangee && bougees.length > 0 && !verrou ? ` À partir de la ${premiereInchangee.libelle}, les dates ne changent pas.` : ''}
      </div>
      {lignes.length > 0 && (
        <Table caption={`Nouvelles dates de la période ${bloc.numero}`} minWidth="0" maxHeight="320px">
          <thead>
            <tr>
              <Th>Séance</Th>
              <Th>Avant</Th>
              <Th>Après</Th>
            </tr>
          </thead>
          <tbody>
            {lignes.map((l) => (l.type === 'sautee' ? (
              <Tr key={`s-${l.date}`} fond={ADMIN_TONES.warning.bg}>
                <Td>
                  <CaseForcer date={l.date} coche={false} onForcer={onForcer} libelle="Forcer" />
                </Td>
                <Td />
                <Td>
                  <span style={{ textDecoration: 'line-through', color: ADMIN_COLORS.textSecondary }}>{formatDateCourte(l.date)}</span>{' '}
                  <StatutBadge label={`⏭ Sautée · ${l.libelle}`} tone="warning" />
                </Td>
              </Tr>
            ) : (
              <LigneSeance key={l.id} ligne={l} forcee={datesForcees.includes(l.date_apres) && l.etat === 'decalee'} onForcer={onForcer} />
            )))}
          </tbody>
        </Table>
      )}
    </div>
  );
}

function LigneSeance({ ligne, forcee, onForcer }) {
  const fond = forcee ? ADMIN_TONES.success.bg : ligne.etat === 'verrouillee' ? ADMIN_TONES.neutral.bg : undefined;
  const libelle = <strong>{ligne.libelle.replace(' · Séance ', ' · ')}</strong>;
  return (
    <Tr fond={fond}>
      <Td>{forcee ? <CaseForcer date={ligne.date_apres} coche onForcer={onForcer} libelle={libelle} /> : libelle}</Td>
      <Td>{formatDateCourte(ligne.date_avant)}</Td>
      <Td>
        {ligne.etat === 'verrouillee' && <>🔒 {MOTIFS[ligne.motif]} : non décalée, les suivantes non plus</>}
        {ligne.etat === 'figee' && <>{ligne.statut === 'annulee' ? 'Annulée' : 'Bis'} : date inchangée</>}
        {(ligne.etat === 'decalee' || ligne.etat === 'deplacee') && (
          <>
            → {formatDateCourte(ligne.date_apres)} {ligne.etat === 'deplacee' && <StatutBadge label="déplacée" tone="primary" />}
            {forcee && <StatutBadge label="✓ Forcée" tone="success" />} {ligne.hors_periode && <StatutBadge label="⚠ Hors période" tone="warning" />}
          </>
        )}
      </Td>
    </Tr>
  );
}

function CaseForcer({ date, coche, onForcer, libelle }) {
  return (
    <label style={{ display: 'inline-flex', alignItems: 'center', gap: ADMIN_SPACING.sm, cursor: 'pointer', whiteSpace: 'nowrap' }}>
      <input
        type="checkbox"
        checked={coche}
        onChange={(e) => onForcer(date, e.target.checked)}
        aria-label={`${coche ? 'Ne plus forcer' : 'Forcer'} la séance du ${formatDate(date)}`}
        style={{ width: '16px', height: '16px', margin: 0, cursor: 'pointer' }}
      />
      {libelle}
    </label>
  );
}
