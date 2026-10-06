import { useState } from 'react';
import client from '../../api/client';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminCard, AdminCardHeader, AdminCardBody, AdminBadge } from '../AdminPageLayout';
import { AdminFormField, AdminInput, AdminCheckbox } from '../AdminFormField';
import { ArchiverProfesseurModal } from './CycleVieProfesseurModals';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import LienCopiable from '../ui/LienCopiable';
import { STATUTS_ACCES } from '../../utils/statuts';
import { cleStatutAcces, detailAcces, estAccesNonEnvoye, libelleEnvoiLien, sansMotDePasseDefini } from '../../utils/acces';
import { formatDateHeure } from '../../utils/dates';
import { getErrorMessage, getFieldErrors } from '../../api/errors';

/**
 * Compte de connexion du professeur : état d'accès, envoi d'invitation / lien de réinitialisation (ADMIN-03),
 * archiver / réactiver (PROF-02), changer l'email de connexion. Le serveur reste la source de vérité.
 *
 * @param {object} props
 * @param {object} props.professeur  professeur chargé avec `user` et `acces`
 * @param {(message: string|null) => void} props.onChange  rechargement + message de succès éventuel
 */
export default function CompteProfesseurSection({ professeur, onChange }) {
  const [modal, setModal] = useState(null); // 'archiver' | 'email'
  const [confirmation, setConfirmation] = useState(null); // 'envoi' | 'reactivation'
  const [erreur, setErreur] = useState(null);
  const [lien, setLien] = useState(null);
  const [apresEmail, setApresEmail] = useState(false);
  const [envoi, setEnvoi] = useState(false);
  const [envoyerApresReactivation, setEnvoyerApresReactivation] = useState(true);
  const actif = professeur.statut === 'actif';
  const loginEmail = professeur.user?.email;
  const acces = professeur.acces;
  const id = professeur.id;

  function reinitialiserRetour() {
    setErreur(null);
    setLien(null);
  }

  /** Appel d'envoi (invitation, lien, réactivation) : succès → message ; échec d'envoi → lien de repli. */
  async function executer(promesse, messageOk) {
    setEnvoi(true);
    setConfirmation(null);
    setApresEmail(false);
    reinitialiserRetour();
    try {
      const res = await promesse;
      if (res.data.mail_envoye) {
        onChange(messageOk(res.data));
      } else {
        setErreur('L\u2019email n\u2019a pas pu être envoyé. Réessayez, ou transmettez le lien ci-dessous.');
        setLien(res.data.lien);
        onChange(null);
      }
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  const envoyerLien = () => executer(
    client.post(`/professeurs/${id}/envoyer-lien`),
    () => `Email envoyé à ${loginEmail} à ${formatDateHeure(new Date().toISOString()).slice(-5)}.`,
  );

  const reactiver = async () => {
    if (envoyerApresReactivation) {
      return executer(
        client.post(`/professeurs/${id}/reactiver`),
        () => `Professeur réactivé. Invitation envoyée à ${loginEmail} (l'ancien mot de passe n'est plus valable).`,
      );
    }
    setEnvoi(true);
    setConfirmation(null);
    reinitialiserRetour();
    try {
      await client.post(`/professeurs/${id}/reactiver`, { envoyer_invitation: false });
      onChange('Professeur réactivé. Aucun email envoyé : accès non envoyé (l\u2019ancien mot de passe n\u2019est plus valable).');
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  };

  async function genererLien() {
    reinitialiserRetour();
    setEnvoi(true);
    try {
      const res = await client.post(`/professeurs/${id}/generer-lien`);
      setLien(res.data.lien);
      onChange(null);
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminCard>
      <AdminCardHeader title="🔐 Compte de connexion" />
      <AdminCardBody>
        {erreur && <Banner tone="error">{erreur}</Banner>}
        {apresEmail && actif && (
          <Banner
            tone="info"
            actions={<AdminButton size="sm" variant="secondary" onClick={envoyerLien} loading={envoi}>Envoyer l&apos;invitation à la nouvelle adresse</AdminButton>}
          >
            Email de connexion modifié : les liens en cours ont été annulés.
          </Banner>
        )}
        <p>
          Identifiant : <strong>{loginEmail}</strong>{' '}
          <AdminBadge label={actif ? 'Actif' : 'Archivé'} color={actif ? 'green' : 'purple'} />
        </p>
        {actif && acces?.statut && (
          <p>
            Accès : <StatutBadge table={STATUTS_ACCES} valeur={cleStatutAcces(acces)} />
            <span style={{ display: 'block', fontSize: '13px', color: 'var(--c-text-muted)', marginTop: '4px' }}>{detailAcces(acces)}</span>
          </p>
        )}
        {actif && estAccesNonEnvoye(acces) && (
          <Banner tone="info">Cette personne n&apos;a pas encore accès à l&apos;application. Configurez ses classes et ses tarifs, puis envoyez l&apos;invitation quand vous êtes prêt.</Banner>
        )}
        {actif && sansMotDePasseDefini(acces) && (
          <p style={{ fontSize: '13px', color: 'var(--c-text-muted)' }}>
            Aucun email ne sera envoyé à cette personne avant la définition de son mot de passe. Ses notifications restent visibles dans l&apos;application.
          </p>
        )}
        {!actif && (
          <p style={{ color: 'var(--c-text-2)' }}>
            Ce professeur est archivé : il ne peut plus se connecter et n&apos;est plus assigné à aucune séance à venir.
            Son historique (heures, fiches de défraiement, tarifs, séances passées) est conservé. Réactivez le compte pour envoyer une invitation.
          </p>
        )}
        {lien && <LienCopiable lien={lien} />}

        {confirmation && (
          <div role="group" aria-label="Confirmation d'envoi" style={{ margin: '12px 0', padding: '12px', background: 'var(--c-hover)', borderRadius: '6px' }}>
            <p style={{ marginTop: 0 }}>
              {confirmation === 'envoi'
                ? <>Envoyer un email à <strong>{loginEmail}</strong> ? Les liens précédents seront annulés.</>
                : <>Le compte sera réactivé ; l&apos;ancien mot de passe ne sera plus valable.</>}
            </p>
            {confirmation === 'reactivation' && (
              <AdminCheckbox
                id="reactivation-envoyer"
                label={`Envoyer l'invitation maintenant à ${loginEmail}`}
                checked={envoyerApresReactivation}
                onChange={(e) => setEnvoyerApresReactivation(e.target.checked)}
              />
            )}
            <div style={{ display: 'flex', gap: '8px' }}>
              <AdminButton size="sm" variant="primary" loading={envoi} onClick={confirmation === 'envoi' ? envoyerLien : reactiver}>
                {confirmation === 'envoi' ? 'Envoyer' : (envoyerApresReactivation ? 'Réactiver et envoyer' : 'Réactiver sans envoyer')}
              </AdminButton>
              <AdminButton size="sm" variant="secondary" onClick={() => setConfirmation(null)}>Annuler</AdminButton>
            </div>
          </div>
        )}

        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
          {actif ? (
            <>
              <AdminButton variant="secondary" size="sm" onClick={() => setModal('email')}>Modifier l'email de connexion</AdminButton>
              <AdminButton
                variant="secondary"
                size="sm"
                disabled={envoi || confirmation === 'envoi'}
                onClick={() => { reinitialiserRetour(); setConfirmation('envoi'); }}
              >
                {libelleEnvoiLien(acces)}
              </AdminButton>
              <AdminButton variant="secondary" size="sm" onClick={() => setModal('archiver')}>Archiver…</AdminButton>
            </>
          ) : (
            <AdminButton
              variant="primary"
              size="sm"
              disabled={envoi || confirmation === 'reactivation'}
              onClick={() => { reinitialiserRetour(); setConfirmation('reactivation'); }}
            >
              Réactiver
            </AdminButton>
          )}
        </div>
        {actif && (
          <p style={{ marginTop: '12px', fontSize: '13px' }}>
            L&apos;email n&apos;arrive pas ?{' '}
            <button type="button" onClick={genererLien} disabled={envoi} style={{ background: 'none', border: 'none', padding: 0, color: 'var(--tone-primary-fg)', textDecoration: 'underline', cursor: 'pointer', fontWeight: 400 }}>
              Générer un lien à transmettre
            </button>
          </p>
        )}
      </AdminCardBody>

      {modal === 'archiver' && (
        <ArchiverProfesseurModal
          professeur={professeur}
          onClose={() => setModal(null)}
          onDone={(message) => { setModal(null); reinitialiserRetour(); onChange(message); }}
        />
      )}
      {modal === 'email' && (
        <EmailModal
          professeur={professeur}
          onClose={() => setModal(null)}
          onDone={(message) => { setModal(null); reinitialiserRetour(); setApresEmail(true); onChange(message); }}
        />
      )}
    </AdminCard>
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
