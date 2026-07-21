import { useEffect, useState } from 'react';
import client from '../api/client';

function slugify(text) {
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');
}

// CRUD générique pour un lookup {nom, slug} — réutilisé pour types-cours et types-formation,
// qui ont exactement la même forme.
export default function SimpleLookupAdmin({ title, endpoint }) {
  const [items, setItems] = useState(null);
  const [nom, setNom] = useState('');
  const [editing, setEditing] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client.get(`/${endpoint}`).then((res) => setItems(res.data));
  }, [endpoint]);

  async function handleCreate(e) {
    e.preventDefault();
    setError(null);
    try {
      const res = await client.post(`/${endpoint}`, { nom, slug: slugify(nom) });
      setItems((prev) => [...prev, res.data]);
      setNom('');
    } catch {
      setError('Impossible de créer cet élément (slug déjà utilisé ?).');
    }
  }

  async function handleUpdate(e) {
    e.preventDefault();
    setError(null);
    try {
      const res = await client.put(`/${endpoint}/${editing.id}`, {
        nom: editing.nom,
        slug: editing.slug,
      });
      setItems((prev) => prev.map((i) => (i.id === res.data.id ? res.data : i)));
      setEditing(null);
    } catch {
      setError('Impossible de mettre à jour cet élément.');
    }
  }

  async function handleDelete(id) {
    await client.delete(`/${endpoint}/${id}`);
    setItems((prev) => prev.filter((i) => i.id !== id));
  }

  if (items === null) return <p>Chargement…</p>;

  return (
    <div>
      <h1>{title}</h1>

      <table className="table">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Slug</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {items.map((item) =>
            editing?.id === item.id ? (
              <tr key={item.id}>
                <td>
                  <form onSubmit={handleUpdate} className="inline-form">
                    <input
                      value={editing.nom}
                      onChange={(e) => setEditing({ ...editing, nom: e.target.value })}
                      required
                    />
                    <input
                      value={editing.slug}
                      onChange={(e) => setEditing({ ...editing, slug: e.target.value })}
                      required
                    />
                    <button type="submit">Enregistrer</button>
                    <button type="button" onClick={() => setEditing(null)}>
                      Annuler
                    </button>
                  </form>
                </td>
                <td colSpan="2"></td>
              </tr>
            ) : (
              <tr key={item.id}>
                <td>{item.nom}</td>
                <td>{item.slug}</td>
                <td>
                  <button onClick={() => setEditing(item)}>Modifier</button>
                  <button onClick={() => handleDelete(item.id)}>Supprimer</button>
                </td>
              </tr>
            )
          )}
        </tbody>
      </table>

      <form onSubmit={handleCreate} className="inline-form">
        <input
          placeholder="Nom du nouveau type"
          value={nom}
          onChange={(e) => setNom(e.target.value)}
          required
        />
        <button type="submit">Ajouter</button>
        {error && <span className="error">{error}</span>}
      </form>
    </div>
  );
}
