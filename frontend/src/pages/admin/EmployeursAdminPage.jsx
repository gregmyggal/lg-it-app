import { useState } from 'react';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import AdminModal from '../../components/AdminModal';
import { AdminCheckbox, AdminFormField, AdminInput, AdminSelect } from '../../components/AdminFormField';
import Banner from '../../components/ui/Banner';
import EmployeurBadge from '../../components/employeurs/EmployeurBadge';
import { Table, Th, Td, Tr } from '../../components/ui/Table';
import { LoadingBlock, ErrorBlock } from '../../components/ui/DataStates';
import { creerEmployeur, modifierEmployeur, useEmployeurs } from '../../hooks/useEmployeurs';
import { useAuth } from '../../auth/AuthContext';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage, getFieldErrors } from '../../api/errors';
import { TON_EMPLOYEUR } from '../../utils/employeurs';

const VIDE = { code: '', nom: '', rpm: '', compte_bancaire: '', adresse: '', par_defaut: false, actif: true, couleur_badge: 'gris' };
const A_COMPLETER = 'À COMPLÉTER';
const sansRemplacement = (v) => (String(v || '').includes(A_COMPLETER) ? '' : v || '');
const COULEURS = Object.keys(TON_EMPLOYEUR).map((c) => ({ value: c, label: c.charAt(0).toUpperCase() + c.slice(1) }));

/** Création / modification d'une entité (admin). Le serveur valide RPM, IBAN et unicité ; les erreurs s'affichent sous le champ. */
function EntiteModal({ entite, onClose, onDone }) {
  const creation = !entite;
  const [form, setForm] = useState(entite ? { ...VIDE, ...entite, rpm: sansRemplacement(entite.rpm), adresse: sansRemplacement(entite.adresse), compte_bancaire: entite.compte_bancaire || '' } : VIDE);
  const [envoi, setEnvoi] = useState(false);
  const [erreurs, setErreurs] = useState({});
  const [erreurGlobale, setErreurGlobale] = useState(null);
  const maj = (champ) => (e) => setForm((f) => ({ ...f, [champ]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }));

  async function enregistrer() {
    setEnvoi(true);
    setErreurs({});
    setErreurGlobale(null);
    try {
      const payload = {
        nom: form.nom, rpm: form.rpm, compte_bancaire: form.compte_bancaire || null, adresse: form.adresse,
        par_defaut: form.par_defaut, actif: form.actif, couleur_badge: form.couleur_badge,
      };
      if (creation) await creerEmployeur({ ...payload, code: form.code });
      else await modifierEmployeur(entite.id, payload);
      onDone(creation ? `Entité « ${form.nom} » créée.` : `Entité « ${form.nom} » mise à jour. Les PDF déjà générés ne changent pas.`);
    } catch (err) {
      setErreurs(getFieldErrors(err));
      setErreurGlobale(getErrorMessage(err, 'Enregistrement impossible'));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title={creation ? 'Ajouter une entité' : `Modifier « ${entite.nom} »`}
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton loading={envoi} onClick={enregistrer}>Enregistrer</AdminButton>
        </>
      }
    >
      {erreurGlobale && <Banner tone="error" role="alert">{erreurGlobale}</Banner>}
      {creation && (
        <AdminFormField label="Code" htmlFor="emp-code" error={erreurs.code} description="Identifiant technique, minuscules et _ (ex. mon_entite). Non modifiable ensuite.">
          <AdminInput id="emp-code" value={form.code} onChange={maj('code')} />
        </AdminFormField>
      )}
      <AdminFormField label="Nom" htmlFor="emp-nom" error={erreurs.nom} required>
        <AdminInput id="emp-nom" value={form.nom} onChange={maj('nom')} />
      </AdminFormField>
      <AdminFormField label="Numéro RPM" htmlFor="emp-rpm" error={erreurs.rpm} required description="Format BE0123.456.789">
        <AdminInput id="emp-rpm" value={form.rpm} onChange={maj('rpm')} placeholder="À COMPLÉTER" />
      </AdminFormField>
      <AdminFormField label="Compte bancaire (IBAN)" htmlFor="emp-compte" error={erreurs.compte_bancaire} required={creation} description="Chiffré en base. Imprimé sur les fiches de cette entité.">
        <AdminInput id="emp-compte" value={form.compte_bancaire} onChange={maj('compte_bancaire')} />
      </AdminFormField>
      <AdminFormField label="Adresse" htmlFor="emp-adresse" error={erreurs.adresse} required>
        <AdminInput id="emp-adresse" value={form.adresse} onChange={maj('adresse')} placeholder="À COMPLÉTER" />
      </AdminFormField>
      <AdminFormField label="Couleur du badge" htmlFor="emp-couleur" error={erreurs.couleur_badge}>
        <AdminSelect id="emp-couleur" value={form.couleur_badge || 'gris'} options={COULEURS} onChange={maj('couleur_badge')} />
      </AdminFormField>
      <AdminFormField error={erreurs.par_defaut}>
        <AdminCheckbox id="emp-defaut" label="Entité par défaut (appliquée quand aucun mois antérieur n’est défini)" checked={form.par_defaut} onChange={maj('par_defaut')} />
      </AdminFormField>
      <AdminFormField error={erreurs.actif}>
        <AdminCheckbox id="emp-actif" label="Active (sélectionnable pour un mois)" checked={form.actif} onChange={maj('actif')} />
      </AdminFormField>
    </AdminModal>
  );
}

