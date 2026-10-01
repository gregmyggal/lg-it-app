import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const bloc = {
  border: `1px solid ${ADMIN_COLORS.border}`,
  borderRadius: ADMIN_RADIUS.md,
  padding: ADMIN_SPACING.lg,
  marginBottom: ADMIN_SPACING.md,
};

/**
 * Conflit d'édition (R-T4-9) : un autre professeur a enregistré ce lien pendant que vous l'éditiez. Rien n'a été
 * écrasé : on choisit entre la version enregistrée (reprise) et la sienne (nouvelle version, l'autre reste restaurable).
 *
 * @param {object} props
 * @param {object} props.lienServeur version actuelle renvoyée par le serveur (409)
 * @param {object} props.brouillon ce que l'utilisateur essayait d'enregistrer
 * @param {() => void} props.onReprendre
 * @param {() => void} props.onGarderLaMienne
 * @param {() => void} props.onClose
 */
export default function ConflitLienModal({ lienServeur, brouillon, onReprendre, onGarderLaMienne, onClose }) {
  return (
    <AdminModal
      isOpen
      title="Ce lien vient d'être modifié"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onReprendre}>
            Reprendre la version enregistrée
          </AdminButton>
          <AdminButton onClick={onGarderLaMienne}>Enregistrer ma version</AdminButton>
        </>
      }
    >
      <Banner tone="warning" role="alert">
        Quelqu'un a enregistré une modification de « {lienServeur.titre} » pendant que vous l'éditiez. Rien n'a été écrasé : choisissez la version à conserver.
      </Banner>
      <div style={bloc}>
        <strong>Version enregistrée</strong>
        <div>{lienServeur.titre}</div>
        <div style={{ wordBreak: 'break-all', fontSize: '13px' }}>{lienServeur.url}</div>
      </div>
      <div style={bloc}>
        <strong>Votre version (non enregistrée)</strong>
        <div>{brouillon.titre}</div>
        <div style={{ wordBreak: 'break-all', fontSize: '13px' }}>{brouillon.url}</div>
      </div>
      <p style={{ fontSize: '13px', marginBottom: 0 }}>Dans les deux cas, l'historique garde la trace : la version écartée reste restaurable pendant 6 mois.</p>
    </AdminModal>
  );
}
