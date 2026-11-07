import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect, AdminTextarea } from '../AdminFormField';
import HeuresStepper from './HeuresStepper';
import Banner from '../ui/Banner';
import { ajouterHeuresDirection } from '../../hooks/useTimesheets';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { formatHeures } from '../../utils/format';
import { formatDateCourte } from '../../utils/dates';
import { TYPES_ACTIVITE } from '../../utils/statuts';

const TYPES = ['animation', 'cours', 'preparation'].map((v) => ({ value: v, label: TYPES_ACTIVITE[v].label }));
const MAX_COMMENTAIRE = 500;

const deuxChiffres = (n) => String(n).padStart(2, '0');
const nouvelleLigne = (date) => ({ date, type: 'preparation', heures: '', commentaire: '' });

/**
 * TS-07 : ajouter des heures libres au mois EN BROUILLON d'un professeur. Une ou plusieurs lignes, chacune avec son
 * commentaire obligatoire. Le serveur reste l'autorité (mois non soumis, date dans le mois, bornes, droits).
 *
 * @param {object} props
 * @param {number} props.professeurId
 * @param {string} props.prenom
 * @param {number} props.annee
 * @param {number} props.mois 1-12
 * @param {object[]} props.lignesExistantes saisies du mois (pour repérer un doublon et calculer le total)
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone
 */
export default function AjouterHeuresDirectionModal({ professeurId, prenom, annee, mois, lignesExistantes, onClose, onDone }) {
  const debut = `${annee}-${deuxChiffres(mois)}-01`;
  const fin = `${annee}-${deuxChiffres(mois)}-${deuxChiffres(new Date(annee, mois, 0).getDate())}`;
  const [lignes, setLignes] = useState([nouvelleLigne(debut)]);
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  const maj = (i, champ, valeur) => setLignes((ls) => ls.map((l, k) => (k === i ? { ...l, [champ]: valeur } : l)));
  const heuresValides = (l) => Number(l.heures) >= 0.5 && Number(l.heures) <= 24 && (Number(l.heures) * 4) % 1 === 0;
  const ligneValide = (l) => l.date >= debut && l.date <= fin && heuresValides(l) && l.commentaire.trim().length >= 3 && l.commentaire.length <= MAX_COMMENTAIRE;
  const valide = lignes.length > 0 && lignes.every(ligneValide);

  const totalAvant = lignesExistantes.filter((t) => t.type_activite !== 'deplacement').reduce((s, t) => s + Number(t.nombre_heures), 0);
  const ajout = lignes.reduce((s, l) => s + (heuresValides(l) ? Number(l.heures) : 0), 0);

  async function soumettre(e) {
    e.preventDefault();
    if (!valide) return;
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      await ajouterHeuresDirection(professeurId, {
        annee,
        mois,
        lignes: lignes.map((l) => ({ date_prestation: l.date, type_activite: l.type, nombre_heures: Number(l.heures), commentaire: l.commentaire.trim() })),
      });
      onDone(`${lignes.length} ligne${lignes.length > 1 ? 's' : ''} ajoutée${lignes.length > 1 ? 's' : ''}. ${prenom} a été prévenu(e).`);
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
      title={`Ajouter des heures à ${prenom}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton type="submit" form="form-ajout-direction" disabled={!valide || envoi} loading={envoi}>
            Ajouter {lignes.length} ligne{lignes.length > 1 ? 's' : ''}
          </AdminButton>
        </>
      }
    >
      <form id="form-ajout-direction" onSubmit={soumettre} noValidate>
        {message && <Banner tone="error" role="alert">{message}</Banner>}
        <p style={{ marginTop: 0, fontSize: 13 }}>
          Ces heures sont ajoutées en brouillon au nom de {prenom}. Elle ou il les verra avec votre commentaire et soumettra son mois.
        </p>
        {lignes.map((l, i) => {
          const doublons = lignesExistantes.filter((t) => t.date_prestation.slice(0, 10) === l.date);
          const err = (champ) => erreurs[`lignes.${i}.${champ}`]?.[0];
          return (
            <fieldset key={i} style={{ border: '1px solid var(--c-border)', borderRadius: 8, padding: 12, margin: '0 0 12px' }}>
              <legend style={{ fontSize: 13, fontWeight: 600 }}>Ligne {i + 1}</legend>
              <AdminFormField label="Date" htmlFor={`ajd-date-${i}`} required error={err('date_prestation') || (l.date && (l.date < debut || l.date > fin) ? 'La date doit être dans le mois.' : undefined)}>
                <AdminInput id={`ajd-date-${i}`} type="date" min={debut} max={fin} value={l.date} onChange={(e) => maj(i, 'date', e.target.value)} />
              </AdminFormField>
              {doublons.length > 0 && (
                <Banner tone="info">
                  Déjà encodé le {formatDateCourte(l.date)} : {doublons.map((t) => `${TYPES_ACTIVITE[t.type_activite]?.label || t.type_activite} ${formatHeures(t.nombre_heures)}`).join(', ')}. Vérifiez qu'il ne s'agit pas d'un doublon.
                </Banner>
              )}
              <AdminFormField label="Type d'activité" htmlFor={`ajd-type-${i}`} required error={err('type_activite')}>
                <AdminSelect id={`ajd-type-${i}`} value={l.type} options={TYPES} onChange={(e) => maj(i, 'type', e.target.value)} />
              </AdminFormField>
              <AdminFormField
                label="Heures"
                htmlFor={`ajd-heures-${i}`}
                required
                error={err('nombre_heures') || (l.heures !== '' && !heuresValides(l) ? 'Entre 0,5 et 24 h, par pas de 15 min.' : undefined)}
              >
                <HeuresStepper id={`ajd-heures-${i}`} aria-label={`Heures de la ligne ${i + 1}`} value={l.heures} onChange={(v) => maj(i, 'heures', v)} />
              </AdminFormField>
              <AdminFormField
                label="Commentaire (obligatoire)"
                htmlFor={`ajd-comm-${i}`}
                required
                error={err('commentaire')}
                description={`${l.commentaire.length}/${MAX_COMMENTAIRE} — visible par le professeur.`}
              >
                <AdminTextarea id={`ajd-comm-${i}`} rows={2} maxLength={MAX_COMMENTAIRE} value={l.commentaire} onChange={(e) => maj(i, 'commentaire', e.target.value)} />
              </AdminFormField>
              {lignes.length > 1 && (
                <AdminButton type="button" variant="secondary" size="sm" onClick={() => setLignes((ls) => ls.filter((_, k) => k !== i))}>
                  Retirer cette ligne
                </AdminButton>
              )}
            </fieldset>
          );
        })}
        {lignes.length < 20 && (
          <AdminButton type="button" variant="secondary" size="sm" onClick={() => setLignes((ls) => [...ls, nouvelleLigne(ls[ls.length - 1].date)])}>
            + Ajouter une autre ligne
          </AdminButton>
        )}
        <p style={{ fontSize: 13, marginBottom: 0 }}>
          Total du mois : <strong>{formatHeures(totalAvant)}</strong> → <strong>{formatHeures(totalAvant + ajout)}</strong>
        </p>
      </form>
    </AdminModal>
  );
}
