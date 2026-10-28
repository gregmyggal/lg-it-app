import { useEffect, useRef, useState } from 'react';
import AdminButton from '../AdminButton';
import { AdminTextarea } from '../AdminFormField';
import Banner from '../ui/Banner';
import { enregistrerNote, historiqueNote, supprimerNote } from '../../hooks/useTimesheets';
import { getErrorMessage } from '../../api/errors';
import { formatDateHeure } from '../../utils/dates';

const TAILLE_MAX = 1000;
const DELAI_ANNULATION_MS = 8000;
const FOND = '#fff7e0';
const BORD = '#e0b64a';

/** Mention de confidentialité répétée à chaque zone de note (TS-06). */
export function MentionPrivee() {
  return (
    <div style={{ display: 'flex', gap: 8, alignItems: 'center', background: FOND, border: `1px dashed ${BORD}`, color: '#92400e', borderRadius: 8, padding: '6px 10px', fontSize: 12, fontWeight: 600, marginBottom: 8 }}>
      <span role="img" aria-label="Cadenas">🔒</span> Note interne — visible uniquement par la direction et l'administration
    </div>
  );
}

/**
 * TS-06 : lecture et édition d'une note interne (une par mois, une par ligne). Jamais visible du professeur : le serveur
 * ne la renvoie qu'à la direction et à l'admin. Suppression annulable pendant 8 secondes (la suppression réelle part à
 * l'issue du délai).
 *
 * @param {object} props
 * @param {number} props.professeurId
 * @param {number} props.annee
 * @param {number} props.mois
 * @param {number|null} props.timesheetId nul pour la note du mois
 * @param {object|null} props.note note actuelle (`notes_internes`)
 * @param {string} props.libelle « Note interne du mois », « Note interne de la ligne du 15/10 »
 * @param {boolean} [props.ouvertEnEdition] ouvre directement l'éditeur (note absente)
 * @param {() => void} props.onChange rechargement du détail du mois
 * @param {() => void} [props.onFerme] fermeture de l'éditeur vide
 */
