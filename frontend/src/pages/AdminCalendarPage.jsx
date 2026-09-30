import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import CalendarView from '../components/CalendarView';
import SessionDetailModal from '../components/SessionDetailModal';
import AdminButton from '../components/AdminButton';
import LinkButton from '../components/ui/LinkButton';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import { FilterBar, FilterField } from '../components/ui/Filters';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../components/ui/DataStates';
import { useAnneesScolaires } from '../hooks/useAnneesScolaires';
import { useClasses } from '../hooks/useClasses';
import { useCours } from '../hooks/useCours';
import { useCalendrierScolaire } from '../hooks/useCalendrierScolaire';
import { useCalendarSessions } from '../hooks/useCalendarSessions';
import { useMediaQuery } from '../hooks/useMediaQuery';
import { libelleAnneeListe } from '../utils/statuts';
import {
  MOIS_LONGS,
  addDays,
  aujourdhuiISO,
  debutDeSemaine,
  formatDate,
  libelleClasse,
  parseDate,
  semaineISO,
  toISODate,
} from '../utils/dates';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES } from '../styles/AdminDesignSystem';

const VUES = [
  { id: 'mois', label: 'Mois' },
  { id: 'semaine', label: 'Semaine' },
  { id: 'agenda', label: 'Agenda' },
];
const JOURS_AGENDA = 30;

/** Décale la date de référence d'un « pas » de la vue (mois, semaine ou fenêtre d'agenda). */
function decaler(dateRef, vue, sens) {
  const d = parseDate(dateRef);
  if (vue === 'mois') return toISODate(new Date(d.getFullYear(), d.getMonth() + sens, 1));
  if (vue === 'semaine') return toISODate(addDays(d, 7 * sens));
  return toISODate(addDays(d, JOURS_AGENDA * sens));
}

