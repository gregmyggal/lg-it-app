import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminCheckbox } from '../AdminFormField';
import Banner from '../ui/Banner';
import { ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { creerAnneeScolaire, importerCalendrierFwb } from '../../hooks/useAnneesScolaires';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { useToast } from '../../hooks/useToast';

const FORM_VIDE = {
  libelle: '',
  p1_debut: '',
  p1_fin: '',
  p2_debut: '',
  p2_fin: '',
  importer_fwb: true,
};

/**
 * Modale « Créer une année scolaire » : libellé + 2 périodes (mock-up 05).
 * L'année couvre du début de la période 1 à la fin de la période 2.
 *
 * @param {object} props
 * @param {boolean} props.isOpen
 * @param {() => void} props.onClose
 * @param {(annee: object) => void} props.onCreated appelée avec l'année créée
 * @param {boolean} props.peutImporterFwb l'utilisateur peut déclencher l'import FWB (`can.import_fwb` de l'API)
 */
export default function AnneeScolaireModal({ isOpen, onClose, onCreated, peutImporterFwb }) {
  const toast = useToast();
  const [form, setForm] = useState(FORM_VIDE);
  const [erreurs, setErreurs] = useState({});
  const [messageErreur, setMessageErreur] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  function maj(champ, valeur) {
    setForm((prev) => ({ ...prev, [champ]: valeur }));
    setErreurs((prev) => ({ ...prev, [champ]: undefined }));
  }

  function fermer() {
    setForm(FORM_VIDE);
    setErreurs({});
    setMessageErreur(null);
    onClose();
  }

  async function soumettre(e) {
    e.preventDefault();
    setEnvoi(true);
    setMessageErreur(null);
    setErreurs({});
    try {
      const annee = await creerAnneeScolaire({
        libelle: form.libelle.trim(),
        date_debut: form.p1_debut,
        date_fin: form.p2_fin,
        periodes: [
          { numero: 1, date_debut: form.p1_debut, date_fin: form.p1_fin },
          { numero: 2, date_debut: form.p2_debut, date_fin: form.p2_fin },
        ],
      });
      let message = `Année ${annee.libelle} créée avec ses 2 périodes.`;
      if (peutImporterFwb && form.importer_fwb) {
        try {
          const resultat = await importerCalendrierFwb(annee.id);
          message += ` Calendrier FWB importé : ${resultat.creees} entrée(s).`;
        } catch (errImport) {
          toast.error(`Année ${annee.libelle} créée, mais l'import FWB a échoué : ${getErrorMessage(errImport)}`);
          message = null;
        }
      }
      if (message) toast.success(message);
      setForm(FORM_VIDE);
      onCreated(annee);
    } catch (err) {
      const champs = getFieldErrors(err);
      setErreurs({
        libelle: champs.libelle,
        p1_debut: champs['periodes.0.date_debut'] || champs.date_debut,
        p1_fin: champs['periodes.0.date_fin'],
        p2_debut: champs['periodes.1.date_debut'],
        p2_fin: champs['periodes.1.date_fin'] || champs.date_fin,
      });
      setMessageErreur(getErrorMessage(err, "L'année n'a pas pu être créée."));
    } finally {
      setEnvoi(false);
    }
  }

  const complet = form.libelle.trim() && form.p1_debut && form.p1_fin && form.p2_debut && form.p2_fin;
  const libelleAnnee = form.libelle.trim();

  return (
    <AdminModal
      isOpen={isOpen}
      title="Créer une année scolaire"
      onClose={fermer}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={fermer}>
            Annuler
          </AdminButton>
          <AdminButton type="submit" form="form-annee-scolaire" disabled={!complet || envoi} loading={envoi}>
            {libelleAnnee ? `Créer l'année ${libelleAnnee}` : "Créer l'année"}
          </AdminButton>
        </>
      }
    >
      <form id="form-annee-scolaire" onSubmit={soumettre} noValidate>
        {messageErreur && <Banner tone="error">{messageErreur}</Banner>}
        <AdminFormField label="Libellé" htmlFor="annee-libelle" required error={erreurs.libelle}>
          <AdminInput
            id="annee-libelle"
            value={form.libelle}
            placeholder="2027-2028"
            error={erreurs.libelle}
            onChange={(e) => maj('libelle', e.target.value)}
          />
        </AdminFormField>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: ADMIN_SPACING.lg }}>
          <AdminFormField label="Période 1 : du" htmlFor="annee-p1-debut" required error={erreurs.p1_debut}>
            <AdminInput id="annee-p1-debut" type="date" value={form.p1_debut} error={erreurs.p1_debut} onChange={(e) => maj('p1_debut', e.target.value)} />
          </AdminFormField>
          <AdminFormField label="au" htmlFor="annee-p1-fin" required error={erreurs.p1_fin}>
            <AdminInput id="annee-p1-fin" type="date" value={form.p1_fin} error={erreurs.p1_fin} onChange={(e) => maj('p1_fin', e.target.value)} />
          </AdminFormField>
          <AdminFormField label="Période 2 : du" htmlFor="annee-p2-debut" required error={erreurs.p2_debut}>
            <AdminInput id="annee-p2-debut" type="date" value={form.p2_debut} error={erreurs.p2_debut} onChange={(e) => maj('p2_debut', e.target.value)} />
          </AdminFormField>
          <AdminFormField label="au" htmlFor="annee-p2-fin" required error={erreurs.p2_fin}>
            <AdminInput id="annee-p2-fin" type="date" value={form.p2_fin} error={erreurs.p2_fin} onChange={(e) => maj('p2_fin', e.target.value)} />
          </AdminFormField>
        </div>
        <p style={{ margin: `0 0 ${ADMIN_SPACING.lg}`, fontSize: '13px' }}>
          La période 2 doit commencer après la fin de la période 1. L'année s'étend du début de la période 1 à la fin de la période 2.
        </p>

        {peutImporterFwb && (
          <AdminCheckbox
            id="annee-import-fwb"
            label={`Importer le calendrier FWB${libelleAnnee ? ` ${libelleAnnee}` : ''} (si le fichier est disponible)`}
            checked={form.importer_fwb}
            onChange={(e) => maj('importer_fwb', e.target.checked)}
          />
        )}
      </form>
    </AdminModal>
  );
}