export default function NoteInterne({ professeurId, annee, mois, timesheetId = null, note, libelle, ouvertEnEdition = false, onChange, onFerme }) {
  const [edition, setEdition] = useState(ouvertEnEdition && !note);
  const [texte, setTexte] = useState(note?.texte || '');
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [enregistre, setEnregistre] = useState(null);
  const [historique, setHistorique] = useState(null);
  const [suppression, setSuppression] = useState(false);
  const minuteur = useRef(null);
  const enAttente = useRef(false);
  const idChamp = `note-${timesheetId ?? 'mois'}`;

  const executerSuppression = useRef(() => {});
  executerSuppression.current = async () => {
    enAttente.current = false;
    try {
      await supprimerNote(professeurId, { annee, mois, timesheetId });
      onChange();
    } catch (err) {
      setSuppression(false);
      setErreur(getErrorMessage(err));
    }
  };
  // Quitter l'écran pendant le délai : la suppression demandée est exécutée (jamais perdue ni silencieuse).
  useEffect(() => () => {
    if (enAttente.current) {
      clearTimeout(minuteur.current);
      executerSuppression.current();
    }
  }, []);

  async function enregistrer() {
    setEnvoi(true);
    setErreur(null);
    try {
      await enregistrerNote(professeurId, { annee, mois, timesheetId, texte: texte.trim() });
      setEnregistre(new Date().toLocaleTimeString('fr-BE', { hour: '2-digit', minute: '2-digit' }));
      setEdition(false);
      setHistorique(null);
      onChange();
    } catch (err) {
      setErreur(`Note non enregistrée. ${getErrorMessage(err)}`);
    } finally {
      setEnvoi(false);
    }
  }

  function demanderSuppression() {
    setSuppression(true);
    enAttente.current = true;
    minuteur.current = setTimeout(() => executerSuppression.current(), DELAI_ANNULATION_MS);
  }

  function annulerSuppression() {
    clearTimeout(minuteur.current);
    enAttente.current = false;
    setSuppression(false);
  }

  async function voirHistorique() {
    if (historique) {
      setHistorique(null);
      return;
    }
    try {
      setHistorique(await historiqueNote(professeurId, note.id));
    } catch (err) {
      setErreur(getErrorMessage(err));
    }
  }

  if (suppression) {
    return (
      <div role="status" style={{ display: 'inline-flex', gap: 12, alignItems: 'center', background: '#1f2937', color: '#fff', borderRadius: 8, padding: '8px 12px', fontSize: 13 }}>
        Note supprimée
        <button type="button" onClick={annulerSuppression} style={{ background: 'none', border: 0, color: '#93c5fd', textDecoration: 'underline', cursor: 'pointer' }}>Annuler</button>
      </div>
    );
  }

  if (edition) {
    return (
      <div>
        <MentionPrivee />
        {erreur && <Banner tone="error" role="alert">{erreur} Votre texte est conservé.</Banner>}
        <label htmlFor={idChamp} style={{ display: 'block', fontSize: 12, margin: '0 0 4px' }}>Note interne (privée) — {libelle}</label>
        <AdminTextarea
          id={idChamp}
          rows={3}
          maxLength={TAILLE_MAX}
          value={texte}
          placeholder="Note pour la direction : rien n'est envoyé au professeur"
          aria-describedby={`${idChamp}-aide`}
          style={{ background: '#fffdf5', borderColor: BORD }}
          onChange={(e) => setTexte(e.target.value)}
        />
        <p id={`${idChamp}-aide`} style={{ fontSize: 12, margin: '4px 0 8px' }} aria-live="polite">
          Non visible du professeur. {texte.length} / {TAILLE_MAX} caractères.
        </p>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <AdminButton size="sm" loading={envoi} disabled={texte.trim().length === 0 || envoi} onClick={enregistrer}>Enregistrer la note interne</AdminButton>
          <AdminButton size="sm" variant="secondary" onClick={() => { setEdition(false); setTexte(note?.texte || ''); setErreur(null); onFerme?.(); }}>Annuler</AdminButton>
        </div>
      </div>
    );
  }

  if (!note) return null;

  return (
    <div>
      <MentionPrivee />
      {erreur && <Banner tone="error" role="alert">{erreur}</Banner>}
      <div style={{ background: FOND, border: `1px solid ${BORD}`, borderRadius: 8, padding: '10px 12px' }}>
        <div style={{ whiteSpace: 'pre-wrap' }}>{note.texte}</div>
        <div style={{ fontSize: 12, color: 'var(--c-text-2)', marginTop: 6 }}>
          Par {note.auteur?.nom || 'un utilisateur supprimé'}{note.auteur?.role ? ` · ${note.auteur.role === 'admin' ? 'administration' : 'direction'}` : ''} · {formatDateHeure(note.created_at)}
          {note.modifications > 0 && <> · modifiée {note.modifications} fois — <button type="button" onClick={voirHistorique} style={{ background: 'none', border: 0, padding: 0, color: 'var(--c-primary, #2563eb)', textDecoration: 'underline', cursor: 'pointer' }}>{historique ? 'Masquer l\'historique' : 'Voir l\'historique'}</button></>}
          {note.peut_modifier && (
            <> · <button type="button" onClick={() => { setTexte(note.texte); setEdition(true); }} style={{ background: 'none', border: 0, padding: 0, color: 'var(--c-primary, #2563eb)', textDecoration: 'underline', cursor: 'pointer' }}>Modifier</button>
              {' · '}<button type="button" onClick={demanderSuppression} style={{ background: 'none', border: 0, padding: 0, color: 'var(--c-primary, #2563eb)', textDecoration: 'underline', cursor: 'pointer' }}>Supprimer</button></>
          )}
        </div>
        {enregistre && <p aria-live="polite" style={{ fontSize: 12, margin: '4px 0 0' }}>Enregistré à {enregistre}.</p>}
      </div>
      {historique && (
        <ul style={{ fontSize: 12, margin: '8px 0 0', paddingLeft: 18 }}>
          {historique.map((h, i) => <li key={i}>Remplacée le {formatDateHeure(h.remplace_le)}{h.remplace_par ? ` par ${h.remplace_par}` : ''} : « {h.texte} »</li>)}
        </ul>
      )}
    </div>
  );
}

/** Carte « Note interne du mois » : repliée et vide par défaut, ouverte quand une note existe (TS-06). */
export function NoteInterneMois({ professeurId, annee, mois, note, onChange }) {
  const [ajout, setAjout] = useState(false);
  return (
    <details open={Boolean(note) || ajout} style={{ border: '1px solid var(--c-border)', borderRadius: 8, background: 'var(--c-card)', padding: 12, marginBottom: 12 }}>
      <summary style={{ cursor: 'pointer', fontWeight: 600 }}>
        <span role="img" aria-label="Privé">🔒</span> Note interne du mois{note ? '' : ' — aucune'}
      </summary>
      <div style={{ marginTop: 10 }}>
        {!note && !ajout && (
          <div>
            <p style={{ margin: '0 0 8px', fontSize: 13, color: 'var(--c-text-2)' }}>Aucune note interne.</p>
            <AdminButton size="sm" variant="secondary" onClick={() => setAjout(true)}>Ajouter une note interne</AdminButton>
          </div>
        )}
        {(note || ajout) && (
          <NoteInterne
            professeurId={professeurId}
            annee={annee}
            mois={mois}
            note={note}
            libelle="note du mois"
            ouvertEnEdition={ajout}
            onChange={() => { setAjout(false); onChange(); }}
            onFerme={() => setAjout(false)}
          />
        )}
      </div>
    </details>
  );
}
