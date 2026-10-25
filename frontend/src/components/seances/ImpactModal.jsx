import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';

/**
 * Confirmation avec l'impact énoncé avant d'agir (maquettes UX-MOBILE-02) : liste ✓ ce qui change, • ce qui ne change pas,
 * ! avertissements. Le bouton porte le verbe précis de l'action.
 */
export default function ImpactModal({ titre, sousTitre, lignes, erreur, verbe, envoi, onConfirmer, onClose }) {
  return (
    <AdminModal
      isOpen
      title={titre}
      size="sm"
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton loading={envoi} onClick={onConfirmer}>
            {verbe}
          </AdminButton>
        </>
      }
    >
      {sousTitre && <p style={{ margin: '0 0 8px', fontSize: 13, color: 'var(--c-text-2)' }}>{sousTitre}</p>}
      {erreur && (
        <Banner tone="error" role="alert">
          <strong>{erreur}</strong>
        </Banner>
      )}
      <ul className="impact">
        {lignes.map((l, i) => (
          <li key={i} className={l.type ? `impact--${l.type}` : undefined}>
            {l.texte}
          </li>
        ))}
      </ul>
    </AdminModal>
  );
}
