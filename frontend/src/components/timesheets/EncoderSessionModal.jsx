import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect, AdminTextarea } from '../AdminFormField';
import Banner from '../ui/Banner';
import { creerSaisie, soumettreSaisie } from '../../hooks/useTimesheets';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { optionsStatut, TYPES_ACTIVITE } from '../../utils/statuts';
import { formatDateLongue, formatHoraire } from '../../utils/dates';
import { formatDuree } from '../../utils/format';
import { libelleSession, libelleSessionPhrase } from '../../utils/classes';

/**
 * Raccourci d'encodage d'une session depuis « Mes classes » (mock-up 01) : 2 clics (ouvrir, soumettre).
 * La date, le cours et la durée viennent de la session ; la durée reste modifiable. Chaque professeur encode les
 * siennes, de façon indépendante (co-enseignement, remplacement).
 *
 * @param {object} props
 * @param {object} props.session session de `GET /mes-classes/{id}/sessions` (`duree_par_defaut`, `libelle`, `date`…)
 * @param {string} props.classeLibelle
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone
 */
export default function EncoderSessionModal({ session, classeLibelle, onClose, onDone }) {
  const [type, setType] = useState('animation');
  const [heures, setHeures] = useState(String(session.duree_par_defaut));
  const [commentaire, setCommentaire] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const valide = Number(heures) >= 0.5 && Number(heures) <= 24;

  async function envoyer(soumettre) {
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      const saisie = await creerSaisie({
        course_session_id: session.id,
        type_activite: type,
        nombre_heures: Number(heures),
        commentaire: commentaire || null,
      });
      if (soumettre) await soumettreSaisie(saisie.id);
      onDone(soumettre ? `Heures soumises pour la ${libelleSessionPhrase(session)}.` : `Brouillon enregistré pour la ${libelleSessionPhrase(session)}.`);
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setMessage(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title={`Encoder mes heures — ${libelleSession(session)}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={() => envoyer(false)} disabled={!valide || envoi}>
            Enregistrer en brouillon
          </AdminButton>
          <AdminButton onClick={() => envoyer(true)} disabled={!valide || envoi} loading={envoi}>
            Soumettre les heures
          </AdminButton>
        </>
      }
    >
      {message && (
        <Banner tone="error" role="alert">
          <strong>{message}</strong>
        </Banner>
      )}
      <p style={{ marginTop: 0 }}>
        <strong>{classeLibelle}</strong> · {libelleSession(session)}
        <br />
        {formatDateLongue(session.date)} · {formatHoraire(session.heure_debut, session.heure_fin)}
      </p>
      <AdminFormField label="Type d'activité" htmlFor="enc-type" required error={erreurs.type_activite}>
        <AdminSelect id="enc-type" value={type} options={optionsStatut(TYPES_ACTIVITE)} onChange={(e) => setType(e.target.value)} />
      </AdminFormField>
      <AdminFormField
        label="Heures défrayées"
        htmlFor="enc-duree"
        required
        error={erreurs.nombre_heures}
        description={`Préremplies avec les heures défrayables${session.duree_seance != null ? ` (${formatDuree(session.duree_seance)} de séance + préparation)` : ''} ; modifiables (0,5 à 24 h).${Number(heures) !== Number(session.duree_par_defaut) ? ` Valeur standard : ${formatDuree(session.duree_par_defaut)}.` : ''}`}
      >
        <AdminInput id="enc-duree" type="number" step="0.5" min="0.5" max="24" value={heures} onChange={(e) => setHeures(e.target.value)} />
      </AdminFormField>
      <AdminFormField label="Commentaire (facultatif)" htmlFor="enc-comm" error={erreurs.commentaire}>
        <AdminTextarea id="enc-comm" rows={2} value={commentaire} onChange={(e) => setCommentaire(e.target.value)} />
      </AdminFormField>
      <p style={{ fontSize: '13px', marginBottom: 0 }}>
        Vos heures sont les vôtres : vos co-enseignants encodent les leurs de leur côté. Une fois soumises, elles ne sont plus modifiables par vous.
      </p>
    </AdminModal>
  );
}
