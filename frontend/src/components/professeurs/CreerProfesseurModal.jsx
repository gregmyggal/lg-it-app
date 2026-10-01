import { useState } from 'react';
import client from '../../api/client';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect } from '../AdminFormField';
import Banner from '../ui/Banner';
import LienCopiable from '../ui/LienCopiable';
import { getErrorMessage, getFieldErrors } from '../../api/errors';

const VIDE = {
  prenom: '', nom: '', email: '', login_email: '', telephone: '', type_contrat: '',
  date_entree: new Date().toISOString().slice(0, 10),
};

/**
 * Création d'un professeur + compte de connexion ; l'invitation (lien à usage unique) part vers l'email de
 * connexion, aucun mot de passe n'est communiqué (ADMIN-03). `onCreated(professeur)` recharge la liste ;
 * `onVoirFiche(professeur)` ouvre la fiche (assignation des classes).
 */
export default function CreerProfesseurModal({ onClose, onCreated, onVoirFiche }) {
  const [form, setForm] = useState(VIDE);
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [champs, setChamps] = useState({});
  const [resultat, setResultat] = useState(null); // { professeur, mailEnvoye, lien }
  const [erreurReessai, setErreurReessai] = useState(null);
  const [reessai, setReessai] = useState(false);

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
      setResultat({ professeur: res.data.data, mailEnvoye: res.data.mail_envoye, lien: res.data.lien });
      onCreated?.(res.data.data);
    } catch (err) {
      setChamps(getFieldErrors(err));
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  async function reessayer() {
    setReessai(true);
    setErreurReessai(null);
    try {
      const res = await client.post(`/professeurs/${resultat.professeur.id}/envoyer-lien`);
      setResultat({ professeur: res.data.data, mailEnvoye: res.data.mail_envoye, lien: res.data.lien });
      onCreated?.(res.data.data);
    } catch (err) {
      setErreurReessai(getErrorMessage(err));
    } finally {
      setReessai(false);
    }
  }

  if (resultat) {
    const { professeur, mailEnvoye, lien } = resultat;
    const nom = `${professeur.prenom} ${professeur.nom}`;
    const loginEmail = professeur.user?.email;
    return (
      <AdminModal
        isOpen
        title={mailEnvoye ? 'Professeur créé' : 'Professeur créé, email non envoyé'}
        size="sm"
        onClose={onClose}
        closeOnBackdrop={false}
        footer={
          <>
            {!mailEnvoye && (
              <AdminButton variant="secondary" onClick={reessayer} loading={reessai}>Réessayer l&apos;envoi</AdminButton>
            )}
            <AdminButton variant="secondary" onClick={() => onVoirFiche?.(professeur)}>Voir la fiche</AdminButton>
            <AdminButton variant="primary" onClick={onClose}>Fermer</AdminButton>
          </>
        }
      >
        {mailEnvoye ? (
          <Banner tone="success">
            Invitation envoyée à <strong>{loginEmail}</strong>. Le lien est valable 72 h et permet à {nom} de choisir son mot de passe.
          </Banner>
        ) : (
          <>
            <Banner tone="warning">
              Le compte de <strong>{nom}</strong> a été créé mais l&apos;email n&apos;a pas pu être envoyé à {loginEmail}.
              Réessayez, ou transmettez vous-même le lien ci-dessous.
            </Banner>
            {erreurReessai && <Banner tone="error">{erreurReessai}</Banner>}
            {lien && <LienCopiable lien={lien} />}
          </>
        )}
      </AdminModal>
    );
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
            Créer et envoyer l'invitation
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
          description="Identifiant du compte et adresse qui reçoit l'invitation (lien valable 72 h)."
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
