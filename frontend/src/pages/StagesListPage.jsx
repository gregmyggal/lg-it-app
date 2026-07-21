import { useEffect, useState } from 'react';
import client from '../api/client';

export default function StagesListPage() {
  const [stages, setStages] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client
      .get('/public/stages')
      .then((res) => setStages(res.data))
      .catch(() => setError('Impossible de charger les stages.'));
  }, []);

  if (error) return <p className="error">{error}</p>;
  if (stages === null) return <p>Chargement…</p>;

  return (
    <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '60px 24px' }}>
      <h1 style={{ marginBottom: '48px' }}>Stages vacances</h1>
      {stages.length === 0 && <p>Aucun stage disponible pour le moment.</p>}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '24px' }}>
        {stages.map((s) => (
          <div
            key={s.id}
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
            <h3 style={{ margin: '0 0 12px 0', fontSize: '20px', fontWeight: '700' }}>{s.titre}</h3>
            <p style={{ margin: '0 0 4px 0', color: 'var(--text-muted)', fontSize: '14px' }}>
              <strong>Période:</strong> {s.date_debut} à {s.date_fin}
            </p>
            <p style={{ margin: '0 0 16px 0', color: 'var(--text-muted)', fontSize: '14px' }}>
              <strong>Tarif:</strong> {s.tarif}€
            </p>
            {s.description && (
              <p style={{ margin: '0 0 16px 0', fontSize: '14px', lineHeight: '1.6' }}>{s.description}</p>
            )}
            <a href={`/stages/${s.slug}`} className="btn btn-primary" style={{ display: 'inline-block' }}>
              En savoir plus
            </a>
          </div>
        ))}
      </div>
    </div>
  );
}
