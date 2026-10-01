import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import client from '../api/client';
import { getErrorMessage, getFieldErrors, getStatus } from '../api/errors';

/** Lit token + email dans le fragment d'URL (#token=…&email=…). Lecture sans effet de bord (rendu double en StrictMode). */
function lireLien() {
  const params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
  return { token: params.get('token') || '', email: params.get('email') || '' };
}

/** ADMIN-02 : définition du mot de passe via un lien d'invitation ou de réinitialisation (public). */
export default function DefinirMotDePassePage() {
  const navigate = useNavigate();
  const [{ token, email }] = useState(lireLien);

  const [etat, setEtat] = useState(token && email ? 'verification' : 'invalide'); // verification | formulaire | invalide | reseau
  const [form, setForm] = useState({ password: '', password_confirmation: '' });
  const [visible, setVisible] = useState(false);
  const [champs, setChamps] = useState({});
  const [erreur, setErreur] = useState('');
  const [envoi, setEnvoi] = useState(false);
  const titre = useRef(null);

  useEffect(() => {
    if (etat !== 'verification') return undefined;
    let annule = false;
    client.post('/mot-de-passe/verifier', { email, token })
      .then(() => !annule && setEtat('formulaire'))
      .catch((err) => !annule && setEtat(getStatus(err) === 410 ? 'invalide' : 'reseau'));
    return () => { annule = true; };
  }, [etat, email, token]);

  // Le lien (secret) ne doit pas rester dans la barre d'adresse ni dans l'historique.
  useEffect(() => {
    if (window.location.hash) window.history.replaceState(null, '', window.location.pathname);
  }, []);

  useEffect(() => { titre.current?.focus(); }, [etat]);

  async function soumettre(e) {
    e.preventDefault();
    setEnvoi(true);
    setChamps({});
    setErreur('');
    try {
      await client.post('/mot-de-passe/definir', { email, token, ...form });
      navigate('/connexion', { replace: true, state: { motDePasseDefini: true } });
    } catch (err) {
      if (getStatus(err) === 410) {
        setEtat('invalide');
      } else {
        setChamps(getFieldErrors(err));
        setErreur(getErrorMessage(err));
      }
    } finally {
      setEnvoi(false);
    }
  }

  const maj = (nom) => (e) => setForm((f) => ({ ...f, [nom]: e.target.value }));

  if (etat === 'verification') {
    return (
      <div className="card card--narrow">
        <h1 ref={titre} tabIndex={-1}>Définir mon mot de passe</h1>
        <p role="status">Vérification du lien…</p>
      </div>
    );
  }

  if (etat === 'reseau') {
    return (
      <div className="card card--narrow">
        <h1 ref={titre} tabIndex={-1}>Définir mon mot de passe</h1>
        <p role="alert" className="error">Impossible de vérifier le lien. Vérifiez votre connexion puis réessayez.</p>
        <button type="button" onClick={() => setEtat('verification')}>Réessayer</button>
      </div>
    );
  }

  if (etat === 'invalide') {
    return (
      <div className="card card--narrow">
        <h1 ref={titre} tabIndex={-1}>Ce lien n&apos;est plus valable</h1>
        <p>Il a peut-être expiré, déjà été utilisé, ou remplacé par un lien plus récent.</p>
        <p>
          <button type="button" onClick={() => navigate('/mot-de-passe-oublie', { state: { email } })}>Recevoir un nouveau lien</button>
        </p>
        <p>Si vous venez d&apos;être invité(e), vous pouvez aussi demander une nouvelle invitation à votre administrateur.</p>
        <p><Link to="/connexion">Retour à la connexion</Link></p>
      </div>
    );
  }

  return (
    <div className="card card--narrow">
      <h1 ref={titre} tabIndex={-1}>Définir mon mot de passe</h1>
      <p>Compte : <strong>{email}</strong></p>
      <form onSubmit={soumettre}>
        <label>
          Nouveau mot de passe
          <input
            type={visible ? 'text' : 'password'}
            value={form.password}
            onChange={maj('password')}
            required
            minLength={8}
            autoComplete="new-password"
            aria-invalid={Boolean(champs.password)}
            aria-describedby="aide-mdp"
          />
        </label>
        <p id="aide-mdp" style={{ fontSize: '13px', margin: '-4px 0 12px' }}>
          Au moins 8 caractères{form.password.length > 0 && ` (${form.password.length} saisis)`}.
        </p>
        {champs.password && <p className="error" role="alert">{champs.password}</p>}
        <label>
          Confirmer le mot de passe
          <input
            type={visible ? 'text' : 'password'}
            value={form.password_confirmation}
            onChange={maj('password_confirmation')}
            required
            autoComplete="new-password"
          />
        </label>
        <p>
          <button type="button" aria-pressed={visible} onClick={() => setVisible((v) => !v)}>
            {visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
          </button>
        </p>
        {erreur && !champs.password && <p className="error" role="alert">{erreur}</p>}
        <button type="submit" disabled={envoi}>{envoi ? 'Enregistrement…' : 'Enregistrer mon mot de passe'}</button>
      </form>
    </div>
  );
}
