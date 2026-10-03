import { Link } from 'react-router-dom';
import AdminModal from './AdminModal';
import AdminButton from './AdminButton';
import StatutBadge from './ui/StatutBadge';
import Banner from './ui/Banner';
import { ADMIN_COLORS, ADMIN_SPACING } from '../styles/AdminDesignSystem';
import { coursDeSession, libelleSession } from '../utils/classes';
import { STATUTS_SESSION, TYPES_CALENDRIER, estSessionBarree, getStatut } from '../utils/statuts';
import { formatDateLongue, formatHoraire, nomJour } from '../utils/dates';

/**
 * Détail d'une session (lecture seule) ouvert depuis le calendrier.
 * Les ajustements (déplacer, annuler, bis) se font sur la page de la classe.
 *
 * @param {object} props
 * @param {object|null} props.session CourseSessionResource (avec `cours` de sa période)
 * @param {() => void} props.onClose
 */
export default function SessionDetailModal({ session, onClose }) {
  if (!session) return null;
  const classe = session.classe;
  const alerte = session.alerte_calendrier;

  return (
    <AdminModal
      isOpen
      size="sm"
      title={`${libelleSession(session)} — ${coursDeSession(session)?.titre || 'Session'}`}
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Fermer
          </AdminButton>
          {classe?.id && (
            <Link
              to={`/admin/classes/${classe.id}`}
              style={{
                display: 'inline-flex',
                alignItems: 'center',
                padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
                borderRadius: '8px',
                background: ADMIN_COLORS.primary,
                color: ADMIN_COLORS.cardBg,
                fontWeight: 600,
                textDecoration: 'none',
                fontSize: '14px',
              }}
            >
              Ouvrir la classe
            </Link>
          )}
        </>
      }
    >
      {alerte && (
        <Banner tone="warning">
          <strong>Date en conflit avec le calendrier scolaire :</strong> {getStatut(TYPES_CALENDRIER, alerte.type).label} ·{' '}
          {alerte.libelle}. Ajustez la session depuis la page de la classe.
        </Banner>
      )}
      <dl style={{ margin: 0, display: 'grid', gridTemplateColumns: 'auto 1fr', gap: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.lg}` }}>
        <dt style={{ color: ADMIN_COLORS.textSecondary }}>Date</dt>
        <dd style={{ margin: 0, textDecoration: estSessionBarree(session.statut) ? 'line-through' : undefined }}>
          {formatDateLongue(session.date)}
        </dd>
        <dt style={{ color: ADMIN_COLORS.textSecondary }}>Horaire</dt>
        <dd style={{ margin: 0 }}>{formatHoraire(session.heure_debut, session.heure_fin)}</dd>
        {classe?.jour_semaine && (
          <>
            <dt style={{ color: ADMIN_COLORS.textSecondary }}>Créneau</dt>
            <dd style={{ margin: 0 }}>Classe du {nomJour(classe.jour_semaine)}</dd>
          </>
        )}
        {session.lieu && (
          <>
            <dt style={{ color: ADMIN_COLORS.textSecondary }}>Lieu</dt>
            <dd style={{ margin: 0 }}>{session.lieu}</dd>
          </>
        )}
        {session.hors_periode && (
          <>
            <dt style={{ color: ADMIN_COLORS.textSecondary }}>Période</dt>
            <dd style={{ margin: 0 }}>
              <StatutBadge label="⚠ Hors période · rattrapage" tone="warning" />
            </dd>
          </>
        )}
        <dt style={{ color: ADMIN_COLORS.textSecondary }}>Statut</dt>
        <dd style={{ margin: 0 }}>
          <StatutBadge table={STATUTS_SESSION} valeur={session.statut} />
        </dd>
        {session.motif_annulation && (
          <>
            <dt style={{ color: ADMIN_COLORS.textSecondary }}>Motif</dt>
            <dd style={{ margin: 0 }}>{session.motif_annulation}</dd>
          </>
        )}
      </dl>
    </AdminModal>
  );
}
