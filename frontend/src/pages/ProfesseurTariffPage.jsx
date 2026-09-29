import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import client from '../api/client';
import {
  AdminPageHeader,
  AdminPageContent,
} from '../components/AdminPageLayout';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';
import ProfesseurTariffList from '../components/ProfesseurTariffList';
import ProfesseurTariffForm from '../components/ProfesseurTariffForm';
import ProfesseurTariffTerminateModal from '../components/ProfesseurTariffTerminateModal';
import AdminButton from '../components/AdminButton';

export default function ProfesseurTariffPage() {
  const { id } = useParams();
  const professeurId = parseInt(id);

  const [professeur, setProfesseur] = useState(null);
  const [editingTariff, setEditingTariff] = useState(null);
  const [terminatingTariff, setTerminatingTariff] = useState(null);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [refreshKey, setRefreshKey] = useState(0);
  const [deletingTariff, setDeletingTariff] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);

  useEffect(() => {
    loadProfesseur();
  }, [professeurId]);

  async function loadProfesseur() {
    try {
      const res = await client.get(`/professeurs/${professeurId}`);
      setProfesseur(res.data);
      setError(null);
    } catch (err) {
      setError('Impossible de charger le professeur');
      console.error(err);
    }
  }

  function handleFormSuccess() {
    setShowForm(false);
    setEditingTariff(null);
    setSuccess(
      editingTariff
        ? 'Tarif mis à jour avec succès'
        : 'Tarif créé avec succès'
    );
    setRefreshKey((k) => k + 1);
    setTimeout(() => setSuccess(null), 3000);
  }

  function handleEdit(tariff) {
    setEditingTariff(tariff);
    setShowForm(true);
  }

  function handleTerminate(tariff) {
    setTerminatingTariff(tariff);
  }

  function handleTerminateSuccess() {
    setTerminatingTariff(null);
    setSuccess('Tarif arrêté avec succès');
    setRefreshKey((k) => k + 1);
    setTimeout(() => setSuccess(null), 3000);
  }

  async function handleDelete(tariff) {
    setDeletingTariff(tariff);
    const confirmed = window.confirm(
      `Êtes-vous sûr de vouloir supprimer ce tarif?\n\n` +
      `${tariff.tarif_horaire_eur}€/h du ${new Date(tariff.date_debut).toLocaleDateString('fr-FR')}\n\n` +
      `Cette action ne peut pas être annulée.`
    );

    if (!confirmed) {
      setDeletingTariff(null);
      return;
    }

    try {
      setIsDeleting(true);
      await client.delete(`/professeurs/${professeurId}/tarifs/${tariff.id}`);
      setSuccess('Tarif supprimé avec succès');
      setRefreshKey((k) => k + 1);
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la suppression');
      console.error(err);
    } finally {
      setIsDeleting(false);
      setDeletingTariff(null);
    }
  }

  if (!professeur) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: '#6b7280' }}>
        Chargement…
      </div>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="💰"
        title={`Tarifs - ${professeur.prenom} ${professeur.nom}`}
        description="Gérez les tarifs horaires et leur historique"
      />

      <AdminPageContent>
        {error && (
          <div style={{
            background: '#fee2e2',
            color: ADMIN_COLORS.error,
            padding: '16px',
            borderRadius: '8px',
            marginBottom: '16px',
            display: 'flex',
            gap: '12px',
          }}>
            <span>⚠️</span>
            <div>{error}</div>
          </div>
        )}

        {success && (
          <div style={{
            background: '#d1fae5',
            color: ADMIN_COLORS.success,
            padding: '16px',
            borderRadius: '8px',
            marginBottom: '16px',
            display: 'flex',
            gap: '12px',
          }}>
            <span>✓</span>
            <div>{success}</div>
          </div>
        )}

        {/* Bouton ajouter tarif */}
        {!showForm && (
          <AdminButton
            variant="primary"
            icon="➕"
            onClick={() => {
              setEditingTariff(null);
              setShowForm(true);
            }}
            style={{ marginBottom: '24px' }}
          >
            Ajouter un nouveau tarif
          </AdminButton>
        )}

        {/* Formulaire */}
        {showForm && (
          <ProfesseurTariffForm
            key={`form-${editingTariff?.id || 'new'}`}
            professeurId={professeurId}
            tariff={editingTariff}
            onSuccess={handleFormSuccess}
            onCancel={() => {
              setShowForm(false);
              setEditingTariff(null);
            }}
          />
        )}

        {/* Liste des tarifs */}
        <div style={{ marginBottom: '24px' }}>
          <h3 style={{
            margin: '0 0 16px 0',
            fontSize: '1.1em',
            color: '#1f2937',
          }}>
            📊 Historique des tarifs
          </h3>
          <ProfesseurTariffList
            key={`list-${refreshKey}`}
            professeurId={professeurId}
            onEdit={handleEdit}
            onTerminate={handleTerminate}
            onDelete={handleDelete}
            readonly={false}
          />
        </div>

        {/* Info utile */}
        <div style={{
          background: '#eff6ff',
          border: '1px solid #3b82f6',
          padding: '16px',
          borderRadius: '8px',
          marginTop: '24px',
        }}>
          <h4 style={{ margin: '0 0 12px 0', color: '#1e40af' }}>
            ℹ️ Comment ça fonctionne?
          </h4>
          <ul style={{ margin: 0, paddingLeft: '20px', color: '#1e40af', fontSize: '0.95em' }}>
            <li style={{ marginBottom: '8px' }}>
              <strong>Date de début:</strong> À partir de quand ce tarif est appliqué
            </li>
            <li style={{ marginBottom: '8px' }}>
              <strong>Date de fin:</strong> Jusqu'à quand ce tarif s'applique (optionnel)
            </li>
            <li style={{ marginBottom: '8px' }}>
              <strong>Tarif actif:</strong> Le tarif s'applique à toutes les heures
              prestées dans cette période
            </li>
            <li style={{ marginBottom: '8px' }}>
              <strong>Augmentation:</strong> Créez un nouveau tarif avec date_debut
              = jour d'augmentation
            </li>
            <li>
              <strong>Arrêt:</strong> Utilisez "Arrêter" pour définir une date_fin
            </li>
          </ul>
        </div>
      </AdminPageContent>

      {/* Modal terminer tarif */}
      <ProfesseurTariffTerminateModal
        professeurId={professeurId}
        tariff={terminatingTariff}
        isOpen={!!terminatingTariff}
        onClose={() => setTerminatingTariff(null)}
        onSuccess={handleTerminateSuccess}
      />
    </>
  );
}
