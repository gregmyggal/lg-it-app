import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { chargerDepotParent, telechargerFichierParent } from '../hooks/useDepot';
import { getStatus } from '../api/errors';
import { formatDateHeure } from '../utils/dates';

function formatTaille(octets) {
  if (octets >= 1048576) return `${(octets / 1048576).toFixed(1).replace('.', ',')} Mo`;
  return `${Math.max(1, Math.round(octets / 1024))} Ko`;
}

/** DEP-01 T2 — page publique du parent (mock-ups 04 et 05) : sans compte ni cookie, jamais indexée. */
export default function DepotParentPage() {
  const { token } = useParams();
  const [etat, setEtat] = useState({ chargement: true });
  const [erreurTelechargement, setErreurTelechargement] = useState(false);

  useEffect(() => {
    const meta = document.createElement('meta');
    meta.name = 'robots';
    meta.content = 'noindex, nofollow';
    document.head.appendChild(meta);
    return () => meta.remove();
  }, []);

  useEffect(() => {
    let annule = false;
    chargerDepotParent(token)
      .then((data) => !annule && setEtat({ data }))
      .catch((err) => !annule && setEtat({ expire: getStatus(err) === 410, erreur: getStatus(err) !== 410 }));
    return () => {
      annule = true;
    };
  }, [token]);

  async function telecharger(f) {
    setErreurTelechargement(false);
    try {
      await telechargerFichierParent(token, f);
    } catch {
      setErreurTelechargement(true);
    }
  }

  return (
    <div className="card" lang="fr" style={{ maxWidth: 520, margin: '40px auto' }}>
      <p style={{ margin: '0 0 16px', fontWeight: 700 }}>Logiscool Pays Vert</p>

      {etat.chargement && <p role="status">Chargement…</p>}

      {etat.expire && (
        <div role="status">
          <h1 style={{ fontSize: 20, margin: '0 0 8px' }}>Ce lien n'est plus valable</h1>
          <p style={{ margin: 0 }}>
            Ce lien a expiré ou a été désactivé. Pour des raisons de confidentialité, les liens sont valables 7 jours. Contactez Logiscool Pays Vert pour recevoir un nouveau lien.
          </p>
        </div>
      )}

      {etat.erreur && (
        <p role="alert">Le service ne répond pas. Réessayez dans quelques minutes.</p>
      )}

      {etat.data && (
        <>
          <h1 style={{ fontSize: 20, margin: '0 0 8px' }}>
            {etat.data.libelle ? `Les créations de ${etat.data.libelle}` : 'Les créations de votre enfant'}
          </h1>
          <p style={{ margin: '0 0 16px', color: 'var(--c-text-2)', fontSize: 14 }}>
            Ces fichiers sont disponibles jusqu'au {formatDateHeure(etat.data.expire_le)}. Pensez à les télécharger avant cette date.
          </p>
          {erreurTelechargement && <p role="alert">Le téléchargement a échoué. Réessayez.</p>}
          <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: 12 }}>
            {etat.data.fichiers.map((f) => (
              <li key={f.id} style={{ display: 'flex', gap: 12, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}>
                <span style={{ overflowWrap: 'anywhere' }}>
                  {f.nom} <span style={{ color: 'var(--c-text-2)', fontSize: 13 }}>· {formatTaille(f.taille)}</span>
                </span>
                <button type="button" className="btn" style={{ minHeight: 44 }} onClick={() => telecharger(f)} aria-label={`Télécharger ${f.nom}`}>
                  Télécharger
                </button>
              </li>
            ))}
          </ul>
        </>
      )}
    </div>
  );
}
