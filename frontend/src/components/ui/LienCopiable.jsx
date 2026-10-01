import { useRef, useState } from 'react';

/** Lien à transmettre : champ en lecture seule sélectionnable + bouton « Copier » annoncé en role=status. */
export default function LienCopiable({ lien }) {
  const champ = useRef(null);
  const [copie, setCopie] = useState('');

  async function copier() {
    try {
      await navigator.clipboard.writeText(lien);
      setCopie('Lien copié.');
    } catch {
      champ.current?.select();
      setCopie('Sélectionné : copiez-le avec Ctrl/Cmd + C.');
    }
  }

  return (
    <div style={{ marginTop: '8px' }}>
      <label style={{ display: 'block', fontSize: '13px', marginBottom: '4px' }}>
        Lien à transmettre (affiché une seule fois, valable 72 h)
        <input
          ref={champ}
          type="text"
          readOnly
          value={lien}
          onFocus={(e) => e.target.select()}
          style={{ fontFamily: 'monospace', fontSize: '12px' }}
        />
      </label>
      <button type="button" onClick={copier}>Copier le lien</button>
      <span role="status" style={{ marginLeft: '8px', fontSize: '13px' }}>{copie}</span>
    </div>
  );
}
