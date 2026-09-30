import { Section } from '../ui/Card';
import { Table, Th, Td, Tr } from '../ui/Table';
import StatutBadge from '../ui/StatutBadge';
import { ADMIN_COLORS, ADMIN_TONES } from '../../styles/AdminDesignSystem';
import { formatDate, formatDateCourte } from '../../utils/dates';

/**
 * Aperçu des 14 dates d'une classe (réponse de `POST /classes/apercu`) :
 * séances numérotées, dates sautées avec le libellé du calendrier, dates hors période si blocage.
 *
 * @param {object} props
 * @param {object|null} props.apercu `data` de l'API { seances, dates_sautees, blocage, … }
 * @param {boolean} props.chargement
 * @param {{numero: number, date_fin: string}|null} props.periode période choisie (pour marquer les dates hors période)
 */
export default function ClasseApercu({ apercu, chargement, periode }) {
  if (!apercu) {
    return (
      <Section title="Aperçu des 14 sessions" subtitle="Calculé avec le calendrier scolaire (FWB + École)">
        <p style={{ margin: 0, color: ADMIN_COLORS.textSecondary }} role="status">
          {chargement
            ? "Calcul de l'aperçu…"
            : "Renseignez le cours, la période, le jour, l'horaire et la date de première session : l'aperçu des 14 dates s'affiche ici."}
        </p>
      </Section>
    );
  }

  const lignes = [
    ...apercu.seances.map((s) => ({ type: 'seance', date: s.date, numero: s.seance_numero })),
    ...apercu.dates_sautees.map((d) => ({ type: 'sautee', date: d.date, libelle: d.libelle })),
  ].sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));

  // On ne montre que les dates sautées situées avant la dernière séance.
  const derniere = apercu.seances[apercu.seances.length - 1]?.date;
  const visibles = lignes.filter((l) => l.type === 'seance' || !derniere || l.date < derniere);

  return (
    <Section
      title="Aperçu des 14 sessions"
      subtitle="Calculé avec le calendrier scolaire (FWB + École)"
      bodyPadding={false}
    >
      <div aria-live="polite" aria-busy={chargement}>
        <Table caption="Aperçu des séances et des dates sautées" minWidth="320px" maxHeight="420px">
          <thead>
            <tr>
              <Th>Séance</Th>
              <Th>Date</Th>
            </tr>
          </thead>
          <tbody>
            {visibles.map((l) => {
              const horsPeriode = apercu.blocage && periode && l.type === 'seance' && l.date > periode.date_fin;
              if (l.type === 'sautee') {
                return (
                  <Tr key={`s-${l.date}`} fond={ADMIN_TONES.warning.bg}>
                    <Td>
                      <span className="sr-only">Sautée</span>—
                    </Td>
                    <Td>
                      <span style={{ textDecoration: 'line-through', color: ADMIN_COLORS.textSecondary }}>
                        {formatDateCourte(l.date)}
                      </span>{' '}
                      <StatutBadge label={`⏭ Sautée · ${l.libelle}`} tone="warning" />
                    </Td>
                  </Tr>
                );
              }
              return (
                <Tr key={`n-${l.numero}`} fond={horsPeriode ? ADMIN_TONES.error.bg : undefined}>
                  <Td>{horsPeriode ? <strong>{l.numero}</strong> : l.numero}</Td>
                  <Td>
                    {formatDateCourte(l.date)}{' '}
                    {horsPeriode && (
                      <StatutBadge
                        label={`⛔ Après la fin de la période ${periode.numero} (${formatDate(periode.date_fin)})`}
                        tone="error"
                      />
                    )}
                  </Td>
                </Tr>
              );
            })}
          </tbody>
        </Table>
      </div>
    </Section>
  );
}
