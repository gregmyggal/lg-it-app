import { useState } from 'react';
import client from '../api/client';
import AdminButton from './AdminButton';
import { AdminFormField, AdminInput } from './AdminFormField';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';

export default function ProfesseurTariffTerminateModal({
  professeurId,
  tariff,
  isOpen,
  onClose,
  onSuccess,
}) {
  const [dateFin, setDateFin] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!isOpen || !tariff) return null;

  async function handleTerminate() {
    if (!dateFin) {
      setError('Veuillez sélectionner une date de fin');
      return;
    }

    if (dateFin <= tariff.date_debut) {
      setError('La date de fin doit être après la date de début');
      return;
    }

    try {
      setIsSubmitting(true);
      await client.post(
        `/professeurs/${professeurId}/tarifs/${tariff.id}/terminate`,
        { date_fin: dateFin }
      );

      setDateFin('');
      setError(null);
      onClose();
      if (onSuccess) {
        onSuccess();
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de l\'enregistrement');
      console.error(err);
    } finally {
      setIsSubmitting(false);
    }
  }

  const handleClose = () => {
    setDateFin('');
    setError(null);
    onClose();
  };

  return (
    <div className="adm-modal-overlay" style={{
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
    }}>
      <div className="adm-modal adm-modal--plain" style={{
        background: 'var(--c-card)',
        borderRadius: '8px',
        padding: '24px',
        maxWidth: '500px',
        width: '90%',
        boxShadow: '0 20px 25px rgba(0,0,0,0.15)',
      }}>
        <h2 style={{ margin: '0 0 16px 0', color: 'var(--c-text)', fontSize: '1.2em' }}>
          ⏹️ Arrêter le tarif
        </h2>

        <div style={{
          background: 'var(--tone-warning-bg)',
          border: '1px solid var(--tone-warning-bd)',
          padding: '12px',
          borderRadius: '6px',
          marginBottom: '20px',
          fontSize: '0.9em',
          color: 'var(--tone-warning-fg)',
        }}>
          <strong>Attention:</strong> Après cette date, ce tarif ne sera plus utilisé.
          Vous pourrez créer un nouveau tarif après cette date si nécessaire.
        </div>

        <div style={{
          background: 'var(--c-hover)',
          padding: '12px',
          borderRadius: '6px',
          marginBottom: '20px',
          fontSize: '0.9em',
        }}>
          <div style={{ marginBottom: '8px' }}>
            <strong>Tarif actuel:</strong> {tariff.tarif_horaire_eur.toFixed(2)}€/h
          </div>
          <div>
            <strong>Depuis:</strong>{' '}
            {new Date(tariff.date_debut).toLocaleDateString('fr-FR')}
          </div>
        </div>

        {error && (
          <div style={{
            background: 'var(--tone-error-bg)',
            color: ADMIN_COLORS.error,
            padding: '12px',
            borderRadius: '6px',
            marginBottom: '16px',
            fontSize: '0.9em',
          }}>
            ⚠️ {error}
          </div>
        )}

        <AdminFormField label="Date de fin">
          <AdminInput
            type="date"
            value={dateFin}
            onChange={(e) => {
              setDateFin(e.target.value);
              setError(null);
            }}
            min={tariff.date_debut}
            required
          />
        </AdminFormField>

        <div style={{
          display: 'flex',
          gap: '12px',
          marginTop: '20px',
        }}>
          <AdminButton
            variant="danger"
            onClick={handleTerminate}
            disabled={isSubmitting}
            icon="✓"
          >
            {isSubmitting ? 'Enregistrement…' : 'Confirmer l\'arrêt'}
          </AdminButton>

          <AdminButton
            variant="secondary"
            onClick={handleClose}
            disabled={isSubmitting}
          >
            Annuler
          </AdminButton>
        </div>
      </div>
    </div>
  );
}
