import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import client from '../api/client';
import { getStatus } from '../api/errors';

/** ADMIN-02 : « Mot de passe oublié » (public, tous rôles). La confirmation est identique que le compte existe ou non. */
export default function MotDePasseOubliePage() {
  const location = useLocation();
  const [email, setEmail] = useState(location.state?.email || '');
  const [envoi, setEnvoi] = useState(false);
  const [envoye, setEnvoye] = useState(false);
  const [erreur, setErreur] = useState('');

  async function soumettre(e) {
    e.preventDefault();
    setEnvoi(true);
    setErreur('');
    try {
      await client.post('/mot-de-passe/oublie', { email });
      setEnvoye(true);
    } catch (err) {
      const statut = getStatus(err);
      if (statut === 429) {
        setErreur('Trop de tentatives. Réessayez dans quelques minutes.');
      } else if (statut === 422) {
        setErreur('Saisissez une adresse email valide.');
      } else {
        setErreur('Impossible d’envoyer la demande. Réessayez.');
      }
    } finally {
      setEnvoi(false);
    }
  }

  if (envoye) {
    return (
      <div className="card card--narrow">
        <h1>Vérifiez votre boîte mail</h1>
        <p role="status">
          Si un compte correspond à cette adresse, un email vient d&apos;être envoyé. Le lien est valable 60 minutes.
          Pensez à vérifier vos courriers indésirables.
        </p>
        <p><Link to="/connexion">Retour à la connexion</Link></p>
      </div>
    );
  }

  return (
    <div className="card card--narrow">
      <h1>Mot de passe oublié</h1>
      <p>Saisissez l&apos;adresse email de votre compte : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>
      <form onSubmit={soumettre}>
        <label>
          Email
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" autoFocus />
        </label>
        {erreur && <p className="error" role="alert">{erreur}</p>}
        <button type="submit" disabled={envoi}>{envoi ? 'Envoi…' : 'Envoyer le lien'}</button>
      </form>
      <p><Link to="/connexion">Retour à la connexion</Link></p>
    </div>
  );
}
