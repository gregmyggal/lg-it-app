import { useState } from 'react';
import client from '../../api/client';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput } from '../AdminFormField';
import Banner from '../ui/Banner';
import { getErrorMessage, getFieldErrors } from '../../api/errors';

const CONTRATS = [
  { value: '', label: '—' },
  { value: 'salarie', label: 'Salarié' },
  { value: 'freelance', label: 'Freelance' },
  { value: 'prestataire', label: 'Prestataire' },
];

/**
 * Modifier les informations d'un professeur (UI-M7) : prénom, nom, email de contact, téléphone, contrat, date d'entrée.
 * L'email de CONNEXION se change dans « Compte de connexion » ; l'IBAN dans « Compte bancaire » (écrans dédiés, avec leurs règles).
 */
export default function ModifierProfesseurModal({ professeur, onClose, onDone }) {
  const [form, setForm] = useState({
    prenom: professeur.prenom || '',
    nom: professeur.nom || '',
    email: professeur.email || '',
    telephone: professeur.telephone || '',
    type_contrat: professeur.type_contrat || '',
    date_entree: professeur.date_entree ? String(professeur.date_entree).slice(0, 10) : '',
  });
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const maj = (champ) => (e) => setForm((f) => ({ ...f, [champ]: e.target.value }));

  async function enregistrer() {
    setEnvoi(true);
    setErreurs({});
    setMessage(null);
    try {
      await client.put(`/professeurs/${professeur.id}`, {
        prenom: form.prenom.trim(),
        nom: form.nom.trim(),
        email: form.email.trim(),
        telephone: form.telephone.trim() || null,
        type_contrat: form.type_contrat || null,
        ...(form.date_entree ? { date_entree: form.date_entree } : {}),
      });
      onDone('Informations du professeur enregistrées.');
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setMessage(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title="Modifier les informations"
      size="sm"
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton loading={envoi} disabled={!form.prenom.trim() || !form.nom.trim() || !form.email.trim()} onClick={enregistrer}>
            Enregistrer
          </AdminButton>
        </>
      }
    >
      {message && (
        <Banner tone="error" role="alert">
          <strong>{message}</strong>
        </Banner>
      )}
      <AdminFormField label="Prénom" htmlFor="prof-prenom" error={erreurs.prenom}>
        <AdminInput id="prof-prenom" value={form.prenom} onChange={maj('prenom')} autoComplete="off" />
      </AdminFormField>
      <AdminFormField label="Nom" htmlFor="prof-nom" error={erreurs.nom}>
        <AdminInput id="prof-nom" value={form.nom} onChange={maj('nom')} autoComplete="off" />
      </AdminFormField>
      <AdminFormField label="Email de contact" htmlFor="prof-email" error={erreurs.email}>
        <AdminInput id="prof-email" type="email" inputMode="email" value={form.email} onChange={maj('email')} autoComplete="off" />
      </AdminFormField>
      <AdminFormField label="Téléphone" htmlFor="prof-tel" error={erreurs.telephone}>
        <AdminInput id="prof-tel" type="tel" inputMode="tel" value={form.telephone} onChange={maj('telephone')} autoComplete="off" />
      </AdminFormField>
      <AdminFormField label="Type de contrat" htmlFor="prof-contrat" error={erreurs.type_contrat}>
        <select id="prof-contrat" value={form.type_contrat} onChange={maj('type_contrat')} style={{ width: '100%' }}>
          {CONTRATS.map((c) => (
            <option key={c.value} value={c.value}>
              {c.label}
            </option>
          ))}
        </select>
      </AdminFormField>
      <AdminFormField label="Date d'entrée" htmlFor="prof-entree" error={erreurs.date_entree}>
        <AdminInput id="prof-entree" type="date" value={form.date_entree} onChange={maj('date_entree')} />
      </AdminFormField>
    </AdminModal>
  );
}
