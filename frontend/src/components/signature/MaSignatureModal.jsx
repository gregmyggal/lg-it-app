import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import { LoadingBlock, ErrorBlock } from '../ui/DataStates';
import EtapeCreationSignature from './EtapeCreationSignature';
import { enregistrerMaSignature, useMaSignature } from '../../hooks/useSignature';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage } from '../../api/errors';

/** SIG-01 : « Ma signature » (bloc compte du portail) — créer, modifier, télécharger. Pas de nouvelle page. */
export default function MaSignatureModal({ nom, onClose }) {
  const toast = useToast();
  const actuelle = useMaSignature();
  const [brouillon, setBrouillon] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function enregistrer() {
    setEnvoi(true);
    setErreur(null);
    try {
      await enregistrerMaSignature(brouillon);
      toast.success('Signature enregistrée.');
      onClose();
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title="Ma signature"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton loading={envoi} disabled={!brouillon} onClick={enregistrer}>Enregistrer ma signature</AdminButton>
        </>
      }
    >
      {erreur && <Banner tone="error">{erreur}</Banner>}
      {actuelle.loading && <LoadingBlock lignes={3} />}
      {actuelle.error && <ErrorBlock message={actuelle.error} onRetry={actuelle.reload} />}
      {!actuelle.loading && !actuelle.error && <EtapeCreationSignature nom={nom} initial={actuelle.specimen} onChange={setBrouillon} />}
    </AdminModal>
  );
}
