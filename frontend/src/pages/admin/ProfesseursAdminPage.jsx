import { useEffect, useState } from 'react';
import client from '../../api/client';
import AdminModal from '../../components/AdminModal';
import AdminButton, { AdminIconButton } from '../../components/AdminButton';
import { AdminFormField, AdminInput, AdminTextarea, AdminSelect, AdminCheckboxGroup } from '../../components/AdminFormField';
import {
  AdminPageHeader,
  AdminPageContent,
  AdminCardGrid,
  AdminCard,
  AdminCardHeader,
  AdminCardBody,
  AdminCardFooter,
  AdminBadge,
} from '../../components/AdminPageLayout';
import { ADMIN_COLORS } from '../../styles/AdminDesignSystem';

const emptyCreateForm = {
  login_email: '',
  password: '',
  prenom: '',
  nom: '',
  email: '',
  telephone: '',
  statut: 'actif',
  date_entree: '',
  date_sortie: '',
  type_contrat: '',
  types_cours: [],
};

const emptyEditForm = {
  id: null,
  prenom: '',
  nom: '',
  email: '',
  telephone: '',
  statut: 'actif',
  date_entree: '',
  date_sortie: '',
  type_contrat: '',
  types_cours: [],
};

export default function ProfesseursAdminPage() {
  const [professeurs, setProfesseurs] = useState(null);
  const [typesCours, setTypesCours] = useState([]);
  const [form, setForm] = useState(emptyCreateForm);
  const [editingId, setEditingId] = useState(null);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    client.get('/professeurs').then((res) => setProfesseurs(res.data));
    client.get('/types-cours').then((res) => setTypesCours(res.data));
  }, []);

  function openCreateModal() {
    setForm(emptyCreateForm);
    setError(null);
    setIsCreateModalOpen(true);
  }

  function closeCreateModal() {
    setIsCreateModalOpen(false);
    setForm(emptyCreateForm);
    setError(null);
  }

  function startEdit(p) {
    setEditingId(p.id);
    setForm({
      id: p.id,
      prenom: p.prenom,
      nom: p.nom,
      email: p.email,
      telephone: p.telephone || '',
      statut: p.statut,
      date_entree: p.date_entree?.slice(0, 10) || '',
      date_sortie: p.date_sortie?.slice(0, 10) || '',
      type_contrat: p.type_contrat || '',
      types_cours: p.types_cours.map((t) => t.id),
    });
    setError(null);
    setIsEditModalOpen(true);
  }

  function closeEditModal() {
    setIsEditModalOpen(false);
    setEditingId(null);
    setForm(emptyEditForm);
    setError(null);
  }

  async function handleCreateSubmit(e) {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const res = await client.post('/professeurs', form);
      setProfesseurs((prev) => [...prev, res.data]);
      setSuccess('Professeur créé');
      setTimeout(() => setSuccess(null), 2000);
      closeCreateModal();
    } catch {
      setError('Formulaire invalide (email déjà utilisé ?).');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleEditSubmit(e) {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const { id, ...data } = form;
      const res = await client.put(`/professeurs/${id}`, data);
      setProfesseurs((prev) => prev.map((p) => (p.id === id ? res.data : p)));
      setSuccess('Professeur mis à jour');
      setTimeout(() => setSuccess(null), 2000);
      closeEditModal();
    } catch {
      setError('Impossible de mettre à jour ce professeur.');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id) {
    try {
      setIsSubmitting(true);
      await client.delete(`/professeurs/${id}`);
      setProfesseurs((prev) => prev.filter((p) => p.id !== id));
      setDeleteConfirm(null);
      setSuccess('Professeur supprimé');
      setTimeout(() => setSuccess(null), 2000);
    } catch {
      setError('Erreur lors de la suppression');
    } finally {
      setIsSubmitting(false);
    }
  }

  const getStatutBadge = (statut) => {
    return statut === 'actif'
      ? { label: 'Actif', color: 'green', icon: '🟢' }
      : { label: 'Inactif', color: 'amber', icon: '🟡' };
  };

  const getContratColor = (contrat) => {
    const map = {
      salarie: 'blue',
      freelance: 'purple',
      prestataire: 'cyan',
    };
    return map[contrat] || 'gray';
  };

  if (professeurs === null) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: '#6b7280' }}>
        Chargement…
      </div>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="👨‍🏫"
        title="Professeurs"
        description="Gérez les professeurs et leurs types de cours"
        badge={`${professeurs.length} professeur${professeurs.length !== 1 ? 's' : ''}`}
        action={
          <AdminButton
            variant="primary"
            icon="➕"
            onClick={openCreateModal}
          >
            Nouveau professeur
          </AdminButton>
        }
      />

      <AdminPageContent>
        {error && (
          <div style={{
            background: '#fee2e2',
            color: ADMIN_COLORS.error,
            padding: '16px',
            borderRadius: '8px',
            marginBottom: '16px',
            display: 'flex',
            gap: '12px',
          }}>
            <span>⚠️</span>
            <div>{error}</div>
          </div>
        )}

        {success && (
          <div style={{
            background: '#d1fae5',
            color: ADMIN_COLORS.success,
            padding: '16px',
            borderRadius: '8px',
            marginBottom: '16px',
            display: 'flex',
            gap: '12px',
          }}>
            <span>✓</span>
            <div>{success}</div>
          </div>
        )}

        <AdminCardGrid emptyMessage="Aucun professeur. Créez-en un pour commencer.">
          {professeurs.map((p) => {
            const statut = getStatutBadge(p.statut);
            return (
              <AdminCard key={p.id}>
                <AdminCardHeader
                  title={`${p.prenom} ${p.nom}`}
                  subtitle={p.email}
                  actions={
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <AdminIconButton
                        icon="✏️"
                        title="Modifier"
                        onClick={() => startEdit(p)}
                        variant="primary"
                      />
                      <AdminIconButton
                        icon="🗑️"
                        title="Supprimer"
                        onClick={() => setDeleteConfirm(p.id)}
                        variant="danger"
                      />
                    </div>
                  }
                />
                <AdminCardBody>
                  <div style={{ marginBottom: '12px' }}>
                    <AdminBadge
                      label={statut.label}
                      color={statut.color}
                      icon={statut.icon}
                    />
                    {p.type_contrat && (
                      <AdminBadge
                        label={p.type_contrat}
                        color={getContratColor(p.type_contrat)}
                        style={{ marginLeft: '8px' }}
                      />
                    )}
                  </div>

                  {p.telephone && (
                    <p style={{ fontSize: '13px', color: '#6b7280', margin: '8px 0' }}>
                      📞 {p.telephone}
                    </p>
                  )}

                  {p.date_entree && (
                    <p style={{ fontSize: '13px', color: '#6b7280', margin: '8px 0' }}>
                      📅 Entrée: {p.date_entree.slice(0, 10)}
                    </p>
                  )}

                  {p.types_cours && p.types_cours.length > 0 && (
                    <div style={{ marginTop: '12px' }}>
                      <p style={{ fontSize: '12px', fontWeight: 600, color: '#6b7280', marginBottom: '6px' }}>
                        Types de cours ({p.types_cours.length}):
                      </p>
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                        {p.types_cours.map((t) => (
                          <AdminBadge
                            key={t.id}
                            label={t.nom}
                            color="blue"
                          />
                        ))}
                      </div>
                    </div>
                  )}
                </AdminCardBody>
              </AdminCard>
            );
          })}
        </AdminCardGrid>
      </AdminPageContent>

      {/* Modal Create */}
      <AdminModal
        isOpen={isCreateModalOpen}
        title="Nouveau professeur"
        onClose={closeCreateModal}
        size="lg"
        footer={
          <div style={{ display: 'flex', gap: '12px' }}>
            <AdminButton
              variant="secondary"
              onClick={closeCreateModal}
              disabled={isSubmitting}
            >
              Annuler
            </AdminButton>
            <AdminButton
              variant="primary"
              icon={isSubmitting ? '⏳' : '✓'}
              onClick={handleCreateSubmit}
              disabled={isSubmitting}
            >
              Créer
            </AdminButton>
          </div>
        }
      >
        <form onSubmit={handleCreateSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <AdminFormField label="Email de connexion (login)" required>
            <AdminInput
              type="email"
              value={form.login_email}
              onChange={(e) => setForm({ ...form, login_email: e.target.value })}
              placeholder="professeur@logiscool.com"
              required
            />
          </AdminFormField>

          <AdminFormField label="Mot de passe initial" description="Minimum 8 caractères" required>
            <AdminInput
              type="password"
              value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })}
              placeholder="••••••••"
              minLength={8}
              required
            />
          </AdminFormField>

          <AdminFormField label="Prénom" required>
            <AdminInput
              value={form.prenom}
              onChange={(e) => setForm({ ...form, prenom: e.target.value })}
              placeholder="Jean"
              required
            />
          </AdminFormField>

          <AdminFormField label="Nom" required>
            <AdminInput
              value={form.nom}
              onChange={(e) => setForm({ ...form, nom: e.target.value })}
              placeholder="Dupont"
              required
            />
          </AdminFormField>

          <AdminFormField label="Email professionnel" required>
            <AdminInput
              type="email"
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              placeholder="jean.dupont@logiscool.com"
              required
            />
          </AdminFormField>

          <AdminFormField label="Téléphone">
            <AdminInput
              value={form.telephone}
              onChange={(e) => setForm({ ...form, telephone: e.target.value })}
              placeholder="06 XX XX XX XX"
            />
          </AdminFormField>

          <AdminFormField label="Date d'entrée" required>
            <AdminInput
              type="date"
              value={form.date_entree}
              onChange={(e) => setForm({ ...form, date_entree: e.target.value })}
              required
            />
          </AdminFormField>

          <AdminFormField label="Type de contrat">
            <AdminSelect
              value={form.type_contrat}
              onChange={(e) => setForm({ ...form, type_contrat: e.target.value })}
              placeholder="Sélectionnez un type"
              options={[
                { value: '', label: '— Non précisé —' },
                { value: 'salarie', label: 'Salarié' },
                { value: 'freelance', label: 'Freelance' },
                { value: 'prestataire', label: 'Prestataire' },
              ]}
            />
          </AdminFormField>

          <AdminFormField label="Types de cours enseignés">
            <AdminCheckboxGroup
              options={typesCours.map((t) => ({ value: t.id, label: t.nom }))}
              selected={form.types_cours}
              onChange={(types_cours) => setForm({ ...form, types_cours })}
            />
          </AdminFormField>
        </form>
      </AdminModal>

      {/* Modal Edit */}
      <AdminModal
        isOpen={isEditModalOpen}
        title={`Modifier ${form.prenom} ${form.nom}`}
        onClose={closeEditModal}
        size="lg"
        footer={
          <div style={{ display: 'flex', gap: '12px' }}>
            <AdminButton
              variant="secondary"
              onClick={closeEditModal}
              disabled={isSubmitting}
            >
              Annuler
            </AdminButton>
            <AdminButton
              variant="primary"
              icon={isSubmitting ? '⏳' : '✓'}
              onClick={handleEditSubmit}
              disabled={isSubmitting}
            >
              Enregistrer
            </AdminButton>
          </div>
        }
      >
        <form onSubmit={handleEditSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <AdminFormField label="Prénom" required>
            <AdminInput
              value={form.prenom}
              onChange={(e) => setForm({ ...form, prenom: e.target.value })}
              placeholder="Jean"
              required
            />
          </AdminFormField>

          <AdminFormField label="Nom" required>
            <AdminInput
              value={form.nom}
              onChange={(e) => setForm({ ...form, nom: e.target.value })}
              placeholder="Dupont"
              required
            />
          </AdminFormField>

          <AdminFormField label="Email professionnel" required>
            <AdminInput
              type="email"
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              placeholder="jean.dupont@logiscool.com"
              required
            />
          </AdminFormField>

          <AdminFormField label="Téléphone">
            <AdminInput
              value={form.telephone}
              onChange={(e) => setForm({ ...form, telephone: e.target.value })}
              placeholder="06 XX XX XX XX"
            />
          </AdminFormField>

          <AdminFormField label="Statut">
            <AdminSelect
              value={form.statut}
              onChange={(e) => setForm({ ...form, statut: e.target.value })}
              options={[
                { value: 'actif', label: 'Actif' },
                { value: 'inactif', label: 'Inactif' },
              ]}
            />
          </AdminFormField>

          <AdminFormField label="Date d'entrée" required>
            <AdminInput
              type="date"
              value={form.date_entree}
              onChange={(e) => setForm({ ...form, date_entree: e.target.value })}
              required
            />
          </AdminFormField>

          <AdminFormField label="Date de sortie">
            <AdminInput
              type="date"
              value={form.date_sortie}
              onChange={(e) => setForm({ ...form, date_sortie: e.target.value })}
            />
          </AdminFormField>

          <AdminFormField label="Type de contrat">
            <AdminSelect
              value={form.type_contrat}
              onChange={(e) => setForm({ ...form, type_contrat: e.target.value })}
              placeholder="Sélectionnez un type"
              options={[
                { value: '', label: '— Non précisé —' },
                { value: 'salarie', label: 'Salarié' },
                { value: 'freelance', label: 'Freelance' },
                { value: 'prestataire', label: 'Prestataire' },
              ]}
            />
          </AdminFormField>

          <AdminFormField label="Types de cours enseignés">
            <AdminCheckboxGroup
              options={typesCours.map((t) => ({ value: t.id, label: t.nom }))}
              selected={form.types_cours}
              onChange={(types_cours) => setForm({ ...form, types_cours })}
            />
          </AdminFormField>
        </form>
      </AdminModal>

      {/* Delete Confirmation Modal */}
      <AdminModal
        isOpen={deleteConfirm !== null}
        title="Supprimer le professeur"
        onClose={() => setDeleteConfirm(null)}
        size="sm"
        footer={
          <div style={{ display: 'flex', gap: '12px' }}>
            <AdminButton
              variant="secondary"
              onClick={() => setDeleteConfirm(null)}
              disabled={isSubmitting}
            >
              Annuler
            </AdminButton>
            <AdminButton
              variant="danger"
              onClick={() => handleDelete(deleteConfirm)}
              disabled={isSubmitting}
              icon={isSubmitting ? '⏳' : '🗑️'}
            >
              Supprimer
            </AdminButton>
          </div>
        }
      >
        <p style={{ color: ADMIN_COLORS.textSecondary, marginBottom: '16px' }}>
          Êtes-vous sûr de vouloir supprimer ce professeur et son compte d'accès ? Cette action ne peut pas être annulée.
        </p>
      </AdminModal>
    </>
  );
}
