import { useState } from 'react';
import client from '../../api/client';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from './Banner';
import { getErrorMessage } from '../../api/errors';

/**
 * ADMIN-05 : envoi en lot des invitations « accès non envoyé » (confirmation nominative puis résultat par ligne).
 * Le backend reste la source de vérité (ignore les comptes déjà invités ou désactivés, 1 envoi/min/compte).
 *
 * @param {object} props
 * @param {{id: number, nom: string, email: string}[]} props.comptes  comptes sélectionnés
 * @param {string} props.endpoint  ex. '/professeurs/envoyer-invitations'
 * @param {() => void} props.onClose
 * @param {() => void} [props.onDone]  rechargement de la liste après envoi
 */
export default function EnvoiLotModal({ comptes, endpoint, onClose, onDone }) {
  const [cibles, setCibles] = useState(comptes);
  const [lignes, setLignes] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function envoyer(liste) {
    setEnvoi(true);
    setErreur(null);
    try {
      const res = await client.post(endpoint, { ids: liste.map((c) => c.id) });
      setCibles(liste);
      setLignes(res.data.data);
      onDone?.();
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  if (lignes) {
    const nbEnvoyes = lignes.filter((l) => l.resultat === 'envoye').length;
    const echecs = lignes.filter((l) => l.resultat === 'echec');
    const nbIgnores = lignes.length - nbEnvoyes - echecs.length;
    return (
      <AdminModal
        isOpen
        title="Résultat de l'envoi"
        size="md"
        onClose={onClose}
        closeOnBackdrop={false}
        footer={
          <>
            {echecs.length > 0 && (
              <AdminButton
                variant="secondary"
                loading={envoi}
                onClick={() => envoyer(cibles.filter((c) => echecs.some((l) => l.id === c.id)))}
              >
                Réessayer les échecs ({echecs.length})
              </AdminButton>
            )}
            <AdminButton variant="primary" onClick={onClose}>Fermer</AdminButton>
          </>
        }
      >
        {erreur && <Banner tone="error">{erreur}</Banner>}
        <Banner tone={echecs.length ? 'warning' : 'success'} role="status">
          {nbEnvoyes} invitation{nbEnvoyes > 1 ? 's' : ''} envoyée{nbEnvoyes > 1 ? 's' : ''}
          {echecs.length > 0 && `, ${echecs.length} en échec`}
          {nbIgnores > 0 && `, ${nbIgnores} ignorée${nbIgnores > 1 ? 's' : ''}`}.
        </Banner>
        <ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
          {lignes.map((l) => (
            <li key={l.id} style={{ padding: '6px 0', borderBottom: '1px solid var(--c-border)' }}>
              <strong>{l.resultat === 'envoye' ? '✓' : l.resultat === 'echec' ? '✕' : '–'} {l.nom}</strong>{' '}
              <span style={{ color: 'var(--c-text-2)' }}>{l.email}</span>
              {l.motif && <div style={{ fontSize: '13px' }}>{l.motif}</div>}
            </li>
          ))}
        </ul>
      </AdminModal>
    );
  }

  return (
    <AdminModal
      isOpen
      title={`Envoyer ${cibles.length} invitation${cibles.length > 1 ? 's' : ''} ?`}
      size="md"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton variant="primary" loading={envoi} onClick={() => envoyer(cibles)}>
            Envoyer {cibles.length} invitation{cibles.length > 1 ? 's' : ''}
          </AdminButton>
        </>
      }
    >
      {erreur && <Banner tone="error">{erreur}</Banner>}
      <p style={{ marginTop: 0 }}>Un email contenant un lien valable 72 h sera envoyé à :</p>
      <ul style={{ margin: 0, paddingLeft: '20px', maxHeight: '260px', overflowY: 'auto' }}>
        {cibles.map((c) => (
          <li key={c.id}><strong>{c.nom}</strong> — {c.email}</li>
        ))}
      </ul>
    </AdminModal>
  );
}
