import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import client from '../api/client';
import AdminButton from '../components/AdminButton';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import { ADMIN_COLORS, ADMIN_SPACING } from '../styles/AdminDesignSystem';
import SessionCard from '../components/SessionCard';
import SessionDetailModal from '../components/SessionDetailModal';
import SessionAssignmentModal from '../components/SessionAssignmentModal';

export default function AdminCoursSessionsPage() {
  const { coursId } = useParams();
  const [cours, setCours] = useState(null);
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [selectedSession, setSelectedSession] = useState(null);
  const [showSessionModal, setShowSessionModal] = useState(false);
  const [showAssignmentModal, setShowAssignmentModal] = useState(false);

  useEffect(() => {
    loadData();
  }, [coursId]);

  const loadData = async () => {
    setLoading(true);
    try {
      // Load course details
      const coursRes = await client.get(`/cours/${coursId}`);
      setCours(coursRes.data);

      // Load sessions for this course
      const sessionsRes = await client.get(`/cours/${coursId}/sessions`);
      setSessions(sessionsRes.data.data || []);

      setError(null);
    } catch (err) {
      setError('Erreur lors du chargement des données');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleAddSession = () => {
    setSelectedSession({ cours_id: parseInt(coursId) });
    setShowSessionModal(true);
  };

  const handleEditSession = (session) => {
    setSelectedSession(session);
    setShowSessionModal(true);
  };

  const handleDeleteSession = async (sessionId) => {
    if (!window.confirm('Êtes-vous sûr de vouloir supprimer cette session ?')) {
      return;
    }

    try {
      await client.delete(`/sessions/${sessionId}`);
      setSessions(sessions.filter((s) => s.id !== sessionId));
    } catch (err) {
      alert('Erreur lors de la suppression');
    }
  };

  const handleAssignProfessors = (session) => {
    setSelectedSession(session);
    setShowAssignmentModal(true);
  };

  const handleSave = () => {
    loadData();
  };

  if (loading && !cours) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: '#6b7280' }}>
        Chargement…
      </div>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="📅"
        title={cours?.titre || 'Sessions du cours'}
        description="Gérez les sessions, programmez les dates et assignez les professeurs"
        badge={`${sessions.length} sessions`}
      />

      <AdminPageContent>
        {error && (
          <div
            style={{
              background: '#fee2e2',
              color: ADMIN_COLORS.error,
              padding: ADMIN_SPACING.md,
              borderRadius: '8px',
              marginBottom: ADMIN_SPACING.lg,
            }}
          >
            ⚠️ {error}
          </div>
        )}

        {/* Actions */}
        <div style={{ display: 'flex', gap: ADMIN_SPACING.md, marginBottom: ADMIN_SPACING.lg }}>
          <AdminButton variant="primary" icon="➕" onClick={handleAddSession}>
            Nouvelle session
          </AdminButton>
        </div>

        {/* Sessions */}
        {sessions.length === 0 ? (
          <div
            style={{
              textAlign: 'center',
              padding: '40px 20px',
              background: '#f9fafb',
              borderRadius: '8px',
              border: `1px solid ${ADMIN_COLORS.border}`,
            }}
          >
            <div style={{ fontSize: '32px', marginBottom: '12px' }}>📭</div>
            <div style={{ color: '#6b7280', marginBottom: '16px' }}>
              Aucune session programmée pour ce cours
            </div>
            <AdminButton variant="primary" icon="➕" onClick={handleAddSession}>
              Créer la première session
            </AdminButton>
          </div>
        ) : (
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))',
              gap: ADMIN_SPACING.lg,
            }}
          >
            {sessions.map((session) => (
              <div key={session.id}>
                <SessionCard
                  session={session}
                  onEdit={(s) => handleEditSession(s)}
                  onDelete={(id) => handleDeleteSession(id)}
                />
                <div
                  style={{
                    display: 'flex',
                    gap: ADMIN_SPACING.sm,
                    marginTop: ADMIN_SPACING.sm,
                  }}
                >
                  <AdminButton
                    variant="secondary"
                    size="sm"
                    fullWidth
                    onClick={() => handleAssignProfessors(session)}
                  >
                    👨‍🏫 Professeurs
                  </AdminButton>
                </div>
              </div>
            ))}
          </div>
        )}
      </AdminPageContent>

      {/* Modals */}
      <SessionDetailModal
        session={selectedSession}
        isOpen={showSessionModal}
        onClose={() => {
          setShowSessionModal(false);
          setSelectedSession(null);
        }}
        onSave={handleSave}
      />

      <SessionAssignmentModal
        session={selectedSession}
        isOpen={showAssignmentModal}
        onClose={() => {
          setShowAssignmentModal(false);
          setSelectedSession(null);
        }}
        onSave={handleSave}
      />
    </>
  );
}
