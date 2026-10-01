import { useEffect, useState } from 'react';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import StatutBadge from '../../components/ui/StatutBadge';
import { Table, Th, Td, Tr } from '../../components/ui/Table';
import { FilterBar, FilterField } from '../../components/ui/Filters';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../../components/ui/DataStates';
import { useStaff, useCreateStaff, useDeactivateStaff, useReactivateStaff } from '../../hooks/useStaff';
import StaffCreateModal from '../../components/staff/StaffCreateModal';
import StaffDetailModal from '../../components/staff/StaffDetailModal';
import StaffDeactivateModal from '../../components/staff/StaffDeactivateModal';
import { STATUTS_STAFF, libelleRole } from '../../utils/statuts';
import { formatDateCourte } from '../../utils/dates';

const LIBELLESTATUT = {
  actif: { label: 'Actif', color: 'green' },
  inactif: { label: 'Désactivé', color: 'gray' },
};

export default function StaffAdminPage() {
  const [filtres, setFiltres] = useState({ statut: '' });
  const [selectedStaff, setSelectedStaff] = useState(null);
  const [modaleCreation, setModaleCreation] = useState(false);
  const [modaleDetail, setModaleDetail] = useState(false);
  const [modaleDesactivation, setModaleDesactivation] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');

  const staff = useStaff(filtres);
  const { create: creerStaff } = useCreateStaff();
  const { deactivate: desactiverStaff } = useDeactivateStaff();
  const { reactivate: reactiverStaff } = useReactivateStaff();

  function majFiltre(cle, valeur) {
    setFiltres((prev) => ({ ...prev, [cle]: valeur }));
  }

  async function handleCreerStaff(data) {
    try {
      const response = await creerStaff(data);
      setModaleCreation(false);
      setSuccessMessage(`${data.role === 'admin' ? 'Admin' : 'Directeur'} créé avec succès. Mot de passe provisoire: ${response.password}`);
      staff.reload();
    } catch (err) {
      // L'erreur est gérée dans la modale
    }
  }

  async function handleDesactiver() {
    try {
      await desactiverStaff(selectedStaff.id);
      setModaleDesactivation(false);
      setModaleDetail(false);
      setSelectedStaff(null);
      setSuccessMessage('Staff désactivé avec succès');
      staff.reload();
    } catch (err) {
      // L'erreur est gérée dans la modale
    }
  }

  async function handleReactiver() {
    try {
      const response = await reactiverStaff(selectedStaff.id);
      setSelectedStaff({ ...selectedStaff, statut: 'actif' });
      setSuccessMessage(`Réactivé. Nouveau mot de passe: ${response.password}`);
      staff.reload();
    } catch (err) {
      // L'erreur est gérée
    }
  }

  function ouvrirDetail(s) {
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
          <FilterField label="Statut">
            <select value={filtres.statut} onChange={(e) => majFiltre('statut', e.target.value)}>
              <option value="">Tous</option>
              <option value="actif">Actifs</option>
              <option value="inactif">Désactivés</option>
            </select>
          </FilterField>
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
                  <StatutBadge statut={s.statut} libelle={LIBELLESTATUT[s.statut]?.label} />
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
      <AdminPageHeader title="Gestion du staff">
        <AdminButton onClick={() => setModaleCreation(true)}>＋ Créer</AdminButton>
      </AdminPageHeader>
      <AdminPageContent>{contenu}</AdminPageContent>

      {modaleCreation && <StaffCreateModal onClose={() => setModaleCreation(false)} onSubmit={handleCreerStaff} />}
      {modaleDetail && selectedStaff && (
        <StaffDetailModal
          staff={selectedStaff}
          onClose={() => setModaleDetail(false)}
          onDesactiver={() => setModaleDesactivation(true)}
          onReactiver={handleReactiver}
        />
      )}
      {modaleDesactivation && selectedStaff && (
        <StaffDeactivateModal
          staff={selectedStaff}
          onClose={() => setModaleDesactivation(false)}
          onConfirm={handleDesactiver}
        />
      )}
    </>
  );
}
