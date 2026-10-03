import { Section } from '../ui/Card';
import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import Banner from '../ui/Banner';
import PeriodeBadge from './PeriodeBadge';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';
import { formatDate, formatDateCourte } from '../../utils/dates';

/**
 * Aperçu des séances d'une ou deux périodes d'une classe (réponse de `POST /classes/apercu`
 * ou `POST /classes/{id}/periodes/apercu` : `data.periodes[]`).
 * Une colonne par période : séances numérotées « P1 · 3 », dates sautées avec leur libellé du calendrier,
 * badge « hors période » (non bloquant) et avertissements du serveur.
 *
 * @param {object} props
 * @param {{periodes: object[]}|null} props.apercu `data` de l'API
 * @param {boolean} props.chargement
 * @param {Record<number, string>} [props.cours] titre du cours par numéro de période (affiché à côté du badge)
 * @param {string} [props.titre]
 */
export default function ClasseApercu({ apercu, chargement, cours = {}, titre }) {
  const periodes = apercu?.periodes || [];
  const total = periodes.reduce((n, p) => n + p.seances.length, 0);
  const sousTitre = 'Calculé avec le calendrier scolaire (FWB + École)';
  const titreSection = titre || (total ? `Aperçu des ${periodes.map((p) => p.seances.length).join(' + ')} séances` : 'Aperçu des séances');

  if (!apercu) {
    return (
      <Section title={titreSection} subtitle={sousTitre}>
        <p style={{ margin: 0, color: ADMIN_COLORS.textSecondary }} role="status">
          {chargement
            ? "Calcul de l'aperçu…"
            : "Renseignez le jour, l'horaire, le cours et la date de démarrage de chaque période : l'aperçu des séances s'affiche ici."}
        </p>
      </Section>
    );
  }

  return (
    <Section title={titreSection} subtitle={sousTitre} bodyPadding={false}>
      <div aria-live="polite" aria-busy={chargement}>
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 280px), 1fr))',
            gap: ADMIN_SPACING.lg,
            padding: ADMIN_SPACING.lg,
            alignItems: 'start',
          }}
        >
          {periodes.map((p) => (
            <ColonnePeriode key={p.numero ?? p.periode_id} periode={p} cours={cours[p.numero]} />
          ))}
        </div>
      </div>
    </Section>
  );
}

function ColonnePeriode({ periode, cours }) {
  const seances = periode.seances || [];
  const derniere = seances[seances.length - 1]?.date;
  const lignes = [
    ...seances.map((s) => ({ type: 'seance', date: s.date, numero: s.seance_numero, hors: s.hors_periode })),
    ...(periode.dates_sautees || []).map((d) => ({ type: 'sautee', date: d.date, libelle: d.libelle })),
  ].sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
  // On ne montre que les dates sautées situées avant la dernière séance.
  const visibles = lignes.filter((l) => l.type === 'seance' || !derniere || l.date < derniere);
  const avertissements = periode.avertissements || [];

  return (
    <div>
      <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, alignItems: 'center', flexWrap: 'wrap', marginBottom: ADMIN_SPACING.sm }}>
        <PeriodeBadge numero={periode.numero} />
        <strong>Période {periode.numero}</strong>
        {cours && <span style={{ color: ADMIN_COLORS.textSecondary }}>· {cours}</span>}
      </div>
      <div style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary, marginBottom: ADMIN_SPACING.sm }}>
        {seances.length} séances{seances.length ? ` du ${formatDate(seances[0].date)} au ${formatDate(derniere)}` : ''}
        {periode.recale && ' · première date recalée sur le jour de la classe'}
      </div>
      {avertissements.map((a) => (
        <Banner key={a} tone="warning">
          <strong>Avertissement (non bloquant) :</strong> {a}
        </Banner>
      ))}
      <Table caption={`Aperçu des séances de la période ${periode.numero}`} minWidth="240px" maxHeight="420px">
        <thead>
          <tr>
            <Th>Séance</Th>
            <Th>Date</Th>
          </tr>
        </thead>
        <tbody>
          {visibles.map((l) =>
            l.type === 'sautee' ? (
              <Tr key={`s-${l.date}`} fond={ADMIN_TONES.warning.bg}>
                <Td>
                  <span className="sr-only">Sautée</span>—
                </Td>
                <Td>
                  <span style={{ textDecoration: 'line-through', color: ADMIN_COLORS.textSecondary }}>{formatDateCourte(l.date)}</span>{' '}
                  <StatutBadge label={`⏭ Sautée · ${l.libelle}`} tone="warning" />
                </Td>
              </Tr>
            ) : (
              <Tr key={`n-${l.numero}`} fond={l.hors ? ADMIN_TONES.warning.bg : undefined}>
                <Td>
                  <strong>P{periode.numero} · {l.numero}</strong>
                </Td>
                <Td>
                  {formatDateCourte(l.date)} {l.hors && <StatutBadge label="⚠ Hors période" tone="warning" />}
                </Td>
              </Tr>
            ),
          )}
        </tbody>
      </Table>
    </div>
  );
}
