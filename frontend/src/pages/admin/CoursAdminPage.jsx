import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';
import CheckboxGroup from '../../components/CheckboxGroup';
import ClasseLiensManager from '../../components/ClasseLiensManager';
import AdminModal from '../../components/AdminModal';
import AdminButton, { AdminIconButton } from '../../components/AdminButton';
import { AdminFormField, AdminInput, AdminTextarea, AdminSelect } from '../../components/AdminFormField';

const emptyForm = {
  titre: '',
  slug: '',
  contenu: '',
  extrait: '',
  statut: 'draft',
  types_cours: [],
};

export default function CoursAdminPage() {
  const navigate = useNavigate();
  const [cours, setCours] = useState(null);
  const [typesCours, setTypesCours] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [expandedId, setExpandedId] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    client.get('/cours').then((res) => setCours(res.data));
    client.get('/types-cours').then((res) => setTypesCours(res.data));
  }, []);

  function startCreate() {
    setEditingId(null);
    setForm(emptyForm);
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
      statut: c.statut,
      types_cours: c.types_cours.map((t) => t.id),
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
        const res = await client.put(`/cours/${editingId}`, form);
        setCours((prev) => prev.map((c) => (c.id === editingId ? { ...c, ...res.data } : c)));
        setSuccess('Cours modifié avec succès !');
      } else {
        const res = await client.post('/cours', form);
        setCours((prev) => [...prev, { ...res.data, ressources: [] }]);
        setSuccess('Cours créé avec succès !');
      }
      setTimeout(closeModal, 1500);
      setTimeout(() => setSuccess(null), 2500);
    } catch {
      setError('Formulaire invalide (slug déjà utilisé ?).');
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
      <div style={{ minHeight: '100vh', background: 'linear-gradient(135deg, #f5f7fa 0%, #f0f4f8 100%)' }}>
        {/* Header */}
        <div style={{ background: 'white', borderBottom: '1px solid #e5e7eb', padding: '32px 24px', marginBottom: '32px' }}>
          <div style={{ maxWidth: '1400px', margin: '0 auto', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <div>
              <h1 style={{ margin: '0 0 8px 0', fontSize: '32px', fontWeight: 700 }}>Gestion des Cours</h1>
              <p style={{ margin: 0, fontSize: '15px', color: '#6b7280' }}>Créez, modifiez et organisez vos cours</p>
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
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: '0 24px 32px 24px' }}>
          {/* Messages */}
          {success && <SuccessMessage message={success} />}
          {error && <ErrorMessage message={error} />}

          {/* Courses Grid */}
          <div style={{ marginBottom: '48px' }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '24px' }}>
              <h2 style={{ margin: 0, fontSize: '20px', fontWeight: 600, color: '#111827' }}>Cours existants</h2>
              {cours.length > 0 && (
                <span style={{ background: '#dbeafe', color: '#1e40af', padding: '6px 12px', borderRadius: '20px', fontSize: '13px', fontWeight: 600 }}>
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
                    onToggleResources={() => setExpandedId(expandedId === c.id ? null : c.id)}
                    isExpanded={expandedId === c.id}
                  />
                ))}
              </div>
            )}
          </div>

          {/* Ressources Section */}
          {expandedId && (
            <div style={{ background: 'white', borderRadius: '12px', padding: '24px', marginBottom: '48px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
              <RessourcesPanel
                cours={cours.find((c) => c.id === expandedId)}
                onClose={() => setExpandedId(null)}
              />
              <ClasseLiensManager parentType="cours" parentId={expandedId} />
            </div>
          )}
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

          <AdminFormField label="Types de cours">
            <div style={{ background: '#f9fafb', padding: '16px', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
              <CheckboxGroup
                options={typesCours}
                selected={form.types_cours}
                onChange={(types_cours) => setForm({ ...form, types_cours })}
              />
            </div>
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
        <p style={{ color: '#6b7280', marginBottom: '16px' }}>
          Êtes-vous sûr de vouloir supprimer ce cours ? Cette action ne peut pas être annulée.
        </p>
      </AdminModal>
    </>
  );

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
    </div>
  );
}

function CourseCard({ course, onEdit, onDelete, onNavigateContent, onToggleResources, isExpanded }) {
  const statusColor = course.statut === 'publish' ? '#10b981' : '#f59e0b';
  const statusLabel = course.statut === 'publish' ? 'Publié' : 'Brouillon';

  return (
    <div
      style={{
        background: 'white',
        borderRadius: '12px',
        padding: '20px',
        boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
        transition: 'all 0.3s ease',
        border: '1px solid #e5e7eb',
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
        <h3 style={{ margin: '0 0 8px 0', fontSize: '16px', fontWeight: 600, color: '#111827' }}>{course.titre}</h3>
        <p style={{ margin: 0, fontSize: '13px', color: '#6b7280' }}>Slug: <code style={{ background: '#f3f4f6', padding: '2px 6px', borderRadius: '4px' }}>{course.slug}</code></p>
      </div>

      <div style={{ marginBottom: '16px', display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
        <span style={{ background: statusColor + '20', color: statusColor, padding: '4px 10px', borderRadius: '20px', fontSize: '12px', fontWeight: 600 }}>
          {statusLabel}
        </span>
        {course.types_cours.map((t) => (
          <span key={t.id} style={{ background: '#dbeafe', color: '#1e40af', padding: '4px 10px', borderRadius: '20px', fontSize: '12px', fontWeight: 500 }}>
            {t.nom}
          </span>
        ))}
      </div>

      <div style={{ display: 'grid', gap: '8px' }}>
        <ActionButton color="#2563eb" onClick={() => onEdit(course)}>📝 Modifier</ActionButton>
        <ActionButton color="#7c3aed" onClick={onNavigateContent}>📄 Contenus</ActionButton>
        <ActionButton color="#0891b2" onClick={onToggleResources}>{isExpanded ? '✕ Fermer ressources' : '📚 Ressources'}</ActionButton>
        <ActionButton color="#ef4444" onClick={() => onDelete(course.id)}>🗑️ Supprimer</ActionButton>
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
        background: color + '15',
        color: color,
        border: `1px solid ${color}30`,
        borderRadius: '6px',
        cursor: 'pointer',
        fontWeight: 500,
        fontSize: '13px',
        transition: 'all 0.2s ease',
      }}
      onMouseEnter={(e) => {
        e.target.style.background = color + '25';
        e.target.style.borderColor = color + '50';
      }}
      onMouseLeave={(e) => {
        e.target.style.background = color + '15';
        e.target.style.borderColor = color + '30';
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
        <span style={{ fontSize: '14px', fontWeight: 600, color: '#111827' }}>
          {label}
          {required && <span style={{ color: '#ef4444' }}> *</span>}
        </span>
        {description && <p style={{ margin: '4px 0 0 0', fontSize: '12px', color: '#6b7280' }}>{description}</p>}
      </label>
      {children}
    </div>
  );
}

const inputStyle = {
  width: '100%',
  padding: '10px 12px',
  border: '1px solid #e5e7eb',
  borderRadius: '8px',
  fontSize: '14px',
  color: '#111827',
  transition: 'border-color 0.2s ease',
  fontFamily: 'inherit',
};

function SuccessMessage({ message, onClose }) {
  useEffect(() => {
    const timer = setTimeout(onClose, 3000);
    return () => clearTimeout(timer);
  }, [onClose]);

  return (
    <div style={{ background: '#ecfdf5', border: '1px solid #d1fae5', borderRadius: '8px', padding: '16px', marginBottom: '24px', display: 'flex', alignItems: 'center', gap: '12px', animation: 'slideDown 0.3s ease' }}>
      <span style={{ fontSize: '20px' }}>✓</span>
      <p style={{ margin: 0, fontWeight: 500, color: '#047857' }}>{message}</p>
    </div>
  );
}

function ErrorMessage({ message }) {
  return (
    <div style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', padding: '16px', marginBottom: '24px', display: 'flex', alignItems: 'center', gap: '12px', animation: 'slideDown 0.3s ease' }}>
      <span style={{ fontSize: '20px' }}>⚠</span>
      <p style={{ margin: 0, fontWeight: 500, color: '#991b1b' }}>{message}</p>
    </div>
  );
}

function EmptyState() {
  return (
    <div style={{ background: 'white', borderRadius: '12px', padding: '48px 24px', textAlign: 'center', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
      <div style={{ fontSize: '48px', marginBottom: '16px' }}>📚</div>
      <h3 style={{ margin: '0 0 8px 0', fontSize: '18px', fontWeight: 600, color: '#111827' }}>Aucun cours pour le moment</h3>
      <p style={{ margin: 0, fontSize: '14px', color: '#6b7280' }}>Créez votre premier cours en utilisant le formulaire ci-dessous</p>
    </div>
  );
}

function RessourcesPanel({ cours, onClose }) {
  const [ressources, setRessources] = useState(cours.ressources);
  const [titre, setTitre] = useState('');
  const [url, setUrl] = useState('');
  const [type, setType] = useState('outil');
  const [showForm, setShowForm] = useState(false);

  async function handleAdd(e) {
    e.preventDefault();
    const res = await client.post(`/cours/${cours.id}/ressources`, {
      titre_ressource: titre,
      url_ressource: url,
      type_ressource: type,
    });
    setRessources((prev) => [...prev, res.data]);
    setTitre('');
    setUrl('');
    setShowForm(false);
  }

  async function handleDelete(id) {
    if (confirm('Supprimer cette ressource ?')) {
      await client.delete(`/ressources/${id}`);
      setRessources((prev) => prev.filter((r) => r.id !== id));
    }
  }

  return (
    <div style={{ marginBottom: '32px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h3 style={{ margin: 0, fontSize: '16px', fontWeight: 600, color: '#111827' }}>📚 Ressources pédagogiques</h3>
        <p style={{ margin: 0, fontSize: '12px', color: '#6b7280' }}>{ressources.length} ressource(s)</p>
      </div>

      {ressources.length > 0 && (
        <div style={{ display: 'grid', gap: '12px', marginBottom: '20px' }}>
          {ressources.map((r) => (
            <div key={r.id} style={{ background: '#f9fafb', padding: '12px', borderRadius: '8px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <a href={r.url_ressource} target="_blank" rel="noreferrer" style={{ color: '#2563eb', textDecoration: 'none', fontWeight: 500 }}>
                  {r.titre_ressource}
                </a>
                <span style={{ marginLeft: '8px', fontSize: '12px', color: '#6b7280' }}>({r.type_ressource})</span>
              </div>
              <button onClick={() => handleDelete(r.id)} style={{ background: '#fee2e2', color: '#991b1b', border: 'none', padding: '4px 8px', borderRadius: '4px', cursor: 'pointer', fontSize: '12px', fontWeight: 500 }}>
                Supprimer
              </button>
            </div>
          ))}
        </div>
      )}

      {!showForm && (
        <button
          onClick={() => setShowForm(true)}
          style={{ padding: '10px 16px', background: '#dbeafe', color: '#1e40af', border: 'none', borderRadius: '6px', cursor: 'pointer', fontWeight: 500, fontSize: '13px' }}
        >
          ➕ Ajouter une ressource
        </button>
      )}

      {showForm && (
        <form onSubmit={handleAdd} style={{ background: '#f0f9ff', padding: '16px', borderRadius: '8px', display: 'grid', gap: '12px' }}>
          <input placeholder="Titre" value={titre} onChange={(e) => setTitre(e.target.value)} required style={{ ...inputStyle, padding: '8px 12px' }} />
          <input placeholder="https://…" value={url} onChange={(e) => setUrl(e.target.value)} required style={{ ...inputStyle, padding: '8px 12px' }} />
          <select value={type} onChange={(e) => setType(e.target.value)} style={{ ...inputStyle, padding: '8px 12px' }}>
            <option value="video">Vidéo</option>
            <option value="outil">Outil</option>
            <option value="document">Document</option>
            <option value="jeu">Jeu</option>
          </select>
          <div style={{ display: 'flex', gap: '8px' }}>
            <button type="submit" style={{ flex: 1, padding: '8px 12px', background: '#2563eb', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontWeight: 500, fontSize: '13px' }}>
              Ajouter
            </button>
            <button type="button" onClick={() => setShowForm(false)} style={{ flex: 1, padding: '8px 12px', background: 'white', color: '#6b7280', border: '1px solid #e5e7eb', borderRadius: '6px', cursor: 'pointer', fontWeight: 500, fontSize: '13px' }}>
              Annuler
            </button>
          </div>
        </form>
      )}
    </div>
  );
}

function LoadingState() {
  return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh', background: 'linear-gradient(135deg, #f5f7fa 0%, #f0f4f8 100%)' }}>
      <div style={{ textAlign: 'center' }}>
        <div style={{ fontSize: '48px', marginBottom: '16px', animation: 'spin 1s linear infinite' }}>⏳</div>
        <p style={{ color: '#6b7280', fontSize: '16px' }}>Chargement en cours...</p>
      </div>
      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}
