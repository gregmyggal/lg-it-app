import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES, ADMIN_RADIUS } from '../styles/AdminDesignSystem';
import { coursDeSession, libelleSession } from '../utils/classes';
import { STATUTS_SESSION, TYPES_CALENDRIER, estSessionBarree, getStatut } from '../utils/statuts';
import {
  addDays,
  debutDeSemaine,
  formatDateCourte,
  formatHeure,
  parseDate,
  toISODate,
  aujourdhuiISO,
} from '../utils/dates';

const JOURS_ENTETE = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

/** Entrées du calendrier scolaire couvrant un jour donné. */
function entreesDuJour(entrees, iso) {
  return entrees.filter((e) => e.date_debut <= iso && iso <= e.date_fin);
}

/** Texte court d'une session pour une pastille du calendrier : « 14h P1 · Séance 5 · React ». */
function texteSession(s) {
  return `${formatHeure(s.heure_debut)} ${libelleSession(s)} · ${coursDeSession(s)?.titre || 'Cours'}${s.hors_periode ? ' · hors période' : ''}`;
}

function SessionChip({ session, onClick }) {
  const statut = getStatut(STATUTS_SESSION, session.statut);
  const couleurs = ADMIN_TONES[statut.tone] || ADMIN_TONES.neutral;
  const alerte = session.alerte_calendrier;
  return (
    <button
      type="button"
      onClick={() => onClick(session)}
      title={`${texteSession(session)} — ${statut.label}${alerte ? ` — ⚠ ${alerte.libelle}` : ''}`}
      style={{
        display: 'block',
        width: '100%',
        textAlign: 'left',
        marginBottom: ADMIN_SPACING.xs,
        padding: '3px 6px',
        borderRadius: ADMIN_RADIUS.sm,
        border: `1px solid ${alerte ? ADMIN_TONES.warning.border : couleurs.border}`,
        background: couleurs.bg,
        color: couleurs.fg,
        fontSize: '12px',
        fontWeight: 600,
        cursor: 'pointer',
        textDecoration: estSessionBarree(session.statut) ? 'line-through' : undefined,
        overflowWrap: 'anywhere',
        lineHeight: 1.3,
      }}
    >
      {alerte && <span aria-hidden="true">⚠ </span>}
      {texteSession(session)}
      <span className="sr-only"> — {statut.label}{alerte ? ` — date en conflit : ${alerte.libelle}` : ''}</span>
    </button>
  );
}

/** Libellé d'une entrée du calendrier scolaire ; `complet = false` : texte réservé aux lecteurs d'écran. */
function EntreeScolaire({ entree, complet = true }) {
  const type = getStatut(TYPES_CALENDRIER, entree.type);
  const couleurs = ADMIN_TONES[type.tone] || ADMIN_TONES.neutral;
  if (!complet) {
    return <span className="sr-only">{type.label} — {entree.libelle}</span>;
  }
  return (
    <div
      style={{
        marginBottom: ADMIN_SPACING.xs,
        padding: '2px 6px',
        borderRadius: ADMIN_RADIUS.sm,
        background: couleurs.bg,
        color: couleurs.fg,
        fontSize: '11px',
        fontWeight: 600,
      }}
    >
      {type.label} — {entree.libelle}
    </div>
  );
}

function CelluleJour({ iso, sessions, entrees, onSessionClick, estAutreMois, afficherJourSemaine }) {
  const jour = parseDate(iso);
  const auj = iso === aujourdhuiISO();
  const blocages = entreesDuJour(entrees, iso);
  const fondEntree = blocages[0] ? (ADMIN_TONES[getStatut(TYPES_CALENDRIER, blocages[0].type).tone] || ADMIN_TONES.neutral).bg : null;
  const lundi = jour.getDay() === 1;
  return (
    <div
      style={{
        minHeight: '110px',
        padding: ADMIN_SPACING.sm,
        borderRight: `1px solid ${ADMIN_COLORS.border}`,
        borderBottom: `1px solid ${ADMIN_COLORS.border}`,
        background: fondEntree || (estAutreMois ? ADMIN_COLORS.background : ADMIN_COLORS.cardBg),
        opacity: estAutreMois ? 0.7 : 1,
        minWidth: 0,
      }}
    >
      <div
        style={{
          fontSize: '13px',
          fontWeight: 700,
          marginBottom: ADMIN_SPACING.xs,
          color: auj ? ADMIN_COLORS.primary : ADMIN_COLORS.textPrimary,
        }}
      >
        {afficherJourSemaine ? `${JOURS_ENTETE[(jour.getDay() + 6) % 7]} ` : ''}
        {jour.getDate()}
        {auj && <span className="sr-only"> (aujourd'hui)</span>}
      </div>
      {blocages.map((e) => (
        <EntreeScolaire key={e.id} entree={e} complet={Boolean(e.date_debut === iso || lundi || afficherJourSemaine)} />
      ))}
      {sessions.map((s) => (
        <SessionChip key={s.id} session={s} onClick={onSessionClick} />
      ))}
    </div>
  );
}

function GrilleSemaines({ jours, parJour, entrees, onSessionClick, moisRef, afficherJourSemaine }) {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(7, minmax(0, 1fr))',
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRight: 0,
        borderBottom: 0,
        borderRadius: ADMIN_RADIUS.md,
        overflow: 'hidden',
        background: ADMIN_COLORS.cardBg,
      }}
    >
      {!afficherJourSemaine &&
        JOURS_ENTETE.map((j) => (
          <div
            key={j}
            style={{
              padding: ADMIN_SPACING.sm,
              textAlign: 'center',
              fontSize: '12px',
              fontWeight: 700,
              color: ADMIN_COLORS.textSecondary,
              background: ADMIN_COLORS.background,
              borderRight: `1px solid ${ADMIN_COLORS.border}`,
              borderBottom: `1px solid ${ADMIN_COLORS.border}`,
            }}
          >
            {j}
          </div>
        ))}
      {jours.map((iso) => (
        <CelluleJour
          key={iso}
          iso={iso}
          sessions={parJour[iso] || []}
          entrees={entrees}
          onSessionClick={onSessionClick}
          estAutreMois={moisRef !== undefined && parseDate(iso).getMonth() !== moisRef}
          afficherJourSemaine={afficherJourSemaine}
        />
      ))}
    </div>
  );
}

