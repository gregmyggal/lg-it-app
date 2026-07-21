import { useEffect, useState } from 'react';
import client from '../api/client';

export default function FormationsListPage() {
  const [formations, setFormations] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client
      .get('/public/formations')
      .then((res) => setFormations(res.data))
      .catch(() => setError('Impossible de charger les formations.'));
  }, []);

  if (error) return <p className="error">{error}</p>;
  if (formations === null) return <p>Chargement…</p>;

  return (
    <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '60px 24px' }}>
      <h1 style={{ marginBottom: '48px' }}>Formations professionnelles</h1>
      {formations.length === 0 && <p>Aucune formation disponible pour le moment.</p>}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '24px' }}>
        {formations.map((f) => (
          <div
            key={f.id}
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
            <h3 style={{ margin: '0 0 12px 0', fontSize: '20px', fontWeight: '700' }}>{f.titre}</h3>
            <p style={{ margin: '0 0 4px 0', color: 'var(--text-muted)', fontSize: '14px' }}>
              {f.types_formation?.map((t) => t.nom).join(', ') || 'Aucun type'}
            </p>
            <p style={{ margin: '0 0 16px 0', color: 'var(--text-muted)', fontSize: '14px' }}>
              <strong>Durée:</strong> {f.duree || 'À consulter'}
            </p>
            {f.description && (
              <p style={{ margin: '0 0 16px 0', fontSize: '14px', lineHeight: '1.6' }}>{f.description}</p>
            )}
            <a href={`/formations/${f.slug}`} className="btn btn-primary" style={{ display: 'inline-block' }}>
              En savoir plus
            </a>
          </div>
        ))}
      </div>
    </div>
  );
}
