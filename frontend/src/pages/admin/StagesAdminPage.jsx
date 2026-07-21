import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';
import ClasseLiensManager from '../../components/ClasseLiensManager';
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

const VACANCES = ['Été', 'Toussaint', 'Noël', 'Carnaval', 'Pâques'];

const emptyForm = {
  titre: '',
  slug: '',
  theme_stage: '',
  tranche_age: '',
  lieu: '',
  prix: '',
  description: '',
  statut: 'draft',
  sessions_vacances: [],
};

export default function StagesAdminPage() {
  const navigate = useNavigate();
  const [stages, setStages] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [expandedDatesPanelId, setExpandedDatesPanelId] = useState(null);
  const [expandedClassesId, setExpandedClassesId] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    client.get('/stages').then((res) => setStages(res.data));
  }, []);

  function startCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setError(null);
    setIsModalOpen(true);
  }

  function startEdit(s) {
    setEditingId(s.id);
    setForm({
      titre: s.titre,
      slug: s.slug,
      theme_stage: s.theme_stage,
      tranche_age: s.tranche_age,
      lieu: s.lieu,
      prix: s.prix,
      description: s.description || '',
      statut: s.statut,
      sessions_vacances: s.sessions_vacances || [],
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
        const res = await client.put(`/stages/${editingId}`, form);
        setStages((prev) => prev.map((s) => (s.id === editingId ? { ...s, ...res.data } : s)));
      } else {
        const res = await client.post('/stages', form);
        setStages((prev) => [...prev, { ...res.data, dates: [] }]);
      }
      setSuccess(editingId ? 'Stage mis à jour' : 'Stage créé');
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
      await client.delete(`/stages/${id}`);
      setStages((prev) => prev.filter((s) => s.id !== id));
      setDeleteConfirm(null);
      setSuccess('Stage supprimé');
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

  if (stages === null) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: '#6b7280' }}>
        Chargement…
      </div>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="🏖️"
        title="Stages"
        description="Gérez vos stages et périodes de vacances"
        badge={`${stages.length} stage${stages.length !== 1 ? 's' : ''}`}
        action={
          <AdminButton
            variant="primary"
            icon="➕"
            onClick={startCreate}
          >
            Nouveau stage
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

        <AdminCardGrid emptyMessage="Aucun stage. Créez-en un pour commencer.">
          {stages.map((s) => {
            const statut = getStatutBadge(s.statut);
            return (
              <AdminCard key={s.id}>
                <AdminCardHeader
                  title={s.titre}
                  subtitle={`${s.theme_stage} • ${s.tranche_age}`}
                  actions={
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <AdminIconButton
                        icon="✏️"
                        title="Modifier"
                        onClick={() => startEdit(s)}
                        variant="primary"
                      />
                      <AdminIconButton
                        icon="🗑️"
                        title="Supprimer"
                        onClick={() => setDeleteConfirm(s.id)}
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
                    {s.prix && (
                      <AdminBadge
                        label={`${s.prix}€`}
                        color="purple"
                        style={{ marginLeft: '8px' }}
                      />
                    )}
                  </div>
                  {s.lieu && (
                    <p style={{ fontSize: '13px', color: '#6b7280', margin: '12px 0' }}>
                      📍 {s.lieu}
                    </p>
                  )}
                  {s.description && (
                    <p style={{ fontSize: '13px', color: '#6b7280', margin: '12px 0' }}>
                      {s.description.substring(0, 60)}...
                    </p>
                  )}
                </AdminCardBody>
                <AdminCardFooter>
                  <AdminButton
                    variant="secondary"
                    size="sm"
                    icon="📝"
                    onClick={() => navigate(`/admin/stages/${s.id}/contenu`)}
                  >
                    Contenu
                  </AdminButton>
                  <AdminButton
                    variant="secondary"
                    size="sm"
                    icon="📅"
                    onClick={() => setExpandedDatesPanelId(expandedDatesPanelId === s.id ? null : s.id)}
                  >
                    Dates
                  </AdminButton>
                  <AdminButton
                    variant="secondary"
                    size="sm"
                    icon="🔗"
                    onClick={() => setExpandedClassesId(expandedClassesId === s.id ? null : s.id)}
                  >
                    Classes
                  </AdminButton>
                </AdminCardFooter>

                {expandedDatesPanelId === s.id && (
                  <div style={{ marginTop: '16px', paddingTop: '16px', borderTop: '1px solid #e5e7eb' }}>
                    <DatesPanel stage={s} onUpdate={(updatedDates) => {
                      setStages((prev) =>
                        prev.map((stage) =>
                          stage.id === s.id ? { ...stage, dates: updatedDates } : stage
                        )
                      );
                    }} />
                  </div>
                )}

                {expandedClassesId === s.id && (
                  <div style={{ marginTop: '16px', paddingTop: '16px', borderTop: '1px solid #e5e7eb' }}>
                    <ClasseLiensManager parentType="stages" parentId={s.id} />
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
        title={editingId ? 'Modifier le stage' : 'Nouveau stage'}
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
          <AdminFormField label="Titre" required>
            <AdminInput
              value={form.titre}
              onChange={(e) => setForm({ ...form, titre: e.target.value })}
              placeholder="Ex: Stage Été Robotique"
              required
            />
          </AdminFormField>

          <AdminFormField label="Slug" description="URL-friendly identifier" required>
            <AdminInput
              value={form.slug}
              onChange={(e) => setForm({ ...form, slug: e.target.value })}
              placeholder="stage-ete-robotique"
              required
            />
          </AdminFormField>

          <AdminFormField label="Thème" required>
            <AdminInput
              value={form.theme_stage}
              onChange={(e) => setForm({ ...form, theme_stage: e.target.value })}
              placeholder="Ex: Robotique, Codage, Science"
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

          <AdminFormField label="Lieu" required>
            <AdminInput
              value={form.lieu}
              onChange={(e) => setForm({ ...form, lieu: e.target.value })}
              placeholder="Ex: Centre de Lattes"
              required
            />
          </AdminFormField>

          <AdminFormField label="Prix (€)" required>
            <AdminInput
              type="number"
              step="0.01"
              value={form.prix}
              onChange={(e) => setForm({ ...form, prix: e.target.value })}
              placeholder="0.00"
              required
            />
          </AdminFormField>

          <AdminFormField label="Description">
            <AdminTextarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              placeholder="Description détaillée du stage..."
              rows={3}
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

          <AdminFormField label="Sessions de vacances">
            <AdminCheckboxGroup
              options={VACANCES}
              selected={form.sessions_vacances}
              onChange={(sessions_vacances) => setForm({ ...form, sessions_vacances })}
            />
          </AdminFormField>
        </form>
      </AdminModal>

      {/* Delete Confirmation Modal */}
      <AdminModal
        isOpen={deleteConfirm !== null}
        title="Supprimer le stage"
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
          Êtes-vous sûr de vouloir supprimer ce stage ? Cette action ne peut pas être annulée.
        </p>
      </AdminModal>
    </>
  );
}

function DatesPanel({ stage, onUpdate }) {
  const [dates, setDates] = useState(stage.dates || []);
  const [date, setDate] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleAdd(e) {
    e.preventDefault();
    if (!date) return;
    setError(null);
    setIsSubmitting(true);

    try {
      const res = await client.post(`/stages/${stage.id}/dates`, { date_session: date });
      const updatedDates = [...dates, res.data];
      setDates(updatedDates);
      onUpdate(updatedDates);
      setDate('');
    } catch {
      setError('Erreur lors de l\'ajout de la date');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id) {
    try {
      await client.delete(`/stages/${stage.id}/dates/${id}`);
      const updatedDates = dates.filter((d) => d.id !== id);
      setDates(updatedDates);
      onUpdate(updatedDates);
    } catch {
      setError('Erreur lors de la suppression');
    }
  }

  return (
    <div>
      <h4 style={{ fontSize: '16px', fontWeight: 600, marginBottom: '12px' }}>📅 Dates de sessions</h4>

      {error && (
        <div style={{
          background: '#fee2e2',
          color: ADMIN_COLORS.error,
          padding: '12px',
          borderRadius: '6px',
          marginBottom: '12px',
          fontSize: '13px',
        }}>
          {error}
        </div>
      )}

      <div style={{
        display: 'flex',
        flexDirection: 'column',
        gap: '8px',
        marginBottom: '16px',
      }}>
        {dates.length === 0 ? (
          <p style={{ fontSize: '13px', color: '#9ca3af', fontStyle: 'italic' }}>
            Aucune date ajoutée
          </p>
        ) : (
          dates.map((d) => (
            <div
              key={d.id}
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                padding: '8px 12px',
                background: '#f9fafb',
                borderRadius: '6px',
                fontSize: '13px',
              }}
            >
              <span>{d.date_session?.slice(0, 10)}</span>
              <AdminIconButton
                icon="🗑️"
                title="Supprimer"
                onClick={() => handleDelete(d.id)}
                variant="danger"
                size="sm"
              />
            </div>
          ))
        )}
      </div>

      <form
        onSubmit={handleAdd}
        style={{
          display: 'flex',
          gap: '8px',
          borderTop: '1px solid #e5e7eb',
          paddingTop: '12px',
        }}
      >
        <input
          type="date"
          value={date}
          onChange={(e) => setDate(e.target.value)}
          required
          style={{
            flex: 1,
            padding: '8px 12px',
            border: '1px solid #e5e7eb',
            borderRadius: '6px',
            fontSize: '13px',
          }}
        />
        <AdminButton
          type="submit"
          variant="primary"
          size="sm"
          icon="➕"
          disabled={isSubmitting || !date}
        >
          Ajouter
        </AdminButton>
      </form>
    </div>
  );
}
