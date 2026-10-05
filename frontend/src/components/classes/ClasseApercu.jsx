import { Section } from '../ui/Card';
import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import Banner from '../ui/Banner';
import PeriodeBadge from './PeriodeBadge';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';
import { formatDate, formatDateCourte, parseDate } from '../../utils/dates';

/**
 * Aperçu des séances d'une ou deux périodes d'une classe (réponse de `POST /classes/apercu`
 * ou `POST /classes/{id}/periodes/apercu` : `data.periodes[]`).
 * Une colonne par période : séances numérotées « P1 · 3 », dates sautées avec leur libellé du calendrier,
 * badge « hors période » (non bloquant) et avertissements du serveur.
 * Avec `onForcer`, chaque date sautée porte une case « Forcer » et chaque séance forcée une case pour l'annuler (CLS-05).
 *
 * @param {object} props
 * @param {{periodes: object[]}|null} props.apercu `data` de l'API
 * @param {boolean} props.chargement
 * @param {Record<number, string>} [props.cours] titre du cours par numéro de période (affiché à côté du badge)
 * @param {string} [props.titre]
 * @param {(numeroPeriode: number, date: string, forcer: boolean) => void} [props.onForcer]
 */
export default function ClasseApercu({ apercu, chargement, cours = {}, titre, onForcer }) {
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
            <ColonnePeriode
              key={p.numero ?? p.periode_id}
              periode={p}
              cours={cours[p.numero]}
              onForcer={onForcer && ((date, forcer) => onForcer(p.numero, date, forcer))}
            />
          ))}
        </div>
      </div>
    </Section>
  );
}

function ColonnePeriode({ periode, cours, onForcer }) {
  const seances = periode.seances || [];
  const derniere = seances[seances.length - 1]?.date;
  const nbForcees = seances.filter((s) => s.forcee).length;
  const lignes = [
    ...seances.map((s) => ({ type: 'seance', date: s.date, numero: s.seance_numero, hors: s.hors_periode, forcee: s.forcee, conge: s.conge })),
    ...(periode.dates_sautees || []).map((d) => ({ type: 'sautee', date: d.date, libelle: d.libelle, forceeDansSource: d.forcee_dans_source })),
  ].sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
  // On ne montre que les dates sautées situées avant la dernière séance.
  const visibles = lignes.filter((l) => l.type === 'seance' || !derniere || l.date < derniere);
  const avertissements = periode.avertissements || [];
  // CLS-07 : comparaison avec la classe source (aperçu d'une copie dans l'année de la source).
  const comparaison = periode.comparaison ? Object.fromEntries(periode.comparaison.map((c) => [c.seance_numero, c])) : null;
  const datesSource = (periode.comparaison || []).map((c) => c.date_source).filter(Boolean).sort();

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
        {nbForcees > 0 && ` · ${nbForcees} séance${nbForcees > 1 ? 's' : ''} forcée${nbForcees > 1 ? 's' : ''} sur un congé`}
        {datesSource.length > 0 && (
          <div>
            Source : du {formatDate(datesSource[0])} au {formatDate(datesSource.at(-1))}
          </div>
        )}
      </div>
      {onForcer && periode.dates_sautees?.length > 0 && (
        <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginBottom: ADMIN_SPACING.sm }}>
          Cochez « Forcer » sur une date sautée pour y maintenir la séance : les suivantes sont renumérotées.
        </div>
      )}
      {avertissements.map((a) => (
        <Banner key={a} tone="warning">
          <strong>Avertissement (non bloquant) :</strong> {a}
        </Banner>
      ))}
      <Table caption={`Aperçu des séances de la période ${periode.numero}`} minWidth="240px" maxHeight="420px">
        <thead>
          <tr>
            <Th>Séance</Th>
            {comparaison && <Th>Source</Th>}
            <Th>{comparaison ? 'Copie' : 'Date'}</Th>
            {comparaison && <Th>Écart</Th>}
          </tr>
        </thead>
        <tbody>
          {visibles.map((l) =>
            l.type === 'sautee' ? (
              <Tr key={`s-${l.date}`} fond={ADMIN_TONES.warning.bg}>
                <Td>
                  {onForcer ? (
                    <CaseForcer date={l.date} coche={false} onForcer={onForcer} libelle="Forcer" />
                  ) : (
                    <>
                      <span className="sr-only">Sautée</span>—
                    </>
                  )}
                </Td>
                {comparaison && <Td />}
                <Td>
                  <span style={{ textDecoration: 'line-through', color: ADMIN_COLORS.textSecondary }}>{formatDateCourte(l.date)}</span>{' '}
                  <StatutBadge label={`⏭ Sautée · ${l.libelle}`} tone="warning" />
                  {l.forceeDansSource && <div style={{ fontSize: '12px', marginTop: '2px' }}>ⓘ forcée dans la source</div>}
                </Td>
                {comparaison && <Td />}
              </Tr>
            ) : (
              <Tr key={`n-${l.numero}`} fond={l.forcee ? ADMIN_TONES.success.bg : l.hors ? ADMIN_TONES.warning.bg : undefined}>
                <Td>
                  {onForcer && l.forcee ? (
                    <CaseForcer date={l.date} coche onForcer={onForcer} libelle={<strong>P{periode.numero} · {l.numero}</strong>} />
                  ) : (
                    <strong>P{periode.numero} · {l.numero}</strong>
                  )}
                </Td>
                {comparaison && <Td>{texteSource(comparaison[l.numero])}</Td>}
                <Td>
                  {formatDateCourte(l.date)} {l.forcee && <StatutBadge label={`✓ Forcée · ${l.conge?.libelle}`} tone="success" />}{' '}
                  {l.hors && <StatutBadge label="⚠ Hors période" tone="warning" />}
                  {comparaison?.[l.numero]?.passee && <> · passée</>}
                </Td>
                {comparaison && <Td>{texteEcart(comparaison[l.numero])}</Td>}
              </Tr>
            ),
          )}
        </tbody>
      </Table>
    </div>
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

const court = (d) => `${d.slice(8, 10)}/${d.slice(5, 7)}`;

/** « 05/11 · déplacée (était 04/11) », « 09/12 · annulée · bis 12/12 (non copiés) », « 11/11 · pendant un congé (Armistice) » */
function texteSource(c) {
  if (!c?.date_source) return '—';
  const signal = {
    deplacee: ` · déplacée (était ${c.date_source_prevue ? court(c.date_source_prevue) : '?'})`,
    annulee: ' · annulée',
    pendant_conge: ` · pendant un congé (${c.conge_source})`,
  }[c.etat_source] || '';
  const bis = c.bis_source ? ` · bis ${court(c.bis_source)} (non copié)` : '';
  return `${court(c.date_source)}${signal}${bis}`;
}

/** Écart en semaines civiles : « même jour », « même sem. », « 2 sem. plus tôt / plus tard ». */
function texteEcart(c) {
  if (!c?.date_source) return '';
  if (c.date_source === c.date_copie) return 'même jour';
  const lundi = (d) => {
    const x = parseDate(d);
    x.setDate(x.getDate() - ((x.getDay() + 6) % 7));
    return x;
  };
  const semaines = Math.round((lundi(c.date_copie) - lundi(c.date_source)) / (7 * 86400000));
  if (semaines === 0) return 'même sem.';
  return `${Math.abs(semaines)} sem. plus ${semaines < 0 ? 'tôt' : 'tard'}`;
}

