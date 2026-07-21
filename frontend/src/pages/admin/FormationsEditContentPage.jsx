import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import client from '../../api/client';
import ListItemEditor from '../../components/ListItemEditor';

const SECTIONS = [
  { id: 'apropos', label: 'À propos', icon: '📖', color: '#0891b2' },
  { id: 'competences', label: 'Compétences', icon: '🎯', color: '#7c3aed' },
  { id: 'approche', label: 'Notre approche', icon: '📚', color: '#db2777' },
  { id: 'parcours', label: 'Votre parcours', icon: '🛤️', color: '#ea580c' },
  { id: 'infos', label: 'Infos clés', icon: 'ℹ️', color: '#2563eb' },
  { id: 'public', label: 'Pour qui ?', icon: '👥', color: '#7c3aed' },
  { id: 'resultats', label: 'Résultats', icon: '✨', color: '#059669' },
];

export default function FormationsEditContentPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [formation, setFormation] = useState(null);
  const [form, setForm] = useState({});
  const [error, setError] = useState(null);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState(false);
  const [expandedSection, setExpandedSection] = useState('apropos');

  useEffect(() => {
    client
      .get(`/formations/${id}`)
      .then((res) => {
        setFormation(res.data);
        setForm({
          section_apropos: res.data.section_apropos || '',
          section_competences: res.data.section_competences || [],
          section_approche: res.data.section_approche || [],
          section_parcours: res.data.section_parcours || [],
          sidebar_infos: res.data.sidebar_infos || { type: '', duree: '', format: '' },
          sidebar_public: res.data.sidebar_public || [],
          sidebar_resultats: res.data.sidebar_resultats || [],
        });
      })
      .catch(() => setError('Impossible de charger cette formation.'));
  }, [id]);

  async function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(false);

    try {
      const payload = {
        section_apropos: form.section_apropos,
        section_competences: form.section_competences,
        section_approche: form.section_approche,
        section_parcours: form.section_parcours,
        sidebar_infos: form.sidebar_infos,
        sidebar_public: form.sidebar_public,
        sidebar_resultats: form.sidebar_resultats,
      };

      await client.put(`/formations/${id}`, payload);
      setSuccess(true);
      setTimeout(() => navigate(`/admin/formations`), 2000);
    } catch (err) {
      setError('Erreur lors de la sauvegarde.');
    } finally {
      setSaving(false);
    }
  }

  function getSectionKey(id) {
    const mapping = {
      apropos: 'section_apropos',
      competences: 'section_competences',
      approche: 'section_approche',
      parcours: 'section_parcours',
      infos: 'sidebar_infos',
      public: 'sidebar_public',
      resultats: 'sidebar_resultats',
    };
    return mapping[id];
  }

  if (!formation) return <LoadingState />;

  return (
    <div style={{ minHeight: '100vh', background: 'linear-gradient(135deg, #f5f7fa 0%, #f0f4f8 100%)' }}>
      {/* Header */}
      <div style={{ background: 'white', borderBottom: '1px solid #e5e7eb', padding: '32px 24px', position: 'sticky', top: 0, zIndex: 100 }}>
        <div style={{ maxWidth: '1400px', margin: '0 auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <h1 style={{ margin: '0 0 8px 0', fontSize: '28px', fontWeight: 700 }}>Éditer les contenus</h1>
            <p style={{ margin: 0, fontSize: '15px', color: '#6b7280' }}>Personnalisez chaque section pour {formation.titre}</p>
          </div>
          <button
            onClick={() => navigate(`/admin/formations`)}
            style={{
              background: 'transparent',
              border: 'none',
              fontSize: '24px',
              cursor: 'pointer',
              color: '#6b7280',
              padding: '8px',
            }}
          >
            ✕
          </button>
        </div>
      </div>

      {/* Messages */}
      {success && <SuccessMessage />}
      {error && <ErrorMessage message={error} />}

      {/* Content */}
      <div style={{ maxWidth: '1400px', margin: '0 auto', padding: '32px 24px', display: 'grid', gridTemplateColumns: '280px 1fr', gap: '32px' }}>
        {/* Sidebar Navigation */}
        <aside style={{ height: 'fit-content', position: 'sticky', top: '120px' }}>
          <div style={{ background: 'white', borderRadius: '12px', overflow: 'hidden', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
            {SECTIONS.map((section) => (
              <button
                key={section.id}
                onClick={() => setExpandedSection(section.id)}
                style={{
                  width: '100%',
                  padding: '16px 20px',
                  border: 'none',
                  background: expandedSection === section.id ? '#f0f9ff' : 'transparent',
                  borderLeft: `4px solid ${expandedSection === section.id ? section.color : 'transparent'}`,
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '12px',
                  transition: 'all 0.2s ease',
                  fontSize: '14px',
                  fontWeight: expandedSection === section.id ? 600 : 500,
                  color: expandedSection === section.id ? section.color : '#6b7280',
                  textAlign: 'left',
                }}
                onMouseEnter={(e) => {
                  if (expandedSection !== section.id) e.target.style.background = '#f9fafb';
                }}
                onMouseLeave={(e) => {
                  if (expandedSection !== section.id) e.target.style.background = 'transparent';
                }}
              >
                <span style={{ fontSize: '18px' }}>{section.icon}</span>
                <span>{section.label}</span>
              </button>
            ))}
          </div>
        </aside>

        {/* Main Editor */}
        <form onSubmit={handleSave} style={{ display: 'grid', gap: '24px' }}>
          {SECTIONS.map((section) => (
            <EditorSection
              key={section.id}
              section={section}
              isExpanded={expandedSection === section.id}
              onToggle={() => setExpandedSection(expandedSection === section.id ? null : section.id)}
              form={form}
              setForm={setForm}
              sectionKey={getSectionKey(section.id)}
            />
          ))}

          {/* Actions */}
          <div style={{ display: 'flex', gap: '12px', paddingTop: '24px', borderTop: '1px solid #e5e7eb' }}>
            <button
              type="submit"
              disabled={saving}
              style={{
                padding: '12px 24px',
                background: '#2563eb',
                color: 'white',
                border: 'none',
                borderRadius: '8px',
                fontWeight: 600,
                cursor: saving ? 'not-allowed' : 'pointer',
                opacity: saving ? 0.6 : 1,
                transition: 'all 0.2s ease',
                fontSize: '14px',
              }}
              onMouseEnter={(e) => {
                if (!saving) e.target.style.background = '#1d4ed8';
              }}
              onMouseLeave={(e) => {
                if (!saving) e.target.style.background = '#2563eb';
              }}
            >
              {saving ? '💾 Sauvegarde en cours…' : '✓ Sauvegarder les contenus'}
            </button>
            <button
              type="button"
              onClick={() => navigate(`/admin/formations`)}
              style={{
                padding: '12px 24px',
                background: 'white',
                color: '#6b7280',
                border: '1px solid #e5e7eb',
                borderRadius: '8px',
                fontWeight: 600,
                cursor: 'pointer',
                transition: 'all 0.2s ease',
                fontSize: '14px',
              }}
              onMouseEnter={(e) => {
                e.target.style.background = '#f9fafb';
                e.target.style.borderColor = '#d1d5db';
              }}
              onMouseLeave={(e) => {
                e.target.style.background = 'white';
                e.target.style.borderColor = '#e5e7eb';
              }}
            >
              Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

function EditorSection({ section, isExpanded, onToggle, form, setForm, sectionKey }) {
  const value = form[sectionKey] || '';

  return (
    <div
      style={{
        background: 'white',
        borderRadius: '12px',
        overflow: 'hidden',
        boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
        transition: 'all 0.3s ease',
        border: `1px solid #e5e7eb`,
      }}
    >
      <button
        type="button"
        onClick={onToggle}
        style={{
          width: '100%',
          padding: '20px 24px',
          background: isExpanded ? '#f9fafb' : 'white',
          border: 'none',
          cursor: 'pointer',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          transition: 'all 0.2s ease',
        }}
        onMouseEnter={(e) => {
          if (!isExpanded) e.currentTarget.style.background = '#f9fafb';
        }}
        onMouseLeave={(e) => {
          if (!isExpanded) e.currentTarget.style.background = 'white';
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          <span style={{ fontSize: '20px' }}>{section.icon}</span>
          <h3 style={{ margin: 0, fontSize: '16px', fontWeight: 600, color: '#111827' }}>{section.label}</h3>
        </div>
        <span style={{ fontSize: '18px', transition: 'transform 0.3s ease', transform: isExpanded ? 'rotate(180deg)' : 'rotate(0deg)' }}>
          ▼
        </span>
      </button>

      {isExpanded && (
        <div style={{ padding: '24px', borderTop: '1px solid #e5e7eb', animation: 'slideDown 0.3s ease' }}>
          <ListItemEditor
            sectionKey={sectionKey}
            value={value}
            onChange={(newValue) => setForm({ ...form, [sectionKey]: newValue })}
            label={section.label}
          />
        </div>
      )}

      <style>
        {`
          @keyframes slideDown {
            from {
              opacity: 0;
              transform: translateY(-10px);
            }
            to {
              opacity: 1;
              transform: translateY(0);
            }
          }
        `}
      </style>
    </div>
  );
}

function SuccessMessage() {
  return (
    <div
      style={{
        background: '#ecfdf5',
        borderBottom: '2px solid #10b981',
        padding: '16px 24px',
        animation: 'slideDown 0.3s ease',
      }}
    >
      <div style={{ maxWidth: '1400px', margin: '0 auto', display: 'flex', alignItems: 'center', gap: '12px', color: '#047857' }}>
        <span style={{ fontSize: '20px' }}>✓</span>
        <p style={{ margin: 0, fontWeight: 500 }}>Contenus sauvegardés avec succès ! Redirection en cours...</p>
      </div>
    </div>
  );
}

function ErrorMessage({ message }) {
  return (
    <div
      style={{
        background: '#fef2f2',
        borderBottom: '2px solid #ef4444',
        padding: '16px 24px',
        animation: 'slideDown 0.3s ease',
      }}
    >
      <div style={{ maxWidth: '1400px', margin: '0 auto', display: 'flex', alignItems: 'center', gap: '12px', color: '#991b1b' }}>
        <span style={{ fontSize: '20px' }}>⚠</span>
        <p style={{ margin: 0, fontWeight: 500 }}>{message}</p>
      </div>
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
