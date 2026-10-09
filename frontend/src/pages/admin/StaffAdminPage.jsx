import { useMemo, useState } from 'react';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import StatutBadge from '../../components/ui/StatutBadge';
import { Table, Th, Td, Tr } from '../../components/ui/Table';
import { FilterToolbar, ResultCount } from '../../components/ui/Filters';
import { useFiltresListe } from '../../hooks/useFiltresListe';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../../components/ui/DataStates';
import { useStaff, useReactivateStaff, useSendStaffLink } from '../../hooks/useStaff';
import LienCopiable from '../../components/ui/LienCopiable';
import { getErrorMessage } from '../../api/errors';
import StaffCreateModal from '../../components/staff/StaffCreateModal';
import StaffDetailModal from '../../components/staff/StaffDetailModal';
import StaffDeactivateModal from '../../components/staff/StaffDeactivateModal';
import StaffEditEmailModal from '../../components/staff/StaffEditEmailModal';
import { STATUTS_ACCES, STATUTS_STAFF, libelleRole } from '../../utils/statuts';
import { LOT_MAX, cleStatutAcces, eligibleLot, estAccesNonEnvoye, relanceRapide } from '../../utils/acces';
import EnvoiLotModal from '../../components/ui/EnvoiLotModal';
import {
  OPTIONS_ACCES, OPTIONS_STATUT, STATUT_PAR_DEFAUT, correspondAcces, correspondRecherche,
  libelleResultats, statutPourApi,
} from '../../utils/filtres';

const DEFAUTS = { q: '', statut: STATUT_PAR_DEFAUT, role: '', acces: '' };
const AUTORISEES = {
  statut: OPTIONS_STATUT.map((o) => o.value),
  role: ['admin', 'directeur'],
  acces: OPTIONS_ACCES.map((o) => o.value),
};
const ID_RECHERCHE = 'staff-recherche';

