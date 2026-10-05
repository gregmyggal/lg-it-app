import { useState } from 'react';
import { AdminCard, AdminCardHeader, AdminCardBody } from '../AdminPageLayout';
import Banner from '../ui/Banner';
import { LoadingBlock, ErrorBlock } from '../ui/DataStates';
import { enregistrerSignatureParametres, useSignatureParametres } from '../../hooks/useSignature';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage } from '../../api/errors';

/** SIG-01 : vérification publique des signatures (QR sur le PDF + page /verif). S'applique aux signatures émises ensuite. */
export default function SignatureParametresCard() {
  const toast = useToast();
  const p = useSignatureParametres();
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function basculer(valeur) {
    setEnvoi(true);
    setErreur(null);
    try {
      await enregistrerSignatureParametres({ verification_publique: valeur });
      toast.success(valeur ? 'Vérification publique activée pour les prochaines signatures.' : 'Vérification publique désactivée pour les prochaines signatures.');
      p.reload();
    } catch (err) {
      setErreur(getErrorMessage(err, 'Enregistrement impossible'));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminCard>
      <AdminCardHeader title="Signature électronique des fiches" />
      <AdminCardBody>
        {erreur && <Banner tone="error">{erreur}</Banner>}
        {p.loading && <LoadingBlock lignes={2} />}
        {p.error && <ErrorBlock message={p.error} onRetry={p.reload} />}
        {p.data && (
          <>
            <label style={{ display: 'flex', gap: 10, alignItems: 'flex-start', cursor: envoi ? 'wait' : 'pointer' }}>
              <input
                type="checkbox"
                checked={p.data.verification_publique}
                disabled={envoi}
                onChange={(e) => basculer(e.target.checked)}
                style={{ marginTop: 3 }}
              />
              <span>
                <strong>Vérification publique des signatures</strong>
                <br />
                <span style={{ fontSize: 13, color: 'var(--c-text-2)' }}>
                  Un QR code et un lien de vérification sont imprimés sous la signature. Toute personne qui a la fiche peut alors vérifier
                  qu’elle a bien été signée, sans voir ni montant, ni compte bancaire, ni adresse IP.
                </span>
              </span>
            </label>
            <p style={{ fontSize: 12, color: 'var(--c-text-2)', margin: '10px 0 0' }}>
              Ne s’applique qu’aux signatures émises après le changement. Les signatures déjà émises gardent leur réglage d’origine.
            </p>
          </>
        )}
      </AdminCardBody>
    </AdminCard>
  );
}
