import { useNavigate } from 'react-router-dom';
import AdminButton from './AdminButton';
import { ADMIN_COLORS, ADMIN_SPACING } from '../styles/AdminDesignSystem';

export default function ProfesseurCoursCard({ cours, onRemove }) {
  const navigate = useNavigate();

  if (!cours) {
    return null;
  }

  const roleEmoji = {
    principal: '👑',
    'co-enseignant': '👥',
    'remplaçant': '🔄',
  };

  const roleLabel = {
    principal: 'Principal',
    'co-enseignant': 'Co-enseignant',
    'remplaçant': 'Remplaçant',
  };

  const role = cours.pivot?.role || 'co-enseignant';
  const dateDebut = cours.pivot?.date_debut;
  const dateFin = cours.pivot?.date_fin;

  // Filtrer les co-professeurs (professeurs autres que le professeur actuel)
  const coProfesseurs = cours.professeurs?.filter(
    (p) => p.pivot?.role !== 'principal' || p.pivot?.role === 'principal',
  ) || [];

  return (
    <div
      style={{
        background: 'white',
        border: `1px solid ${ADMIN_COLORS.border}`,
        borderRadius: '8px',
        padding: ADMIN_SPACING.lg,
        display: 'flex',
        flexDirection: 'column',
        gap: ADMIN_SPACING.md,
        boxShadow: '0 1px 2px rgba(0, 0, 0, 0.05)',
      }}
    >
      {/* Titre & Rôle */}
      <div>
        <div
          style={{
            display: 'flex',
            alignItems: 'flex-start',
            gap: ADMIN_SPACING.md,
            marginBottom: ADMIN_SPACING.sm,
          }}
        >
          <div style={{ flex: 1, minWidth: 0 }}>
            <h4
              style={{
                margin: 0,
                fontSize: '16px',
                fontWeight: 600,
                color: ADMIN_COLORS.textPrimary,
                wordBreak: 'break-word',
              }}
            >
              {cours.titre}
            </h4>
          </div>
          <div
            style={{
              background:
                role === 'principal' ? '#10b981' : '#f59e0b',
              color: 'white',
              padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
              borderRadius: '4px',
              fontSize: '12px',
              fontWeight: 600,
              whiteSpace: 'nowrap',
            }}
          >
            {roleEmoji[role]} {roleLabel[role]}
          </div>
        </div>

        {/* Slug/Référence */}
        {cours.slug && (
          <div style={{ fontSize: '12px', color: '#6b7280', marginBottom: ADMIN_SPACING.sm }}>
            {cours.slug}
          </div>
        )}

        {/* Dates */}
        <div
          style={{
            fontSize: '13px',
            color: '#6b7280',
          }}
        >
          📅 {dateDebut ? new Date(dateDebut).toLocaleDateString('fr-FR') : '—'}
          {dateFin ? ` → ${new Date(dateFin).toLocaleDateString('fr-FR')}` : ' → ∞'}
        </div>
      </div>

      {/* Co-professeurs */}
      {coProfesseurs.length > 0 && (
        <div>
          <div style={{ fontSize: '12px', fontWeight: 600, color: '#6b7280', marginBottom: '6px' }}>
            👥 CO-PROFESSEURS
          </div>
          <div
            style={{
              display: 'flex',
              flexWrap: 'wrap',
              gap: ADMIN_SPACING.sm,
            }}
          >
            {coProfesseurs.map((prof) => (
              <div
                key={prof.id}
                onClick={() => navigate(`/admin/professeurs/${prof.id}`)}
                style={{
                  background: '#f3f4f6',
                  padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                  borderRadius: '4px',
                  fontSize: '12px',
                  color: ADMIN_COLORS.textSecondary,
                  cursor: 'pointer',
                  transition: 'all 0.2s',
                  border: `1px solid ${ADMIN_COLORS.border}`,
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.background = ADMIN_COLORS.primary;
                  e.currentTarget.style.color = 'white';
                  e.currentTarget.style.borderColor = ADMIN_COLORS.primary;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.background = '#f3f4f6';
                  e.currentTarget.style.color = ADMIN_COLORS.textSecondary;
                  e.currentTarget.style.borderColor = ADMIN_COLORS.border;
                }}
              >
                <span style={{ marginRight: '4px' }}>
                  {prof.pivot?.role === 'principal' ? '👑' : '👥'}
                </span>
                {prof.prenom} {prof.nom}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Actions */}
      <div
        style={{
          display: 'flex',
          gap: ADMIN_SPACING.md,
          paddingTop: ADMIN_SPACING.md,
          borderTop: `1px solid ${ADMIN_COLORS.border}`,
        }}
      >
        <AdminButton
          variant="secondary"
          size="sm"
          fullWidth
          onClick={() => navigate(`/admin/cours/${cours.id}/ressources`)}
        >
          📎 Ressources
        </AdminButton>

        <AdminButton
          variant="danger"
          size="sm"
          icon="🗑️"
          onClick={onRemove}
        />
      </div>
    </div>
  );
}
