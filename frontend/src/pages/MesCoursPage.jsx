import { useEffect, useState } from 'react';
import client from '../api/client';
import AdminModal from '../components/AdminModal';
import AdminButton from '../components/AdminButton';
import { AdminFormField, AdminInput, AdminSelect } from '../components/AdminFormField';
import {
  AdminPageHeader,
  AdminPageContent,
  AdminCardGrid,
  AdminCard,
  AdminCardHeader,
  AdminCardBody,
  AdminCardFooter,
  AdminBadge,
} from '../components/AdminPageLayout';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';

const TYPE_OPTIONS = [
  { value: 'video', label: '🎥 Vidéo' },
  { value: 'outil', label: '🛠️ Outil' },
  { value: 'document', label: '📄 Document' },
  { value: 'jeu', label: '🎮 Jeu' },
];

export default function MesCoursPage() {
  const [cours, setCours] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [resourceForm, setResourceForm] = useState({
    coursId: null,
    titre: '',
    url: '',
    type: 'outil',
  });
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    client
      .get('/cours')
      .then((res) => setCours(res.data))
      .catch(() => setError('Impossible de charger vos cours.'));
  }, []);

  function openAddResourceModal(coursId) {
    setResourceForm({
      coursId,
      titre: '',
      url: '',
      type: 'outil',
    });
    setError(null);
    setIsModalOpen(true);
  }

  function closeModal() {
    setIsModalOpen(false);
    setResourceForm({ coursId: null, titre: '', url: '', type: 'outil' });
    setError(null);
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const res = await client.post(`/cours/${resourceForm.coursId}/ressources`, {
        titre_ressource: resourceForm.titre,
        url_ressource: resourceForm.url,
        type_ressource: resourceForm.type,
      });
      setCours((prev) =>
        prev.map((c) =>
          c.id === resourceForm.coursId
            ? { ...c, ressources: [...c.ressources, res.data] }
            : c
        )
      );
      setSuccess('Ressource ajoutée');
      setTimeout(() => setSuccess(null), 2000);
      closeModal();
    } catch {
      setError("Impossible d'ajouter la ressource.");
    } finally {
      setIsSubmitting(false);
    }
  }

  if (error && !success) {
    return (
      <div style={{ padding: '24px' }}>
        <p style={{ color: ADMIN_COLORS.error }}>⚠️ {error}</p>
      </div>
    );
  }

  if (cours === null) {
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
        title="Mes cours"
        description="Retrouvez ici tous vos cours assignés et les ressources"
        badge={`${cours.length} cours`}
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

        <AdminCardGrid emptyMessage="Aucun cours ne vous est assigné.">
          {cours.map((c) => (
            <AdminCard key={c.id}>
              <AdminCardHeader
                title={c.titre}
                subtitle={c.slug}
              />
              <AdminCardBody>
                {c.types_cours && c.types_cours.length > 0 && (
                  <div style={{ marginBottom: '12px', display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                    {c.types_cours.map((t) => (
                      <AdminBadge key={t.id} label={t.nom} color="blue" />
                    ))}
                  </div>
                )}

                {c.ressources && c.ressources.length > 0 ? (
                  <div>
                    <p style={{ fontSize: '12px', fontWeight: 600, color: '#6b7280', marginBottom: '8px' }}>
                      Ressources ({c.ressources.length}):
                    </p>
                    <ul style={{ margin: '0', paddingLeft: '20px' }}>
                      {c.ressources.map((r) => (
                        <li key={r.id} style={{ fontSize: '13px', marginBottom: '6px' }}>
                          <a
                            href={r.url_ressource}
                            target="_blank"
                            rel="noreferrer"
                            style={{ color: ADMIN_COLORS.primary, textDecoration: 'none' }}
                          >
                            {r.titre_ressource}
                          </a>
                          {' '}
                          <AdminBadge
                            label={r.type_ressource}
                            color="amber"
                          />
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : (
                  <p style={{ fontSize: '13px', color: '#9ca3af', fontStyle: 'italic' }}>
                    Aucune ressource ajoutée
                  </p>
                )}
              </AdminCardBody>
              <AdminCardFooter>
                <AdminButton
                  variant="primary"
                  size="sm"
                  icon="➕"
                  onClick={() => openAddResourceModal(c.id)}
                >
                  Ajouter ressource
                </AdminButton>
              </AdminCardFooter>
            </AdminCard>
          ))}
        </AdminCardGrid>
      </AdminPageContent>

      {/* Modal Add Resource */}
      <AdminModal
        isOpen={isModalOpen}
        title="Ajouter une ressource"
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
              Ajouter
            </AdminButton>
          </div>
        }
      >
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <AdminFormField label="Titre de la ressource" required>
            <AdminInput
              value={resourceForm.titre}
              onChange={(e) => setResourceForm({ ...resourceForm, titre: e.target.value })}
              placeholder="Ex: Tutoriel Python"
              required
            />
          </AdminFormField>

          <AdminFormField label="URL" required>
            <AdminInput
              type="url"
              value={resourceForm.url}
              onChange={(e) => setResourceForm({ ...resourceForm, url: e.target.value })}
              placeholder="https://…"
              required
            />
          </AdminFormField>

          <AdminFormField label="Type de ressource">
            <AdminSelect
              value={resourceForm.type}
              onChange={(e) => setResourceForm({ ...resourceForm, type: e.target.value })}
              options={TYPE_OPTIONS}
            />
          </AdminFormField>
        </form>
      </AdminModal>
    </>
  );
}
