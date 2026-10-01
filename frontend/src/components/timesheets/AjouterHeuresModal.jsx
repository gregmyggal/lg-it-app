import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect, AdminTextarea } from '../AdminFormField';
import Banner from '../ui/Banner';
import { creerSaisie, modifierSaisie } from '../../hooks/useTimesheets';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { optionsStatut, TYPES_ACTIVITE } from '../../utils/statuts';

/**
 * Ajouter des heures (encodage libre, mock-up 02) ou modifier un brouillon.
 * Libre = fonction normale : type, date et durée requis ; cours, séance et commentaire facultatifs, aucun motif.
 * Si une séance est choisie, la saisie est liée à la session (date et cours déduits par le serveur).
 *
 * @param {object} props
 * @param {string} props.dateParDefaut `YYYY-MM-DD` proposée (1er jour du mois affiché)
 * @param {{value: string, label: string}[]} [props.cours] cours proposés (facultatif)
 * @param {{value: string, label: string}[]} [props.seances] séances encodables du mois (facultatif)
 * @param {object} [props.saisie] saisie en brouillon à modifier (durée, commentaire)
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone
 */
export default function AjouterHeuresModal({ dateParDefaut, cours = [], seances = [], saisie, onClose, onDone }) {
  const edition = Boolean(saisie);
  const [type, setType] = useState(saisie?.type_activite || 'preparation');
  const [date, setDate] = useState(saisie ? saisie.date_prestation.slice(0, 10) : dateParDefaut);
  const [heures, setHeures] = useState(saisie ? String(Number(saisie.nombre_heures)) : '');
  const [coursId, setCoursId] = useState(saisie?.cours_id ? String(saisie.cours_id) : '');
  const [seance, setSeance] = useState('');
  const [commentaire, setCommentaire] = useState(saisie?.commentaire || '');
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  const liee = Boolean(seance) || Boolean(saisie?.course_session_id);
  const valide = Number(heures) >= 0.5 && Number(heures) <= 24 && (liee || Boolean(date));

  async function soumettre(e) {
    e.preventDefault();
    if (!valide) return;
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      if (edition) {
        await modifierSaisie(saisie.id, { nombre_heures: Number(heures), commentaire: commentaire || null, ...(liee ? {} : { date_prestation: date, cours_id: coursId || null }) });
        onDone('Heures modifiées.');
      } else {
        await creerSaisie({
          type_activite: type,
          nombre_heures: Number(heures),
          commentaire: commentaire || null,
          ...(seance ? { course_session_id: Number(seance) } : { date_prestation: date, cours_id: coursId ? Number(coursId) : null }),
        });
        onDone('Heures ajoutées en brouillon. Elles seront soumises avec le mois.');
      }
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
      title={edition ? 'Modifier mes heures' : 'Ajouter des heures'}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton type="submit" form="form-heures" disabled={!valide || envoi} loading={envoi}>
            {edition ? 'Enregistrer' : 'Ajouter ces heures'}
          </AdminButton>
        </>
      }
    >
      <form id="form-heures" onSubmit={soumettre} noValidate>
        {message && (
          <Banner tone="error" role="alert">
            <strong>{message}</strong>
          </Banner>
        )}
        {!edition && (
          <AdminFormField label="Type d'activité" htmlFor="heures-type" required error={erreurs.type_activite}>
            <AdminSelect id="heures-type" value={type} options={optionsStatut(TYPES_ACTIVITE)} onChange={(e) => setType(e.target.value)} />
          </AdminFormField>
        )}
        {!liee && (
          <AdminFormField label="Date" htmlFor="heures-date" required error={erreurs.date_prestation}>
            <AdminInput id="heures-date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
          </AdminFormField>
        )}
        <AdminFormField label="Durée (heures)" htmlFor="heures-duree" required error={erreurs.nombre_heures} description="Entre 0,5 et 24 heures.">
          <AdminInput id="heures-duree" type="number" step="0.5" min="0.5" max="24" value={heures} onChange={(e) => setHeures(e.target.value)} />
        </AdminFormField>
        {!liee && !edition && cours.length > 0 && (
          <AdminFormField label="Cours (facultatif)" htmlFor="heures-cours" error={erreurs.cours_id}>
            <AdminSelect id="heures-cours" value={coursId} placeholder="Aucun cours" options={cours} onChange={(e) => setCoursId(e.target.value)} />
          </AdminFormField>
        )}
        {!edition && seances.length > 0 && (
          <AdminFormField
            label="Séance (facultatif)"
            htmlFor="heures-seance"
            error={erreurs.course_session_id}
            description="Reliez les heures à une séance quand elles la concernent : la direction les retrouve alors dans la classe. Sinon, laissez vide."
          >
            <AdminSelect id="heures-seance" value={seance} placeholder="Aucune séance" options={seances} onChange={(e) => setSeance(e.target.value)} />
          </AdminFormField>
        )}
        <AdminFormField label="Commentaire (facultatif)" htmlFor="heures-comm" error={erreurs.commentaire}>
          <AdminTextarea id="heures-comm" rows={2} value={commentaire} onChange={(e) => setCommentaire(e.target.value)} />
        </AdminFormField>
      </form>
    </AdminModal>
  );
}
