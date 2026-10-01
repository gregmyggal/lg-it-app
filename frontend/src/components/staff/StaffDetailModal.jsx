import { useState } from 'react';
import Modal from '../ui/Modal';
import AdminButton from '../AdminButton';
import LienCopiable from './LienCopiable';
import { useGenerateStaffLink, useSendStaffLink } from '../../hooks/useStaff';
import { STATUTS_ACCES, libelleRole } from '../../utils/statuts';
import { formatDate, formatDateHeure } from '../../utils/dates';
import { getErrorMessage } from '../../api/errors';

const LIBELLE_ACCES_DETAIL = {
  mot_de_passe_defini: (a) => `Mot de passe défini le ${formatDateHeure(a.mot_de_passe_defini_le)}`,
  invitation_en_attente: (a) => `Invitation envoyée le ${formatDateHeure(a.invitation_envoyee_le)} (expire le ${formatDateHeure(a.invitation_expire_le)})`,
  invitation_expiree: (a) => `Invitation expirée le ${formatDateHeure(a.invitation_expire_le)}`,
  invitation_non_envoyee: () => 'Invitation non envoyée',
  mot_de_passe_provisoire: () => 'Mot de passe provisoire (jamais changé)',
};

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
  const [info, setInfo] = useState('');
  const [erreur, setErreur] = useState('');
  const [lien, setLien] = useState('');

  const actif = staff.statut === 'actif';
  const acces = staff.acces;
  const invitation = acces?.statut?.startsWith('invitation_');
  const libelleEnvoi = invitation ? 'Renvoyer l’invitation' : 'Envoyer un lien de réinitialisation';
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
            <strong>{STATUTS_ACCES[acces.statut]?.label ?? acces.statut}</strong>
            <div style={{ fontSize: '13px', color: '#4b5563' }}>{LIBELLE_ACCES_DETAIL[acces.statut]?.(acces)}</div>
          </>
        ))}
        {staff.date_sortie && champ('Date de sortie:', formatDate(staff.date_sortie))}
      </div>

      <div role="status">
        {info && <p style={{ padding: '12px', backgroundColor: '#d4edda', borderRadius: '4px' }}>{info}</p>}
      </div>
      {erreur && (
        <p role="alert" style={{ padding: '12px', backgroundColor: '#ffcdd2', color: '#c62828', borderRadius: '4px' }}>{erreur}</p>
      )}
      {lien && <LienCopiable lien={lien} />}

      {confirmation && (
        <div style={{ margin: '16px 0', padding: '12px', backgroundColor: '#f5f5f5', borderRadius: '4px' }}>
          <p style={{ marginTop: 0 }}>
            Envoyer un email à <strong>{staff.email}</strong> ? Les liens précédents seront annulés.
          </p>
          <div style={{ display: 'flex', gap: '8px' }}>
            <AdminButton onClick={envoyer} disabled={occupe}>Envoyer</AdminButton>
            <button type="button" onClick={() => setConfirmation(false)}>Annuler</button>
          </div>
        </div>
      )}

      {!actif && (
        <p style={{ fontSize: '13px', color: '#4b5563' }}>
          Réactivez le compte pour envoyer une invitation.
        </p>
      )}

      <div style={{ borderTop: '1px solid #ddd', paddingTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end', flexWrap: 'wrap' }}>
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
          <AdminButton onClick={onReactiver} style={{ background: '#4caf50' }}>
            Réactiver
          </AdminButton>
        )}
        <button type="button" onClick={onClose} style={{ background: 'none', border: '1px solid #ccc', padding: '8px 16px', cursor: 'pointer' }}>
          Fermer
        </button>
      </div>

      {actif && (
        <p style={{ marginTop: '12px', fontSize: '13px' }}>
          L&apos;email n&apos;arrive pas ?{' '}
          <button type="button" onClick={genererLien} disabled={occupe} style={{ background: 'none', border: 'none', padding: 0, color: '#1d4ed8', textDecoration: 'underline', cursor: 'pointer', fontWeight: 400 }}>
            Générer un lien à transmettre
          </button>
        </p>
      )}
    </Modal>
  );
}
