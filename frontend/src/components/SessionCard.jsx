import { useNavigate } from 'react-router-dom';
import AdminButton from './AdminButton';
import { ADMIN_COLORS, ADMIN_SPACING } from '../styles/AdminDesignSystem';

const statusConfig = {
  scheduled: { bg: '#dbeafe', text: '#0c4a6e', emoji: '📅' },
  in_progress: { bg: '#fef3c7', text: '#78350f', emoji: '⏱️' },
  completed: { bg: '#d1fae5', text: '#065f46', emoji: '✅' },
  cancelled: { bg: '#fee2e2', text: '#7f1d1d', emoji: '❌' },
};

export default function SessionCard({ session, onDelete, onEdit }) {
  const navigate = useNavigate();
  const config = statusConfig[session.statut] || statusConfig.scheduled;

  const formatTime = (time) => {
    if (!time) return '—';
    return time.substring(0, 5); // HH:mm
  };

  const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('fr-FR', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
    });
  };

  return (
    <div
      style={{
        background: 'white',
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRadius: '8px',
        overflow: 'hidden',
        boxShadow: '0 1px 2px rgba(0, 0, 0, 0.05)',
        transition: 'all 0.2s',
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.boxShadow = '0 1px 2px rgba(0, 0, 0, 0.05)';
      }}
    >
      {/* En-tête avec statut */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: ADMIN_SPACING.md,
          background: config.bg,
          borderBottom: `1px solid ${ADMIN_COLORS.border}`,
        }}
      >
        <div style={{ flex: 1, minWidth: 0 }}>
          <h4
            style={{
              margin: '0 0 4px 0',
              fontSize: '15px',
              fontWeight: 600,
              color: ADMIN_COLORS.textPrimary,
              wordBreak: 'break-word',
            }}
          >
            {session.titre || session.cours?.titre || 'Session sans titre'}
          </h4>
        </div>
        <div
          style={{
            background: config.bg,
            color: config.text,
            padding: `4px ${ADMIN_SPACING.sm}`,
            borderRadius: '4px',
            fontSize: '11px',
            fontWeight: 600,
            whiteSpace: 'nowrap',
            marginLeft: ADMIN_SPACING.md,
          }}
        >
          {config.emoji} {session.statut}
        </div>
      </div>

      {/* Contenu */}
      <div style={{ padding: ADMIN_SPACING.md }}>
        {/* Date et heure */}
        <div
          style={{
            display: 'flex',
            gap: ADMIN_SPACING.lg,
            marginBottom: ADMIN_SPACING.md,
            fontSize: '13px',
          }}
        >
          <div>
            <div style={{ fontWeight: 600, color: '#6b7280', fontSize: '11px', marginBottom: '2px' }}>
              DATE
            </div>
            <div style={{ color: ADMIN_COLORS.textPrimary }}>
              {formatDate(session.date_debut)}
            </div>
          </div>
          <div>
            <div style={{ fontWeight: 600, color: '#6b7280', fontSize: '11px', marginBottom: '2px' }}>
              HORAIRE
            </div>
            <div style={{ color: ADMIN_COLORS.textPrimary }}>
              {formatTime(session.heure_debut)} - {formatTime(session.heure_fin)}
            </div>
          </div>
        </div>

        {/* Lieu */}
        {session.lieu && (
          <div style={{ marginBottom: ADMIN_SPACING.md, fontSize: '13px' }}>
            <div style={{ fontWeight: 600, color: '#6b7280', fontSize: '11px', marginBottom: '2px' }}>
              📍 LIEU
            </div>
            <div style={{ color: ADMIN_COLORS.textPrimary }}>{session.lieu}</div>
          </div>
        )}

        {/* Professeurs */}
        {session.professeurs && session.professeurs.length > 0 && (
          <div style={{ marginBottom: ADMIN_SPACING.md }}>
            <div style={{ fontWeight: 600, color: '#6b7280', fontSize: '11px', marginBottom: '6px' }}>
              👨‍🏫 PROFESSEURS
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: ADMIN_SPACING.sm }}>
              {session.professeurs.map((prof) => (
                <div
                  key={prof.id}
                  style={{
                    background: '#f3f4f6',
                    padding: `4px ${ADMIN_SPACING.sm}`,
                    borderRadius: '4px',
                    fontSize: '12px',
                    cursor: 'pointer',
                    border: `1px solid ${ADMIN_COLORS.border}`,
                    transition: 'all 0.2s',
                  }}
                  onClick={() => navigate(`/admin/professeurs/${prof.id}`)}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.background = ADMIN_COLORS.primary;
                    e.currentTarget.style.color = 'white';
                    e.currentTarget.style.borderColor = ADMIN_COLORS.primary;
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.background = '#f3f4f6';
                    e.currentTarget.style.color = 'inherit';
                    e.currentTarget.style.borderColor = ADMIN_COLORS.border;
                  }}
                >
                  {prof.pivot?.role === 'principal' ? '👑' : '👥'} {prof.prenom} {prof.nom}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Élèves */}
        {(session.nb_eleves_attendus || session.nb_eleves_presentes) && (
          <div style={{ marginBottom: ADMIN_SPACING.md, fontSize: '13px' }}>
            <div style={{ fontWeight: 600, color: '#6b7280', fontSize: '11px', marginBottom: '2px' }}>
              👥 ÉLÈVES
            </div>
            <div style={{ color: ADMIN_COLORS.textPrimary }}>
              {session.nb_eleves_presentes !== null
                ? `${session.nb_eleves_presentes} présents`
                : `${session.nb_eleves_attendus} attendus`}
            </div>
          </div>
        )}
      </div>

      {/* Actions */}
      <div
        style={{
          display: 'flex',
          gap: ADMIN_SPACING.md,
          padding: ADMIN_SPACING.md,
          borderTop: `1px solid ${ADMIN_COLORS.border}`,
          background: '#fafafa',
        }}
      >
        <AdminButton
          variant="secondary"
          size="sm"
          fullWidth
          onClick={() => onEdit?.(session)}
        >
          ✏️ Éditer
        </AdminButton>
        <AdminButton
          variant="danger"
          size="sm"
          icon="🗑️"
          onClick={() => onDelete?.(session.id)}
        />
      </div>
    </div>
  );
}
