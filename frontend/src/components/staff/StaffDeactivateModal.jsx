import { useEffect, useState } from 'react';
import Modal from '../ui/Modal';
import AdminButton from '../AdminButton';
import { useDeactivateStaff, useGetImpactInfo } from '../../hooks/useStaff';

export default function StaffDeactivateModal({ staff, onClose, onConfirm }) {
  const { deactivate, loading } = useDeactivateStaff();
  const { fetch: fetchImpact, impact, loading: impactLoading } = useGetImpactInfo();
  const [error, setError] = useState('');

  useEffect(() => {
    fetchImpact(staff.id).catch(() => setError('Impossible de charger l\'impact'));
  }, [staff.id, fetchImpact]);

  const isLastAdmin = impact?.isLastAdmin;
  const isLastDirecteur = impact?.isLastDirecteur;
  const canDeactivate = !isLastAdmin;

  async function handleConfirm() {
    if (!canDeactivate) return;

    try {
      await deactivate(staff.id);
      onConfirm();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la désactivation');
    }
  }

  return (
    <Modal onClose={onClose}>
      <h2>Désactiver {staff.name}</h2>

      {isLastAdmin && (
        <div style={{ marginBottom: '24px', padding: '16px', backgroundColor: '#ffcdd2', borderRadius: '4px', color: '#c62828' }}>
          <strong>❌ Impossible:</strong> Vous ne pouvez pas désactiver le dernier administrateur du système.
        </div>
      )}

      {isLastDirecteur && !isLastAdmin && (
        <div style={{ marginBottom: '24px', padding: '16px', backgroundColor: '#fff3cd', borderRadius: '4px', color: '#856404' }}>
          <strong>⚠️ Avertissement:</strong> Ceci est le dernier directeur actif. Assurez-vous d'avoir un administrateur pour remplacer les tâches.
        </div>
      )}

      {!isLastAdmin && (
        <div style={{ marginBottom: '24px', padding: '16px', backgroundColor: '#f5f5f5', borderRadius: '4px' }}>
          <strong>Impact de cette désactivation:</strong>
          <ul style={{ marginTop: '8px', paddingLeft: '20px' }}>
            <li>Accès immédiatement coupé</li>
            <li>Tous les tokens actifs révoqués</li>
            <li>Historique des données conservé</li>
          </ul>
        </div>
      )}

      {error && (
        <div style={{ marginBottom: '16px', padding: '12px', backgroundColor: '#ffcdd2', color: '#c62828', borderRadius: '4px' }}>
          {error}
        </div>
      )}

      <p style={{ marginBottom: '16px' }}>
        Êtes-vous sûr de vouloir désactiver le compte de <strong>{staff.name}</strong>?
      </p>

      <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
        <button
          onClick={onClose}
          disabled={loading || impactLoading}
          style={{ background: 'none', border: '1px solid #ccc', padding: '8px 16px', cursor: 'pointer' }}
        >
          Annuler
        </button>
        <AdminButton
          onClick={handleConfirm}
          disabled={loading || impactLoading || !canDeactivate}
          style={{ background: '#f44336' }}
        >
          {loading ? 'Désactivation...' : 'Désactiver'}
        </AdminButton>
      </div>
    </Modal>
  );
}
