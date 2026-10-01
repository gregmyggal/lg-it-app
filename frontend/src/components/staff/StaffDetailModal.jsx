import { useState } from 'react';
import Modal from '../ui/Modal';
import AdminButton from '../AdminButton';
import { useResetStaffPassword } from '../../hooks/useStaff';
import { libelleRole } from '../../utils/statuts';
import { formatDate } from '../../utils/dates';

export default function StaffDetailModal({ staff, onClose, onDesactiver, onReactiver, onEditEmail }) {
  const { reset, loading: resetLoading } = useResetStaffPassword();
  const [nouveauMotDePasse, setNouveauMotDePasse] = useState('');
  const [erreur, setErreur] = useState('');

  async function handleReinitMotDePasse() {
    try {
      setErreur('');
      const response = await reset(staff.id);
      setNouveauMotDePasse(response.data.password);
    } catch (err) {
      setErreur(err.response?.data?.message || 'Erreur lors de la réinitialisation du mot de passe');
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
            <div>{formatDate(staff.date_sortie)}</div>
          </div>
        )}
      </div>

      {nouveauMotDePasse && (
        <div role="status" style={{ marginBottom: '16px', padding: '12px', backgroundColor: '#d4edda', borderRadius: '4px' }}>
          Nouveau mot de passe provisoire (affiché une seule fois) :
          <div style={{ fontFamily: 'monospace', userSelect: 'all', marginTop: '8px' }}>{nouveauMotDePasse}</div>
        </div>
      )}
      {erreur && (
        <div role="alert" style={{ marginBottom: '16px', padding: '12px', backgroundColor: '#ffcdd2', color: '#c62828', borderRadius: '4px' }}>{erreur}</div>
      )}

      <div style={{ borderTop: '1px solid #ddd', paddingTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end', flexWrap: 'wrap' }}>
        {staff.statut === 'actif' ? (
          <>
            <AdminButton onClick={onEditEmail} disabled={resetLoading} style={{ background: '#2196f3' }}>
              Modifier email
            </AdminButton>
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
