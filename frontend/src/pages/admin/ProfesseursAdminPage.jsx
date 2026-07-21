import { useEffect, useState } from 'react';
import client from '../../api/client';
import CheckboxGroup from '../../components/CheckboxGroup';

const emptyCreateForm = {
  login_email: '',
  password: '',
  prenom: '',
  nom: '',
  email: '',
  telephone: '',
  statut: 'actif',
  date_entree: '',
  date_sortie: '',
  type_contrat: '',
  types_cours: [],
};

export default function ProfesseursAdminPage() {
  const [professeurs, setProfesseurs] = useState(null);
  const [typesCours, setTypesCours] = useState([]);
  const [createForm, setCreateForm] = useState(emptyCreateForm);
  const [editing, setEditing] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client.get('/professeurs').then((res) => setProfesseurs(res.data));
    client.get('/types-cours').then((res) => setTypesCours(res.data));
  }, []);

  async function handleCreate(e) {
    e.preventDefault();
    setError(null);
    try {
      const res = await client.post('/professeurs', createForm);
      setProfesseurs((prev) => [...prev, res.data]);
      setCreateForm(emptyCreateForm);
    } catch {
      setError('Formulaire invalide (email déjà utilisé ?).');
    }
  }

  function startEdit(p) {
    setEditing({
      id: p.id,
      prenom: p.prenom,
      nom: p.nom,
      email: p.email,
      telephone: p.telephone || '',
      statut: p.statut,
      date_entree: p.date_entree?.slice(0, 10) || '',
      date_sortie: p.date_sortie?.slice(0, 10) || '',
      type_contrat: p.type_contrat || '',
      types_cours: p.types_cours.map((t) => t.id),
    });
  }

  async function handleUpdate(e) {
    e.preventDefault();
    setError(null);
    try {
      const { id, ...data } = editing;
      const res = await client.put(`/professeurs/${id}`, data);
      setProfesseurs((prev) => prev.map((p) => (p.id === id ? res.data : p)));
      setEditing(null);
    } catch {
      setError('Impossible de mettre à jour ce professeur.');
    }
  }

  async function handleDelete(id) {
    await client.delete(`/professeurs/${id}`);
    setProfesseurs((prev) => prev.filter((p) => p.id !== id));
  }

  if (professeurs === null) return <p>Chargement…</p>;

  return (
    <div>
      <h1>Professeurs</h1>

      <table className="table">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Email</th>
            <th>Types de cours</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {professeurs.map((p) => (
            <tr key={p.id}>
              <td>{p.prenom} {p.nom}</td>
              <td>{p.email}</td>
              <td>{p.types_cours.map((t) => t.nom).join(', ')}</td>
              <td>{p.statut}</td>
              <td>
                <button onClick={() => startEdit(p)}>Modifier</button>
                <button onClick={() => handleDelete(p.id)}>Supprimer (+ compte)</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {editing && (
        <>
          <h2>Modifier {editing.prenom} {editing.nom}</h2>
          <form onSubmit={handleUpdate}>
            <label>
              Prénom
              <input value={editing.prenom} onChange={(e) => setEditing({ ...editing, prenom: e.target.value })} required />
            </label>
            <label>
              Nom
              <input value={editing.nom} onChange={(e) => setEditing({ ...editing, nom: e.target.value })} required />
            </label>
            <label>
              Email professionnel
              <input type="email" value={editing.email} onChange={(e) => setEditing({ ...editing, email: e.target.value })} required />
            </label>
            <label>
              Téléphone
              <input value={editing.telephone} onChange={(e) => setEditing({ ...editing, telephone: e.target.value })} />
            </label>
            <label>
              Statut
              <select value={editing.statut} onChange={(e) => setEditing({ ...editing, statut: e.target.value })}>
                <option value="actif">Actif</option>
                <option value="inactif">Inactif</option>
              </select>
            </label>
            <label>
              Date d'entrée
              <input type="date" value={editing.date_entree} onChange={(e) => setEditing({ ...editing, date_entree: e.target.value })} required />
            </label>
            <label>
              Date de sortie
              <input type="date" value={editing.date_sortie} onChange={(e) => setEditing({ ...editing, date_sortie: e.target.value })} />
            </label>
            <label>
              Type de contrat
              <select value={editing.type_contrat} onChange={(e) => setEditing({ ...editing, type_contrat: e.target.value })}>
                <option value="">— Non précisé —</option>
                <option value="salarie">Salarié</option>
                <option value="freelance">Freelance</option>
                <option value="prestataire">Prestataire</option>
              </select>
            </label>
            <label>Types de cours enseignés</label>
            <CheckboxGroup
              options={typesCours}
              selected={editing.types_cours}
              onChange={(types_cours) => setEditing({ ...editing, types_cours })}
            />
            <button type="submit">Enregistrer</button>
            <button type="button" onClick={() => setEditing(null)}>
              Annuler
            </button>
            {error && <p className="error">{error}</p>}
          </form>
        </>
      )}

      <h2>Nouveau professeur</h2>
      <form onSubmit={handleCreate}>
        <label>
          Email de connexion
          <input
            type="email"
            value={createForm.login_email}
            onChange={(e) => setCreateForm({ ...createForm, login_email: e.target.value })}
            required
          />
        </label>
        <label>
          Mot de passe initial
          <input
            type="password"
            value={createForm.password}
            onChange={(e) => setCreateForm({ ...createForm, password: e.target.value })}
            minLength={8}
            required
          />
        </label>
        <label>
          Prénom
          <input value={createForm.prenom} onChange={(e) => setCreateForm({ ...createForm, prenom: e.target.value })} required />
        </label>
        <label>
          Nom
          <input value={createForm.nom} onChange={(e) => setCreateForm({ ...createForm, nom: e.target.value })} required />
        </label>
        <label>
          Email professionnel
          <input type="email" value={createForm.email} onChange={(e) => setCreateForm({ ...createForm, email: e.target.value })} required />
        </label>
        <label>
          Téléphone
          <input value={createForm.telephone} onChange={(e) => setCreateForm({ ...createForm, telephone: e.target.value })} />
        </label>
        <label>
          Date d'entrée
          <input type="date" value={createForm.date_entree} onChange={(e) => setCreateForm({ ...createForm, date_entree: e.target.value })} required />
        </label>
        <label>
          Type de contrat
          <select value={createForm.type_contrat} onChange={(e) => setCreateForm({ ...createForm, type_contrat: e.target.value })}>
            <option value="">— Non précisé —</option>
            <option value="salarie">Salarié</option>
            <option value="freelance">Freelance</option>
            <option value="prestataire">Prestataire</option>
          </select>
        </label>
        <label>Types de cours enseignés</label>
        <CheckboxGroup
          options={typesCours}
          selected={createForm.types_cours}
          onChange={(types_cours) => setCreateForm({ ...createForm, types_cours })}
        />
        <button type="submit">Créer</button>
        {error && <p className="error">{error}</p>}
      </form>
    </div>
  );
}
