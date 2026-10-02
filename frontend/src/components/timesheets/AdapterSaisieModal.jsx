import { useEffect, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect, AdminTextarea } from '../AdminFormField';
import Banner from '../ui/Banner';
import { adapterSaisie, chargerHistoriqueSaisie } from '../../hooks/useTimesheets';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { formatDateCourte, formatDateHeure } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';

const TYPES = [
  { value: 'animation', label: 'Animation' },
  { value: 'cours', label: 'Cours' },
  { value: 'preparation', label: 'Préparation' },
  { value: 'deplacement', label: 'Frais de déplacement' },
];
const LIBELLE_TYPE = Object.fromEntries(TYPES.map((t) => [t.value, t.label]));
const LIBELLE_CHAMP = { nombre_heures: 'Heures', date_prestation: 'Date', type_activite: 'Type' };

function formatValeur(champ, v) {
  if (champ === 'nombre_heures') return formatHeures(v);
  if (champ === 'type_activite') return LIBELLE_TYPE[v] || v;
  if (champ === 'date_prestation') return formatDateCourte(v);
  return String(v);
}

/**
 * Adaptation d'une saisie soumise ou confirmée par le directeur/admin (TS-01 T1) : heures, type, et date pour une
 * saisie libre. Motif obligatoire, aperçu avant/après, historique des adaptations. Le serveur reste l'autorité.
 */
export default function AdapterSaisieModal({ saisie, onClose, onDone }) {
  const dateLiee = saisie.course_session_id != null;
  const [heures, setHeures] = useState(String(Number(saisie.nombre_heures)));
  const [type, setType] = useState(saisie.type_activite);
  const [date, setDate] = useState(saisie.date_prestation.slice(0, 10));
  const [motif, setMotif] = useState('');
  const [erreur, setErreur] = useState(null);
  const [champs, setChamps] = useState({});
  const [envoi, setEnvoi] = useState(false);
  const [historique, setHistorique] = useState([]);

  useEffect(() => {
    chargerHistoriqueSaisie(saisie.id).then(setHistorique).catch(() => setHistorique([]));
  }, [saisie.id]);

  const heuresNum = Number(heures.replace(',', '.'));
  const changements = {};
  if (Number.isFinite(heuresNum) && heuresNum !== Number(saisie.nombre_heures)) changements.nombre_heures = heuresNum;
  if (type !== saisie.type_activite) changements.type_activite = type;
  if (!dateLiee && date !== saisie.date_prestation.slice(0, 10)) changements.date_prestation = date;

  const tarif = Number(saisie.nombre_heures) > 0 && saisie.montant_brut != null
    ? Number(saisie.montant_brut) / Number(saisie.nombre_heures)
    : null;
  const nouveauMontant = tarif != null && Number.isFinite(heuresNum) ? tarif * heuresNum : null;
  const peutAppliquer = Object.keys(changements).length > 0 && motif.trim().length >= 3 && !envoi;

  async function appliquer() {
    setEnvoi(true);
    setErreur(null);
    setChamps({});
    try {
      await adapterSaisie(saisie.id, { ...changements, motif: motif.trim() });
      onDone('Saisie adaptée. Le changement est tracé dans l’historique.');
    } catch (err) {
      setChamps(getFieldErrors(err));
      setErreur(getErrorMessage(err, 'Adaptation impossible'));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title={`Adapter — ${saisie.professeur?.prenom ?? ''} ${saisie.professeur?.nom ?? ''}, ${formatDateCourte(saisie.date_prestation)}`}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton onClick={appliquer} disabled={!peutAppliquer}>
            {envoi ? 'Enregistrement…' : 'Appliquer'}
          </AdminButton>
        </>
      }
    >
      {erreur && <Banner tone="error" role="alert">{erreur}</Banner>}
      {saisie.statut_validation === 'confirme' && (
        <Banner tone="warning">
          Cette saisie est confirmée : l’adapter retire la signature du professeur, qui devra reconfirmer.
        </Banner>
      )}
      <AdminFormField label="Heures" htmlFor="adapt-heures" error={champs.nombre_heures}>
        <AdminInput id="adapt-heures" inputMode="decimal" value={heures} onChange={(e) => setHeures(e.target.value)} />
      </AdminFormField>
      <AdminFormField label="Type" htmlFor="adapt-type" error={champs.type_activite}>
        <AdminSelect id="adapt-type" value={type} onChange={(e) => setType(e.target.value)} options={TYPES} />
      </AdminFormField>
      <AdminFormField
        label="Date"
        htmlFor="adapt-date"
        error={champs.date_prestation}
        description={dateLiee ? 'Liée à la session : non modifiable.' : 'Doit rester dans le même mois.'}
      >
        <AdminInput id="adapt-date" type="date" value={date} disabled={dateLiee} onChange={(e) => setDate(e.target.value)} />
      </AdminFormField>
      <AdminFormField label="Motif (obligatoire)" htmlFor="adapt-motif" error={champs.motif}>
        <AdminTextarea id="adapt-motif" rows={2} value={motif} onChange={(e) => setMotif(e.target.value)} />
      </AdminFormField>

      {Object.keys(changements).length > 0 && (
        <div aria-live="polite" style={{ fontSize: 13, marginBottom: 12 }}>
          <strong>Aperçu</strong>
          <ul style={{ margin: '4px 0 0', paddingLeft: 18 }}>
            {Object.entries(changements).map(([champ, valeur]) => (
              <li key={champ}>
                {LIBELLE_CHAMP[champ]} : <s>{formatValeur(champ, champ === 'date_prestation' ? saisie.date_prestation : saisie[champ])}</s> → <strong>{formatValeur(champ, valeur)}</strong>
              </li>
            ))}
            {nouveauMontant != null && changements.nombre_heures !== undefined && (
              <li>Montant : <s>{formatEuros(saisie.montant_brut)}</s> → <strong>{formatEuros(nouveauMontant)}</strong></li>
            )}
          </ul>
        </div>
      )}

      <div style={{ fontSize: 13 }}>
        <strong>Historique</strong>
        {historique.length === 0 ? (
          <p style={{ margin: '4px 0 0', color: 'var(--c-text-2)' }}>Aucune adaptation.</p>
        ) : (
          <ul style={{ margin: '4px 0 0', paddingLeft: 18 }}>
            {historique.map((h) => (
              <li key={h.id}>
                {formatDateHeure(h.created_at)} — {h.auteur || 'Système'} :{' '}
                {h.action === 'lissage'
                  ? `Lissage : ${formatHeures(h.apres.heures_deplacees)} déplacées vers le ${formatDateCourte(h.apres.date_cible)}`
                  : Object.keys(h.apres).map((c) => `${LIBELLE_CHAMP[c] || c} ${formatValeur(c, h.avant[c])} → ${formatValeur(c, h.apres[c])}`).join(', ')}
                {' '}— « {h.motif} »
              </li>
            ))}
          </ul>
        )}
      </div>
    </AdminModal>
  );
}
