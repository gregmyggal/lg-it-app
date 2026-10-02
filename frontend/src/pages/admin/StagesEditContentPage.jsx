import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import client from '../../api/client';
import ListItemEditor from '../../components/ListItemEditor';

const SECTIONS = [
  { id: 'apropos', label: 'À propos', icon: '📖', color: '#ea580c' },
  { id: 'programme', label: 'Programme', icon: '📅', color: '#7c3aed' },
  { id: 'strengths', label: 'Points forts', icon: '⚡', color: '#db2777' },
  { id: 'infos', label: 'Dates & tarifs', icon: '💰', color: 'var(--c-info)' },
  { id: 'inclus', label: 'Inclus', icon: '✓', color: 'var(--c-success)' },
];

export default function StagesEditContentPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [stage, setStage] = useState(null);
  const [form, setForm] = useState({});
  const [error, setError] = useState(null);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState(false);
  const [expandedSection, setExpandedSection] = useState('apropos');

  useEffect(() => {
    client
      .get(`/stages/${id}`)
      .then((res) => {
        setStage(res.data);
        setForm({
          section_apropos: res.data.section_apropos || '',
          section_programme: res.data.section_programme || [],
          section_strengths: res.data.section_strengths || [],
          sidebar_infos: res.data.sidebar_infos || { dates: '', tarif: '', horaires: '' },
          sidebar_inclus: res.data.sidebar_inclus || [],
        });
      })
      .catch(() => setError('Impossible de charger ce stage.'));
  }, [id]);

  async function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(false);

    try {
      const payload = {
        section_apropos: form.section_apropos,
        section_programme: form.section_programme,
        section_strengths: form.section_strengths,
        sidebar_infos: form.sidebar_infos,
        sidebar_inclus: form.sidebar_inclus,
      };

      await client.put(`/stages/${id}`, payload);
      setSuccess(true);
      setTimeout(() => navigate(`/admin/stages`), 2000);
    } catch (err) {
      setError('Erreur lors de la sauvegarde.');
    } finally {
      setSaving(false);
    }
  }

  function getSectionKey(id) {
    const mapping = {
      apropos: 'section_apropos',
      programme: 'section_programme',
      strengths: 'section_strengths',
      infos: 'sidebar_infos',
      inclus: 'sidebar_inclus',
    };
    return mapping[id];
  }

  if (!stage) return <LoadingState />;

  return (
    <div style={{ minHeight: '100vh', background: 'linear-gradient(135deg, var(--c-bg) 0%, var(--c-bg) 100%)' }}>
      {/* Header */}
      <div style={{ background: 'var(--c-card)', borderBottom: '1px solid var(--c-border)', padding: '32px 24px', position: 'sticky', top: 0, zIndex: 100 }}>
        <div style={{ maxWidth: 'var(--page-max, 1760px)', margin: '0 auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <h1 style={{ margin: '0 0 8px 0', fontSize: '28px', fontWeight: 700 }}>Éditer les contenus</h1>
            <p style={{ margin: 0, fontSize: '15px', color: 'var(--c-text-2)' }}>Personnalisez chaque section pour {stage.titre}</p>
          </div>
          <button
            onClick={() => navigate(`/admin/stages`)}
            style={{
              background: 'transparent',
              border: 'none',
              fontSize: '24px',
              cursor: 'pointer',
              color: 'var(--c-text-2)',
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
      <div style={{ maxWidth: 'var(--page-max, 1760px)', margin: '0 auto', padding: '32px 24px', display: 'grid', gridTemplateColumns: '280px 1fr', gap: '32px' }}>
        {/* Sidebar Navigation */}
        <aside style={{ height: 'fit-content', position: 'sticky', top: '120px' }}>
          <div style={{ background: 'var(--c-card)', borderRadius: '12px', overflow: 'hidden', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
            {SECTIONS.map((section) => (
              <button
                key={section.id}
                onClick={() => setExpandedSection(section.id)}
                style={{
                  width: '100%',
                  padding: '16px 20px',
                  border: 'none',
                  background: expandedSection === section.id ? 'var(--tone-primary-bg)' : 'transparent',
                  borderLeft: `4px solid ${expandedSection === section.id ? section.color : 'transparent'}`,
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '12px',
                  transition: 'all 0.2s ease',
                  fontSize: '14px',
                  fontWeight: expandedSection === section.id ? 600 : 500,
                  color: expandedSection === section.id ? section.color : 'var(--c-text-2)',
                  textAlign: 'left',
                }}
                onMouseEnter={(e) => {
                  if (expandedSection !== section.id) e.target.style.background = 'var(--c-bg)';
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
          <div style={{ display: 'flex', gap: '12px', paddingTop: '24px', borderTop: '1px solid var(--c-border)' }}>
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
              onClick={() => navigate(`/admin/stages`)}
              style={{
                padding: '12px 24px',
                background: 'var(--c-card)',
                color: 'var(--c-text-2)',
                border: '1px solid var(--c-border)',
                borderRadius: '8px',
                fontWeight: 600,
                cursor: 'pointer',
                transition: 'all 0.2s ease',
                fontSize: '14px',
              }}
              onMouseEnter={(e) => {
                e.target.style.background = 'var(--c-bg)';
                e.target.style.borderColor = 'var(--c-border)';
              }}
              onMouseLeave={(e) => {
                e.target.style.background = 'var(--c-card)';
                e.target.style.borderColor = 'var(--c-border)';
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
        background: 'var(--c-card)',
        borderRadius: '12px',
        overflow: 'hidden',
        boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
        transition: 'all 0.3s ease',
        border: `1px solid var(--c-border)`,
      }}
    >
      <button
        type="button"
        onClick={onToggle}
        style={{
          width: '100%',
          padding: '20px 24px',
          background: isExpanded ? 'var(--c-bg)' : 'var(--c-card)',
          border: 'none',
          cursor: 'pointer',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          transition: 'all 0.2s ease',
        }}
        onMouseEnter={(e) => {
          if (!isExpanded) e.currentTarget.style.background = 'var(--c-bg)';
        }}
        onMouseLeave={(e) => {
          if (!isExpanded) e.currentTarget.style.background = 'var(--c-card)';
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          <span style={{ fontSize: '20px' }}>{section.icon}</span>
          <h3 style={{ margin: 0, fontSize: '16px', fontWeight: 600, color: 'var(--c-text)' }}>{section.label}</h3>
        </div>
        <span style={{ fontSize: '18px', transition: 'transform 0.3s ease', transform: isExpanded ? 'rotate(180deg)' : 'rotate(0deg)' }}>
          ▼
        </span>
      </button>

      {isExpanded && (
        <div style={{ padding: '24px', borderTop: '1px solid var(--c-border)', animation: 'slideDown 0.3s ease' }}>
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
        background: 'var(--tone-success-bg)',
        borderBottom: '2px solid #10b981',
        padding: '16px 24px',
        animation: 'slideDown 0.3s ease',
      }}
    >
      <div style={{ maxWidth: 'var(--page-max, 1760px)', margin: '0 auto', display: 'flex', alignItems: 'center', gap: '12px', color: 'var(--tone-success-fg)' }}>
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
        background: 'var(--tone-error-bg)',
        borderBottom: '2px solid #ef4444',
        padding: '16px 24px',
        animation: 'slideDown 0.3s ease',
      }}
    >
      <div style={{ maxWidth: 'var(--page-max, 1760px)', margin: '0 auto', display: 'flex', alignItems: 'center', gap: '12px', color: 'var(--tone-error-fg)' }}>
        <span style={{ fontSize: '20px' }}>⚠</span>
        <p style={{ margin: 0, fontWeight: 500 }}>{message}</p>
      </div>
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
