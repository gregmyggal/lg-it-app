import { useState } from 'react';
import Modal from '../ui/Modal';
import { FormField } from '../ui/FormField';
import AdminButton from '../AdminButton';
import { useCreateStaff } from '../../hooks/useStaff';

export default function StaffCreateModal({ onClose, onSubmit }) {
  const [formData, setFormData] = useState({ name: '', email: '', role: 'directeur' });
  const [errors, setErrors] = useState({});
  const [password, setPassword] = useState('');
  const { create, loading } = useCreateStaff();

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors({});
    try {
      const response = await create(formData);
      setPassword(response.password);
      setFormData({ name: '', email: '', role: 'directeur' });
      // Appeler onSubmit après un délai pour que l'utilisateur puisse voir le mot de passe
      setTimeout(() => onSubmit(formData), 3000);
    } catch (err) {
      setErrors(err);
    }
  }

  if (password) {
    return (
      <Modal onClose={onClose}>
        <h2>Directeur créé avec succès</h2>
        <p>Mot de passe temporaire à communiquer au directeur:</p>
        <div style={{
          padding: '12px',
          backgroundColor: '#f0f0f0',
          borderRadius: '4px',
          fontFamily: 'monospace',
          userSelect: 'all',
          marginBottom: '16px',
        }}>
          {password}
        </div>
        <div style={{ textAlign: 'center' }}>
          <AdminButton onClick={onClose}>Fermer</AdminButton>
        </div>
      </Modal>
    );
  }

  return (
    <Modal onClose={onClose}>
      <h2>Créer un compte staff</h2>
      <form onSubmit={handleSubmit}>
        <FormField
          label="Rôle"
          error={errors.role}
          required
        >
          <select
            value={formData.role}
            onChange={(e) => setFormData({ ...formData, role: e.target.value })}
            disabled={loading}
          >
            <option value="directeur">Directeur</option>
            <option value="admin">Admin</option>
          </select>
        </FormField>

        <FormField
          label="Nom complet"
          error={errors.name}
          required
        >
          <input
            type="text"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
            placeholder="Jean Dupont"
            disabled={loading}
            required
          />
        </FormField>

        <FormField
          label="Email de connexion"
          error={errors.email}
          required
        >
          <input
            type="email"
            value={formData.email}
            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
            placeholder="jean@example.com"
            disabled={loading}
            required
          />
        </FormField>

        {Object.keys(errors).length > 0 && (
          <div style={{ color: '#d32f2f', marginBottom: '16px', fontSize: '14px' }}>
            Veuillez corriger les erreurs ci-dessus.
          </div>
        )}

        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
          <button onClick={onClose} disabled={loading} style={{ background: 'none', border: '1px solid #ccc', padding: '8px 16px', cursor: 'pointer' }}>
            Annuler
          </button>
          <AdminButton type="submit" disabled={loading}>
            {loading ? 'Création...' : 'Créer'}
          </AdminButton>
        </div>
      </form>
    </Modal>
  );
}
