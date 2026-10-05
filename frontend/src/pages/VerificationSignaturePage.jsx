import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { verifierSignature } from '../hooks/useSignature';
import { getStatus } from '../api/errors';
import { formatDateHeure } from '../utils/dates';

const ETATS = {
  valide: { icone: '✓', titre: 'Signature valide', bg: 'var(--tone-success-bg)', fg: 'var(--tone-success-fg)' },
  remplacee: {
    icone: '↻', titre: 'Signature remplacée', bg: 'var(--tone-warning-bg)', fg: 'var(--tone-warning-fg)',
    texte: 'Une signature plus récente existe pour ce document. Demandez la dernière version de la fiche.',
  },
  archivee: {
    icone: '✓', titre: 'Signature archivée', bg: 'var(--tone-info-bg)', fg: 'var(--tone-info-fg)',
    texte: 'Cette signature a plus de 7 ans : sa preuve détaillée n’est plus conservée.',
  },
  invalide: {
    icone: '✕', titre: 'Signature invalide', bg: 'var(--tone-error-bg)', fg: 'var(--tone-error-fg)',
    texte: 'La preuve de cette signature ne correspond plus à ce qui a été scellé. Contactez l’école.',
  },
};

/** SIG-01 : page publique (lien ou QR imprimé sous la signature). Données minimales, jamais montant, IBAN ni IP. */
export default function VerificationSignaturePage() {
  const { id } = useParams();
  const [etat, setEtat] = useState({ chargement: true });

  useEffect(() => {
    let annule = false;
    verifierSignature(id)
      .then((data) => !annule && setEtat({ data }))
      .catch((err) => !annule && setEtat({ introuvable: getStatus(err) === 404, erreur: getStatus(err) !== 404 }));
    return () => {
      annule = true;
    };
  }, [id]);

  const e = etat.data && ETATS[etat.data.statut];

  return (
    <div className="card" style={{ maxWidth: 460, margin: '40px auto' }}>
      <p style={{ margin: '0 0 16px', fontWeight: 700 }}>Logiscool Pays Vert · Vérification de signature</p>
      {etat.chargement && <p role="status">Vérification en cours…</p>}
      {etat.introuvable && (
        <Resultat icone="?" titre="Signature introuvable" bg="var(--c-bg)" fg="var(--c-text-2)">
          Vérifiez l’identifiant imprimé sous la signature (<code>{id}</code>).
        </Resultat>
      )}
      {etat.erreur && (
        <Resultat icone="!" titre="Vérification impossible" bg="var(--tone-error-bg)" fg="var(--tone-error-fg)">
          Le service ne répond pas. Réessayez dans quelques minutes.
        </Resultat>
      )}
      {e && (
        <Resultat icone={e.icone} titre={e.titre} bg={e.bg} fg={e.fg}>
          {e.texte && <p style={{ margin: '0 0 12px' }}>{e.texte}</p>}
          <dl style={{ display: 'grid', gridTemplateColumns: 'auto 1fr', gap: '6px 16px', margin: 0, textAlign: 'left', fontSize: 14 }}>
            <dt style={{ color: 'var(--c-text-2)' }}>Identifiant</dt><dd style={{ margin: 0 }}><code>{etat.data.public_id}</code></dd>
            <dt style={{ color: 'var(--c-text-2)' }}>Document</dt><dd style={{ margin: 0 }}>{etat.data.document}</dd>
            <dt style={{ color: 'var(--c-text-2)' }}>Signataire</dt><dd style={{ margin: 0 }}>{etat.data.signataire}</dd>
            <dt style={{ color: 'var(--c-text-2)' }}>Signé le</dt><dd style={{ margin: 0 }}>{formatDateHeure(etat.data.signed_at)}</dd>
            <dt style={{ color: 'var(--c-text-2)' }}>Émetteur</dt><dd style={{ margin: 0 }}>{etat.data.emetteur}</dd>
          </dl>
        </Resultat>
      )}
      <p style={{ fontSize: 12, color: 'var(--c-text-2)', margin: '16px 0 0' }}>
        Cette page confirme qu’une fiche de défraiement a été signée électroniquement par le volontaire. Elle n’affiche ni montant, ni compte bancaire, ni donnée de connexion.
      </p>
    </div>
  );
}

function Resultat({ icone, titre, bg, fg, children }) {
  return (
    <div style={{ textAlign: 'center' }}>
      <div aria-hidden="true" style={{ width: 56, height: 56, borderRadius: '50%', background: bg, color: fg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 28, margin: '0 auto 10px' }}>{icone}</div>
      <h1 style={{ fontSize: 20, margin: '0 0 12px' }} role="status">{titre}</h1>
      {children}
    </div>
  );
}
