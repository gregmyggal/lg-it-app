import Modal from '../ui/Modal';
import AdminButton from '../AdminButton';
import { useResetStaffPassword } from '../../hooks/useStaff';
import { libelleRole } from '../../utils/statuts';

export default function StaffDetailModal({ staff, onClose, onDesactiver, onReactiver }) {
  const { reset, loading: resetLoading } = useResetStaffPassword();

  async function handleReinitMotDePasse() {
    try {
      const response = await reset(staff.id);
      alert(`Nouveau mot de passe: ${response.password}`);
    } catch (err) {
      alert('Erreur lors de la réinitialisation du mot de passe');
    }
  }

  return (
    <Modal onClose={onClose}>
      <h2>{staff.name}</h2>

      <div style={{ marginBottom: '24px' }}>
        <div style={{ marginBottom: '16px' }}>
          <label style={{ display: 'block', fontWeight: 'bold', marginBottom: '4px' }}>Email:</label>
          <div>{staff.email}</div>
        </div>

        <div style={{ marginBottom: '16px' }}>
          <label style={{ display: 'block', fontWeight: 'bold', marginBottom: '4px' }}>Rôle:</label>
          <div>{libelleRole(staff.role)}</div>
        </div>

        <div style={{ marginBottom: '16px' }}>
          <label style={{ display: 'block', fontWeight: 'bold', marginBottom: '4px' }}>Statut:</label>
          <div>{staff.statut === 'actif' ? 'Actif' : 'Désactivé'}</div>
        </div>

        {staff.date_sortie && (
          <div style={{ marginBottom: '16px' }}>
            <label style={{ display: 'block', fontWeight: 'bold', marginBottom: '4px' }}>Date de sortie:</label>
            <div>{staff.date_sortie}</div>
          </div>
        )}
      </div>

      <div style={{ borderTop: '1px solid #ddd', paddingTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
        {staff.statut === 'actif' ? (
          <>
            <AdminButton onClick={handleReinitMotDePasse} disabled={resetLoading} style={{ background: '#ff9800' }}>
              Réinitialiser mot de passe
            </AdminButton>
            <AdminButton onClick={onDesactiver} style={{ background: '#f44336' }}>
              Désactiver
            </AdminButton>
          </>
        ) : (
          <AdminButton onClick={onReactiver} style={{ background: '#4caf50' }}>
            Réactiver
          </AdminButton>
        )}
        <button onClick={onClose} style={{ background: 'none', border: '1px solid #ccc', padding: '8px 16px', cursor: 'pointer' }}>
          Fermer
        </button>
      </div>
    </Modal>
  );
}
