import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { getStatus } from '../api/errors';

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const connecte = await login(email, password);
      if (connecte?.must_change_password) {
        navigate('/mot-de-passe');
        return;
      }
      navigate(connecte?.role === 'professeur' ? '/mes-classes' : '/admin/classes');
    } catch (err) {
      // 403 : compte désactivé (message du serveur, révélé seulement avec un mot de passe correct).
      setError(getStatus(err) === 403 && err.response.data?.message ? err.response.data.message : 'Identifiants invalides.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="card card--narrow">
      <h1>Connexion</h1>
      {location.state?.motDePasseDefini && (
        <p role="status">Mot de passe enregistré. Connectez-vous avec votre nouveau mot de passe.</p>
      )}
      <form onSubmit={handleSubmit}>
        <label>
          Email
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </label>
        <label>
          Mot de passe
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </label>
        {error && <p className="error">{error}</p>}
        <p><Link to="/mot-de-passe-oublie" state={{ email }}>Mot de passe oublié ?</Link></p>
        <button type="submit" disabled={submitting}>
          {submitting ? 'Connexion…' : 'Se connecter'}
        </button>
      </form>
    </div>
  );
}