/** Liste chronologique (agenda, et vue mobile du mois/semaine) : un bloc par jour ayant du contenu. */
function ListeAgenda({ jours, parJour, entrees, onSessionClick }) {
  const utiles = jours.filter((iso) => (parJour[iso] || []).length > 0 || entreesDuJour(entrees, iso).length > 0);
  if (utiles.length === 0) return null;
  return (
    <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: ADMIN_SPACING.md }}>
      {utiles.map((iso) => (
        <li
          key={iso}
          style={{
            background: ADMIN_COLORS.cardBg,
            border: `1px solid ${ADMIN_COLORS.border}`,
            borderRadius: ADMIN_RADIUS.md,
            padding: ADMIN_SPACING.md,
          }}
        >
          <h3 style={{ margin: `0 0 ${ADMIN_SPACING.sm}`, fontSize: '14px' }}>{formatDateCourte(iso)}</h3>
          {entreesDuJour(entrees, iso).map((e) => (
            <EntreeScolaire key={e.id} entree={e} />
          ))}
          {(parJour[iso] || []).map((s) => (
            <SessionChip key={s.id} session={s} onClick={onSessionClick} />
          ))}
        </li>
      ))}
    </ol>
  );
}

/** Liste de jours ISO entre deux dates incluses. */
function joursEntre(debut, fin) {
  const liste = [];
  for (let d = parseDate(debut); toISODate(d) <= fin; d = addDays(d, 1)) liste.push(toISODate(d));
  return liste;
}

/**
 * Calendrier des sessions : vue mensuelle, hebdomadaire ou agenda.
 * Présentation seule : les données sont chargées par la page (hook `useCalendarSessions`).
 * Sur écran étroit, le mois et la semaine s'affichent en liste par jour.
 *
 * @param {object} props
 * @param {'mois'|'semaine'|'agenda'} props.vue
 * @param {string} props.dateRef date de référence (YYYY-MM-DD)
 * @param {{du: string, au: string}} props.plage plage affichée (agenda)
 * @param {Record<string, object[]>} props.parJour sessions groupées par date
 * @param {object[]} props.entreesScolaires vacances/fériés/fermetures à afficher
 * @param {boolean} props.etroit écran étroit
 * @param {(session: object) => void} props.onSessionClick
 */
export default function CalendarView({ vue, dateRef, plage, parJour, entreesScolaires, etroit, onSessionClick }) {
  const ref = parseDate(dateRef);

  if (vue === 'agenda') {
    return (
      <ListeAgenda
        jours={joursEntre(plage.du, plage.au)}
        parJour={parJour}
        entrees={entreesScolaires}
        onSessionClick={onSessionClick}
      />
    );
  }

  if (vue === 'semaine') {
    const lundi = debutDeSemaine(ref);
    const jours = Array.from({ length: 7 }, (_, i) => toISODate(addDays(lundi, i)));
    return etroit ? (
      <ListeAgenda jours={jours} parJour={parJour} entrees={entreesScolaires} onSessionClick={onSessionClick} />
    ) : (
      <GrilleSemaines jours={jours} parJour={parJour} entrees={entreesScolaires} onSessionClick={onSessionClick} afficherJourSemaine />
    );
  }

  // Mois : semaines complètes (lundi → dimanche) couvrant le mois.
  const premier = new Date(ref.getFullYear(), ref.getMonth(), 1);
  const dernier = new Date(ref.getFullYear(), ref.getMonth() + 1, 0);
  const debut = debutDeSemaine(premier);
  const fin = addDays(debutDeSemaine(dernier), 6);
  const jours = joursEntre(toISODate(debut), toISODate(fin));
  const joursDuMois = jours.filter((iso) => parseDate(iso).getMonth() === ref.getMonth());

  return etroit ? (
    <ListeAgenda jours={joursDuMois} parJour={parJour} entrees={entreesScolaires} onSessionClick={onSessionClick} />
  ) : (
    <GrilleSemaines
      jours={jours}
      parJour={parJour}
      entrees={entreesScolaires}
      onSessionClick={onSessionClick}
      moisRef={ref.getMonth()}
    />
  );
}
