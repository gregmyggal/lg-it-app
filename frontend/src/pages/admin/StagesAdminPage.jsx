import { Fragment, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';
import ClasseLiensManager from '../../components/ClasseLiensManager';

const VACANCES = ['Été', 'Toussaint', 'Noël', 'Carnaval', 'Pâques'];

const emptyForm = {
  titre: '',
  slug: '',
  theme_stage: '',
  tranche_age: '',
  lieu: '',
  prix: '',
  description: '',
  statut: 'draft',
  sessions_vacances: [],
};

export default function StagesAdminPage() {
  const navigate = useNavigate();
  const [stages, setStages] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [expandedId, setExpandedId] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client.get('/stages').then((res) => setStages(res.data));
  }, []);

  function startEdit(s) {
    setEditingId(s.id);
    setForm({
      titre: s.titre,
      slug: s.slug,
      theme_stage: s.theme_stage,
      tranche_age: s.tranche_age,
      lieu: s.lieu,
      prix: s.prix,
      description: s.description || '',
      statut: s.statut,
      sessions_vacances: s.sessions_vacances || [],
    });
  }

  function resetForm() {
    setEditingId(null);
    setForm(emptyForm);
  }

  function toggleVacance(v) {
    setForm((prev) => ({
      ...prev,
      sessions_vacances: prev.sessions_vacances.includes(v)
        ? prev.sessions_vacances.filter((x) => x !== v)
        : [...prev.sessions_vacances, v],
    }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    try {
      if (editingId) {
        const res = await client.put(`/stages/${editingId}`, form);
        setStages((prev) => prev.map((s) => (s.id === editingId ? { ...s, ...res.data } : s)));
      } else {
        const res = await client.post('/stages', form);
        setStages((prev) => [...prev, { ...res.data, dates: [] }]);
      }
      resetForm();
    } catch {
      setError('Formulaire invalide (slug déjà utilisé ?).');
    }
  }

  async function handleDelete(id) {
    await client.delete(`/stages/${id}`);
    setStages((prev) => prev.filter((s) => s.id !== id));
  }

  if (stages === null) return <p>Chargement…</p>;

  return (
    <div>
      <h1>Stages</h1>

      <table className="table">
        <thead>
          <tr>
            <th>Titre</th>
            <th>Thème</th>
            <th>Prix</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {stages.map((s) => (
            <Fragment key={s.id}>
              <tr>
                <td>{s.titre}</td>
                <td>{s.theme_stage}</td>
                <td>{s.prix} €</td>
                <td>{s.statut}</td>
                <td>
                  <button onClick={() => startEdit(s)}>Modifier</button>
                  <button onClick={() => navigate(`/admin/stages/${s.id}/contenu`)}>Contenus</button>
                  <button onClick={() => setExpandedId(expandedId === s.id ? null : s.id)}>
                    Dates / liens
                  </button>
                  <button onClick={() => handleDelete(s.id)}>Supprimer</button>
                </td>
              </tr>
              {expandedId === s.id && (
                <tr>
                  <td colSpan="5">
                    <DatesPanel stage={s} />
                    <ClasseLiensManager parentType="stages" parentId={s.id} />
                  </td>
                </tr>
              )}
            </Fragment>
          ))}
        </tbody>
      </table>

      <h2>{editingId ? 'Modifier le stage' : 'Nouveau stage'}</h2>
      <form onSubmit={handleSubmit}>
        <label>
          Titre
          <input value={form.titre} onChange={(e) => setForm({ ...form, titre: e.target.value })} required />
        </label>
        <label>
          Slug
          <input value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} required />
        </label>
        <label>
          Thème
          <input value={form.theme_stage} onChange={(e) => setForm({ ...form, theme_stage: e.target.value })} required />
        </label>
        <label>
          Tranche d'âge
          <input value={form.tranche_age} onChange={(e) => setForm({ ...form, tranche_age: e.target.value })} required />
        </label>
        <label>
          Lieu
          <input value={form.lieu} onChange={(e) => setForm({ ...form, lieu: e.target.value })} required />
        </label>
        <label>
          Prix (€)
          <input type="number" step="0.01" value={form.prix} onChange={(e) => setForm({ ...form, prix: e.target.value })} required />
        </label>
        <label>
          Description
          <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={3} />
        </label>
        <label>
          Statut
          <select value={form.statut} onChange={(e) => setForm({ ...form, statut: e.target.value })}>
            <option value="draft">Brouillon</option>
            <option value="publish">Publié</option>
          </select>
        </label>
        <label>Sessions de vacances</label>
        <div className="checkbox-group">
          {VACANCES.map((v) => (
            <label key={v} className="checkbox-group__item">
              <input
                type="checkbox"
                checked={form.sessions_vacances.includes(v)}
                onChange={() => toggleVacance(v)}
              />
              {v}
            </label>
          ))}
        </div>
        <button type="submit">{editingId ? 'Enregistrer' : 'Créer'}</button>
        {editingId && (
          <button type="button" onClick={resetForm}>
            Annuler
          </button>
        )}
        {error && <p className="error">{error}</p>}
      </form>
    </div>
  );
}

function DatesPanel({ stage }) {
  const [dates, setDates] = useState(stage.dates);
  const [date, setDate] = useState('');

  async function handleAdd(e) {
    e.preventDefault();
    const res = await client.post(`/stages/${stage.id}/dates`, { date_session: date });
    setDates((prev) => [...prev, res.data]);
    setDate('');
  }

  async function handleDelete(id) {
    await client.delete(`/stages/${stage.id}/dates/${id}`);
    setDates((prev) => prev.filter((d) => d.id !== id));
  }

  return (
    <div>
      <h4>Dates de sessions</h4>
      <ul>
        {dates.map((d) => (
          <li key={d.id}>
            {d.date_session?.slice(0, 10)}
            <button onClick={() => handleDelete(d.id)}>Supprimer</button>
          </li>
        ))}
        {dates.length === 0 && <li>Aucune date.</li>}
      </ul>
      <form onSubmit={handleAdd} className="inline-form">
        <input type="date" value={date} onChange={(e) => setDate(e.target.value)} required />
        <button type="submit">Ajouter une date</button>
      </form>
    </div>
  );
}
