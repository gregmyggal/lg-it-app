import { useState } from 'react';
import Modal from '../ui/Modal';
import { FormField } from '../ui/FormField';
import AdminButton from '../AdminButton';
import { useUpdateStaff } from '../../hooks/useStaff';

export default function StaffEditEmailModal({ staff, onClose, onSubmit }) {
  const [email, setEmail] = useState(staff.email);
  const [errors, setErrors] = useState({});
  const { update, loading } = useUpdateStaff();

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors({});

    if (!email) {
      setErrors({ email: 'L\'email est requis' });
      return;
    }

    try {
      const response = await update(staff.id, { email });
      onSubmit(response.data.data);
    } catch (err) {
      setErrors(err.response?.data?.errors || { email: err.response?.data?.message || 'Erreur lors de la modification' });
    }
  }

  return (
    <Modal onClose={onClose}>
      <h2>Modifier l'email de {staff.name}</h2>

      <form onSubmit={handleSubmit}>
        <FormField label="Email de connexion" error={errors.email} required>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="nouveau@example.com"
            disabled={loading}
            required
          />
        </FormField>

        <div style={{ fontSize: '12px', color: 'var(--c-text-2)', marginBottom: '16px' }}>
          Après modification, les tokens actuels seront révoqués.
        </div>

        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
          <button
            type="button"
            onClick={onClose}
            disabled={loading}
            style={{ background: 'none', border: '1px solid var(--c-border)', padding: '8px 16px', cursor: 'pointer' }}
          >
            Annuler
          </button>
          <AdminButton type="submit" disabled={loading}>
            {loading ? 'Modification...' : 'Modifier'}
          </AdminButton>
        </div>
      </form>
    </Modal>
  );
}
