import { useEffect, useRef } from 'react';

/** Écran de confirmation plein écran après la soumission du mois (UI-M4). */
export default function MoisSoumisConfirmation({ titreMois, onAccueil, onFermer }) {
  const bouton = useRef(null);
  useEffect(() => {
    bouton.current?.focus();
  }, []);

  return (
    <div className="mm-fait" role="status" aria-live="polite">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="m5 12.5 4.5 4.5L19 7.5" />
      </svg>
      <h2>Mois soumis !</h2>
      <p>
        Vos heures de {titreMois.toLowerCase()} sont envoyées à la direction.
        <br />
        Vous serez prévenu(e) dès qu'elles seront validées : vous n'aurez alors plus qu'à signer.
      </p>
      <button type="button" ref={bouton} className="mm-fait__btn" onClick={onAccueil}>
        Retour à Aujourd'hui
      </button>
      <button type="button" className="mm-fait__lien" onClick={onFermer}>
        Voir mon mois
      </button>
    </div>
  );
}
