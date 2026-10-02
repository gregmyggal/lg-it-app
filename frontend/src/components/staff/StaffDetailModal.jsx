import { useState } from 'react';
import Modal from '../ui/Modal';
import AdminButton from '../AdminButton';
import LienCopiable from '../ui/LienCopiable';
import { useGenerateStaffLink, useSendStaffLink } from '../../hooks/useStaff';
import { STATUTS_ACCES, libelleRole } from '../../utils/statuts';
import { formatDate, formatDateHeure } from '../../utils/dates';
import { cleStatutAcces, detailAcces, libelleEnvoiLien, sansMotDePasseDefini } from '../../utils/acces';
import { getErrorMessage } from '../../api/errors';

const champ = (label, valeur) => (
  <div style={{ marginBottom: '16px' }}>
    <span style={{ display: 'block', fontWeight: 'bold', marginBottom: '4px' }}>{label}</span>
    <div>{valeur}</div>
  </div>
);

export default function StaffDetailModal({ staff, onClose, onDesactiver, onReactiver, onEditEmail, onChanged }) {
  const { send, loading: envoiEnCours } = useSendStaffLink();
  const { generate, loading: generationEnCours } = useGenerateStaffLink();
  const [confirmation, setConfirmation] = useState(false);
  const [confirmReactivation, setConfirmReactivation] = useState(false);
  const [envoyerApresReactivation, setEnvoyerApresReactivation] = useState(true);
  const [info, setInfo] = useState('');
  const [erreur, setErreur] = useState('');
  const [lien, setLien] = useState('');

  const actif = staff.statut === 'actif';
  const acces = staff.acces;
  const libelleEnvoi = libelleEnvoiLien(acces);
  const occupe = envoiEnCours || generationEnCours;

  async function envoyer() {
    setConfirmation(false);
    setInfo('');
    setErreur('');
    setLien('');
    try {
      const response = await send(staff.id);
      const { data, email_envoye: emailEnvoye, lien: lienRepli } = response.data;
      onChanged?.(data);
      if (emailEnvoye) {
        setInfo(`Email envoyé à ${staff.email} à ${formatDateHeure(new Date().toISOString()).slice(-5)}.`);
      } else {
        setErreur('L’email n’a pas pu être envoyé. Réessayez, ou transmettez le lien ci-dessous.');
        setLien(lienRepli);
      }
    } catch (err) {
      setErreur(getErrorMessage(err));
    }
  }

  async function genererLien() {
    setInfo('');
    setErreur('');
    try {
      const response = await generate(staff.id);
      onChanged?.(response.data.data);
      setLien(response.data.lien);
    } catch (err) {
      setErreur(getErrorMessage(err));
    }
  }

  return (
    <Modal onClose={onClose}>
      <h2>{staff.name}</h2>

      <div style={{ marginBottom: '24px' }}>
        {champ('Email:', staff.email)}
        {champ('Rôle:', libelleRole(staff.role))}
        {champ('Statut:', actif ? 'Actif' : 'Désactivé')}
        {actif && acces?.statut && champ('Accès:', (
          <>
            <strong>{STATUTS_ACCES[cleStatutAcces(acces)]?.label ?? acces.statut}</strong>
            <div style={{ fontSize: '13px', color: 'var(--c-text-muted)' }}>{detailAcces(acces)}</div>
          </>
        ))}
        {actif && sansMotDePasseDefini(acces) && (
          <p style={{ fontSize: '13px', color: 'var(--c-text-muted)', marginTop: 0 }}>
            Aucun email ne sera envoyé à cette personne avant la définition de son mot de passe. Ses notifications restent visibles dans l&apos;application.
          </p>
        )}
        {staff.date_sortie && champ('Date de sortie:', formatDate(staff.date_sortie))}
      </div>

      <div role="status">
        {info && <p style={{ padding: '12px', backgroundColor: 'var(--tone-success-bg)', borderRadius: '4px' }}>{info}</p>}
      </div>
      {erreur && (
        <p role="alert" style={{ padding: '12px', backgroundColor: 'var(--tone-error-bg)', color: 'var(--tone-error-fg)', borderRadius: '4px' }}>{erreur}</p>
      )}
      {lien && <LienCopiable lien={lien} />}

      {confirmation && (
        <div style={{ margin: '16px 0', padding: '12px', backgroundColor: 'var(--c-bg)', borderRadius: '4px' }}>
          <p style={{ marginTop: 0 }}>
            Envoyer un email à <strong>{staff.email}</strong> ? Les liens précédents seront annulés.
          </p>
          <div style={{ display: 'flex', gap: '8px' }}>
            <AdminButton onClick={envoyer} disabled={occupe}>Envoyer</AdminButton>
            <button type="button" onClick={() => setConfirmation(false)}>Annuler</button>
          </div>
        </div>
      )}

      {confirmReactivation && (
        <div style={{ margin: '16px 0', padding: '12px', backgroundColor: 'var(--c-bg)', borderRadius: '4px' }}>
          <p style={{ marginTop: 0 }}>
            Le compte sera réactivé ; l&apos;ancien mot de passe ne sera plus valable.
          </p>
          <label style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
            <input type="checkbox" checked={envoyerApresReactivation} onChange={(e) => setEnvoyerApresReactivation(e.target.checked)} />
            Envoyer l&apos;invitation maintenant
          </label>
          <div style={{ display: 'flex', gap: '8px', marginTop: '12px' }}>
            <AdminButton onClick={() => onReactiver({ envoyerInvitation: envoyerApresReactivation })}>
              {envoyerApresReactivation ? 'Réactiver et envoyer' : 'Réactiver sans envoyer'}
            </AdminButton>
            <button type="button" onClick={() => setConfirmReactivation(false)}>Annuler</button>
          </div>
        </div>
      )}

      {!actif && (
        <p style={{ fontSize: '13px', color: 'var(--c-text-muted)' }}>
          Réactivez le compte pour envoyer une invitation.
        </p>
      )}

      <div style={{ borderTop: '1px solid var(--c-border)', paddingTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end', flexWrap: 'wrap' }}>
        {actif ? (
          <>
            <AdminButton onClick={onEditEmail} disabled={occupe} style={{ background: '#2196f3' }}>
              Modifier email
            </AdminButton>
            <AdminButton onClick={() => { setConfirmation(true); setInfo(''); setErreur(''); setLien(''); }} disabled={occupe || confirmation} style={{ background: '#ff9800' }}>
              {envoiEnCours ? 'Envoi…' : libelleEnvoi}
            </AdminButton>
            <AdminButton onClick={onDesactiver} style={{ background: '#f44336' }}>
              Désactiver
            </AdminButton>
          </>
        ) : (
          <AdminButton onClick={() => setConfirmReactivation(true)} disabled={confirmReactivation} style={{ background: '#4caf50' }}>
            Réactiver
          </AdminButton>
        )}
        <button type="button" onClick={onClose} style={{ background: 'none', border: '1px solid var(--c-border)', padding: '8px 16px', cursor: 'pointer' }}>
          Fermer
        </button>
      </div>

      {actif && (
        <p style={{ marginTop: '12px', fontSize: '13px' }}>
          L&apos;email n&apos;arrive pas ?{' '}
          <button type="button" onClick={genererLien} disabled={occupe} style={{ background: 'none', border: 'none', padding: 0, color: 'var(--tone-primary-fg)', textDecoration: 'underline', cursor: 'pointer', fontWeight: 400 }}>
            Générer un lien à transmettre
          </button>
        </p>
      )}
    </Modal>
  );
}
