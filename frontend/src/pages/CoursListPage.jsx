import { useEffect, useState } from 'react';
import client from '../api/client';

export default function CoursListPage() {
  const [cours, setCours] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client
      .get('/public/cours')
      .then((res) => setCours(res.data))
      .catch(() => setError('Impossible de charger les cours.'));
  }, []);

  if (error) return <p className="error">{error}</p>;
  if (cours === null) return <p>Chargement…</p>;

  return (
    <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '60px 24px' }}>
      <h1 style={{ marginBottom: '48px' }}>Cours réguliers</h1>
      {cours.length === 0 && <p>Aucun cours disponible pour le moment.</p>}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '24px' }}>
        {cours.map((c) => (
          <div
            key={c.id}
            style={{
              border: '1px solid var(--border)',
              borderRadius: '12px',
              padding: '24px',
              background: 'var(--surface)',
              transition: 'box-shadow 0.2s, transform 0.2s',
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.boxShadow = 'var(--shadow-md)';
              e.currentTarget.style.transform = 'translateY(-4px)';
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.boxShadow = 'none';
              e.currentTarget.style.transform = 'translateY(0)';
            }}
          >
            <h3 style={{ margin: '0 0 12px 0', fontSize: '20px', fontWeight: '700' }}>{c.titre}</h3>
            <p style={{ margin: '0 0 16px 0', color: 'var(--text-muted)', fontSize: '14px' }}>
              {c.types_cours?.map((t) => t.nom).join(', ') || 'Aucun type'}
            </p>
            {c.description && (
              <p style={{ margin: '0 0 16px 0', fontSize: '14px', lineHeight: '1.6' }}>{c.description}</p>
            )}
            <a href={`/cours/${c.slug}`} className="btn btn-primary" style={{ display: 'inline-block', textDecoration: 'none' }}>
              En savoir plus
            </a>
          </div>
        ))}
      </div>
    </div>
  );
}
