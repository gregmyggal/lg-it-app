import { useEffect, useState } from 'react';
import client from '../api/client';
import AdminButton from './AdminButton';
import { ADMIN_COLORS, ADMIN_SPACING } from '../styles/AdminDesignSystem';

export default function CoursAssignmentModal({
  professeurId,
  onSuccess,
  onCancel,
}) {
  const [allCours, setAllCours] = useState([]);
  const [selected, setSelected] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searching, setSearching] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    loadCours();
  }, []);

  async function loadCours() {
    try {
      const res = await client.get('/cours');
      setAllCours(res.data || []);
      setLoading(false);
    } catch (err) {
      console.error('Erreur lors du chargement des cours:', err);
      setLoading(false);
    }
  }

  const filteredCours = searchQuery
    ? allCours.filter(
        (c) =>
          c.titre.toLowerCase().includes(searchQuery.toLowerCase()) ||
          c.slug.toLowerCase().includes(searchQuery.toLowerCase()),
      )
    : allCours;

  async function handleSubmit() {
    if (selected.length === 0) {
      alert('Sélectionnez au moins un cours');
      return;
    }

    setSubmitting(true);

    try {
      await client.post(`/professeurs/${professeurId}/cours`, {
        courses: selected.map((course) => ({
          id: course.id,
          role: course.role || 'co-enseignant',
          date_debut: course.date_debut || new Date().toISOString().split('T')[0],
          date_fin: course.date_fin || null,
        })),
      });

      onSuccess();
    } catch (err) {
      console.error('Erreur lors de l\'assignation:', err);
      alert('Erreur lors de l\'assignation');
    } finally {
      setSubmitting(false);
    }
  }

  const handleToggleCours = (coursId) => {
    setSelected((prev) => {
      const exists = prev.find((c) => c.id === coursId);
      if (exists) {
        return prev.filter((c) => c.id !== coursId);
      } else {
        return [...prev, { id: coursId, role: 'co-enseignant' }];
      }
    });
  };

  const handleRoleChange = (coursId, role) => {
    setSelected((prev) =>
      prev.map((c) => (c.id === coursId ? { ...c, role } : c)),
    );
  };

  return (
    <div
      style={{
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        background: 'rgba(0, 0, 0, 0.5)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000,
      }}
      onClick={onCancel}
    >
      <div
        style={{
          background: 'white',
          borderRadius: '12px',
          padding: ADMIN_SPACING.xl,
          maxWidth: '600px',
          width: '90%',
          maxHeight: '90vh',
          overflow: 'auto',
          boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.2)',
        }}
        onClick={(e) => e.stopPropagation()}
      >
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginBottom: ADMIN_SPACING.lg,
          }}
        >
          <h2 style={{ margin: 0, fontSize: '20px', fontWeight: 700 }}>
            🎓 Ajouter un Cours
          </h2>
          <button
            onClick={onCancel}
            style={{
              background: 'none',
              border: 'none',
              fontSize: '24px',
              cursor: 'pointer',
              padding: 0,
              width: '32px',
              height: '32px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            ✕
          </button>
        </div>

        {/* Recherche */}
        <div style={{ marginBottom: ADMIN_SPACING.lg }}>
          <label
            style={{
              display: 'block',
              fontSize: '12px',
              fontWeight: 600,
              marginBottom: ADMIN_SPACING.sm,
              textTransform: 'uppercase',
              color: ADMIN_COLORS.textPrimary,
            }}
          >
            Chercher un cours
          </label>
          <input
            type="text"
            placeholder="React Fondamentaux, Angular..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            style={{
              width: '100%',
              padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.lg}`,
              border: `1px solid ${ADMIN_COLORS.border}`,
              borderRadius: '8px',
              fontSize: '14px',
              fontFamily: 'inherit',
            }}
          />
        </div>

        {/* Liste Cours */}
        <div
          style={{
            background: '#f9fafb',
            borderRadius: '8px',
            border: `1px solid ${ADMIN_COLORS.border}`,
            maxHeight: '300px',
            overflowY: 'auto',
            marginBottom: ADMIN_SPACING.lg,
          }}
        >
          {loading ? (
            <div style={{ padding: ADMIN_SPACING.lg, textAlign: 'center', color: '#6b7280' }}>
              Chargement…
            </div>
          ) : filteredCours.length === 0 ? (
            <div style={{ padding: ADMIN_SPACING.lg, textAlign: 'center', color: '#9ca3af' }}>
              📭 Aucun cours trouvé
            </div>
          ) : (
            filteredCours.map((cours) => {
              const isSelected = selected.some((c) => c.id === cours.id);
              const selectedCours = selected.find((c) => c.id === cours.id);

              return (
                <div
                  key={cours.id}
                  style={{
                    padding: ADMIN_SPACING.lg,
                    borderBottom: `1px solid ${ADMIN_COLORS.border}`,
                    background: isSelected ? '#dbeafe' : 'white',
                    cursor: 'pointer',
                    display: 'flex',
                    gap: ADMIN_SPACING.lg,
                    alignItems: 'flex-start',
                  }}
                  onClick={() => handleToggleCours(cours.id)}
                >
                  <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={() => {}}
                    style={{
                      width: '20px',
                      height: '20px',
                      marginTop: '2px',
                      cursor: 'pointer',
                    }}
                    onClick={(e) => e.stopPropagation()}
                  />

                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div
                      style={{
                        fontWeight: 600,
                        color: ADMIN_COLORS.textPrimary,
                        marginBottom: '4px',
                        wordBreak: 'break-word',
                      }}
                    >
                      {cours.titre}
                    </div>
                    {cours.slug && (
                      <div
                        style={{
                          fontSize: '12px',
                          color: '#6b7280',
                          marginBottom: '6px',
                      }}
                      >
                        {cours.slug}
                      </div>
                    )}

                    {isSelected && (
                      <div
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          gap: ADMIN_SPACING.md,
                          marginTop: ADMIN_SPACING.md,
                        }}
                        onClick={(e) => e.stopPropagation()}
                      >
                        <label
                          style={{
                            fontSize: '12px',
                            color: '#6b7280',
                            fontWeight: 600,
                          }}
                        >
                          Rôle:
                        </label>
                        <select
                          value={selectedCours?.role || 'co-enseignant'}
                          onChange={(e) => handleRoleChange(cours.id, e.target.value)}
                          style={{
                            padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                            border: `1px solid ${ADMIN_COLORS.border}`,
                            borderRadius: '4px',
                            fontSize: '13px',
                          }}
                        >
                          <option value="principal">👑 Principal</option>
                          <option value="co-enseignant">👥 Co-enseignant</option>
                          <option value="remplaçant">🔄 Remplaçant</option>
                        </select>
                      </div>
                    )}
                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Résumé Sélection */}
        {selected.length > 0 && (
          <div
            style={{
              background: '#dbeafe',
              border: `1px solid ${ADMIN_COLORS.primary}`,
              borderRadius: '8px',
              padding: ADMIN_SPACING.lg,
              marginBottom: ADMIN_SPACING.lg,
            }}
          >
            <div style={{ fontSize: '14px', fontWeight: 600, marginBottom: ADMIN_SPACING.md }}>
              ✓ {selected.length} cours sélectionné{selected.length > 1 ? 's' : ''}
            </div>
            <div style={{ fontSize: '12px', color: '#1e40af' }}>
              {selected
                .map((c) => {
                  const cours = allCours.find((a) => a.id === c.id);
                  return `${cours?.titre} (${c.role})`;
                })
                .join(', ')}
            </div>
          </div>
        )}

        {/* Boutons */}
        <div
          style={{
            display: 'flex',
            gap: ADMIN_SPACING.lg,
            justifyContent: 'flex-end',
          }}
        >
          <AdminButton variant="secondary" onClick={onCancel} disabled={submitting}>
            Annuler
          </AdminButton>

          <AdminButton
            variant="primary"
            onClick={handleSubmit}
            loading={submitting}
            disabled={selected.length === 0 || submitting}
          >
            {submitting ? 'Assignation...' : `Assigner ${selected.length} cours`}
          </AdminButton>
        </div>
      </div>
    </div>
  );
}
