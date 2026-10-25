import { useMemo, useState } from 'react';
import AdminModal from '../AdminModal';
import { LoadingBlock, ErrorBlock } from '../ui/DataStates';
import { useCandidats } from '../../hooks/useDirection';

/**
 * Feuille « Choisir le remplaçant / le professeur à ajouter » (UI-M6) : les professeurs libres d'abord, puis ceux qui ont un
 * conflit d'horaire (sélectionnables : un conflit est un avertissement, pas un blocage).
 *
 * @param {object} props
 * @param {number} props.sessionId
 * @param {string} props.titre
 * @param {string} [props.sousTitre]
 * @param {(prof: {id: number, nom: string, conflits: object[]}) => void} props.onChoisir
 * @param {() => void} props.onClose
 */
export default function ChoisirProfesseurModal({ sessionId, titre, sousTitre, onChoisir, onClose }) {
  const candidats = useCandidats(sessionId);
  const [recherche, setRecherche] = useState('');

  const { libres, enConflit } = useMemo(() => {
    const liste = (candidats.data || []).filter((p) => p.nom.toLowerCase().includes(recherche.toLowerCase()));
    return { libres: liste.filter((p) => p.conflits.length === 0), enConflit: liste.filter((p) => p.conflits.length > 0) };
  }, [candidats.data, recherche]);

  const ligne = (p) => (
    <li key={p.id}>
      <button type="button" className="choix-prof" onClick={() => onChoisir(p)}>
        <span className="choix-prof__avatar" aria-hidden="true">
          {p.nom.split(' ').map((m) => m[0]).slice(0, 2).join('')}
        </span>
        <span className="choix-prof__texte">
          <strong>{p.nom}</strong>
          {p.conflits.length === 0 ? (
            <small>Libre sur ce créneau</small>
          ) : (
            <small className="choix-prof__conflit">Déjà : {p.conflits.map((c) => `${c.classe} ${c.heure_debut}–${c.heure_fin}`).join(', ')}</small>
          )}
        </span>
      </button>
    </li>
  );

  return (
    <AdminModal isOpen title={titre} size="sm" onClose={onClose}>
      {sousTitre && <p style={{ margin: '0 0 12px', fontSize: 13, color: 'var(--c-text-2)' }}>{sousTitre}</p>}
      <input
        type="search"
        aria-label="Rechercher un professeur"
        placeholder="Rechercher un professeur"
        value={recherche}
        onChange={(e) => setRecherche(e.target.value)}
        style={{ width: '100%', marginBottom: 12 }}
      />
      {candidats.loading && <LoadingBlock message="Chargement des professeurs…" lignes={3} />}
      {candidats.error && <ErrorBlock message={candidats.error} onRetry={candidats.reload} />}
      {candidats.data && libres.length + enConflit.length === 0 && <p>Aucun professeur disponible.</p>}
      {libres.length > 0 && (
        <>
          <h3 className="choix-prof__titre">Disponibles</h3>
          <ul className="choix-prof__liste">{libres.map(ligne)}</ul>
        </>
      )}
      {enConflit.length > 0 && (
        <>
          <h3 className="choix-prof__titre">Conflit d'horaire</h3>
          <ul className="choix-prof__liste">{enConflit.map(ligne)}</ul>
        </>
      )}
    </AdminModal>
  );
}
