import { useState, useEffect } from 'react';
import client from '../api/client';
import AdminButton from './AdminButton';
import { AdminFormField, AdminInput } from './AdminFormField';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';

export default function ProfesseurTariffForm({
  professeurId,
  tariff,
  onSuccess,
  onCancel,
}) {
  const [formData, setFormData] = useState({
    tarif_horaire_eur: '',
    date_debut: '',
    date_fin: '',
  });

  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const isEditing = !!tariff;

  useEffect(() => {
    if (tariff) {
      setFormData({
        tarif_horaire_eur: tariff.tarif_horaire_eur,
        date_debut: tariff.date_debut,
        date_fin: tariff.date_fin || '',
      });
    }
  }, [tariff]);

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);

    if (!formData.tarif_horaire_eur || !formData.date_debut) {
      setError('Veuillez remplir tous les champs obligatoires');
      return;
    }

    try {
      setIsSubmitting(true);

      if (isEditing) {
        await client.put(
          `/professeurs/${professeurId}/tarifs/${tariff.id}`,
          formData
        );
      } else {
        await client.post(
          `/professeurs/${professeurId}/tarifs`,
          formData
        );
      }

      setFormData({
        tarif_horaire_eur: '',
        date_debut: '',
        date_fin: '',
      });

      if (onSuccess) {
        onSuccess();
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la sauvegarde');
      console.error(err);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div style={{
      background: '#f9fafb',
      border: `1px solid ${ADMIN_COLORS.border}`,
      padding: '20px',
      borderRadius: '8px',
      marginBottom: '20px',
    }}>
      <h3 style={{
        margin: '0 0 16px 0',
        color: '#1f2937',
        fontSize: '1.1em',
      }}>
        {isEditing ? '✏️ Modifier le tarif' : '➕ Ajouter un nouveau tarif'}
      </h3>

      {error && (
        <div style={{
          background: '#fee2e2',
          color: ADMIN_COLORS.error,
          padding: '12px',
          borderRadius: '6px',
          marginBottom: '16px',
          fontSize: '0.9em',
        }}>
          ⚠️ {error}
        </div>
      )}

      <form onSubmit={handleSubmit}>
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
          gap: '16px',
          marginBottom: '16px',
        }}>
          <AdminFormField label="Tarif horaire (€)">
            <AdminInput
              type="number"
              step="0.01"
              min="0"
              max="999.99"
              value={formData.tarif_horaire_eur}
              onChange={(e) =>
                setFormData({ ...formData, tarif_horaire_eur: e.target.value })
              }
              placeholder="7.50"
              required
            />
          </AdminFormField>

          <AdminFormField label="Date de début">
            <AdminInput
              type="date"
              value={formData.date_debut}
              onChange={(e) =>
                setFormData({ ...formData, date_debut: e.target.value })
              }
              required
            />
          </AdminFormField>

          <AdminFormField label="Date de fin (optionnel)">
            <AdminInput
              type="date"
              value={formData.date_fin}
              onChange={(e) =>
                setFormData({ ...formData, date_fin: e.target.value })
              }
              placeholder="Laisser vide si sans limite"
            />
          </AdminFormField>
        </div>

        {!formData.date_fin && (
          <div style={{
            background: '#fef3c7',
            border: '1px solid #fcd34d',
            color: '#92400e',
            padding: '12px',
            borderRadius: '6px',
            marginBottom: '16px',
            fontSize: '0.9em',
          }}>
            ℹ️ Pas de date de fin = ce tarif reste actif indéfiniment
            (sauf si vous créez un nouveau tarif après)
          </div>
        )}

        <div style={{
          display: 'flex',
          gap: '12px',
        }}>
          <AdminButton
            variant="primary"
            type="submit"
            disabled={isSubmitting}
            icon={isEditing ? '✓' : '➕'}
          >
            {isSubmitting ? 'Enregistrement…' : (isEditing ? 'Mettre à jour' : 'Créer')}
          </AdminButton>

          <AdminButton
            variant="secondary"
            type="button"
            onClick={onCancel}
            disabled={isSubmitting}
          >
            Annuler
          </AdminButton>
        </div>
      </form>
    </div>
  );
}
