import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput } from '../AdminFormField';
import Banner from '../ui/Banner';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { creerEntreeCalendrier, modifierEntreeCalendrier } from '../../hooks/useCalendrierScolaire';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { TYPES_CALENDRIER, SOURCES_CALENDRIER, getStatut } from '../../utils/statuts';
import { formatDate } from '../../utils/dates';

/**
 * Ajout ou modification d'une entrée du calendrier scolaire (mock-up 05).
 * Les erreurs de validation de l'API s'affichent sous les champs ; la saisie est conservée en cas d'échec.
 *
 * @param {object} props
 * @param {object} props.annee année scolaire concernée
 * @param {object|null} props.entree entrée à modifier, `null` pour un ajout
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone appelée après succès avec le message de confirmation
 */
export default function CalendrierEntreeModal({ annee, entree, onClose, onDone }) {
  const edition = Boolean(entree);
  const [type, setType] = useState(entree?.type || 'fermeture');
  const [libelle, setLibelle] = useState(entree?.libelle || '');
  const [debut, setDebut] = useState(entree?.date_debut || '');
  const [fin, setFin] = useState(entree?.date_fin || '');
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  async function soumettre(e) {
    e.preventDefault();
    setEnvoi(true);
    setErreurs({});
    setMessage(null);
    const payload = { type, libelle: libelle.trim(), date_debut: debut, date_fin: fin || debut };
    try {
      if (edition) {
        await modifierEntreeCalendrier(entree.id, payload);
        onDone(`Date « ${payload.libelle} » (${periodeTexte(payload)}) modifiée.`);
      } else {
        await creerEntreeCalendrier(annee.id, payload);
        onDone(`Date « ${payload.libelle} » (${periodeTexte(payload)}) ajoutée.`);
      }
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setMessage(getErrorMessage(err, "La date n'a pas pu être enregistrée."));
    } finally {
      setEnvoi(false);
    }
  }

  const complet = libelle.trim() && debut;
  const sourceEntree = entree ? getStatut(SOURCES_CALENDRIER, entree.source).label : null;

  return (
    <AdminModal
      isOpen
      title={edition ? `Modifier « ${entree.libelle} »` : 'Ajouter une date au calendrier'}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton type="submit" form="form-calendrier-entree" disabled={!complet || envoi} loading={envoi}>
            {edition ? 'Enregistrer la modification' : 'Ajouter la date'}
          </AdminButton>
        </>
      }
    >
      <form id="form-calendrier-entree" onSubmit={soumettre} noValidate>
        {message && Object.keys(erreurs).length === 0 && <Banner tone="error">{message}</Banner>}

        <fieldset style={{ border: 0, padding: 0, margin: `0 0 ${ADMIN_SPACING.lg}` }}>
          <legend style={{ fontSize: '12px', fontWeight: 600, textTransform: 'uppercase', marginBottom: ADMIN_SPACING.sm }}>
            Type
          </legend>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.lg}` }}>
            {Object.entries(TYPES_CALENDRIER).map(([valeur, { label }]) => (
              <label key={valeur} style={{ display: 'flex', alignItems: 'center', gap: ADMIN_SPACING.sm, fontSize: '14px' }}>
                <input type="radio" name="calendrier-type" checked={type === valeur} onChange={() => setType(valeur)} />
                {label}
              </label>
            ))}
          </div>
          {erreurs.type && <div role="alert" style={{ color: ADMIN_COLORS.error, fontSize: '12px' }}>{erreurs.type}</div>}
        </fieldset>

        <AdminFormField label="Libellé" htmlFor="calendrier-libelle" required error={erreurs.libelle}>
          <AdminInput id="calendrier-libelle" value={libelle} onChange={(e) => setLibelle(e.target.value)} error={erreurs.libelle} />
        </AdminFormField>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: ADMIN_SPACING.lg }}>
          <AdminFormField label="Du" htmlFor="calendrier-debut" required error={erreurs.date_debut}>
            <AdminInput id="calendrier-debut" type="date" value={debut} onChange={(e) => setDebut(e.target.value)} error={erreurs.date_debut} />
          </AdminFormField>
          <AdminFormField label="Au (inclus)" htmlFor="calendrier-fin" error={erreurs.date_fin} description="Laissez vide pour un seul jour.">
            <AdminInput id="calendrier-fin" type="date" value={fin} onChange={(e) => setFin(e.target.value)} error={erreurs.date_fin} />
          </AdminFormField>
        </div>
        <p style={{ margin: 0, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
          {edition
            ? `Source : ${sourceEntree}. Modifier une entrée FWB la marque « FWB · modifiée » et conserve votre version lors des prochains imports.`
            : 'Source enregistrée : École. Les futures classes sauteront cette date ; les sessions déjà générées ne sont pas déplacées automatiquement (une alerte s’affiche sur la classe).'}
        </p>
      </form>
    </AdminModal>
  );
}

function periodeTexte({ date_debut: d1, date_fin: d2 }) {
  return d2 && d2 !== d1 ? `${formatDate(d1)} → ${formatDate(d2)}` : formatDate(d1);
}
