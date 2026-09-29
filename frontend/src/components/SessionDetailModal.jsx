import { useState, useEffect } from 'react';
import AdminButton from './AdminButton';
import { ADMIN_COLORS, ADMIN_SPACING } from '../styles/AdminDesignSystem';
import client from '../api/client';

export default function SessionDetailModal({ session, isOpen, onClose, onSave }) {
  const [formData, setFormData] = useState({
    titre: '',
    lieu: '',
    date_debut: '',
    heure_debut: '',
    heure_fin: '',
    description: '',
    statut: 'scheduled',
    nb_eleves_attendus: 0,
  });

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (session) {
      setFormData({
        titre: session.titre || '',
        lieu: session.lieu || '',
        date_debut: session.date_debut || '',
        heure_debut: session.heure_debut || '',
        heure_fin: session.heure_fin || '',
        description: session.description || '',
        statut: session.statut || 'scheduled',
        nb_eleves_attendus: session.nb_eleves_attendus || 0,
      });
    }
  }, [session]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      if (session?.id) {
        await client.put(`/sessions/${session.id}`, formData);
      } else {
        await client.post('/sessions', formData);
      }
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
          maxWidth: '600px',
          width: '90%',
          maxHeight: '90vh',
          overflowY: 'auto',
        }}
        onClick={(e) => e.stopPropagation()}
      >
        <h2 style={{ margin: '0 0 20px 0', fontSize: '20px', fontWeight: 700 }}>
          {session?.id ? '📝 Éditer la session' : '➕ Nouvelle session'}
        </h2>

        {error && (
          <div
            style={{
              background: '#fee2e2',
              color: ADMIN_COLORS.error,
              padding: ADMIN_SPACING.md,
              borderRadius: '6px',
              marginBottom: ADMIN_SPACING.md,
              fontSize: '14px',
            }}
          >
            ⚠️ {error}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div style={{ display: 'grid', gap: ADMIN_SPACING.md, marginBottom: ADMIN_SPACING.lg }}>
            {/* Titre */}
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px', fontSize: '13px' }}>
                Titre
              </label>
              <input
                type="text"
                name="titre"
                value={formData.titre}
                onChange={handleChange}
                placeholder="Ex: React Avancé - Session 5"
                style={{
                  width: '100%',
                  padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  fontSize: '14px',
                  fontFamily: 'inherit',
                }}
              />
            </div>

            {/* Date */}
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px', fontSize: '13px' }}>
                Date
              </label>
              <input
                type="date"
                name="date_debut"
                value={formData.date_debut.split('T')[0] || ''}
                onChange={handleChange}
                required
                style={{
                  width: '100%',
                  padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  fontSize: '14px',
                }}
              />
            </div>

            {/* Horaires */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: ADMIN_SPACING.md }}>
              <div>
                <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px', fontSize: '13px' }}>
                  Début
                </label>
                <input
                  type="time"
                  name="heure_debut"
                  value={formData.heure_debut.substring(0, 5) || ''}
                  onChange={handleChange}
                  required
                  style={{
                    width: '100%',
                    padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                    border: `1px solid ${ADMIN_COLORS.border}`,
                    borderRadius: '6px',
                    fontSize: '14px',
                  }}
                />
              </div>
              <div>
                <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px', fontSize: '13px' }}>
                  Fin
                </label>
                <input
                  type="time"
                  name="heure_fin"
                  value={formData.heure_fin.substring(0, 5) || ''}
                  onChange={handleChange}
                  required
                  style={{
                    width: '100%',
                    padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                    border: `1px solid ${ADMIN_COLORS.border}`,
                    borderRadius: '6px',
                    fontSize: '14px',
                  }}
                />
              </div>
            </div>

            {/* Lieu */}
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px', fontSize: '13px' }}>
                Lieu
              </label>
              <input
                type="text"
                name="lieu"
                value={formData.lieu}
                onChange={handleChange}
                placeholder="Ex: Salle 201 ou Visio"
                style={{
                  width: '100%',
                  padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  fontSize: '14px',
                  fontFamily: 'inherit',
                }}
              />
            </div>

            {/* Statut */}
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px', fontSize: '13px' }}>
                Statut
              </label>
              <select
                name="statut"
                value={formData.statut}
                onChange={handleChange}
                style={{
                  width: '100%',
                  padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  fontSize: '14px',
                }}
              >
                <option value="scheduled">📅 Planifiée</option>
                <option value="in_progress">⏱️ En cours</option>
                <option value="completed">✅ Complétée</option>
                <option value="cancelled">❌ Annulée</option>
              </select>
            </div>

            {/* Élèves attendus */}
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px', fontSize: '13px' }}>
                Élèves attendus
              </label>
              <input
                type="number"
                name="nb_eleves_attendus"
                value={formData.nb_eleves_attendus}
                onChange={handleChange}
                min="0"
                style={{
                  width: '100%',
                  padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  fontSize: '14px',
                }}
              />
            </div>

            {/* Description */}
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px', fontSize: '13px' }}>
                Description / Notes
              </label>
              <textarea
                name="description"
                value={formData.description}
                onChange={handleChange}
                rows="3"
                placeholder="Notes, agenda détaillé, etc."
                style={{
                  width: '100%',
                  padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  fontSize: '14px',
                  fontFamily: 'inherit',
                  resize: 'vertical',
                }}
              />
            </div>
          </div>

          {/* Boutons */}
          <div style={{ display: 'flex', gap: ADMIN_SPACING.md, justifyContent: 'flex-end' }}>
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
