import { useEffect, useState } from 'react';
import client from '../api/client';
import { useAuth } from '../auth/AuthContext';

const STATUT_LABELS = {
  brouillon: 'Brouillon',
  soumis: 'Soumis',
  valide: 'Validé',
};

function NewTimesheetForm({ professeurId, onCreated }) {
  const [date, setDate] = useState('');
  const [heures, setHeures] = useState('');
  const [error, setError] = useState(null);

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    try {
      const res = await client.post('/timesheets', {
        professeur_id: professeurId,
        date_prestation: date,
        nombre_heures: heures,
      });
      onCreated(res.data);
      setDate('');
      setHeures('');
    } catch {
      setError('Saisie invalide.');
    }
  }

  return (
    <form onSubmit={handleSubmit} className="inline-form">
      <input type="date" value={date} onChange={(e) => setDate(e.target.value)} required />
      <input
        type="number"
        step="0.5"
        min="0.5"
        max="24"
        placeholder="Heures"
        value={heures}
        onChange={(e) => setHeures(e.target.value)}
        required
      />
      <button type="submit">Ajouter une saisie</button>
      {error && <span className="error">{error}</span>}
    </form>
  );
}

export default function TimesheetsPage() {
  const { user } = useAuth();
  const [timesheets, setTimesheets] = useState(null);
  const [error, setError] = useState(null);

  const isStaff = user.role === 'admin' || user.role === 'directeur';

  useEffect(() => {
    client
      .get('/timesheets')
      .then((res) => setTimesheets(res.data))
      .catch(() => setError('Impossible de charger les timesheets.'));
  }, []);

  function updateLocal(id, patch) {
    setTimesheets((prev) => prev.map((t) => (t.id === id ? { ...t, ...patch } : t)));
  }

  async function handleSubmitEntry(id) {
    const res = await client.post(`/timesheets/${id}/submit`);
    updateLocal(id, res.data);
  }

  async function handleValidate(id) {
    const res = await client.post(`/timesheets/${id}/validate`);
    updateLocal(id, res.data);
  }

  if (error) return <p className="error">{error}</p>;
  if (timesheets === null) return <p>Chargement…</p>;

  return (
    <div>
      <h1>Timesheets</h1>

      {!isStaff && user.professeur && (
        <NewTimesheetForm
          professeurId={user.professeur.id}
          onCreated={(t) => setTimesheets((prev) => [t, ...prev])}
        />
      )}

      <table className="table">
        <thead>
          <tr>
            {isStaff && <th>Professeur</th>}
            <th>Date</th>
            <th>Heures</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {timesheets.map((t) => (
            <tr key={t.id}>
              {isStaff && <td>{t.professeur?.prenom} {t.professeur?.nom}</td>}
              <td>{t.date_prestation?.slice(0, 10)}</td>
              <td>{t.nombre_heures}</td>
              <td>{STATUT_LABELS[t.statut_validation]}</td>
              <td>
                {!isStaff && t.statut_validation === 'brouillon' && (
                  <button onClick={() => handleSubmitEntry(t.id)}>Soumettre</button>
                )}
                {isStaff && t.statut_validation === 'soumis' && (
                  <button onClick={() => handleValidate(t.id)}>Valider</button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
