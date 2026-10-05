import { useState } from 'react';
import Banner from '../ui/Banner';
import { formatDateHeure } from '../../utils/dates';

const TYPES = { nom: 'Nom manuscrit', dessin: 'Dessin', initiales: 'Initiales' };

/**
 * SIG-01 : signature du mois vue par la direction — ligne « Signé le … · SIG-… · Preuve », panneau de preuve
 * (IP masquée pour le directeur, complète pour l'admin : décidé par le serveur) et alerte si le contenu a changé.
 */
export default function SignaturePreuve({ signature: s }) {
  const [ouvert, setOuvert] = useState(false);
  if (!s) return null;

  const etat = s.sceau_valide === false
    ? { tone: 'error', texte: 'Sceau invalide : la preuve a été altérée' }
    : s.perimee
      ? { tone: 'warning', texte: 'Contenu modifié depuis la signature' }
      : { tone: 'success', texte: s.anonymisee ? 'Preuve archivée (plus de 7 ans)' : 'Valide — contenu inchangé' };

  return (
    <div style={{ marginTop: 6 }}>
      <span style={{ fontSize: 13, color: 'var(--c-text-2)' }}>
        Signé le {formatDateHeure(s.signed_at)} · <code style={{ whiteSpace: 'nowrap' }}>{s.public_id}</code> ·{' '}
        <button
          type="button"
          aria-expanded={ouvert}
          onClick={() => setOuvert((o) => !o)}
          style={{ border: 0, background: 'none', padding: 0, color: 'var(--c-primary)', textDecoration: 'underline', cursor: 'pointer', font: 'inherit' }}
        >
          Preuve
        </button>
      </span>
      {s.perimee && (
        <Banner tone="warning" style={{ marginTop: 8 }}>
          <span><strong>Données modifiées depuis la signature</strong> ({s.public_id}). Nouvelle signature du professeur requise avant de générer le PDF.</span>
        </Banner>
      )}
      {ouvert && (
        <div style={{ marginTop: 8, padding: 12, border: '1px solid var(--c-border)', borderRadius: 8, background: 'var(--c-bg)', maxWidth: 640 }}>
          <Banner tone={etat.tone} role="status" style={{ marginBottom: 10 }}>{etat.texte}</Banner>
          <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap', alignItems: 'flex-start' }}>
            {s.image && <img src={s.image} alt={`Signature de ${s.signataire}`} style={{ height: 60, background: '#fff', border: '1px solid var(--c-border)', borderRadius: 6, padding: 4 }} />}
            <dl style={{ display: 'grid', gridTemplateColumns: 'auto 1fr', gap: '4px 14px', margin: 0, fontSize: 13 }}>
              <dt style={{ color: 'var(--c-text-2)' }}>Signataire</dt><dd style={{ margin: 0 }}>{s.signataire}</dd>
              <dt style={{ color: 'var(--c-text-2)' }}>Date</dt><dd style={{ margin: 0 }}>{formatDateHeure(s.signed_at)}</dd>
              <dt style={{ color: 'var(--c-text-2)' }}>Identifiant</dt><dd style={{ margin: 0 }}><code>{s.public_id}</code></dd>
              <dt style={{ color: 'var(--c-text-2)' }}>Adresse IP</dt><dd style={{ margin: 0 }}><code>{s.ip || '—'}</code></dd>
              <dt style={{ color: 'var(--c-text-2)' }}>Appareil</dt><dd style={{ margin: 0 }}>{s.appareil || '—'}</dd>
              <dt style={{ color: 'var(--c-text-2)' }}>Type</dt><dd style={{ margin: 0 }}>{TYPES[s.specimen_type] || s.specimen_type}</dd>
              <dt style={{ color: 'var(--c-text-2)' }}>Empreinte</dt><dd style={{ margin: 0 }}><code title={s.empreinte}>sha256:{s.empreinte.slice(0, 4)}…{s.empreinte.slice(-4)}</code></dd>
              <dt style={{ color: 'var(--c-text-2)' }}>Clé serveur</dt><dd style={{ margin: 0 }}><code>Ed25519 · {s.cle}</code></dd>
              <dt style={{ color: 'var(--c-text-2)' }}>Vérif. publique</dt><dd style={{ margin: 0 }}>{s.verification_publique ? 'Oui (QR sur le PDF)' : 'Non'}</dd>
            </dl>
          </div>
        </div>
      )}
    </div>
  );
}
