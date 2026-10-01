import { useState } from 'react';
import AdminModal from '../AdminModal';
import AdminButton from '../AdminButton';
import { AdminFormField, AdminInput, AdminSelect } from '../AdminFormField';
import Banner from '../ui/Banner';
import { creerLien, modifierLien } from '../../hooks/useLiens';
import { getErrorData, getErrorMessage, getFieldErrors, getStatus } from '../../api/errors';
import { TYPES_LIEN } from '../../utils/statuts';
import ConflitLienModal from './ConflitLienModal';

const SEANCES = Array.from({ length: 14 }, (_, i) => ({ value: String(i + 1), label: `Séance ${i + 1}` }));
const TYPES = Object.entries(TYPES_LIEN).map(([value, t]) => ({ value, label: `${t.icone} ${t.label}` }));

/**
 * Ajouter ou modifier un lien du cours (mock-up 01). La portée est toujours visible (« visible par toutes les classes
 * du cours ») ; une modification envoie la `version` ouverte : si un autre professeur a enregistré entre-temps, le
 * serveur répond 409 et on propose de reprendre sa version ou d'enregistrer la sienne — jamais d'écrasement silencieux.
 *
 * @param {object} props
 * @param {number} props.coursId
 * @param {string} props.coursTitre
 * @param {number} props.nbClasses nombre de classes actives du cours (rappel de portée)
 * @param {object} [props.lien] lien à modifier (absent = création)
 * @param {number|null} [props.seanceInitiale] portée proposée à la création (null = général)
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone
 */
export default function LienFormModal({ coursId, coursTitre, nbClasses, lien, seanceInitiale = null, onClose, onDone }) {
  const edition = Boolean(lien);
  const [titre, setTitre] = useState(lien?.titre || '');
  const [url, setUrl] = useState(lien?.url || '');
  const [description, setDescription] = useState(lien?.description || '');
  const [type, setType] = useState(lien?.type || '');
  const [portee, setPortee] = useState((lien ? lien.seance_numero : seanceInitiale) === null ? 'general' : 'seance');
  const [seance, setSeance] = useState(String(Math.min((lien ? lien.seance_numero : seanceInitiale) || 1, 14)));
  const [versionOuverte, setVersionOuverte] = useState(lien?.version);
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const [conflit, setConflit] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  const valide = titre.trim() !== '' && /^https?:\/\//i.test(url.trim());

  const payload = () => ({
    titre: titre.trim(),
    url: url.trim(),
    description: description.trim() || null,
    type: type || null,
    seance_numero: portee === 'general' ? null : Number(seance),
  });

  async function enregistrer(version = versionOuverte) {
    setEnvoi(true);
    setMessage(null);
    setErreurs({});
    try {
      if (edition) {
        await modifierLien(lien.id, { ...payload(), version });
        onDone(`Lien « ${titre.trim()} » enregistré. Visible par ${nbClasses > 1 ? `les ${nbClasses} classes` : 'la classe'} du cours ; modification ajoutée à l'historique.`);
      } else {
        await creerLien(coursId, payload());
        onDone(`Lien « ${titre.trim()} » ajouté. Visible par ${nbClasses > 1 ? `les ${nbClasses} classes` : 'la classe'} de ${coursTitre} et par les élèves.`);
      }
    } catch (err) {
      if (getStatus(err) === 409 && getErrorData(err).lien) {
        setConflit(getErrorData(err).lien);
      } else {
        setErreurs(getFieldErrors(err));
        setMessage(getErrorMessage(err));
      }
    } finally {
      setEnvoi(false);
    }
  }

  if (conflit) {
    return (
      <ConflitLienModal
        lienServeur={conflit}
        brouillon={payload()}
        onReprendre={() => {
          // On reprend la version du serveur : on recharge la liste, rien n'est enregistré.
          onClose();
        }}
        onGarderLaMienne={() => {
          setConflit(null);
          setVersionOuverte(conflit.version);
          enregistrer(conflit.version);
        }}
        onClose={() => setConflit(null)}
      />
    );
  }

  return (
    <AdminModal
      isOpen
      title={edition ? `Modifier « ${lien.titre} »` : 'Ajouter un lien'}
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton type="submit" form="form-lien" disabled={!valide || envoi} loading={envoi}>
            {edition ? 'Enregistrer le lien' : 'Ajouter le lien'}
          </AdminButton>
        </>
      }
    >
      <form
        id="form-lien"
        noValidate
        onSubmit={(e) => {
          e.preventDefault();
          if (valide) enregistrer();
        }}
      >
        {message && (
          <Banner tone="error" role="alert">
            <strong>{message}</strong>
          </Banner>
        )}
        <Banner tone="info">
          Ce lien sera visible par <strong>toutes les classes</strong> de {coursTitre} ({nbClasses} classe{nbClasses > 1 ? 's' : ''}) et par les élèves via le code de partage. Chaque
          modification est enregistrée à votre nom et peut être annulée pendant 6 mois.
        </Banner>
        <AdminFormField label="Titre" htmlFor="lien-titre" required error={erreurs.titre || (titre === '' && url !== '' ? 'Le titre est obligatoire.' : null)}>
          <AdminInput id="lien-titre" value={titre} maxLength={255} onChange={(e) => setTitre(e.target.value)} />
        </AdminFormField>
        <AdminFormField
          label="Adresse du lien"
          htmlFor="lien-url"
          required
          error={erreurs.url || (url !== '' && !/^https?:\/\//i.test(url.trim()) ? 'Adresse invalide : elle doit commencer par https:// ou http://.' : null)}
        >
          <AdminInput id="lien-url" type="url" value={url} placeholder="https://…" onChange={(e) => setUrl(e.target.value)} />
        </AdminFormField>
        <AdminFormField label="Description (facultatif)" htmlFor="lien-desc" description="255 caractères maximum. Affichée sous le titre, aux professeurs et aux élèves." error={erreurs.description}>
          <AdminInput id="lien-desc" value={description} maxLength={255} onChange={(e) => setDescription(e.target.value)} />
        </AdminFormField>
        <AdminFormField label="Type (facultatif)" htmlFor="lien-type" error={erreurs.type}>
          <AdminSelect id="lien-type" value={type} placeholder="Aucun type" options={TYPES} onChange={(e) => setType(e.target.value)} />
        </AdminFormField>
        <fieldset style={{ border: 0, padding: 0, margin: 0 }}>
          <legend style={{ fontSize: '12px', fontWeight: 600, marginBottom: '6px' }}>Portée du lien</legend>
          <label style={{ display: 'flex', gap: '8px', alignItems: 'center', marginBottom: '6px' }}>
            <input type="radio" name="portee" checked={portee === 'general'} onChange={() => setPortee('general')} />
            Général (toutes les séances)
          </label>
          <label style={{ display: 'flex', gap: '8px', alignItems: 'center', marginBottom: '6px' }}>
            <input type="radio" name="portee" checked={portee === 'seance'} onChange={() => setPortee('seance')} />
            Séance
          </label>
          {portee === 'seance' && (
            <AdminFormField label="Numéro de séance" htmlFor="lien-seance" error={erreurs.seance_numero} description="Le numéro est fixe : le lien s'affiche aussi sur les sessions bis de cette séance.">
              <AdminSelect id="lien-seance" value={seance} options={SEANCES} onChange={(e) => setSeance(e.target.value)} />
            </AdminFormField>
          )}
        </fieldset>
      </form>
    </AdminModal>
  );
}
