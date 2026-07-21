import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';
import CheckboxGroup from '../../components/CheckboxGroup';
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

const emptyForm = {
  titre: '',
  slug: '',
  programme: '',
  extrait: '',
  format: 'presentiel',
  duree: '',
  prix: '',
  public_cible: '',
  niveau: '',
  prochaine_date: '',
  objectifs: '',
  url_inscription: '',
  statut: 'draft',
  types_formation: [],
};

export default function FormationsAdminPage() {
  const navigate = useNavigate();
  const [formations, setFormations] = useState(null);
  const [typesFormation, setTypesFormation] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [expandedId, setExpandedId] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    client.get('/formations').then((res) => setFormations(res.data));
    client.get('/types-formation').then((res) => setTypesFormation(res.data));
  }, []);

  function startCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setError(null);
    setIsModalOpen(true);
  }

  function startEdit(f) {
    setEditingId(f.id);
    setForm({
      titre: f.titre,
      slug: f.slug,
      programme: f.programme || '',
      extrait: f.extrait || '',
      format: f.format,
      duree: f.duree || '',
      prix: f.prix || '',
      public_cible: f.public_cible || '',
      niveau: f.niveau || '',
      prochaine_date: f.prochaine_date ? f.prochaine_date.slice(0, 10) : '',
      objectifs: f.objectifs || '',
      url_inscription: f.url_inscription || '',
      statut: f.statut,
      types_formation: f.types_formation.map((t) => t.id),
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
        const res = await client.put(`/formations/${editingId}`, form);
        setFormations((prev) => prev.map((f) => (f.id === editingId ? res.data : f)));
      } else {
        const res = await client.post('/formations', form);
        setFormations((prev) => [...prev, res.data]);
      }
      setSuccess(editingId ? 'Formation mise à jour' : 'Formation créée');
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
      await client.delete(`/formations/${id}`);
      setFormations((prev) => prev.filter((f) => f.id !== id));
      setDeleteConfirm(null);
      setSuccess('Formation supprimée');
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

  if (formations === null) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: '#6b7280' }}>
        Chargement…
      </div>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="📚"
        title="Formations"
        description="Gérez vos formations et cursus de formation"
        badge={`${formations.length} formation${formations.length !== 1 ? 's' : ''}`}
        action={
          <AdminButton
            variant="primary"
            icon="➕"
            onClick={startCreate}
          >
            Nouvelle formation
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

        <AdminCardGrid emptyMessage="Aucune formation. Créez-en une pour commencer.">
          {formations.map((f) => {
            const statut = getStatutBadge(f.statut);
            return (
              <AdminCard key={f.id}>
                <AdminCardHeader
                  title={f.titre}
                  subtitle={`${f.format} • ${f.duree || '–'}`}
                  actions={
                    <div style={{ display: 'flex', gap: '8px' }}>
                      <AdminIconButton
                        icon="✏️"
                        title="Modifier"
                        onClick={() => startEdit(f)}
                        variant="primary"
                      />
                      <AdminIconButton
                        icon="🗑️"
                        title="Supprimer"
                        onClick={() => setDeleteConfirm(f.id)}
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
                    {f.niveau && (
                      <AdminBadge
                        label={f.niveau}
                        color="blue"
                        style={{ marginLeft: '8px' }}
                      />
                    )}
                  </div>
                  {f.extrait && (
                    <p style={{ fontSize: '13px', color: '#6b7280', margin: '12px 0' }}>
                      {f.extrait.substring(0, 80)}...
                    </p>
                  )}
                  {f.prix && (
                    <div style={{ fontSize: '14px', fontWeight: 600, color: ADMIN_COLORS.primary }}>
                      {f.prix}€
                    </div>
                  )}
                </AdminCardBody>
                <AdminCardFooter>
                  <AdminButton
                    variant="secondary"
                    size="sm"
                    icon="📝"
                    onClick={() => navigate(`/admin/formations/${f.id}/contenu`)}
                  >
                    Contenu
                  </AdminButton>
                  <AdminButton
                    variant="secondary"
                    size="sm"
                    icon="🔗"
                    onClick={() => setExpandedId(expandedId === f.id ? null : f.id)}
                  >
                    Classes
                  </AdminButton>
                </AdminCardFooter>
                {expandedId === f.id && (
                  <div style={{ marginTop: '16px', paddingTop: '16px', borderTop: '1px solid #e5e7eb' }}>
                    <ClasseLiensManager parentType="formations" parentId={f.id} />
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
        title={editingId ? 'Modifier la formation' : 'Nouvelle formation'}
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
              placeholder="Ex: Formation Python Avancée"
              required
            />
          </AdminFormField>

          <AdminFormField label="Slug" description="URL-friendly identifier" required>
            <AdminInput
              value={form.slug}
              onChange={(e) => setForm({ ...form, slug: e.target.value })}
              placeholder="formation-python-avancee"
              required
            />
          </AdminFormField>

          <AdminFormField label="Extrait">
            <AdminInput
              value={form.extrait}
              onChange={(e) => setForm({ ...form, extrait: e.target.value })}
              placeholder="Brève description"
            />
          </AdminFormField>

          <AdminFormField label="Programme" description="Contenu détaillé du programme">
            <AdminTextarea
              value={form.programme}
              onChange={(e) => setForm({ ...form, programme: e.target.value })}
              placeholder="Décrivez le programme..."
              rows={4}
            />
          </AdminFormField>

          <AdminFormField label="Format">
            <AdminSelect
              value={form.format}
              onChange={(e) => setForm({ ...form, format: e.target.value })}
              options={[
                { value: 'presentiel', label: 'Présentiel' },
                { value: 'en_ligne', label: 'En ligne' },
                { value: 'hybride', label: 'Hybride' },
              ]}
            />
          </AdminFormField>

          <AdminFormField label="Durée">
            <AdminInput
              value={form.duree}
              onChange={(e) => setForm({ ...form, duree: e.target.value })}
              placeholder="Ex: 5 mois, 40h"
            />
          </AdminFormField>

          <AdminFormField label="Prix (€)">
            <AdminInput
              type="number"
              step="0.01"
              value={form.prix}
              onChange={(e) => setForm({ ...form, prix: e.target.value })}
              placeholder="0.00"
            />
          </AdminFormField>

          <AdminFormField label="Public cible">
            <AdminInput
              value={form.public_cible}
              onChange={(e) => setForm({ ...form, public_cible: e.target.value })}
              placeholder="Ex: Professionnels en reconversion"
            />
          </AdminFormField>

          <AdminFormField label="Niveau">
            <AdminSelect
              value={form.niveau}
              onChange={(e) => setForm({ ...form, niveau: e.target.value })}
              placeholder="Sélectionnez un niveau"
              options={[
                { value: '', label: '— Non précisé —' },
                { value: 'debutant', label: 'Débutant' },
                { value: 'initie', label: 'Initié' },
                { value: 'confirme', label: 'Confirmé' },
                { value: 'tous', label: 'Tous niveaux' },
              ]}
            />
          </AdminFormField>

          <AdminFormField label="Prochaine date">
            <AdminInput
              type="date"
              value={form.prochaine_date}
              onChange={(e) => setForm({ ...form, prochaine_date: e.target.value })}
            />
          </AdminFormField>

          <AdminFormField label="Objectifs pédagogiques">
            <AdminTextarea
              value={form.objectifs}
              onChange={(e) => setForm({ ...form, objectifs: e.target.value })}
              placeholder="Qu'apprendront les participants ?"
              rows={3}
            />
          </AdminFormField>

          <AdminFormField label="Lien d'inscription">
            <AdminInput
              value={form.url_inscription}
              onChange={(e) => setForm({ ...form, url_inscription: e.target.value })}
              placeholder="https://…"
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

          <AdminFormField label="Types de formation">
            <AdminCheckboxGroup
              options={typesFormation.map((t) => ({ value: t.id, label: t.libelle }))}
              selected={form.types_formation}
              onChange={(types_formation) => setForm({ ...form, types_formation })}
            />
          </AdminFormField>
        </form>
      </AdminModal>

      {/* Delete Confirmation Modal */}
      <AdminModal
        isOpen={deleteConfirm !== null}
        title="Supprimer la formation"
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
          Êtes-vous sûr de vouloir supprimer cette formation ? Cette action ne peut pas être annulée.
        </p>
      </AdminModal>
    </>
  );
}
