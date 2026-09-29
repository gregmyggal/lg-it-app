import { useState, useEffect } from 'react';
import AdminButton from './AdminButton';
import { ADMIN_COLORS, ADMIN_SPACING } from '../styles/AdminDesignSystem';
import client from '../api/client';

export default function SessionAssignmentModal({ session, isOpen, onClose, onSave }) {
  const [professors, setProfessors] = useState([]);
  const [assigned, setAssigned] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState('');

  useEffect(() => {
    if (session && isOpen) {
      loadData();
    }
  }, [session, isOpen]);

  const loadData = async () => {
    setLoading(true);
    try {
      // Load all professors
      const profsRes = await client.get('/professeurs');
      setProfessors(profsRes.data || []);

      // Load current assignments
      const assignRes = await client.get(`/sessions/${session.id}/professors`);
      setAssigned(assignRes.data.data || []);
    } catch (err) {
      setError('Erreur lors du chargement des données');
    } finally {
      setLoading(false);
    }
  };

  const filteredProfessors = professors.filter((prof) => {
    const query = search.toLowerCase();
    return (
      prof.prenom.toLowerCase().includes(query) ||
      prof.nom.toLowerCase().includes(query) ||
      prof.email.toLowerCase().includes(query)
    );
  });

  const handleAddProfessor = (prof, role = 'assistant') => {
    const exists = assigned.find((a) => a.professeur_id === prof.id && a.role === role);
    if (!exists) {
      setAssigned([...assigned, { professeur_id: prof.id, role, professeur: prof }]);
    }
  };

  const handleRemoveProfessor = (assignmentIdx) => {
    setAssigned(assigned.filter((_, idx) => idx !== assignmentIdx));
  };

  const handleRoleChange = (idx, newRole) => {
    const newAssigned = [...assigned];
    newAssigned[idx].role = newRole;
    setAssigned(newAssigned);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      await client.post(`/sessions/${session.id}/professors/bulk`, {
        professors: assigned.map((a) => ({
          professeur_id: a.professeur_id,
          role: a.role,
        })),
      });
      onSave?.();
      onClose();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la sauvegarde');
    } finally {
      setLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div
      style={{
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        background: 'rgba(0,0,0,0.5)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000,
      }}
      onClick={onClose}
    >
      <div
        style={{
          background: 'white',
          borderRadius: '8px',
          padding: ADMIN_SPACING.lg,
          maxWidth: '700px',
          width: '90%',
          maxHeight: '90vh',
          overflowY: 'auto',
        }}
        onClick={(e) => e.stopPropagation()}
      >
        <h2 style={{ margin: '0 0 20px 0', fontSize: '20px', fontWeight: 700 }}>
          👨‍🏫 Assigner des professeurs
        </h2>

        {error && (
          <div
            style={{
              background: '#fee2e2',
              color: ADMIN_COLORS.error,
              padding: ADMIN_SPACING.md,
              borderRadius: '6px',
              marginBottom: ADMIN_SPACING.md,
            }}
          >
            ⚠️ {error}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: ADMIN_SPACING.lg }}>
            {/* Available Professors */}
            <div>
              <h3 style={{ margin: '0 0 12px 0', fontSize: '14px', fontWeight: 600 }}>
                Professeurs disponibles
              </h3>

              <input
                type="text"
                placeholder="Rechercher..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                style={{
                  width: '100%',
                  padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  marginBottom: ADMIN_SPACING.md,
                  fontSize: '14px',
                }}
              />

              <div
                style={{
                  background: '#fafafa',
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  maxHeight: '400px',
                  overflowY: 'auto',
                }}
              >
                {filteredProfessors.length === 0 ? (
                  <div style={{ padding: ADMIN_SPACING.md, color: '#9ca3af', textAlign: 'center' }}>
                    Aucun professeur trouvé
                  </div>
                ) : (
                  filteredProfessors.map((prof) => (
                    <div
                      key={prof.id}
                      style={{
                        padding: ADMIN_SPACING.md,
                        borderBottom: `1px solid ${ADMIN_COLORS.border}`,
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                      }}
                    >
                      <div>
                        <div style={{ fontWeight: 600, fontSize: '13px' }}>
                          {prof.prenom} {prof.nom}
                        </div>
                        <div style={{ fontSize: '12px', color: '#6b7280' }}>{prof.email}</div>
                      </div>
                      <select
                        onChange={(e) => handleAddProfessor(prof, e.target.value)}
                        style={{
                          padding: `4px 8px`,
                          border: `1px solid ${ADMIN_COLORS.border}`,
                          borderRadius: '4px',
                          fontSize: '12px',
                        }}
                      >
                        <option value="">Ajouter...</option>
                        <option value="principal">👑 Principal</option>
                        <option value="assistant">👥 Assistant</option>
                        <option value="substitute">🔄 Remplaçant</option>
                        <option value="observer">👁️ Observateur</option>
                      </select>
                    </div>
                  ))
                )}
              </div>
            </div>

            {/* Assigned Professors */}
            <div>
              <h3 style={{ margin: '0 0 12px 0', fontSize: '14px', fontWeight: 600 }}>
                Assignés ({assigned.length})
              </h3>

              <div
                style={{
                  background: '#f0fdf4',
                  border: `1px solid #bbf7d0`,
                  borderRadius: '6px',
                  maxHeight: '400px',
                  overflowY: 'auto',
                }}
              >
                {assigned.length === 0 ? (
                  <div style={{ padding: ADMIN_SPACING.md, color: '#6b7280', textAlign: 'center' }}>
                    📭 Aucun professeur assigné
                  </div>
                ) : (
                  assigned.map((assignment, idx) => (
                    <div
                      key={idx}
                      style={{
                        padding: ADMIN_SPACING.md,
                        borderBottom: `1px solid #bbf7d0`,
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                      }}
                    >
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontWeight: 600, fontSize: '13px' }}>
                          {assignment.professeur?.prenom} {assignment.professeur?.nom}
                        </div>
                      </div>
                      <select
                        value={assignment.role}
                        onChange={(e) => handleRoleChange(idx, e.target.value)}
                        style={{
                          padding: `4px 8px`,
                          border: `1px solid ${ADMIN_COLORS.border}`,
                          borderRadius: '4px',
                          fontSize: '12px',
                          marginRight: ADMIN_SPACING.sm,
                        }}
                      >
                        <option value="principal">👑 Principal</option>
                        <option value="assistant">👥 Assistant</option>
                        <option value="substitute">🔄 Remplaçant</option>
                        <option value="observer">👁️ Observateur</option>
                      </select>
                      <button
                        type="button"
                        onClick={() => handleRemoveProfessor(idx)}
                        style={{
                          background: '#fee2e2',
                          color: ADMIN_COLORS.error,
                          border: 'none',
                          padding: '4px 8px',
                          borderRadius: '4px',
                          cursor: 'pointer',
                          fontSize: '12px',
                          fontWeight: 600,
                        }}
                      >
                        ✕
                      </button>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>

          {/* Buttons */}
          <div style={{ display: 'flex', gap: ADMIN_SPACING.md, justifyContent: 'flex-end', marginTop: ADMIN_SPACING.lg }}>
            <AdminButton variant="secondary" onClick={onClose}>
              Annuler
            </AdminButton>
            <AdminButton variant="primary" type="submit" disabled={loading}>
              {loading ? '⏳ Enregistrement...' : '💾 Enregistrer'}
            </AdminButton>
          </div>
        </form>
      </div>
    </div>
  );
}
