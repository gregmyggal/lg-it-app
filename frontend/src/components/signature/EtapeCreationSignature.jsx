import { useState } from 'react';
import SignatureCreator from './SignatureCreator';

/**
 * Créateur + consentement, partagé par « Ma signature » et la première signature d'un mois.
 * `onChange` reçoit le brouillon prêt à envoyer (ou null tant que l'image est vide ou le consentement non coché).
 */
export default function EtapeCreationSignature({ nom, initial, onChange }) {
  const [brouillon, setBrouillon] = useState(null);
  const [consenti, setConsenti] = useState(false);

  function maj(b, c) {
    setBrouillon(b);
    setConsenti(c);
    onChange(b?.image && c ? { ...b, consentement: true } : null);
  }

  return (
    <>
      <SignatureCreator nom={nom} initial={initial} onChange={(b) => maj(b, consenti)} />
      <label style={{ display: 'flex', gap: 8, alignItems: 'flex-start', marginTop: 12, fontSize: 13 }}>
        <input type="checkbox" checked={consenti} onChange={(e) => maj(brouillon, e.target.checked)} style={{ marginTop: 3 }} />
        <span>Je reconnais cette image comme ma signature et j’accepte son utilisation sur mes fiches de défraiement.</span>
      </label>
      <p style={{ fontSize: 12, color: 'var(--c-text-2)', margin: '8px 0 0' }}>Vos fiches déjà signées ne changent pas si vous modifiez votre signature plus tard.</p>
    </>
  );
}
