import { useEffect, useState } from 'react';
import client from '../../api/client';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminCard, AdminCardHeader, AdminCardBody, AdminBadge } from '../AdminPageLayout';
import { AdminFormField, AdminInput, AdminCheckbox } from '../AdminFormField';
import Banner from '../ui/Banner';
import MotDePasseProvisoireModal from './MotDePasseProvisoireModal';
import { getErrorMessage, getFieldErrors } from '../../api/errors';

/**
 * Compte de connexion du professeur : activer / désactiver, réinitialiser le mot de passe,
 * changer l'email de connexion. Le serveur reste la source de vérité (impact, règles, tokens).
 *
 * @param {object} props
 * @param {object} props.professeur  professeur chargé avec `user`
 * @param {(message: string) => void} props.onChange  rechargement + message de succès
 */
export default function CompteProfesseurSection({ professeur, onChange }) {
  const [modal, setModal] = useState(null); // 'desactiver' | 'email'
  const [secret, setSecret] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const actif = professeur.statut === 'actif';
  const loginEmail = professeur.user?.email;

  async function action(promesse, message, avecSecret) {
    setEnvoi(true);
    setErreur(null);
    try {
      const res = await promesse;
      if (avecSecret) {
        setSecret({ titre: avecSecret, motDePasse: res.data.mot_de_passe, mailEnvoye: res.data.mail_envoye, message });
      } else {
        onChange(message);
      }
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  const id = professeur.id;

  return (
    <AdminCard>
      <AdminCardHeader title="🔐 Compte de connexion" />
      <AdminCardBody>
        {erreur && <Banner tone="error">{erreur}</Banner>}
        <p>
          Identifiant : <strong>{loginEmail}</strong>{' '}
          <AdminBadge label={actif ? 'Actif' : 'Désactivé'} color={actif ? 'green' : 'red'} />
        </p>
        {!actif && (
          <p style={{ color: '#6b7280' }}>
            Ce professeur ne peut plus se connecter. Son historique (heures, tarifs, séances passées) est conservé.
          </p>
        )}
        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
          {actif ? (
            <>
              <AdminButton variant="secondary" size="sm" onClick={() => setModal('email')}>Modifier l'email de connexion</AdminButton>
              <AdminButton
                variant="secondary"
                size="sm"
                loading={envoi}
                onClick={() => {
                  if (window.confirm('Générer un nouveau mot de passe provisoire et déconnecter ce professeur ?')) {
                    action(client.post(`/professeurs/${id}/reinitialiser-mot-de-passe`), 'Mot de passe réinitialisé.', 'Nouveau mot de passe provisoire');
                  }
                }}
              >
                Réinitialiser le mot de passe
              </AdminButton>
              <AdminButton variant="danger" size="sm" onClick={() => setModal('desactiver')}>Désactiver</AdminButton>
            </>
          ) : (
            <AdminButton
              variant="primary"
              size="sm"
              loading={envoi}
              onClick={() => action(client.post(`/professeurs/${id}/reactiver`), 'Professeur réactivé.', 'Professeur réactivé')}
            >
              Réactiver
            </AdminButton>
          )}
        </div>
      </AdminCardBody>

      {modal === 'desactiver' && (
        <DesactiverModal
          professeur={professeur}
          onClose={() => setModal(null)}
          onDone={(message) => { setModal(null); onChange(message); }}
        />
      )}
      {modal === 'email' && (
        <EmailModal
          professeur={professeur}
          onClose={() => setModal(null)}
          onDone={(message) => { setModal(null); onChange(message); }}
        />
      )}
      {secret && (
        <MotDePasseProvisoireModal
          titre={secret.titre}
          loginEmail={loginEmail}
          motDePasse={secret.motDePasse}
          mailEnvoye={secret.mailEnvoye}
          onClose={() => { const m = secret.message; setSecret(null); onChange(m); }}
        />
      )}
    </AdminCard>
  );
}

function DesactiverModal({ professeur, onClose, onDone }) {
  const [impact, setImpact] = useState(null);
  const [terminer, setTerminer] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  useEffect(() => {
    client.get(`/professeurs/${professeur.id}/impact-desactivation`)
      .then((res) => setImpact(res.data.data))
      .catch((err) => setErreur(getErrorMessage(err)));
  }, [professeur.id]);

  async function confirmer() {
    setEnvoi(true);
    setErreur(null);
    try {
      await client.post(`/professeurs/${professeur.id}/desactiver`, { terminer_assignations: terminer });
      onDone(`${professeur.prenom} ${professeur.nom} est désactivé.`);
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title={`Désactiver ${professeur.prenom} ${professeur.nom} ?`}
      size="sm"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton variant="danger" onClick={confirmer} loading={envoi} disabled={!impact}>Désactiver</AdminButton>
        </>
      }
    >
      {erreur && <Banner tone="error">{erreur}</Banner>}
      {!impact && !erreur && <p>Calcul de l'impact…</p>}
      {impact && (
        <>
          <p>Il ne pourra plus se connecter et sera déconnecté immédiatement. Son historique est conservé.</p>
          <ul>
            <li>{impact.classes_actives} classe(s) active(s)</li>
            <li>{impact.seances_a_venir} séance(s) à venir</li>
            <li>{impact.heures_en_attente} encodage(s) d'heures non finalisé(s)</li>
          </ul>
          {impact.classes_sans_autre_professeur.length > 0 && (
            <Banner tone="warning">
              Dernier professeur de : {impact.classes_sans_autre_professeur.join(', ')}. Pensez à en assigner un autre.
            </Banner>
          )}
          <AdminCheckbox
            label="Terminer ses assignations de classes et le retirer des séances à venir"
            checked={terminer}
            onChange={(e) => setTerminer(e.target.checked)}
          />
        </>
      )}
    </AdminModal>
  );
}

function EmailModal({ professeur, onClose, onDone }) {
  const [email, setEmail] = useState(professeur.user?.email || '');
  const [erreur, setErreur] = useState(null);
  const [champ, setChamp] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  async function soumettre(e) {
    e.preventDefault();
    setEnvoi(true);
    setErreur(null);
    setChamp(null);
    try {
      await client.put(`/professeurs/${professeur.id}/compte`, { login_email: email });
      onDone('Email de connexion modifié ; le professeur a été déconnecté.');
    } catch (err) {
      setChamp(getFieldErrors(err).login_email || null);
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title="Modifier l'email de connexion"
      size="sm"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton variant="primary" type="submit" form="form-email-connexion" loading={envoi}>Enregistrer</AdminButton>
        </>
      }
    >
      {erreur && <Banner tone="error">{erreur}</Banner>}
      <form id="form-email-connexion" onSubmit={soumettre}>
        <AdminFormField label="Email de connexion" htmlFor="login-email" required error={champ}>
          <AdminInput id="login-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required error={champ} />
        </AdminFormField>
      </form>
    </AdminModal>
  );
}
