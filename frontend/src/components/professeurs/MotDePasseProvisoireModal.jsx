import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';

/**
 * Affiche UNE seule fois le mot de passe provisoire (création, réactivation, réinitialisation).
 * Le serveur ne le renverra plus : la direction peut le copier si le mail n'arrive pas.
 */
export default function MotDePasseProvisoireModal({ titre, loginEmail, motDePasse, mailEnvoye, onClose }) {
  const [copie, setCopie] = useState(false);

  async function copier() {
    try {
      await navigator.clipboard.writeText(motDePasse);
      setCopie(true);
    } catch {
      setCopie(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title={titre}
      size="sm"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={<AdminButton variant="primary" onClick={onClose}>J'ai noté le mot de passe</AdminButton>}
    >
      <Banner tone={mailEnvoye ? 'success' : 'warning'}>
        {mailEnvoye
          ? 'Un email d\'invitation a été envoyé au professeur.'
          : 'L\'email d\'invitation n\'a pas pu être envoyé : communiquez ces identifiants vous-même.'}
      </Banner>
      <p>Identifiant : <strong>{loginEmail}</strong></p>
      <p>
        Mot de passe provisoire : <code data-testid="mot-de-passe">{motDePasse}</code>{' '}
        <AdminButton variant="secondary" size="sm" onClick={copier}>{copie ? 'Copié ✓' : 'Copier'}</AdminButton>
      </p>
      <p style={{ fontSize: '13px', color: '#6b7280' }}>
        Il ne sera plus affiché ensuite. Le professeur devra le changer à sa première connexion.
      </p>
    </AdminModal>
  );
}
