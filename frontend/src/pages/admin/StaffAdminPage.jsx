import { useState } from 'react';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import StatutBadge from '../../components/ui/StatutBadge';
import { Table, Th, Td, Tr } from '../../components/ui/Table';
import { FilterBar, FilterField } from '../../components/ui/Filters';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../../components/ui/DataStates';
import { useStaff, useReactivateStaff } from '../../hooks/useStaff';
import StaffCreateModal from '../../components/staff/StaffCreateModal';
import StaffDetailModal from '../../components/staff/StaffDetailModal';
import StaffDeactivateModal from '../../components/staff/StaffDeactivateModal';
import StaffEditEmailModal from '../../components/staff/StaffEditEmailModal';
import { STATUTS_STAFF, libelleRole } from '../../utils/statuts';

export default function StaffAdminPage() {
  const [filtres, setFiltres] = useState({ statut: '', role: '' });
  const [selectedStaff, setSelectedStaff] = useState(null);
  const [modaleCreation, setModaleCreation] = useState(false);
  const [modaleDetail, setModaleDetail] = useState(false);
  const [modaleDesactivation, setModaleDesactivation] = useState(false);
  const [modaleEditEmail, setModaleEditEmail] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [erreurAction, setErreurAction] = useState('');

  const staff = useStaff(filtres);
  const { reactivate: reactiverStaff } = useReactivateStaff();

  function majFiltre(cle, valeur) {
    setFiltres((prev) => ({ ...prev, [cle]: valeur }));
  }

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

  async function handleReactiver() {
    try {
      const response = await reactiverStaff(selectedStaff.id);
      setSelectedStaff({ ...selectedStaff, statut: 'actif', date_sortie: null });
      setSuccessMessage(`${selectedStaff.name} réactivé. Nouveau mot de passe provisoire : ${response.data.password}`);
      staff.reload();
    } catch (err) {
      setSuccessMessage('');
      setErreurAction(err.response?.data?.message || 'Erreur lors de la réactivation');
    }
  }

  function ouvrirDetail(s) {
    setErreurAction('');
    setSelectedStaff(s);
    setModaleDetail(true);
  }

  let contenu;
  if (staff.loading) {
    contenu = <LoadingBlock message="Chargement du staff…" />;
  } else if (staff.error) {
    contenu = <ErrorBlock message="Impossible de charger le staff." onRetry={staff.reload} />;
  } else if (staff.data.length === 0) {
    contenu = (
      <EmptyBlock icon="👥" title="Aucun staff">
        Créez un directeur ou un administrateur pour commencer.
      </EmptyBlock>
    );
  } else {
    contenu = (
      <>
        {erreurAction && (
          <div role="alert" style={{ padding: '12px', marginBottom: '16px', backgroundColor: '#ffcdd2', color: '#c62828', borderRadius: '4px' }}>
            {erreurAction}
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
          <div style={{ padding: '12px', marginBottom: '16px', backgroundColor: '#d4edda', color: '#155724', borderRadius: '4px' }}>
            {successMessage}
            <button
              onClick={() => setSuccessMessage('')}
              style={{
                float: 'right',
                background: 'none',
                border: 'none',
                color: '#155724',
                cursor: 'pointer',
                fontSize: '18px',
              }}
            >
              ×
            </button>
          </div>
        )}
        <FilterBar>
          <FilterField
            label="Statut"
            value={filtres.statut}
            onChange={(value) => majFiltre('statut', value)}
            options={[
              { value: 'actif', label: 'Actifs' },
              { value: 'inactif', label: 'Désactivés' },
            ]}
            placeholder="Tous"
          />
          <FilterField
            label="Rôle"
            value={filtres.role}
            onChange={(value) => majFiltre('role', value)}
            options={[
              { value: 'admin', label: 'Admin' },
              { value: 'directeur', label: 'Directeur' },
            ]}
            placeholder="Tous"
          />
        </FilterBar>
        <Table>
          <thead>
            <Tr>
              <Th>Nom</Th>
              <Th>Email</Th>
              <Th>Rôle</Th>
              <Th>Statut</Th>
              <Th>Actions</Th>
            </Tr>
          </thead>
          <tbody>
            {staff.data.map((s) => (
              <Tr key={s.id}>
                <Td>{s.name}</Td>
                <Td>{s.email}</Td>
                <Td>{libelleRole(s.role)}</Td>
                <Td>
                  <StatutBadge table={STATUTS_STAFF} valeur={s.statut} />
                </Td>
                <Td>
                  <AdminButton onClick={() => ouvrirDetail(s)} small>
                    Modifier
                  </AdminButton>
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      </>
    );
  }

  return (
    <>
      <AdminPageHeader
        title="Gestion du staff"
        action={<AdminButton onClick={() => setModaleCreation(true)}>＋ Créer</AdminButton>}
      />
      <AdminPageContent>{contenu}</AdminPageContent>

      {modaleCreation && <StaffCreateModal onClose={() => setModaleCreation(false)} onCreated={handleStaffCree} />}
      {modaleDetail && selectedStaff && (
        <StaffDetailModal
          staff={selectedStaff}
          onClose={() => setModaleDetail(false)}
          onDesactiver={() => setModaleDesactivation(true)}
          onReactiver={handleReactiver}
          onEditEmail={() => setModaleEditEmail(true)}
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
