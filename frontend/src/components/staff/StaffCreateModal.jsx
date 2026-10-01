import { useState } from 'react';
import Modal from '../ui/Modal';
import { FormField } from '../ui/FormField';
import AdminButton from '../AdminButton';
import LienCopiable from '../ui/LienCopiable';
import { useCreateStaff, useSendStaffLink } from '../../hooks/useStaff';
import { getErrorMessage } from '../../api/errors';

const VIDE = { name: '', email: '', role: 'directeur' };

/** ADMIN-02 : création d'un compte staff ; l'invitation est envoyée par email (aucun mot de passe affiché). */
export default function StaffCreateModal({ onClose, onCreated }) {
  const [formData, setFormData] = useState(VIDE);
  const [errors, setErrors] = useState({});
  const [resultat, setResultat] = useState(null); // { staff, emailEnvoye, lien }
  const [erreurReessai, setErreurReessai] = useState('');
  const { create, loading } = useCreateStaff();
  const { send, loading: reessaiEnCours } = useSendStaffLink();

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors({});
    try {
      const response = await create(formData);
      const { data, email_envoye: emailEnvoye, lien } = response.data;
      setResultat({ staff: data, emailEnvoye, lien });
      onCreated?.(data);
    } catch (err) {
      const apiErrors = err.response?.data?.errors;
      setErrors(apiErrors || { general: getErrorMessage(err, 'Erreur lors de la création') });
    }
  }

  async function reessayer() {
    setErreurReessai('');
    try {
      const response = await send(resultat.staff.id);
      const { data, email_envoye: emailEnvoye, lien } = response.data;
      setResultat({ staff: data, emailEnvoye, lien });
      onCreated?.(data);
    } catch (err) {
      setErreurReessai(getErrorMessage(err));
    }
  }

  function creerUnAutre() {
    setResultat(null);
    setFormData(VIDE);
    setErrors({});
  }

  if (resultat?.emailEnvoye) {
    return (
      <Modal onClose={onClose}>
        <h2>Invitation envoyée</h2>
        <p role="status">
          Un email d&apos;invitation a été envoyé à <strong>{resultat.staff.email}</strong>. Le lien est valable 72 h
          et permet à {resultat.staff.name} de choisir son mot de passe.
        </p>
        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
          <button type="button" onClick={creerUnAutre}>Créer un autre compte</button>
          <AdminButton onClick={onClose}>Fermer</AdminButton>
        </div>
      </Modal>
    );
  }

  if (resultat) {
    return (
      <Modal onClose={onClose}>
        <h2>Compte créé, email non envoyé</h2>
        <div role="alert" style={{ padding: '12px', backgroundColor: '#fff3cd', color: '#856404', borderRadius: '4px', marginBottom: '16px' }}>
          Le compte de <strong>{resultat.staff.name}</strong> a été créé mais l&apos;email n&apos;a pas pu être envoyé à {resultat.staff.email}.
          Réessayez, ou transmettez vous-même le lien ci-dessous.
        </div>
        {erreurReessai && <p role="alert" className="error">{erreurReessai}</p>}
        <LienCopiable lien={resultat.lien} />
        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', marginTop: '16px' }}>
          <button type="button" onClick={reessayer} disabled={reessaiEnCours}>
            {reessaiEnCours ? 'Envoi…' : 'Réessayer l’envoi'}
          </button>
          <AdminButton onClick={onClose}>Fermer</AdminButton>
        </div>
      </Modal>
    );
  }

  return (
    <Modal onClose={onClose}>
      <h2>Créer un compte staff</h2>
      <form onSubmit={handleSubmit}>
        <FormField label="Rôle" error={errors.role} required>
          <select
            value={formData.role}
            onChange={(e) => setFormData({ ...formData, role: e.target.value })}
            disabled={loading}
          >
            <option value="directeur">Directeur</option>
            <option value="admin">Admin</option>
          </select>
        </FormField>

        <FormField label="Nom complet" error={errors.name} required>
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
          helperText="Un email d'invitation sera envoyé à cette adresse."
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

        {errors.general && (
          <div role="alert" style={{ color: '#d32f2f', marginBottom: '16px', fontSize: '14px' }}>{errors.general}</div>
        )}

        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
          <button type="button" onClick={onClose} disabled={loading} style={{ background: 'none', border: '1px solid #ccc', padding: '8px 16px', cursor: 'pointer' }}>
            Annuler
          </button>
          <AdminButton type="submit" disabled={loading}>
            {loading ? 'Envoi…' : 'Créer et envoyer l’invitation'}
          </AdminButton>
        </div>
      </form>
    </Modal>
  );
}