/** Entités employeurs (EMP-01 T3) : lecture pour le staff, gestion réservée à l'admin. */
export default function EmployeursAdminPage() {
  const toast = useToast();
  const { user } = useAuth();
  const entites = useEmployeurs();
  const [edition, setEdition] = useState(undefined); // undefined = fermé, null = création, objet = modification
  const estAdmin = user?.role === 'admin';

  function apres(message) {
    setEdition(undefined);
    toast.success(message);
    entites.reload();
  }

  return (
    <>
      <AdminPageHeader
        icon="🏢"
        title="Entités employeurs"
        description="ASBL, L-IT Solutions… : l’entité qui emploie un animateur est choisie mois par mois."
        action={estAdmin ? <AdminButton onClick={() => setEdition(null)}>Ajouter</AdminButton> : undefined}
      />
      <AdminPageContent>
        <Banner tone="info">
          {estAdmin ? 'Administrateur uniquement. ' : 'Lecture seule : seul un administrateur modifie les entités. '}
          Une entité utilisée ne se supprime pas, elle se désactive. Modifier des coordonnées n’altère pas les PDF déjà générés (elles y sont figées).
        </Banner>
        {entites.loading && !entites.data && <LoadingBlock message="Chargement des entités…" />}
        {entites.error && <ErrorBlock message="Impossible de charger les employeurs." onRetry={entites.reload} />}
        {entites.data && (
          <Table caption="Entités employeurs" minWidth="860px">
            <thead>
              <tr><Th>Nom</Th><Th>RPM</Th><Th>Compte</Th><Th>Adresse</Th><Th>Mois liés</Th><Th>Statut</Th><Th srOnly>Actions</Th></tr>
            </thead>
            <tbody>
              {entites.data.map((e) => (
                <Tr key={e.id}>
                  <Td><EmployeurBadge employeur={e} source="explicite" />{e.par_defaut && <span style={{ marginLeft: 6, fontSize: 12 }}>défaut</span>}</Td>
                  <Td>{e.rpm}</Td>
                  <Td>{e.compte_bancaire || 'À COMPLÉTER'}</Td>
                  <Td>{e.adresse}</Td>
                  <Td>{e.mois_lies ?? 0}</Td>
                  <Td>
                    {!e.actif ? 'Désactivée' : 'Active'}
                    {!e.coordonnees_completes && <div style={{ fontSize: 12, color: 'var(--tone-warning-fg)' }}>⚠ Coordonnées à compléter : fiches PDF bloquées</div>}
                  </Td>
                  <Td>{e.can?.update && <AdminButton variant="secondary" size="sm" onClick={() => setEdition(e)}>Modifier</AdminButton>}</Td>
                </Tr>
              ))}
            </tbody>
          </Table>
        )}
        {edition !== undefined && <EntiteModal entite={edition} onClose={() => setEdition(undefined)} onDone={apres} />}
      </AdminPageContent>
    </>
  );
}