/** Écran « Calendrier » : sessions de toutes les classes, filtrables par année, classe et cours (mock-up 08). */
export default function AdminCalendarPage() {
  const [params, setParams] = useSearchParams();
  const [session, setSession] = useState(null);
  const etroit = useMediaQuery('(max-width: 700px)');

  const vue = VUES.some((v) => v.id === params.get('vue')) ? params.get('vue') : 'mois';
  const dateRef = params.get('date') || aujourdhuiISO();
  const filtres = {
    annee_scolaire_id: params.get('annee_scolaire_id') || '',
    classe_id: params.get('classe_id') || '',
    cours_id: params.get('cours_id') || '',
  };

  const annees = useAnneesScolaires();
  const cours = useCours();
  const classes = useClasses({ annee_scolaire_id: filtres.annee_scolaire_id });
  const listeAnnees = useMemo(() => annees.data || [], [annees.data]);
  // Année dont on affiche les vacances/fériés : celle filtrée, sinon celle qui couvre la date affichée.
  const anneeCalendrier = filtres.annee_scolaire_id
    ? listeAnnees.find((a) => String(a.id) === filtres.annee_scolaire_id)
    : listeAnnees.find((a) => a.date_debut <= dateRef && dateRef <= a.date_fin);
  const entrees = useCalendrierScolaire(anneeCalendrier?.id ?? null);

  const ref = parseDate(dateRef);
  const periode = useMemo(() => {
    if (vue === 'mois') return { annee: ref.getFullYear(), mois: ref.getMonth() + 1 };
    if (vue === 'semaine') {
      const { annee, semaine } = semaineISO(ref);
      return { annee, semaine };
    }
    return { du: dateRef, au: toISODate(addDays(ref, JOURS_AGENDA)) };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [vue, dateRef]);
  const donnees = useCalendarSessions(vue, periode, filtres);

  function maj(cle, valeur) {
    const suivant = new URLSearchParams(params);
    if (valeur) suivant.set(cle, valeur);
    else suivant.delete(cle);
    if (cle === 'annee_scolaire_id') suivant.delete('classe_id');
    setParams(suivant, { replace: true });
  }

  const libellePeriode =
    vue === 'mois'
      ? `${MOIS_LONGS[ref.getMonth()]} ${ref.getFullYear()}`
      : vue === 'semaine'
        ? `semaine du ${formatDate(toISODate(debutDeSemaine(ref)))}`
        : `du ${formatDate(periode.du)} au ${formatDate(periode.au)}`;
  const libelleVide = vue === 'mois' ? `en ${libellePeriode}` : `sur la période (${libellePeriode})`;
  const precedent = decaler(dateRef, vue, -1);
  const suivant = decaler(dateRef, vue, 1);
  const abrege = (iso) => `${MOIS_LONGS[parseDate(iso).getMonth()].slice(0, 4)}.`;
  const navigation =
    vue === 'mois'
      ? { prev: `‹ ${abrege(precedent)}`, next: `${abrege(suivant)} ›`, aprev: 'Afficher le mois précédent', anext: 'Afficher le mois suivant' }
      : vue === 'semaine'
        ? { prev: '‹ Semaine', next: 'Semaine ›', aprev: 'Afficher la semaine précédente', anext: 'Afficher la semaine suivante' }
        : { prev: `‹ ${JOURS_AGENDA} jours`, next: `${JOURS_AGENDA} jours ›`, aprev: `Afficher les ${JOURS_AGENDA} jours précédents`, anext: `Afficher les ${JOURS_AGENDA} jours suivants` };

  let contenu;
  if (!donnees.data && !donnees.error) {
    contenu = <LoadingBlock message="Chargement du calendrier…" lignes={6} />;
  } else if (donnees.error) {
    contenu = <ErrorBlock message="Impossible de charger le calendrier. Réessayez." onRetry={donnees.reload} />;
  } else if (donnees.data.sessions.length === 0) {
    contenu = (
      <EmptyBlock
        icon="📆"
        title={`Aucune session pour ces filtres ${libelleVide}`}
        actions={<LinkButton to="/admin/classes/nouvelle" variant="primary">Créer une classe</LinkButton>}
      >
        Modifiez les filtres ou créez une classe pour générer des sessions.
      </EmptyBlock>
    );
  } else {
    contenu = (
      <CalendarView
        vue={vue}
        dateRef={dateRef}
        plage={periode}
        parJour={donnees.data.parJour}
        entreesScolaires={entrees.data || []}
        etroit={etroit}
        onSessionClick={setSession}
      />
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="📆"
        title="Calendrier"
        badge={libellePeriode}
        breadcrumb={<>Scolarité › Calendrier</>}
        description="Sessions de toutes les classes, avec les vacances et fermetures du calendrier scolaire."
      />

      <AdminPageContent>
        <FilterBar label="Filtres du calendrier">
          <FilterField
            id="cal-annee"
            label="Année scolaire"
            value={filtres.annee_scolaire_id}
            onChange={(v) => maj('annee_scolaire_id', v)}
            placeholder="Toutes les années"
            options={listeAnnees.map((a) => ({ value: String(a.id), label: libelleAnneeListe(a) }))}
          />
          <FilterField
            id="cal-classe"
            label="Classe"
            value={filtres.classe_id}
            onChange={(v) => maj('classe_id', v)}
            placeholder="Toutes les classes"
            options={(classes.data || []).map((c) => ({ value: String(c.id), label: libelleClasse(c) }))}
          />
          <FilterField
            id="cal-cours"
            label="Cours"
            value={filtres.cours_id}
            onChange={(v) => maj('cours_id', v)}
            placeholder="Tous les cours"
            options={(cours.data || []).map((c) => ({ value: String(c.id), label: c.titre }))}
          />
        </FilterBar>

        <div
          style={{
            display: 'flex',
            flexWrap: 'wrap',
            gap: ADMIN_SPACING.md,
            alignItems: 'center',
            justifyContent: 'space-between',
            marginBottom: ADMIN_SPACING.lg,
          }}
        >
          <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap', alignItems: 'center' }}>
            <AdminButton variant="secondary" size="sm" onClick={() => maj('date', precedent)} aria-label={navigation.aprev}>
              {navigation.prev}
            </AdminButton>
            <AdminButton variant="secondary" size="sm" onClick={() => maj('date', '')}>
              Aujourd'hui
            </AdminButton>
            <AdminButton variant="secondary" size="sm" onClick={() => maj('date', suivant)} aria-label={navigation.anext}>
              {navigation.next}
            </AdminButton>
            <h2 style={{ margin: `0 0 0 ${ADMIN_SPACING.md}`, fontSize: '18px' }} aria-live="polite">
              {libellePeriode.charAt(0).toUpperCase() + libellePeriode.slice(1)}
            </h2>
          </div>
          <div role="group" aria-label="Type de vue" style={{ display: 'flex', gap: ADMIN_SPACING.xs }}>
            {VUES.map((v) => (
              <AdminButton
                key={v.id}
                size="sm"
                variant={vue === v.id ? 'primary' : 'secondary'}
                aria-pressed={vue === v.id}
                onClick={() => maj('vue', v.id === 'mois' ? '' : v.id)}
              >
                {v.label}
              </AdminButton>
            ))}
          </div>
        </div>

        {contenu}

        <p style={{ marginTop: ADMIN_SPACING.lg, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
          <span
            style={{
              background: ADMIN_TONES.info.bg,
              color: ADMIN_TONES.info.fg,
              padding: '1px 6px',
              borderRadius: '4px',
            }}
          >
            Vacances / férié / fermeture
          </span>{' '}
          : entrées du calendrier scolaire, indiquées par leur libellé. Cliquez sur une session pour ses détails ;
          les ajustements se font depuis la page de la classe.
        </p>
      </AdminPageContent>

      <SessionDetailModal session={session} onClose={() => setSession(null)} />
    </>
  );
}
