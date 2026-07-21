import { useEffect, useState } from 'react';
import client from '../api/client';

const TYPE_OPTIONS = [
  { value: 'video', label: 'Vidéo' },
  { value: 'outil', label: 'Outil' },
  { value: 'document', label: 'Document' },
  { value: 'jeu', label: 'Jeu' },
];

function RessourceForm({ coursId, onCreated }) {
  const [titre, setTitre] = useState('');
  const [url, setUrl] = useState('');
  const [type, setType] = useState('outil');
  const [error, setError] = useState(null);

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    try {
      const res = await client.post(`/cours/${coursId}/ressources`, {
        titre_ressource: titre,
        url_ressource: url,
        type_ressource: type,
      });
      onCreated(res.data);
      setTitre('');
      setUrl('');
    } catch {
      setError("Impossible d'ajouter la ressource.");
    }
  }

  return (
    <form onSubmit={handleSubmit} className="inline-form">
      <input
        placeholder="Titre"
        value={titre}
        onChange={(e) => setTitre(e.target.value)}
        required
      />
      <input
        placeholder="https://…"
        value={url}
        onChange={(e) => setUrl(e.target.value)}
        required
      />
      <select value={type} onChange={(e) => setType(e.target.value)}>
        {TYPE_OPTIONS.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
      <button type="submit">Ajouter</button>
      {error && <span className="error">{error}</span>}
    </form>
  );
}

function CoursCard({ cours, onRessourceCreated }) {
  return (
    <div className="card">
      <h3>{cours.titre}</h3>
      <p>{cours.types_cours?.map((t) => t.nom).join(', ')}</p>

      <ul>
        {cours.ressources?.map((r) => (
          <li key={r.id}>
            <a href={r.url_ressource} target="_blank" rel="noreferrer">
              {r.titre_ressource}
            </a>{' '}
            ({r.type_ressource})
          </li>
        ))}
        {cours.ressources?.length === 0 && <li>Aucune ressource.</li>}
      </ul>

      <RessourceForm
        coursId={cours.id}
        onCreated={(r) => onRessourceCreated(cours.id, r)}
      />
    </div>
  );
}

export default function MesCoursPage() {
  const [cours, setCours] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client
      .get('/cours')
      .then((res) => setCours(res.data))
      .catch(() => setError('Impossible de charger vos cours.'));
  }, []);

  function handleRessourceCreated(coursId, ressource) {
    setCours((prev) =>
      prev.map((c) =>
        c.id === coursId ? { ...c, ressources: [...c.ressources, ressource] } : c
      )
    );
  }

  if (error) return <p className="error">{error}</p>;
  if (cours === null) return <p>Chargement…</p>;

  return (
    <div>
      <h1>Mes cours</h1>
      {cours.length === 0 && <p>Aucun cours ne vous est assigné.</p>}
      <div className="cours-list">
        {cours.map((c) => (
          <CoursCard key={c.id} cours={c} onRessourceCreated={handleRessourceCreated} />
        ))}
      </div>
    </div>
  );
}
