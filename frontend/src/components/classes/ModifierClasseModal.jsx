import { useEffect, useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import { AdminFormField, AdminInput } from '../AdminFormField';
import client from '../../api/client';
import { modifierClasse } from '../../hooks/useClasses';
import { getErrorMessage, getFieldErrors } from '../../api/errors';

const hhmm = (h) => String(h || '').slice(0, 5);

/**
 * Modifier le créneau (heures) et le lieu d'une classe (UI-M7), avec l'impact énoncé avant d'enregistrer : combien de séances
 * à venir seront mises à jour, combien resteront inchangées. Le jour de la classe ne se modifie pas ici.
 */
export default function ModifierClasseModal({ classe, onClose, onDone }) {
  const [debut, setDebut] = useState(hhmm(classe.heure_debut));
  const [fin, setFin] = useState(hhmm(classe.heure_fin));
  const [lieu, setLieu] = useState(classe.lieu || '');
  const [apercu, setApercu] = useState(null);
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  const change = debut !== hhmm(classe.heure_debut) || fin !== hhmm(classe.heure_fin) || lieu !== (classe.lieu || '');
  const payload = { heure_debut: debut, heure_fin: fin, lieu: lieu || null };

  // Aperçu dynamique : aucune écriture.
  useEffect(() => {
    setApercu(null);
    if (!change || !debut || !fin || fin <= debut) return undefined;
    let annule = false;
    const t = setTimeout(() => {
      client
        .post(`/classes/${classe.id}/apercu-modification`, payload)
        .then((res) => !annule && setApercu(res.data.data))
        .catch(() => !annule && setApercu(null));
    }, 300);
    return () => {
      annule = true;
      clearTimeout(t);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debut, fin, lieu]);

  async function enregistrer() {
    setEnvoi(true);
    setErreurs({});
    setMessage(null);
    try {
      await modifierClasse(classe.id, payload);
      const n = apercu?.seances_a_mettre_a_jour;
      onDone(n != null ? `Créneau et lieu enregistrés : ${n} séance${n > 1 ? 's' : ''} à venir mise${n > 1 ? 's' : ''} à jour.` : 'Créneau et lieu enregistrés.');
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
      title="Créneau et lieu"
      size="sm"
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton loading={envoi} disabled={!change || fin <= debut} onClick={enregistrer}>
            Enregistrer
          </AdminButton>
        </>
      }
    >
      <p style={{ margin: '0 0 12px', fontSize: 13, color: 'var(--c-text-2)' }}>Le jour de la semaine ne change pas ici : pour un autre jour, créez une autre classe.</p>
      {message && (
        <Banner tone="error" role="alert">
          <strong>{message}</strong>
        </Banner>
      )}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
        <AdminFormField label="Début" htmlFor="classe-debut" error={erreurs.heure_debut}>
          <AdminInput id="classe-debut" type="time" value={debut} onChange={(e) => setDebut(e.target.value)} />
        </AdminFormField>
        <AdminFormField label="Fin" htmlFor="classe-fin" error={erreurs.heure_fin || (fin <= debut ? "L'heure de fin doit être postérieure à l'heure de début." : undefined)}>
          <AdminInput id="classe-fin" type="time" value={fin} onChange={(e) => setFin(e.target.value)} />
        </AdminFormField>
      </div>
      <AdminFormField label="Lieu" htmlFor="classe-lieu" error={erreurs.lieu}>
        <AdminInput id="classe-lieu" value={lieu} onChange={(e) => setLieu(e.target.value)} />
      </AdminFormField>
      {apercu && (
        <ul className="impact" aria-live="polite">
          <li>
            {apercu.seances_a_mettre_a_jour} séance{apercu.seances_a_mettre_a_jour > 1 ? 's' : ''} à venir seront mises à jour
          </li>
          <li className="impact--neutre">
            {apercu.seances_inchangees} inchangée{apercu.seances_inchangees > 1 ? 's' : ''} (passées, annulées ou déjà ajustées)
          </li>
        </ul>
      )}
    </AdminModal>
  );
}
