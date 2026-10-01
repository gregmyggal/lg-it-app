import { useState } from 'react';
import client from '../../api/client';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect } from '../AdminFormField';
import Banner from '../ui/Banner';
import { getErrorMessage, getFieldErrors } from '../../api/errors';

const VIDE = {
  prenom: '', nom: '', email: '', login_email: '', telephone: '', type_contrat: '',
  date_entree: new Date().toISOString().slice(0, 10),
};

/** Création d'un professeur + compte de connexion. `onCreated({ professeur, motDePasse, mailEnvoye })`. */
export default function CreerProfesseurModal({ onClose, onCreated }) {
  const [form, setForm] = useState(VIDE);
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [champs, setChamps] = useState({});

  const maj = (nom) => (e) => {
    const valeur = e.target.value;
    setForm((f) => {
      const suivant = { ...f, [nom]: valeur };
      // L'email de connexion suit l'email de contact tant qu'il n'a pas été modifié à la main.
      if (nom === 'email' && f.login_email === f.email) suivant.login_email = valeur;
      return suivant;
    });
  };

  async function soumettre(e) {
    e.preventDefault();
    setEnvoi(true);
    setErreur(null);
    setChamps({});
    try {
      const payload = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
      const res = await client.post('/professeurs', payload);
      onCreated({ professeur: res.data.data, motDePasse: res.data.mot_de_passe, mailEnvoye: res.data.mail_envoye });
    } catch (err) {
      setChamps(getFieldErrors(err));
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title="Nouveau professeur"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton variant="primary" type="submit" form="form-creer-professeur" loading={envoi}>
            Créer et inviter
          </AdminButton>
        </>
      }
    >
      {erreur && <Banner tone="error">{erreur}</Banner>}
      <form id="form-creer-professeur" onSubmit={soumettre}>
        <AdminFormField label="Prénom" htmlFor="prof-prenom" required error={champs.prenom}>
          <AdminInput id="prof-prenom" value={form.prenom} onChange={maj('prenom')} required error={champs.prenom} />
        </AdminFormField>
        <AdminFormField label="Nom" htmlFor="prof-nom" required error={champs.nom}>
          <AdminInput id="prof-nom" value={form.nom} onChange={maj('nom')} required error={champs.nom} />
        </AdminFormField>
        <AdminFormField label="Email de contact" htmlFor="prof-email" required error={champs.email}>
          <AdminInput id="prof-email" type="email" value={form.email} onChange={maj('email')} required error={champs.email} />
        </AdminFormField>
        <AdminFormField
          label="Email de connexion"
          htmlFor="prof-login"
          required
          description="Identifiant de connexion ; l'invitation est envoyée à l'email de contact."
          error={champs.login_email}
        >
          <AdminInput id="prof-login" type="email" value={form.login_email} onChange={maj('login_email')} required error={champs.login_email} />
        </AdminFormField>
        <AdminFormField label="Téléphone" htmlFor="prof-tel" error={champs.telephone}>
          <AdminInput id="prof-tel" value={form.telephone} onChange={maj('telephone')} error={champs.telephone} />
        </AdminFormField>
        <AdminFormField label="Type de contrat" htmlFor="prof-contrat">
          <AdminSelect
            id="prof-contrat"
            value={form.type_contrat}
            onChange={maj('type_contrat')}
            options={[
              { value: '', label: '—' },
              { value: 'salarie', label: 'Salarié' },
              { value: 'freelance', label: 'Freelance' },
              { value: 'prestataire', label: 'Prestataire' },
            ]}
          />
        </AdminFormField>
        <AdminFormField label="Date d'entrée" htmlFor="prof-entree" required error={champs.date_entree}>
          <AdminInput id="prof-entree" type="date" value={form.date_entree} onChange={maj('date_entree')} required error={champs.date_entree} />
        </AdminFormField>
      </form>
    </AdminModal>
  );
}
