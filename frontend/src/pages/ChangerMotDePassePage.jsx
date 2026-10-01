import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { getErrorMessage, getFieldErrors } from '../api/errors';

/** Changement du mot de passe (obligatoire après un mot de passe provisoire). */
export default function ChangerMotDePassePage() {
  const { user, setUser } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [erreur, setErreur] = useState(null);
  const [champs, setChamps] = useState({});
  const [envoi, setEnvoi] = useState(false);
  const maj = (nom) => (e) => setForm((f) => ({ ...f, [nom]: e.target.value }));

  async function soumettre(e) {
    e.preventDefault();
    setEnvoi(true);
    setErreur(null);
    setChamps({});
    try {
      const res = await client.post('/me/mot-de-passe', form);
      setUser(res.data);
      navigate(res.data.role === 'professeur' ? '/mes-classes' : '/admin/classes');
    } catch (err) {
      setChamps(getFieldErrors(err));
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <div className="card card--narrow">
      <h1>Changer mon mot de passe</h1>
      {user?.must_change_password && (
        <p role="status">Pour votre sécurité, choisissez un nouveau mot de passe avant de continuer.</p>
      )}
      <form onSubmit={soumettre}>
        <label>
          Mot de passe actuel
          <input type="password" value={form.current_password} onChange={maj('current_password')} required autoComplete="current-password" />
          {champs.current_password && <span className="error">{champs.current_password}</span>}
        </label>
        <label>
          Nouveau mot de passe (8 caractères minimum)
          <input type="password" value={form.password} onChange={maj('password')} required minLength={8} autoComplete="new-password" />
          {champs.password && <span className="error">{champs.password}</span>}
        </label>
        <label>
          Confirmer le nouveau mot de passe
          <input type="password" value={form.password_confirmation} onChange={maj('password_confirmation')} required autoComplete="new-password" />
        </label>
        {erreur && !Object.keys(champs).length && <p className="error" role="alert">{erreur}</p>}
        <button type="submit" disabled={envoi}>{envoi ? 'Enregistrement…' : 'Enregistrer'}</button>
      </form>
    </div>
  );
}
