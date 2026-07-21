import { useEffect, useState } from 'react';
import client from '../api/client';
import AdminModal from './AdminModal';
import AdminButton, { AdminIconButton } from './AdminButton';
import { AdminFormField, AdminInput } from './AdminFormField';
import {
  AdminPageHeader,
  AdminPageContent,
  AdminBadge,
} from './AdminPageLayout';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';

function slugify(text) {
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');
}

// CRUD générique pour un lookup {nom, slug} — réutilisé pour types-cours et types-formation,
// qui ont exactement la même forme.
export default function SimpleLookupAdmin({ title, endpoint }) {
  const [items, setItems] = useState(null);
  const [form, setForm] = useState({ nom: '', slug: '' });
  const [editingId, setEditingId] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    client.get(`/${endpoint}`).then((res) => setItems(res.data));
  }, [endpoint]);

  function openCreateModal() {
    setEditingId(null);
    setForm({ nom: '', slug: '' });
    setError(null);
    setIsModalOpen(true);
  }

  function startEdit(item) {
    setEditingId(item.id);
    setForm({ nom: item.nom, slug: item.slug });
    setError(null);
    setIsModalOpen(true);
  }

  function closeModal() {
    setIsModalOpen(false);
    setEditingId(null);
    setForm({ nom: '', slug: '' });
    setError(null);
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      if (editingId) {
        const res = await client.put(`/${endpoint}/${editingId}`, {
          nom: form.nom,
          slug: form.slug,
        });
        setItems((prev) => prev.map((i) => (i.id === res.data.id ? res.data : i)));
        setSuccess('Modifié avec succès');
      } else {
        const res = await client.post(`/${endpoint}`, { nom: form.nom, slug: slugify(form.nom) });
        setItems((prev) => [...prev, res.data]);
        setSuccess('Créé avec succès');
      }
      setTimeout(closeModal, 1000);
      setTimeout(() => setSuccess(null), 2000);
    } catch {
      setError('Impossible de traiter cette requête (slug déjà utilisé ?).');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id) {
    try {
      setIsSubmitting(true);
      await client.delete(`/${endpoint}/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      setDeleteConfirm(null);
      setSuccess('Supprimé avec succès');
      setTimeout(() => setSuccess(null), 2000);
    } catch {
      setError('Erreur lors de la suppression');
    } finally {
      setIsSubmitting(false);
    }
  }

  if (items === null) return <p style={{ padding: '24px', color: '#6b7280' }}>Chargement…</p>;

  return (
    <>
      <AdminPageHeader
        icon="🏷️"
        title={title}
        description={`Gérez les ${title.toLowerCase()}`}
        badge={`${items.length} ${items.length > 1 ? 'éléments' : 'élément'}`}
        action={
          <AdminButton
            variant="primary"
            icon="➕"
            onClick={openCreateModal}
          >
            Ajouter
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

        <div style={{
          background: 'white',
          borderRadius: '8px',
          border: `1px solid ${ADMIN_COLORS.border}`,
          overflow: 'hidden',
          boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
        }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{
                background: '#f9fafb',
                borderBottom: `1px solid ${ADMIN_COLORS.border}`,
              }}>
                <th style={{
                  padding: '16px',
                  textAlign: 'left',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: ADMIN_COLORS.textPrimary,
                  textTransform: 'uppercase',
                  letterSpacing: '0.5px',
                }}>
                  Nom
                </th>
                <th style={{
                  padding: '16px',
                  textAlign: 'left',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: ADMIN_COLORS.textPrimary,
                  textTransform: 'uppercase',
                  letterSpacing: '0.5px',
                }}>
                  Slug
                </th>
                <th style={{
                  padding: '16px',
                  textAlign: 'right',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: ADMIN_COLORS.textPrimary,
                  textTransform: 'uppercase',
                  letterSpacing: '0.5px',
                }}>
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {items.length === 0 ? (
                <tr>
                  <td colSpan="3" style={{
                    padding: '40px',
                    textAlign: 'center',
                    color: '#9ca3af',
                  }}>
                    Aucun élément
                  </td>
                </tr>
              ) : (
                items.map((item, idx) => (
                  <tr
                    key={item.id}
                    style={{
                      borderBottom: `1px solid ${ADMIN_COLORS.border}`,
                      background: idx % 2 === 0 ? 'white' : '#f9fafb',
                    }}
                  >
                    <td style={{
                      padding: '16px',
                      fontSize: '14px',
                      color: ADMIN_COLORS.textPrimary,
                    }}>
                      {item.nom}
                    </td>
                    <td style={{
                      padding: '16px',
                      fontSize: '13px',
                      color: '#6b7280',
                    }}>
                      <code style={{ background: '#f3f4f6', padding: '2px 6px', borderRadius: '4px' }}>
                        {item.slug}
                      </code>
                    </td>
                    <td style={{
                      padding: '16px',
                      textAlign: 'right',
                      display: 'flex',
                      gap: '8px',
                      justifyContent: 'flex-end',
                    }}>
                      <AdminIconButton
                        icon="✏️"
                        title="Modifier"
                        onClick={() => startEdit(item)}
                        variant="primary"
                      />
                      <AdminIconButton
                        icon="🗑️"
                        title="Supprimer"
                        onClick={() => setDeleteConfirm(item.id)}
                        variant="danger"
                      />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </AdminPageContent>

      {/* Modal Create/Edit */}
      <AdminModal
        isOpen={isModalOpen}
        title={editingId ? `Modifier ${title.toLowerCase()}` : `Ajouter ${title.toLowerCase()}`}
        onClose={closeModal}
        size="md"
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
          <AdminFormField label="Nom" required>
            <AdminInput
              value={form.nom}
              onChange={(e) => setForm({ ...form, nom: e.target.value })}
              placeholder={`Ex: Débutant`}
              required
            />
          </AdminFormField>

          <AdminFormField label="Slug" required>
            <AdminInput
              value={form.slug}
              onChange={(e) => setForm({ ...form, slug: e.target.value })}
              placeholder="Ex: debutant"
              required
            />
          </AdminFormField>
        </form>
      </AdminModal>

      {/* Delete Confirmation Modal */}
      <AdminModal
        isOpen={deleteConfirm !== null}
        title="Supprimer"
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
        <p style={{ color: ADMIN_COLORS.textSecondary }}>
          Êtes-vous sûr de vouloir supprimer cet élément ? Cette action ne peut pas être annulée.
        </p>
      </AdminModal>
    </>
  );
}
