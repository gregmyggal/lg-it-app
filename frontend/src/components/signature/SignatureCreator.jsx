import { useEffect, useRef, useState } from 'react';
import '@fontsource/dancing-script/600.css';
import '@fontsource/caveat/600.css';
import '@fontsource/great-vibes/400.css';
import AdminButton from '../AdminButton';
import { enregistrerBlob } from '../../utils/telechargement';

// Valeurs acceptées par le serveur (SignatureSpecimen::POLICES / COULEURS).
const POLICES = [
  { famille: 'Dancing Script', poids: 600 },
  { famille: 'Caveat', poids: 600 },
  { famille: 'Great Vibes', poids: 400 },
];
const ENCRES = [
  { valeur: '#1a3d8f', libelle: 'Bleu' },
  { valeur: '#111111', libelle: 'Noir' },
];
const ONGLETS = [
  { id: 'nom', libelle: 'Nom manuscrit' },
  { id: 'dessin', libelle: 'Dessiner' },
  { id: 'initiales', libelle: 'Initiales' },
];
const PAD_L = 900;
const PAD_H = 300;

function initialesDe(nom) {
  return (nom || '').split(/[\s-]+/).filter(Boolean).map((m) => `${m[0].toUpperCase()}.`).join('');
}

function dessinerTraits(ctx, traits, couleur, echelle = 1) {
  ctx.strokeStyle = couleur;
  ctx.lineWidth = 5 * echelle;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  traits.forEach((t) => {
    const p = t.map(([x, y]) => [x * echelle, y * echelle]);
    ctx.beginPath();
    ctx.moveTo(...p[0]);
    if (p.length === 1) ctx.lineTo(p[0][0] + 0.1, p[0][1]);
    for (let i = 1; i < p.length - 1; i++) {
      ctx.quadraticCurveTo(p[i][0], p[i][1], (p[i][0] + p[i + 1][0]) / 2, (p[i][1] + p[i + 1][1]) / 2);
    }
    if (p.length > 1) ctx.lineTo(...p[p.length - 1]);
    ctx.stroke();
  });
}

/** Rogne les marges transparentes (avec un petit liseré) ; null si l'image est vide. */
function rogner(canvas) {
  const { width, height } = canvas;
  const d = canvas.getContext('2d').getImageData(0, 0, width, height).data;
  let x0 = width, y0 = height, x1 = -1, y1 = -1;
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      if (d[(y * width + x) * 4 + 3] > 8) {
        if (x < x0) x0 = x;
        if (x > x1) x1 = x;
        if (y < y0) y0 = y;
        if (y > y1) y1 = y;
      }
    }
  }
  if (x1 < 0) return null;
  const marge = 12;
  const sortie = document.createElement('canvas');
  sortie.width = x1 - x0 + 2 * marge;
  sortie.height = y1 - y0 + 2 * marge;
  sortie.getContext('2d').drawImage(canvas, x0 - marge, y0 - marge, sortie.width, sortie.height, 0, 0, sortie.width, sortie.height);
  return sortie;
}

async function imageSignature({ type, texte, police, couleur, traits }) {
  const c = document.createElement('canvas');
  c.width = 1200;
  c.height = 400;
  const ctx = c.getContext('2d');
  if (type === 'dessin') {
    dessinerTraits(ctx, traits, couleur, 1200 / PAD_L);
  } else {
    const t = (texte || '').trim();
    if (!t) return null;
    const p = POLICES.find((x) => x.famille === police) || POLICES[0];
    await document.fonts.load(`${p.poids} 120px "${p.famille}"`);
    let taille = type === 'nom' ? 150 : 190;
    do {
      ctx.font = `${p.poids} ${taille}px "${p.famille}"`;
      taille -= 6;
    } while (ctx.measureText(t).width > 1120 && taille > 40);
    ctx.fillStyle = couleur;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(t, 600, 210);
  }
  const r = rogner(c);
  return r ? r.toDataURL('image/png') : null;
}

/**
 * SIG-01 : création de la signature (nom manuscrit par défaut, dessin, initiales), encre bleue ou noire, aperçu PNG
 * transparent et téléchargement. `onChange` reçoit { type, texte, police, couleur, image } (image = null si vide).
 */
