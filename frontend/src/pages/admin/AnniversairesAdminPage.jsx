import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';
import ClasseLiensManager from '../../components/ClasseLiensManager';
import AdminModal from '../../components/AdminModal';
import AdminButton, { AdminIconButton } from '../../components/AdminButton';
import { AdminFormField, AdminInput, AdminTextarea, AdminSelect } from '../../components/AdminFormField';
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

const emptyForm = {
  nom_theme: '',
  slug: '',
  description: '',
  tranche_age: '',
  tarif: '',
  inclus: '',
  options: '',
  statut: 'draft',
};

export default function AnniversairesAdminPage() {
  const navigate = useNavigate();
  const [anniversaires, setAnniversaires] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [expandedId, setExpandedId] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    client.get('/anniversaires').then((res) => setAnniversaires(res.data));
  }, []);

  function startCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setError(null);
    setIsModalOpen(true);
  }

  function startEdit(a) {
    setEditingId(a.id);
    setForm({
      nom_theme: a.nom_theme,
      slug: a.slug,
      description: a.description,
      tranche_age: a.tranche_age,
      tarif: a.tarif,
      inclus: a.inclus || '',
      options: a.options || '',
      statut: a.statut,
    });
    setError(null);
    setIsModalOpen(true);
  }

  function closeModal() {
    setIsModalOpen(false);
    setEditingId(null);
    setForm(emptyForm);
    setError(null);
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      if (editingId) {
        const res = await client.put(`/anniversaires/${editingId}`, form);
        setAnniversaires((prev) => prev.map((a) => (a.id === editingId ? res.data : a)));
      } else {
        const res = await client.post('/anniversaires', form);
        setAnniversaires((prev) => [...prev, res.data]);
      }
      setSuccess(editingId ? 'Thème mis à jour' : 'Thème créé');
      setTimeout(() => setSuccess(null), 2000);
      closeModal();
    } catch {
      setError('Formulaire invalide (slug déjà utilisé ?).');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id) {
    try {
      setIsSubmitting(true);
      await client.delete(`/anniversaires/${id}`);
      setAnniversaires((prev) => prev.filter((a) => a.id !== id));
      setDeleteConfirm(null);
      setSuccess('Thème supprimé');
      setTimeout(() => setSuccess(null), 2000);
    } catch {
      setError('Erreur lors de la suppression');
    } finally {
      setIsSubmitting(false);
    }
  }

  const getStatutBadge = (statut) => {
    return statut === 'draft'
      ? { label: 'Brouillon', color: 'amber', icon: '🟡' }
      : { label: 'Publié', color: 'green', icon: '🟢' };
  };

  if (anniversaires === null) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: '#6b7280' }}>
        Chargement…
      </div>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="🎂"
        title="Anniversaires"
        description="Gérez vos offres d'anniversaires thématiques"
        badge={`${anniversaires.length} thème${anniversaires.length !== 1 ? 's' : ''}`}
        action={
          <AdminButton
            variant="primary"
            icon="➕"
            onClick={startCreate}
          >
            Nouveau thème
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

        <AdminCardGrid emptyMessage="Aucun thème d'anniversaire. Créez-en un pour commencer.">
          {anniversaires.map((a) => {
            const statut = getStatutBadge(a.statut);
            return (
              <AdminCard key={a.id}>
                <AdminCardHeader
                  title={a.nom_theme}
                  subtitle={a.tranche_age}
                  actions={
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <AdminIconButton
                        icon="✏️"
                        title="Modifier"
                        onClick={() => startEdit(a)}
                        variant="primary"
                      />
                      <AdminIconButton
                        icon="🗑️"
                        title="Supprimer"
                        onClick={() => setDeleteConfirm(a.id)}
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
                    <AdminBadge
                      label={`${a.tarif}€`}
                      color="purple"
                      style={{ marginLeft: '8px' }}
                    />
                  </div>
                  {a.description && (
                    <p style={{ fontSize: '13px', color: '#6b7280', margin: '12px 0' }}>
                      {a.description.substring(0, 80)}...
                    </p>
                  )}
                </AdminCardBody>
                <AdminCardFooter>
                  <AdminButton
                    variant="secondary"
                    size="sm"
                    icon="📝"
                    onClick={() => navigate(`/admin/anniversaires/${a.id}/contenu`)}
                  >
                    Contenu
                  </AdminButton>
                  <AdminButton
                    variant="secondary"
                    size="sm"
                    icon="🔗"
                    onClick={() => setExpandedId(expandedId === a.id ? null : a.id)}
                  >
                    Classes
                  </AdminButton>
                </AdminCardFooter>
                {expandedId === a.id && (
                  <div style={{ marginTop: '16px', paddingTop: '16px', borderTop: '1px solid #e5e7eb' }}>
                    <ClasseLiensManager parentType="anniversaires" parentId={a.id} />
                  </div>
                )}
              </AdminCard>
            );
          })}
        </AdminCardGrid>
      </AdminPageContent>

      {/* Modal Create/Edit */}
      <AdminModal
        isOpen={isModalOpen}
        title={editingId ? 'Modifier le thème' : 'Nouveau thème'}
        onClose={closeModal}
        size="lg"
        footer={
          <div style={{ display: 'flex', gap: '12px' }}>
            <AdminButton
              variant="secondary"
              onClick={closeModal}
              disabled={isSubmitting}
            >
              Annuler
            </AdminButton>
            <AdminButton
              variant="primary"
              icon={isSubmitting ? '⏳' : '✓'}
              onClick={handleSubmit}
              disabled={isSubmitting}
            >
              {editingId ? 'Enregistrer' : 'Créer'}
            </AdminButton>
          </div>
        }
      >
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <AdminFormField label="Nom du thème" required>
            <AdminInput
              value={form.nom_theme}
              onChange={(e) => setForm({ ...form, nom_theme: e.target.value })}
              placeholder="Ex: Anniversaire Pirates"
              required
            />
          </AdminFormField>

          <AdminFormField label="Slug" description="URL-friendly identifier" required>
            <AdminInput
              value={form.slug}
              onChange={(e) => setForm({ ...form, slug: e.target.value })}
              placeholder="anniversaire-pirates"
              required
            />
          </AdminFormField>

          <AdminFormField label="Description" required>
            <AdminTextarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              placeholder="Décrivez ce thème d'anniversaire..."
              rows={3}
              required
            />
          </AdminFormField>

          <AdminFormField label="Tranche d'âge" required>
            <AdminInput
              value={form.tranche_age}
              onChange={(e) => setForm({ ...form, tranche_age: e.target.value })}
              placeholder="Ex: 7-10 ans"
              required
            />
          </AdminFormField>

          <AdminFormField label="Tarif (€)" required>
            <AdminInput
              type="number"
              step="0.01"
              value={form.tarif}
              onChange={(e) => setForm({ ...form, tarif: e.target.value })}
              placeholder="0.00"
              required
            />
          </AdminFormField>

          <AdminFormField label="Ce qui est compris">
            <AdminTextarea
              value={form.inclus}
              onChange={(e) => setForm({ ...form, inclus: e.target.value })}
              placeholder="Animation, gâteau, décorations..."
              rows={2}
            />
          </AdminFormField>

          <AdminFormField label="Options disponibles">
            <AdminTextarea
              value={form.options}
              onChange={(e) => setForm({ ...form, options: e.target.value })}
              placeholder="Ex: Photo souvenir, goody bag premium..."
              rows={2}
            />
          </AdminFormField>

          <AdminFormField label="Statut">
            <AdminSelect
              value={form.statut}
              onChange={(e) => setForm({ ...form, statut: e.target.value })}
              options={[
                { value: 'draft', label: 'Brouillon' },
                { value: 'publish', label: 'Publié' },
              ]}
            />
          </AdminFormField>
        </form>
      </AdminModal>

      {/* Delete Confirmation Modal */}
      <AdminModal
        isOpen={deleteConfirm !== null}
        title="Supprimer le thème"
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
          Êtes-vous sûr de vouloir supprimer ce thème d'anniversaire ? Cette action ne peut pas être annulée.
        </p>
      </AdminModal>
    </>
  );
}
