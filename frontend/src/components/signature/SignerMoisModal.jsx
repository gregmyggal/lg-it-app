import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import { LoadingBlock } from '../ui/DataStates';
import EtapeCreationSignature from './EtapeCreationSignature';
import { enregistrerMaSignature, useMaSignature } from '../../hooks/useSignature';
import { signerMois } from '../../hooks/useTimesheets';
import { getErrorMessage } from '../../api/errors';
import { formatEuros, formatHeures } from '../../utils/format';

const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/**
 * SIG-01 : « Accepter et signer… ». Récapitulatif du mois, signature, certification, puis « Signer ».
 * Sans signature enregistrée, le dialogue commence par la créer (étape 1).
 */
export default function SignerMoisModal({ professeurId, annee, mois, recapitulatif, nom, onClose, onSigne }) {
  const ma = useMaSignature();
  const [creation, setCreation] = useState(false);
  const [brouillon, setBrouillon] = useState(null);
  const [certifie, setCertifie] = useState(false);
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [creeIci, setCreeIci] = useState(false);

  const etapeCreation = creation || (!ma.loading && !ma.error && !ma.specimen);
  const premiereFois = !ma.loading && (!ma.specimen || creeIci);
  const r = recapitulatif;

  async function action(fn) {
    setEnvoi(true);
    setErreur(null);
    try {
      await fn();
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  const enregistrer = () => action(async () => {
    await enregistrerMaSignature(brouillon);
    if (!ma.specimen) setCreeIci(true);
    setCreation(false);
    setBrouillon(null);
    ma.reload();
  });
  const signer = () => action(async () => {
    await signerMois(professeurId, annee, mois, true);
    onSigne();
  });

  return (
    <AdminModal
      isOpen
      title={`Signer mon mois ${/^[aeiouy]/.test(MOIS[mois - 1]) ? 'd’' : 'de '}${MOIS[mois - 1]} ${annee}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={etapeCreation && ma.specimen ? () => setCreation(false) : onClose}>
            {etapeCreation && ma.specimen ? 'Retour' : 'Annuler'}
          </AdminButton>
          {etapeCreation ? (
            <AdminButton loading={envoi} disabled={!brouillon} onClick={enregistrer}>{ma.specimen ? 'Enregistrer ma signature' : 'Continuer'}</AdminButton>
          ) : (
            <AdminButton loading={envoi} disabled={!certifie || !ma.specimen} onClick={signer}>Signer</AdminButton>
          )}
        </>
      }
    >
      {premiereFois && !ma.loading && (
        <ol aria-label="Étapes" style={{ display: 'flex', gap: 6, listStyle: 'none', padding: 0, margin: '0 0 12px', fontSize: 12 }}>
          <li style={pastille(etapeCreation ? 'courante' : 'faite')}>1. Ma signature{etapeCreation ? '' : ' ✓'}</li>
          <li style={pastille(etapeCreation ? 'a-venir' : 'courante')}>2. Signer</li>
        </ol>
      )}
      {erreur && <Banner tone="error">{erreur}</Banner>}
      {ma.loading && <LoadingBlock lignes={3} />}

      {!ma.loading && etapeCreation && <EtapeCreationSignature nom={nom} initial={ma.specimen} onChange={setBrouillon} />}

      {!ma.loading && !etapeCreation && (
        <>
          {r && (
            <>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 8, marginBottom: 6 }}>
                <Chiffre valeur={r.lignes} libelle={r.lignes > 1 ? 'lignes' : 'ligne'} />
                <Chiffre valeur={formatHeures(r.heures)} libelle="prestées" />
                <Chiffre valeur={formatEuros(r.total_eur)} libelle="défraiement" />
              </div>
              <p style={{ fontSize: 12, color: 'var(--c-text-2)', margin: '0 0 10px' }}>
                {r.ajustements > 0 && `dont ${r.ajustements} ajustement${r.ajustements > 1 ? 's' : ''} de la direction · `}
                {r.compte_bancaire ? `compte ${r.compte_bancaire}` : 'compte bancaire non renseigné'}
              </p>
            </>
          )}
          <div style={{ position: 'relative', border: '1px solid var(--c-border)', borderRadius: 8, background: '#fff', height: 100, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <span style={{ position: 'absolute', top: 6, left: 10, fontSize: 11, color: '#6b7280' }}>Votre signature</span>
            <img src={ma.specimen.image} alt="Votre signature" style={{ maxHeight: 70, maxWidth: '75%' }} />
          </div>
          <div style={{ textAlign: 'right', marginTop: 4 }}>
            <button type="button" onClick={() => setCreation(true)} style={{ border: 0, background: 'none', padding: 0, color: 'var(--c-primary)', textDecoration: 'underline', cursor: 'pointer', fontSize: 12 }}>
              Modifier ma signature
            </button>
          </div>
          <label style={{ display: 'flex', gap: 8, alignItems: 'flex-start', marginTop: 12, fontSize: 13 }}>
            <input type="checkbox" checked={certifie} onChange={(e) => setCertifie(e.target.checked)} style={{ marginTop: 3 }} />
            <span>Je certifie l’exactitude de ces prestations bénévoles.</span>
          </label>
          <p style={{ fontSize: 12, color: 'var(--c-text-2)', background: 'var(--c-bg, #f9fafb)', borderRadius: 8, padding: 10, margin: '12px 0 0' }}>
            Signature électronique horodatée par le serveur, liée à votre compte et au contenu exact de ce mois (lignes, montants, compte bancaire). Si une saisie change ensuite, une nouvelle signature vous sera demandée.
          </p>
        </>
      )}
    </AdminModal>
  );
}

function Chiffre({ valeur, libelle }) {
  return (
    <div style={{ border: '1px solid var(--c-border)', borderRadius: 8, padding: 8, textAlign: 'center' }}>
      <strong style={{ display: 'block', fontSize: 18 }}>{valeur}</strong>
      <span style={{ fontSize: 12, color: 'var(--c-text-2)' }}>{libelle}</span>
    </div>
  );
}

function pastille(etat) {
  const styles = {
    courante: { background: 'var(--c-primary)', color: '#fff', fontWeight: 600 },
    faite: { background: 'var(--tone-success-bg, #d1fae5)', color: 'var(--tone-success-fg, #047857)' },
    'a-venir': { background: 'var(--c-border)', color: 'var(--c-text-2)' },
  };
  return { padding: '3px 10px', borderRadius: 99, ...styles[etat] };
}
