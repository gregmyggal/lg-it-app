import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';
import AdminModal from '../../components/AdminModal';
import AdminButton, { AdminIconButton } from '../../components/AdminButton';
import { AdminFormField, AdminInput, AdminTextarea, AdminSelect } from '../../components/AdminFormField';
import { formatDuree } from '../../utils/format';
import ImpactDefrayage from '../../components/ImpactDefrayage';
import { getFieldErrors } from '../../api/errors';

const emptyForm = {
  titre: '',
  slug: '',
  contenu: '',
  extrait: '',
  heures_defrayables: '', // vide = défaut global de l'année
  statut: 'draft',
};

export default function CoursAdminPage() {
  const navigate = useNavigate();
  const [cours, setCours] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});
  const [defautGlobal, setDefautGlobal] = useState(null); // heures défrayables par défaut (année courante)

  useEffect(() => {
    client.get('/cours').then((res) => setCours(res.data));
    client
      .get(`/timesheet-parametres/${new Date().getFullYear()}`)
      .then((res) => setDefautGlobal(res.data.data.heures_defrayables))
      .catch(() => setDefautGlobal(null));
  }, []);

  function startCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setFieldErrors({});
    setError(null);
    setIsModalOpen(true);
  }

  function startEdit(c) {
    setEditingId(c.id);
    setForm({
      titre: c.titre,
      slug: c.slug,
      contenu: c.contenu || '',
      extrait: c.extrait || '',
      heures_defrayables: c.heures_defrayables != null ? String(c.heures_defrayables) : '',
      statut: c.statut,
    });
    setFieldErrors({});
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
    setFieldErrors({});
    setIsSubmitting(true);

    // Heures défrayables : vide = défaut global (null côté serveur) ; virgule décimale acceptée.
    const payload = { ...form, heures_defrayables: String(form.heures_defrayables).trim() === '' ? null : String(form.heures_defrayables).replace(',', '.') };

    try {
      if (editingId) {
        const res = await client.put(`/cours/${editingId}`, payload);
        setCours((prev) => prev.map((c) => (c.id === editingId ? { ...c, ...res.data } : c)));
        setSuccess('Cours modifié avec succès !');
      } else {
        const res = await client.post('/cours', payload);
        setCours((prev) => [...prev, { ...res.data, ressources: [] }]);
        setSuccess('Cours créé avec succès !');
      }
      setTimeout(closeModal, 1500);
      setTimeout(() => setSuccess(null), 2500);
    } catch (err) {
      setFieldErrors(getFieldErrors(err));
      setError('Formulaire invalide (slug déjà utilisé ? heures défrayables entre 0,5 et 8 h ?).');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id) {
    try {
      setIsSubmitting(true);
      await client.delete(`/cours/${id}`);
      setCours((prev) => prev.filter((c) => c.id !== id));
      setDeleteConfirm(null);
      setSuccess('Cours supprimé');
      setTimeout(() => setSuccess(null), 2000);
    } catch {
      setError('Erreur lors de la suppression');
    } finally {
      setIsSubmitting(false);
    }
  }

  if (cours === null) return <LoadingState />;

  return (
    <>
      <div style={{ minHeight: '100vh', background: 'linear-gradient(135deg, var(--c-bg) 0%, var(--c-bg) 100%)' }}>
        {/* Header */}
        <div style={{ background: 'var(--c-card)', borderBottom: '1px solid var(--c-border)', padding: '32px 24px', marginBottom: '32px' }}>
          <div style={{ maxWidth: 'var(--page-max, 1760px)', margin: '0 auto', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <div>
              <h1 style={{ margin: '0 0 8px 0', fontSize: '32px', fontWeight: 700 }}>Gestion des Cours</h1>
              <p style={{ margin: 0, fontSize: '15px', color: 'var(--c-text-2)' }}>Créez, modifiez et organisez vos cours</p>
            </div>
            <AdminButton
              variant="primary"
              icon="➕"
              onClick={startCreate}
            >
              Nouveau cours
            </AdminButton>
          </div>
        </div>

        {/* Main Content */}
        <div style={{ maxWidth: 'var(--page-max, 1760px)', margin: '0 auto', padding: '0 24px 32px 24px' }}>
          {/* Messages */}
          {success && <SuccessMessage message={success} />}
          {error && <ErrorMessage message={error} />}

          {/* Courses Grid */}
          <div style={{ marginBottom: '48px' }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '24px' }}>
              <h2 style={{ margin: 0, fontSize: '20px', fontWeight: 600, color: 'var(--c-text)' }}>Cours existants</h2>
              {cours.length > 0 && (
                <span style={{ background: 'var(--tone-primary-bg)', color: 'var(--tone-primary-fg)', padding: '6px 12px', borderRadius: '20px', fontSize: '13px', fontWeight: 600 }}>
                  {cours.length} cours
                </span>
              )}
            </div>

            {cours.length === 0 ? (
              <EmptyState />
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '20px' }}>
                {cours.map((c) => (
                  <CourseCard
                    key={c.id}
                    course={c}
                    onEdit={startEdit}
                    onDelete={() => setDeleteConfirm(c.id)}
                    onNavigateContent={() => navigate(`/admin/cours/${c.id}/contenu`)}
                    onNavigateLiens={() => navigate(`/admin/cours/${c.id}/liens`)}
                  />
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Modal Create/Edit */}
      <AdminModal
        isOpen={isModalOpen}
        title={editingId ? '✏️ Modifier le cours' : '➕ Créer un nouveau cours'}
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
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
            <AdminFormField label="Titre" required>
              <AdminInput
                value={form.titre}
                onChange={(e) => setForm({ ...form, titre: e.target.value })}
                placeholder="Ex: Scratch Junior"
                required
              />
            </AdminFormField>
            <AdminFormField label="Slug" required>
              <AdminInput
                value={form.slug}
                onChange={(e) => setForm({ ...form, slug: e.target.value })}
                placeholder="Ex: scratch-junior"
                required
              />
            </AdminFormField>
          </div>

          <AdminFormField label="Extrait">
            <AdminInput
              value={form.extrait}
              onChange={(e) => setForm({ ...form, extrait: e.target.value })}
              placeholder="Courte description du cours"
            />
          </AdminFormField>

          <AdminFormField label="Contenu">
            <AdminTextarea
              value={form.contenu}
              onChange={(e) => setForm({ ...form, contenu: e.target.value })}
              placeholder="Description complète du cours..."
              rows={4}
            />
          </AdminFormField>

          <AdminFormField
            label="Heures défrayables par séance"
            htmlFor="cours-heures-defrayables"
            error={fieldErrors.heures_defrayables}
            description={
              form.heures_defrayables === ''
                ? `Laissez vide pour utiliser le défaut global${defautGlobal != null ? ` (${formatDuree(defautGlobal)})` : ''}. Durée payée au professeur par séance de ce cours : cours + préparation.`
                : `= ${formatDuree(form.heures_defrayables)} par séance. S'applique aux séances non encore encodées ; entre 0,5 h et 8 h, par pas de 15 min.`
            }
          >
            <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' }}>
              <div style={{ flex: '0 1 180px' }}>
                <AdminInput
                  id="cours-heures-defrayables"
                  inputMode="decimal"
                  value={form.heures_defrayables}
                  onChange={(e) => setForm({ ...form, heures_defrayables: e.target.value })}
                  placeholder={defautGlobal != null ? `Défaut : ${formatDuree(defautGlobal)}` : 'Défaut global'}
                />
              </div>
              <span
                style={{
                  fontSize: '12px',
                  fontWeight: 600,
                  padding: '2px 9px',
                  borderRadius: '99px',
                  background: form.heures_defrayables === '' ? 'var(--tone-neutral-bg)' : 'var(--tone-primary-bg)',
                  color: form.heures_defrayables === '' ? 'var(--tone-neutral-fg)' : 'var(--tone-primary-fg)',
                }}
              >
                {form.heures_defrayables === '' ? 'Hérité du défaut' : 'Personnalisé'}
              </span>
              {form.heures_defrayables !== '' && (
                <AdminButton type="button" variant="secondary" size="sm" onClick={() => setForm({ ...form, heures_defrayables: '' })}>
                  Revenir au défaut
                </AdminButton>
              )}
              <a href="/admin/timesheets/parametres" style={{ fontSize: '12px', color: 'var(--c-primary)' }}>Modifier le défaut global</a>
            </div>
          </AdminFormField>

          {editingId && <ImpactDefrayage annee={new Date().getFullYear()} portee="cours" coursId={editingId} heures={form.heures_defrayables} />}

          <AdminFormField label="Statut">
            <AdminSelect
              value={form.statut}
              onChange={(e) => setForm({ ...form, statut: e.target.value })}
              options={[
                { value: 'draft', label: '🔒 Brouillon' },
                { value: 'publish', label: '✓ Publié' },
              ]}
            />
          </AdminFormField>

        </form>
      </AdminModal>

      {/* Delete Confirmation Modal */}
      <AdminModal
        isOpen={deleteConfirm !== null}
        title="Supprimer le cours"
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
        <p style={{ color: 'var(--c-text-2)', marginBottom: '16px' }}>
          Êtes-vous sûr de vouloir supprimer ce cours ? Cette action ne peut pas être annulée.
        </p>
      </AdminModal>

      <style>{`
        @keyframes slideDown {
          from { opacity: 0; transform: translateY(-10px); }
          to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.5; }
        }
      `}</style>
    </>
  );
}

function CourseCard({ course, onEdit, onDelete, onNavigateContent, onNavigateLiens }) {
  const statusColor = course.statut === 'publish' ? 'var(--c-success)' : 'var(--c-warning)';
  const statusLabel = course.statut === 'publish' ? 'Publié' : 'Brouillon';

  return (
    <div
      style={{
        background: 'var(--c-card)',
        borderRadius: '12px',
        padding: '20px',
        boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
        transition: 'all 0.3s ease',
        border: '1px solid var(--c-border)',
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
        e.currentTarget.style.transform = 'translateY(-2px)';
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
        e.currentTarget.style.transform = 'translateY(0)';
      }}
    >
      <div style={{ marginBottom: '16px' }}>
        <h3 style={{ margin: '0 0 8px 0', fontSize: '16px', fontWeight: 600, color: 'var(--c-text)' }}>{course.titre}</h3>
        {course.heures_defrayables != null && (
          <p style={{ margin: '0 0 6px 0', fontSize: '12px', fontWeight: 600, color: 'var(--tone-primary-fg)' }}>Défrayé {formatDuree(course.heures_defrayables)} par séance</p>
        )}
        <p style={{ margin: 0, fontSize: '13px', color: 'var(--c-text-2)' }}>Slug: <code style={{ background: 'var(--c-hover)', padding: '2px 6px', borderRadius: '4px' }}>{course.slug}</code></p>
      </div>

      <div style={{ marginBottom: '16px', display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
        <span style={{ background: `color-mix(in srgb, ${statusColor} 12%, transparent)`, color: statusColor, padding: '4px 10px', borderRadius: '20px', fontSize: '12px', fontWeight: 600 }}>
          {statusLabel}
        </span>
      </div>

      <div style={{ display: 'grid', gap: '8px' }}>
        <ActionButton color="var(--c-primary)" onClick={() => onEdit(course)}>📝 Modifier</ActionButton>
        <ActionButton color="var(--b-purple-fg)" onClick={onNavigateContent}>📄 Contenus</ActionButton>
        <ActionButton color="var(--c-info)" onClick={onNavigateLiens}>🔗 Liens ({(course.liens_generaux_count || 0) + (course.liens_seance_count || 0)})</ActionButton>
        <ActionButton color="var(--c-error)" onClick={() => onDelete(course.id)}>🗑️ Supprimer</ActionButton>
      </div>
    </div>
  );
}

function ActionButton({ children, onClick, color }) {
  return (
    <button
      onClick={onClick}
      style={{
        padding: '10px 14px',
        background: `color-mix(in srgb, ${color} 8%, transparent)`,
        color: color,
        border: `1px solid color-mix(in srgb, ${color} 19%, transparent)`,
        borderRadius: '6px',
        cursor: 'pointer',
        fontWeight: 500,
        fontSize: '13px',
        transition: 'all 0.2s ease',
      }}
      onMouseEnter={(e) => {
        e.target.style.background = `color-mix(in srgb, ${color} 15%, transparent)`;
        e.target.style.borderColor = `color-mix(in srgb, ${color} 31%, transparent)`;
      }}
      onMouseLeave={(e) => {
        e.target.style.background = `color-mix(in srgb, ${color} 8%, transparent)`;
        e.target.style.borderColor = `color-mix(in srgb, ${color} 19%, transparent)`;
      }}
    >
      {children}
    </button>
  );
}

function FormField({ label, required, description, children }) {
  return (
    <div>
      <label style={{ display: 'block', marginBottom: '8px' }}>
        <span style={{ fontSize: '14px', fontWeight: 600, color: 'var(--c-text)' }}>
          {label}
          {required && <span style={{ color: 'var(--c-error)' }}> *</span>}
        </span>
        {description && <p style={{ margin: '4px 0 0 0', fontSize: '12px', color: 'var(--c-text-2)' }}>{description}</p>}
      </label>
      {children}
    </div>
  );
}

const inputStyle = {
  width: '100%',
  padding: '10px 12px',
  border: '1px solid var(--c-border)',
  borderRadius: '8px',
  fontSize: '14px',
  color: 'var(--c-text)',
  transition: 'border-color 0.2s ease',
  fontFamily: 'inherit',
};

function SuccessMessage({ message, onClose }) {
  useEffect(() => {
    const timer = setTimeout(onClose, 3000);
    return () => clearTimeout(timer);
  }, [onClose]);

  return (
    <div style={{ background: 'var(--tone-success-bg)', border: '1px solid var(--tone-success-bd)', borderRadius: '8px', padding: '16px', marginBottom: '24px', display: 'flex', alignItems: 'center', gap: '12px', animation: 'slideDown 0.3s ease' }}>
      <span style={{ fontSize: '20px' }}>✓</span>
      <p style={{ margin: 0, fontWeight: 500, color: 'var(--tone-success-fg)' }}>{message}</p>
    </div>
  );
}

function ErrorMessage({ message }) {
  return (
    <div style={{ background: 'var(--tone-error-bg)', border: '1px solid var(--tone-error-bd)', borderRadius: '8px', padding: '16px', marginBottom: '24px', display: 'flex', alignItems: 'center', gap: '12px', animation: 'slideDown 0.3s ease' }}>
      <span style={{ fontSize: '20px' }}>⚠</span>
      <p style={{ margin: 0, fontWeight: 500, color: 'var(--tone-error-fg)' }}>{message}</p>
    </div>
  );
}

function EmptyState() {
  return (
    <div style={{ background: 'var(--c-card)', borderRadius: '12px', padding: '48px 24px', textAlign: 'center', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
      <div style={{ fontSize: '48px', marginBottom: '16px' }}>📚</div>
      <h3 style={{ margin: '0 0 8px 0', fontSize: '18px', fontWeight: 600, color: 'var(--c-text)' }}>Aucun cours pour le moment</h3>
      <p style={{ margin: 0, fontSize: '14px', color: 'var(--c-text-2)' }}>Créez votre premier cours en utilisant le formulaire ci-dessous</p>
    </div>
  );
}

function LoadingState() {
  return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh', background: 'linear-gradient(135deg, var(--c-bg) 0%, var(--c-bg) 100%)' }}>
      <div style={{ textAlign: 'center' }}>
        <div style={{ fontSize: '48px', marginBottom: '16px', animation: 'spin 1s linear infinite' }}>⏳</div>
        <p style={{ color: 'var(--c-text-2)', fontSize: '16px' }}>Chargement en cours...</p>
      </div>
      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}
