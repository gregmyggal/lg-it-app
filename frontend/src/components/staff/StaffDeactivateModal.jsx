import Modal from '../ui/Modal';
import AdminButton from '../AdminButton';
import { useDeactivateStaff } from '../../hooks/useStaff';

export default function StaffDeactivateModal({ staff, onClose, onConfirm }) {
  const { deactivate, loading } = useDeactivateStaff();

  async function handleConfirm() {
    try {
      await deactivate(staff.id);
      onConfirm();
    } catch (err) {
      alert('Erreur lors de la désactivation');
    }
  }

  return (
    <Modal onClose={onClose}>
      <h2>Désactiver {staff.name}</h2>

      <div style={{ marginBottom: '24px', padding: '16px', backgroundColor: '#fff3cd', borderRadius: '4px', color: '#856404' }}>
        <strong>⚠️ Attention:</strong> Cette action coupera immédiatement l'accès à ce compte. L'historique des données (classes, heures, etc.) sera conservé.
      </div>

      <p style={{ marginBottom: '16px' }}>
        Êtes-vous sûr de vouloir désactiver le compte de <strong>{staff.name}</strong>?
      </p>

      <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
        <button
          onClick={onClose}
          disabled={loading}
          style={{ background: 'none', border: '1px solid #ccc', padding: '8px 16px', cursor: 'pointer' }}
        >
          Annuler
        </button>
        <AdminButton
          onClick={handleConfirm}
          disabled={loading}
          style={{ background: '#f44336' }}
        >
          {loading ? 'Désactivation...' : 'Désactiver'}
        </AdminButton>
      </div>
    </Modal>
  );
}