export default function SignatureCreator({ nom, initial, onChange }) {
  const [type, setType] = useState(initial?.type || 'nom');
  const [texteNom, setTexteNom] = useState(initial?.type === 'nom' ? initial.texte : nom || '');
  const [texteInit, setTexteInit] = useState(initial?.type === 'initiales' ? initial.texte : initialesDe(nom));
  const [police, setPolice] = useState(initial?.police || POLICES[0].famille);
  const [couleur, setCouleur] = useState(initial?.couleur || ENCRES[0].valeur);
  const [traits, setTraits] = useState([]);
  const [image, setImage] = useState(null);
  const pad = useRef(null);
  const enCours = useRef(null);
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;
  const ongletsRef = useRef([]);

  const texte = type === 'nom' ? texteNom : type === 'initiales' ? texteInit : null;

  useEffect(() => {
    let annule = false;
    imageSignature({ type, texte, police, couleur, traits }).then((img) => {
      if (annule) return;
      setImage(img);
      onChangeRef.current?.({ type, texte: type === 'dessin' ? null : texte, police: type === 'dessin' ? null : police, couleur, image: img });
    });
    return () => {
      annule = true;
    };
  }, [type, texte, police, couleur, traits]);

  // Le pad est redessiné à chaque trait (et au changement d'encre).
  useEffect(() => {
    const c = pad.current;
    if (!c) return;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, PAD_L, PAD_H);
    dessinerTraits(ctx, enCours.current ? [...traits, enCours.current] : traits, couleur);
  }, [traits, couleur, type]);

  function position(e) {
    const r = pad.current.getBoundingClientRect();
    return [((e.clientX - r.left) * PAD_L) / r.width, ((e.clientY - r.top) * PAD_H) / r.height];
  }
  function debut(e) {
    pad.current.setPointerCapture(e.pointerId);
    enCours.current = [position(e)];
  }
  function deplacement(e) {
    if (!enCours.current) return;
    enCours.current.push(position(e));
    const ctx = pad.current.getContext('2d');
    ctx.clearRect(0, 0, PAD_L, PAD_H);
    dessinerTraits(ctx, [...traits, enCours.current], couleur);
  }
  function fin() {
    if (!enCours.current) return;
    const t = enCours.current;
    enCours.current = null;
    setTraits((prev) => [...prev, t]);
  }

  function clavierOnglets(e, i) {
    if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
    const n = (i + (e.key === 'ArrowRight' ? 1 : ONGLETS.length - 1)) % ONGLETS.length;
    setType(ONGLETS[n].id);
    ongletsRef.current[n]?.focus();
  }

  async function telecharger() {
    const blob = await (await fetch(image)).blob();
    enregistrerBlob(blob, 'ma-signature.png');
  }

  const apercuTexte = type === 'initiales' ? texteInit || 'E.R.' : texteNom || nom || 'Votre nom';

  return (
    <div>
      <div role="tablist" aria-label="Façon de signer" style={{ display: 'flex', borderBottom: '1px solid var(--c-border)', marginBottom: 14, overflowX: 'auto' }}>
        {ONGLETS.map((o, i) => (
          <button
            key={o.id}
            ref={(el) => (ongletsRef.current[i] = el)}
            type="button"
            role="tab"
            id={`sig-onglet-${o.id}`}
            aria-selected={type === o.id}
            aria-controls="sig-panneau"
            tabIndex={type === o.id ? 0 : -1}
            onClick={() => setType(o.id)}
            onKeyDown={(e) => clavierOnglets(e, i)}
            style={{
              border: 0, background: 'none', padding: '8px 14px', cursor: 'pointer', whiteSpace: 'nowrap', font: 'inherit',
              color: type === o.id ? 'var(--c-primary)' : 'var(--c-text-2)', fontWeight: type === o.id ? 600 : 400,
              borderBottom: `2px solid ${type === o.id ? 'var(--c-primary)' : 'transparent'}`,
            }}
          >
            {o.libelle}
          </button>
        ))}
      </div>

      <div id="sig-panneau" role="tabpanel" aria-labelledby={`sig-onglet-${type}`}>
        {type !== 'dessin' && (
          <>
            <label htmlFor="sig-texte" style={{ display: 'block', fontSize: 13, marginBottom: 4 }}>
              {type === 'nom' ? 'Votre nom' : 'Vos initiales'}
            </label>
            <input
              id="sig-texte"
              type="text"
              maxLength={type === 'nom' ? 60 : 8}
              value={type === 'nom' ? texteNom : texteInit}
              onChange={(e) => (type === 'nom' ? setTexteNom(e.target.value) : setTexteInit(e.target.value))}
              style={{ width: '100%', padding: '8px 10px', border: '1px solid var(--c-border)', borderRadius: 6, font: 'inherit', background: 'var(--c-card)', color: 'inherit', boxSizing: 'border-box' }}
            />
            <div role="radiogroup" aria-label="Style d'écriture" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: 8, margin: '10px 0' }}>
              {POLICES.map((p) => (
                <label
                  key={p.famille}
                  style={{
                    border: police === p.famille ? '2px solid var(--c-primary)' : '1px solid var(--c-border)', borderRadius: 8,
                    padding: police === p.famille ? 7 : 8, textAlign: 'center', cursor: 'pointer', background: '#fff', color: couleur,
                    fontFamily: `"${p.famille}"`, fontWeight: p.poids, fontSize: 26, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
                  }}
                >
                  <input type="radio" name="sig-police" value={p.famille} checked={police === p.famille} onChange={() => setPolice(p.famille)} aria-label={p.famille} style={{ position: 'absolute', opacity: 0 }} />
                  {apercuTexte}
                </label>
              ))}
            </div>
          </>
        )}

        {type === 'dessin' && (
          <>
            <div style={{ position: 'relative', border: '1px dashed #9ca3af', borderRadius: 8, background: '#fff', aspectRatio: '3 / 1', touchAction: 'none' }}>
              <canvas
                ref={pad}
                width={PAD_L}
                height={PAD_H}
                aria-label="Zone de dessin de la signature"
                onPointerDown={debut}
                onPointerMove={deplacement}
                onPointerUp={fin}
                onPointerCancel={fin}
                style={{ width: '100%', height: '100%', display: 'block', cursor: 'crosshair', touchAction: 'none' }}
              />
              <div aria-hidden="true" style={{ position: 'absolute', left: '8%', right: '8%', bottom: '24%', borderBottom: '1px solid #d1d5db', pointerEvents: 'none' }} />
              {traits.length === 0 && (
                <div aria-hidden="true" style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af', pointerEvents: 'none' }}>
                  Signez ici avec la souris ou le doigt
                </div>
              )}
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8, marginTop: 6 }}>
              <span style={{ fontSize: 12, color: 'var(--c-text-2)' }}>Sur téléphone, tournez l’écran pour plus d’espace. Vous pouvez aussi taper votre nom.</span>
              <span style={{ display: 'flex', gap: 6 }}>
                <AdminButton size="sm" variant="secondary" disabled={traits.length === 0} onClick={() => setTraits((t) => t.slice(0, -1))}>Annuler le trait</AdminButton>
                <AdminButton size="sm" variant="secondary" disabled={traits.length === 0} onClick={() => setTraits([])}>Effacer</AdminButton>
              </span>
            </div>
          </>
        )}
      </div>

      <div style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13, color: 'var(--c-text-2)', marginTop: 12 }} role="radiogroup" aria-label="Couleur de l'encre">
        Encre :
        {ENCRES.map((e) => (
          <button
            key={e.valeur}
            type="button"
            role="radio"
            aria-checked={couleur === e.valeur}
            aria-label={e.libelle}
            title={e.libelle}
            className="swatch-couleur"
            onClick={() => setCouleur(e.valeur)}
            style={{ width: 36, height: 36, minWidth: 36, padding: 0, flex: '0 0 auto', borderRadius: '50%', background: e.valeur, border: '2px solid #fff', cursor: 'pointer', boxShadow: couleur === e.valeur ? '0 0 0 2px var(--c-primary)' : '0 0 0 1px var(--c-border)' }}
          />
        ))}
      </div>

      <div style={{ position: 'relative', border: '1px solid var(--c-border)', borderRadius: 8, background: '#fff', height: 110, display: 'flex', alignItems: 'center', justifyContent: 'center', marginTop: 12 }}>
        <span style={{ position: 'absolute', top: 6, left: 10, fontSize: 11, color: '#6b7280' }}>Aperçu</span>
        {image ? (
          <img src={image} alt="Aperçu de votre signature" style={{ maxHeight: 80, maxWidth: '80%' }} />
        ) : (
          <span style={{ fontSize: 13, color: '#6b7280' }}>{type === 'dessin' ? 'Dessinez votre signature' : 'Tapez votre nom'}</span>
        )}
      </div>
      <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 6 }}>
        <AdminButton size="sm" variant="secondary" disabled={!image} onClick={telecharger}>Télécharger en PNG</AdminButton>
      </div>
    </div>
  );
}
