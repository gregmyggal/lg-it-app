import { useEffect, useState } from 'react';
import client from '../api/client';

// Gère les liens de classe + réglages (séance active, code d'accès) pour un parent
// polymorphe (cours|stages|formations|anniversaires) — réutilisé sur toutes les
// pages d'administration qui ont une notion de "portail /classe".
export default function ClasseLiensManager({ parentType, parentId }) {
  const [liens, setLiens] = useState(null);
  const [settings, setSettings] = useState(null);
  const [titre, setTitre] = useState('');
  const [url, setUrl] = useState('');
  const [error, setError] = useState(null);

  useEffect(() => {
    client.get(`/${parentType}/${parentId}/liens`).then((res) => setLiens(res.data));
    client.get(`/${parentType}/${parentId}/settings`).then((res) => setSettings(res.data));
  }, [parentType, parentId]);

  async function handleAddLien(e) {
    e.preventDefault();
    setError(null);
    try {
      const res = await client.post(`/${parentType}/${parentId}/liens`, { titre, url });
      setLiens((prev) => [...prev, res.data]);
      setTitre('');
      setUrl('');
    } catch {
      setError("Impossible d'ajouter ce lien.");
    }
  }

  async function handleDeleteLien(id) {
    await client.delete(`/liens/${id}`);
    setLiens((prev) => prev.filter((l) => l.id !== id));
  }

  async function handleSaveSettings(e) {
    e.preventDefault();
    const res = await client.put(`/${parentType}/${parentId}/settings`, {
      seance_active: settings.seance_active || null,
      access_code: settings.access_code || null,
    });
    setSettings(res.data);
  }

  if (liens === null || settings === null) return <p>Chargement des liens…</p>;

  return (
    <div className="classe-liens">
      <h4>Liens de classe</h4>
      <ul>
        {liens.map((l) => (
          <li key={l.id}>
            <a href={l.url} target="_blank" rel="noreferrer">
              {l.titre}
            </a>
            <button onClick={() => handleDeleteLien(l.id)}>Supprimer</button>
          </li>
        ))}
        {liens.length === 0 && <li>Aucun lien.</li>}
      </ul>
      <form onSubmit={handleAddLien} className="inline-form">
        <input placeholder="Titre" value={titre} onChange={(e) => setTitre(e.target.value)} required />
        <input placeholder="https://…" value={url} onChange={(e) => setUrl(e.target.value)} required />
        <button type="submit">Ajouter un lien</button>
        {error && <span className="error">{error}</span>}
      </form>

      <h4>Réglages du portail /classe</h4>
      <form onSubmit={handleSaveSettings} className="inline-form">
        <input
          placeholder="Séance active"
          value={settings.seance_active || ''}
          onChange={(e) => setSettings({ ...settings, seance_active: e.target.value })}
        />
        <input
          placeholder="Code d'accès"
          value={settings.access_code || ''}
          onChange={(e) => setSettings({ ...settings, access_code: e.target.value })}
        />
        <button type="submit">Enregistrer</button>
      </form>
    </div>
  );
}
