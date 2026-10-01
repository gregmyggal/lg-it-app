import { useEffect, useState } from 'react';
import client from '../../api/client';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminCard, AdminCardHeader, AdminCardBody, AdminBadge } from '../AdminPageLayout';
import { AdminFormField, AdminInput, AdminCheckbox } from '../AdminFormField';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import LienCopiable from '../ui/LienCopiable';
import { STATUTS_ACCES } from '../../utils/statuts';
import { detailAcces, libelleEnvoiLien } from '../../utils/acces';
import { formatDateHeure } from '../../utils/dates';
import { getErrorMessage, getFieldErrors } from '../../api/errors';

/**
 * Compte de connexion du professeur : état d'accès, envoi d'invitation / lien de réinitialisation (ADMIN-03),
 * activer / désactiver, changer l'email de connexion. Le serveur reste la source de vérité.
 *
 * @param {object} props
 * @param {object} props.professeur  professeur chargé avec `user` et `acces`
 * @param {(message: string|null) => void} props.onChange  rechargement + message de succès éventuel
 */
export default function CompteProfesseurSection({ professeur, onChange }) {
  const [modal, setModal] = useState(null); // 'desactiver' | 'email'
  const [confirmation, setConfirmation] = useState(null); // 'envoi' | 'reactivation'
  const [erreur, setErreur] = useState(null);
  const [lien, setLien] = useState(null);
  const [apresEmail, setApresEmail] = useState(false);
  const [envoi, setEnvoi] = useState(false);
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

  const reactiver = () => executer(
    client.post(`/professeurs/${id}/reactiver`),
    () => `Professeur réactivé. Invitation envoyée à ${loginEmail} (l'ancien mot de passe n'est plus valable).`,
  );

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
          <AdminBadge label={actif ? 'Actif' : 'Désactivé'} color={actif ? 'green' : 'red'} />
        </p>
        {actif && acces?.statut && (
          <p>
            Accès : <StatutBadge table={STATUTS_ACCES} valeur={acces.statut} />
            <span style={{ display: 'block', fontSize: '13px', color: '#4b5563', marginTop: '4px' }}>{detailAcces(acces)}</span>
          </p>
        )}
        {!actif && (
          <p style={{ color: '#6b7280' }}>
            Ce professeur ne peut plus se connecter. Son historique (heures, tarifs, séances passées) est conservé.
            Réactivez le compte pour envoyer une invitation.
          </p>
        )}
        {lien && <LienCopiable lien={lien} />}

        {confirmation && (
          <div role="group" aria-label="Confirmation d'envoi" style={{ margin: '12px 0', padding: '12px', background: '#f3f4f6', borderRadius: '6px' }}>
            <p style={{ marginTop: 0 }}>
              {confirmation === 'envoi'
                ? <>Envoyer un email à <strong>{loginEmail}</strong> ? Les liens précédents seront annulés.</>
                : <>Le compte sera réactivé et un email de définition de mot de passe sera envoyé à <strong>{loginEmail}</strong> (l&apos;ancien mot de passe ne sera plus valable).</>}
            </p>
            <div style={{ display: 'flex', gap: '8px' }}>
              <AdminButton size="sm" variant="primary" loading={envoi} onClick={confirmation === 'envoi' ? envoyerLien : reactiver}>
                {confirmation === 'envoi' ? 'Envoyer' : 'Réactiver et envoyer'}
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
              <AdminButton variant="danger" size="sm" onClick={() => setModal('desactiver')}>Désactiver</AdminButton>
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
            <button type="button" onClick={genererLien} disabled={envoi} style={{ background: 'none', border: 'none', padding: 0, color: '#1d4ed8', textDecoration: 'underline', cursor: 'pointer', fontWeight: 400 }}>
              Générer un lien à transmettre
            </button>
          </p>
        )}
      </AdminCardBody>

      {modal === 'desactiver' && (
        <DesactiverModal
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