export default function StaffAdminPage() {
  const { valeurs, majFiltre, reinitialiser, nbActifs, estParDefaut } = useFiltresListe(DEFAUTS, AUTORISEES);
  const [selectedStaff, setSelectedStaff] = useState(null);
  const [modaleCreation, setModaleCreation] = useState(false);
  const [modaleDetail, setModaleDetail] = useState(false);
  const [modaleDesactivation, setModaleDesactivation] = useState(false);
  const [modaleEditEmail, setModaleEditEmail] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [erreurAction, setErreurAction] = useState('');
  const [lienRepli, setLienRepli] = useState(null); // { nom, lien } quand un email n'a pas pu partir

  // Statut et rôle sont filtrés par le serveur ; recherche et accès côté client (invisible pour l'utilisateur).
  const filtresServeur = useMemo(
    () => ({ statut: statutPourApi(valeurs.statut), role: valeurs.role }),
    [valeurs.statut, valeurs.role],
  );
  const staff = useStaff(filtresServeur);
  const { reactivate: reactiverStaff } = useReactivateStaff();
  const { send: envoyerLien } = useSendStaffLink();
  const [envoiEnCoursId, setEnvoiEnCoursId] = useState(null);
  const [selection, setSelection] = useState([]);
  const [showLot, setShowLot] = useState(false);

  function handleStaffCree(created) {
    setSuccessMessage(`${created.role === 'admin' ? 'Administrateur' : 'Directeur'} « ${created.name} » créé.`);
    staff.reload();
  }

  // L'appel API est fait par StaffDeactivateModal ; ici uniquement la suite de l'écran.
  function handleStaffDesactive() {
    setModaleDesactivation(false);
    setModaleDetail(false);
    setSuccessMessage(`${selectedStaff.name} a été désactivé. Ses sessions ont été révoquées.`);
    setSelectedStaff(null);
    staff.reload();
  }

  function annoncerEnvoi(nom, email, { email_envoye: emailEnvoye, lien }, messageOk) {
    if (emailEnvoye) {
      setLienRepli(null);
      setSuccessMessage(messageOk);
    } else {
      setSuccessMessage('');
      setErreurAction(`L'email destiné à ${email} n'a pas pu être envoyé. Réessayez, ou transmettez le lien ci-dessous.`);
      setLienRepli({ nom, lien });
    }
  }

  async function handleReactiver({ envoyerInvitation = true } = {}) {
    setErreurAction('');
    setLienRepli(null);
    try {
      const response = await reactiverStaff(selectedStaff.id, { envoyer_invitation: envoyerInvitation });
      setSelectedStaff(response.data.data);
      if (!envoyerInvitation) {
        setSuccessMessage(`${selectedStaff.name} réactivé. Aucun email envoyé : accès non envoyé (l'ancien mot de passe n'est plus valable).`);
      } else {
        annoncerEnvoi(selectedStaff.name, selectedStaff.email, response.data,
          `${selectedStaff.name} réactivé. Invitation envoyée à ${selectedStaff.email} (l'ancien mot de passe n'est plus valable).`);
      }
      staff.reload();
    } catch (err) {
      setSuccessMessage('');
      setErreurAction(getErrorMessage(err, 'Erreur lors de la réactivation'));
    }
  }

  // Relance rapide depuis la liste (invitation expirée ou non envoyée).
  async function relancer(s) {
    setErreurAction('');
    setLienRepli(null);
    setEnvoiEnCoursId(s.id);
    try {
      const response = await envoyerLien(s.id);
      annoncerEnvoi(s.name, s.email, response.data, `Invitation envoyée à ${s.email}.`);
      staff.reload();
    } catch (err) {
      setSuccessMessage('');
      setErreurAction(getErrorMessage(err));
    } finally {
      setEnvoiEnCoursId(null);
    }
  }

  function handleStaffMisAJour(maj) {
    setSelectedStaff((prev) => ({ ...prev, ...maj }));
    staff.reload();
  }

  function ouvrirDetail(s) {
    setErreurAction('');
    setSelectedStaff(s);
    setModaleDetail(true);
  }

  const lignes = staff.data.filter((s) => correspondAcces(s.acces, valeurs.acces)
    && correspondRecherche([s.name, s.email], valeurs.q));
  const aRelancer = staff.data.filter((s) => s.statut === 'actif' && s.acces?.a_relancer).length;
  const nonEnvoyes = staff.data.filter((s) => s.statut === 'actif' && estAccesNonEnvoye(s.acces)).length;
  const eligiblesAffiches = lignes.filter((s) => s.statut === 'actif' && eligibleLot(s.acces));
  const comptesLot = staff.data
    .filter((s) => selection.includes(s.id) && s.statut === 'actif' && eligibleLot(s.acces))
    .map((s) => ({ id: s.id, nom: s.name, email: s.email }));
  const basculer = (id) => setSelection((sel) => (sel.includes(id) ? sel.filter((i) => i !== id) : sel.length >= LOT_MAX ? sel : [...sel, id]));
  const listeReellementVide = !staff.loading && !staff.error && staff.data.length === 0 && estParDefaut;

  const filtresBarre = [
    { id: 'staff-statut', label: 'Statut', value: valeurs.statut, onChange: (v) => majFiltre('statut', v), options: OPTIONS_STATUT },
    {
      id: 'staff-role',
      label: 'Rôle',
      value: valeurs.role,
      onChange: (v) => majFiltre('role', v),
      options: [{ value: 'admin', label: 'Admin' }, { value: 'directeur', label: 'Directeur' }],
      placeholder: 'Tous',
    },
    { id: 'staff-acces', label: 'Accès', value: valeurs.acces, onChange: (v) => majFiltre('acces', v), options: OPTIONS_ACCES, placeholder: 'Tous' },
  ];

  function reinitialiserEtFocaliser() {
    reinitialiser();
    document.getElementById(ID_RECHERCHE)?.focus();
  }

  let liste;
  if (staff.loading) {
    liste = <LoadingBlock message="Chargement du staff…" />;
  } else if (staff.error) {
    liste = <ErrorBlock message="Impossible de charger le staff." onRetry={staff.reload} />;
  } else if (lignes.length === 0) {
    liste = (
      <EmptyBlock
        icon="🔎"
        title="Aucun compte ne correspond à ces filtres"
        actions={(
          <>
            <AdminButton variant="primary" onClick={reinitialiserEtFocaliser}>Réinitialiser les filtres</AdminButton>
            {valeurs.statut === STATUT_PAR_DEFAUT && (
              <AdminButton variant="secondary" onClick={() => majFiltre('statut', 'tous')}>Inclure les désactivés</AdminButton>
            )}
          </>
        )}
      >
        Modifiez la recherche ou les filtres pour afficher des comptes.
      </EmptyBlock>
    );
  } else {
    liste = (
      <Table cards>
        <thead>
          <Tr>
            <Th>Nom</Th>
            <Th>Email</Th>
            <Th>Rôle</Th>
            <Th>Statut</Th>
            <Th>Accès</Th>
            <Th>Actions</Th>
          </Tr>
        </thead>
        <tbody>
          {lignes.map((s) => (
            <Tr key={s.id}>
              <Td label="Nom">{s.name}</Td>
              <Td label="Email">{s.email}</Td>
              <Td label="Rôle">{libelleRole(s.role)}</Td>
              <Td label="Statut">
                <StatutBadge table={STATUTS_STAFF} valeur={s.statut} />
              </Td>
              <Td label="Accès">
                {s.statut === 'actif' && s.acces?.statut ? (
                  <>
                    {eligibleLot(s.acces) && (
                      <input
                        type="checkbox"
                        aria-label={`Sélectionner ${s.name} pour l'envoi en lot`}
                        checked={selection.includes(s.id)}
                        onChange={() => basculer(s.id)}
                        style={{ marginRight: '8px' }}
                      />
                    )}
                    <StatutBadge table={STATUTS_ACCES} valeur={cleStatutAcces(s.acces)} />
                  </>
                ) : (
                  <span aria-label="Non applicable">—</span>
                )}
              </Td>
              <Td label="Actions">
                <AdminButton onClick={() => ouvrirDetail(s)} small>
                  Modifier
                </AdminButton>
                {s.statut === 'actif' && (relanceRapide(s.acces) || estAccesNonEnvoye(s.acces)) && (
                  <AdminButton onClick={() => relancer(s)} disabled={envoiEnCoursId === s.id} small variant="secondary" style={{ marginLeft: '6px' }}>
                    {envoiEnCoursId === s.id ? 'Envoi…' : (estAccesNonEnvoye(s.acces) ? 'Envoyer l’invitation' : 'Renvoyer')}
                  </AdminButton>
                )}
              </Td>
            </Tr>
          ))}
        </tbody>
      </Table>
    );
  }

  const bandeaux = (
    <>
      {erreurAction && (
        <div role="alert" style={{ padding: '12px', marginBottom: '16px', backgroundColor: 'var(--tone-error-bg)', color: 'var(--tone-error-fg)', borderRadius: '4px' }}>
          {erreurAction}
          {lienRepli && <LienCopiable lien={lienRepli.lien} />}
          <button
            onClick={() => setErreurAction('')}
            aria-label="Fermer le message"
            style={{ float: 'right', background: 'none', border: 'none', color: 'var(--tone-error-fg)', cursor: 'pointer', fontSize: '18px' }}
          >
            ×
          </button>
        </div>
      )}
      {successMessage && (
        <div role="status" style={{ padding: '12px', marginBottom: '16px', backgroundColor: 'var(--tone-success-bg)', color: 'var(--tone-success-fg)', borderRadius: '4px' }}>
          {successMessage}
          <button
            onClick={() => setSuccessMessage('')}
            aria-label="Fermer le message"
            style={{ float: 'right', background: 'none', border: 'none', color: 'var(--tone-success-fg)', cursor: 'pointer', fontSize: '18px' }}
          >
            ×
          </button>
        </div>
      )}
    </>
  );

  return (
    <>
      <AdminPageHeader
        title="Gestion du staff"
        action={<AdminButton onClick={() => setModaleCreation(true)}>＋ Créer</AdminButton>}
      />
      <AdminPageContent>
        {bandeaux}
        {listeReellementVide ? (
          <EmptyBlock icon="👥" title="Aucun staff">
            Créez un directeur ou un administrateur pour commencer.
          </EmptyBlock>
        ) : (
          <>
            <FilterToolbar
              label="Filtres du staff"
              recherche={{ id: ID_RECHERCHE, value: valeurs.q, onChange: (v) => majFiltre('q', v), placeholder: 'Nom ou email' }}
              filtres={filtresBarre}
              nbActifs={nbActifs}
              onReset={reinitialiser}
              resetDisabled={estParDefaut}
            />
            {!staff.loading && !staff.error && (
              <ResultCount>{libelleResultats(lignes.length, staff.data.length, ['compte', 'comptes'])}</ResultCount>
            )}
            {!staff.loading && nonEnvoyes > 0 && valeurs.statut !== 'inactif' && valeurs.acces !== 'non_envoye' && (
              <p style={{ margin: '0 0 12px', fontSize: '14px' }}>
                {nonEnvoyes} accès non envoyé{nonEnvoyes > 1 ? 's' : ''}.{' '}
                <button
                  type="button"
                  onClick={() => majFiltre('acces', 'non_envoye')}
                  style={{ background: 'none', border: 'none', padding: 0, color: 'var(--tone-primary-fg)', textDecoration: 'underline', cursor: 'pointer', fontWeight: 400 }}
                >
                  Les afficher
                </button>
              </p>
            )}
            {!staff.loading && eligiblesAffiches.length > 0 && (
              <div role="region" aria-label="Envoi en lot des invitations" style={{ display: 'flex', gap: '12px', alignItems: 'center', flexWrap: 'wrap', margin: '0 0 12px', fontSize: '14px' }}>
                <AdminButton small variant="secondary" onClick={() => setSelection(eligiblesAffiches.slice(0, LOT_MAX).map((s) => s.id))}>
                  Tout sélectionner ({Math.min(eligiblesAffiches.length, LOT_MAX)})
                </AdminButton>
                {selection.length > 0 && (
                  <>
                    <span role="status">{selection.length} sélectionné{selection.length > 1 ? 's' : ''}</span>
                    <AdminButton small onClick={() => setShowLot(true)} disabled={comptesLot.length === 0}>
                      Envoyer {comptesLot.length} invitation{comptesLot.length > 1 ? 's' : ''}…
                    </AdminButton>
                    <AdminButton small variant="secondary" onClick={() => setSelection([])}>Désélectionner</AdminButton>
                  </>
                )}
              </div>
            )}
            {!staff.loading && aRelancer > 0 && valeurs.statut !== 'inactif' && valeurs.acces !== 'relancer' && (
              <p style={{ margin: '0 0 12px', fontSize: '14px' }}>
                {aRelancer} invitation{aRelancer > 1 ? 's' : ''} à relancer.{' '}
                <button
                  type="button"
                  onClick={() => majFiltre('acces', 'relancer')}
                  style={{ background: 'none', border: 'none', padding: 0, color: 'var(--tone-primary-fg)', textDecoration: 'underline', cursor: 'pointer', fontWeight: 400 }}
                >
                  Les afficher
                </button>
              </p>
            )}
            <div aria-busy={staff.loading}>{liste}</div>
          </>
        )}
      </AdminPageContent>

      {showLot && (
        <EnvoiLotModal
          comptes={comptesLot}
          endpoint="/staff/envoyer-invitations"
          onClose={() => { setShowLot(false); setSelection([]); }}
          onDone={() => staff.reload()}
        />
      )}
      {modaleCreation && <StaffCreateModal onClose={() => setModaleCreation(false)} onCreated={handleStaffCree} />}
      {modaleDetail && selectedStaff && (
        <StaffDetailModal
          staff={selectedStaff}
          onClose={() => setModaleDetail(false)}
          onDesactiver={() => setModaleDesactivation(true)}
          onReactiver={handleReactiver}
          onEditEmail={() => setModaleEditEmail(true)}
          onChanged={handleStaffMisAJour}
        />
      )}
      {modaleDesactivation && selectedStaff && (
        <StaffDeactivateModal
          staff={selectedStaff}
          onClose={() => setModaleDesactivation(false)}
          onConfirm={handleStaffDesactive}
        />
      )}
      {modaleEditEmail && selectedStaff && (
        <StaffEditEmailModal
          staff={selectedStaff}
          onClose={() => setModaleEditEmail(false)}
          onSubmit={(updatedStaff) => {
            setSelectedStaff(updatedStaff);
            setModaleEditEmail(false);
            setSuccessMessage('Email modifié. Les sessions en cours de ce compte ont été révoquées.');
            staff.reload();
          }}
        />
      )}
    </>
  );
}
