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

  async function handleReactiver() {
    setErreurAction('');
    setLienRepli(null);
    try {
      const response = await reactiverStaff(selectedStaff.id);
      setSelectedStaff(response.data.data);
      annoncerEnvoi(selectedStaff.name, selectedStaff.email, response.data,
        `${selectedStaff.name} réactivé. Invitation envoyée à ${selectedStaff.email} (l'ancien mot de passe n'est plus valable).`);
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
      <Table>
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
              <Td>{s.name}</Td>
              <Td>{s.email}</Td>
              <Td>{libelleRole(s.role)}</Td>
              <Td>
                <StatutBadge table={STATUTS_STAFF} valeur={s.statut} />
              </Td>
              <Td>
                {s.statut === 'actif' && s.acces?.statut ? (
                  <StatutBadge table={STATUTS_ACCES} valeur={s.acces.statut} />
                ) : (
                  <span aria-label="Non applicable">—</span>
                )}
              </Td>
              <Td>
                <AdminButton onClick={() => ouvrirDetail(s)} small>
                  Modifier
                </AdminButton>
                {s.statut === 'actif' && ['invitation_expiree', 'invitation_non_envoyee'].includes(s.acces?.statut) && (
                  <AdminButton onClick={() => relancer(s)} disabled={envoiEnCoursId === s.id} small variant="secondary" style={{ marginLeft: '6px' }}>
                    {envoiEnCoursId === s.id ? 'Envoi…' : 'Renvoyer'}
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
        <div role="alert" style={{ padding: '12px', marginBottom: '16px', backgroundColor: '#ffcdd2', color: '#c62828', borderRadius: '4px' }}>
          {erreurAction}
          {lienRepli && <LienCopiable lien={lienRepli.lien} />}
          <button
            onClick={() => setErreurAction('')}
            aria-label="Fermer le message"
            style={{ float: 'right', background: 'none', border: 'none', color: '#c62828', cursor: 'pointer', fontSize: '18px' }}
          >
            ×
          </button>
        </div>
      )}
      {successMessage && (
        <div role="status" style={{ padding: '12px', marginBottom: '16px', backgroundColor: '#d4edda', color: '#155724', borderRadius: '4px' }}>
          {successMessage}
          <button
            onClick={() => setSuccessMessage('')}
            aria-label="Fermer le message"
            style={{ float: 'right', background: 'none', border: 'none', color: '#155724', cursor: 'pointer', fontSize: '18px' }}
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
            {!staff.loading && aRelancer > 0 && valeurs.statut !== 'inactif' && valeurs.acces !== 'relancer' && (
              <p style={{ margin: '0 0 12px', fontSize: '14px' }}>
                {aRelancer} invitation{aRelancer > 1 ? 's' : ''} à relancer.{' '}
                <button
                  type="button"
                  onClick={() => majFiltre('acces', 'relancer')}
                  style={{ background: 'none', border: 'none', padding: 0, color: '#1d4ed8', textDecoration: 'underline', cursor: 'pointer', fontWeight: 400 }}
                >
                  Les afficher
                </button>
              </p>
            )}
            <div aria-busy={staff.loading}>{liste}</div>
          </>
        )}
      </AdminPageContent>

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
