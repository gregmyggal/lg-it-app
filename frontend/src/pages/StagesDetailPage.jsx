import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import client from '../api/client';
import { DetailSection, DetailCards, SidebarBox } from '../components/DetailPageSection';

export default function StagesDetailPage() {
  const { slug } = useParams();
  const [stage, setStage] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client
      .get('/public/stages')
      .then((res) => {
        const found = res.data.find((s) => s.slug === slug);
        if (found) setStage(found);
        else setError('Stage non trouvé.');
      })
      .catch(() => setError('Impossible de charger ce stage.'));
  }, [slug]);

  if (error) return <p className="error">{error}</p>;
  if (stage === null) return <p>Chargement…</p>;

  const apropos = stage.section_apropos || 'Contenu à venir...';
  const programme = Array.isArray(stage.section_programme) ? stage.section_programme : [];
  const strengths = Array.isArray(stage.section_strengths) ? stage.section_strengths : [];
  const infosBox = stage.sidebar_infos || { dates: '', tarif: '', horaires: '' };
  const inclus = Array.isArray(stage.sidebar_inclus) ? stage.sidebar_inclus : [];

  return (
    <div>
      {/* Hero */}
      <section style={{ background: 'linear-gradient(135deg, var(--cta) 0%, #5a4a99 100%)', color: 'white', padding: '80px 24px' }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
          <div style={{ maxWidth: '700px' }}>
            <div style={{ fontSize: '14px', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.06em', opacity: 0.9, marginBottom: '16px' }}>
              Stage de vacances
            </div>
            <h1 style={{ margin: '0 0 24px 0', fontSize: 'clamp(36px, 5vw, 48px)', lineHeight: '1.15' }}>{stage.titre}</h1>
            <p style={{ margin: '0 0 32px 0', fontSize: '18px', lineHeight: '1.6', opacity: 0.95 }}>
              {stage.description || 'Une semaine intensive pour apprendre et progresser rapidement en s\'amusant.'}
            </p>
            <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
              <button className="btn" style={{ background: 'white', color: 'var(--cta)', fontWeight: '700' }}>
                S'inscrire
              </button>
              <button className="btn" style={{ background: 'rgba(255,255,255,0.2)', border: '2px solid white', color: 'white' }}>
                Questions ?
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* Contenu */}
      <section style={{ maxWidth: '1100px', margin: '0 auto', padding: '80px 24px' }}>
        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '60px', alignItems: 'start' }}>
          <div>
            <DetailSection title="À propos du stage">
              <p style={{ fontSize: '17px', lineHeight: '1.8', color: 'var(--text-muted)' }}>
                {apropos}
              </p>
            </DetailSection>

            {programme.length > 0 && (
              <DetailSection title="Programme du stage">
                <DetailCards items={programme} />
              </DetailSection>
            )}

            {strengths.length > 0 && (
              <DetailSection title="Points forts">
                <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '16px' }}>
                  {strengths.map((strength, idx) => (
                    <li key={idx} style={{ display: 'flex', gap: '12px' }}>
                      <span style={{ color: 'var(--cta)', fontWeight: '700', flex: '0 0 auto' }}>⚡</span>
                      <span>{strength}</span>
                    </li>
                  ))}
                </ul>
              </DetailSection>
            )}
          </div>

          {/* Sidebar */}
          <div>
            <div style={{ position: 'sticky', top: '24px' }}>
              <SidebarBox title="Dates et tarifs">
                {infosBox.dates && (
                  <div style={{ marginBottom: '24px' }}>
                    <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Dates</p>
                    <p style={{ margin: 0, fontSize: '16px', fontWeight: '600' }}>{infosBox.dates}</p>
                  </div>
                )}
                {infosBox.tarif && (
                  <div style={{ marginBottom: '24px' }}>
                    <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Tarif</p>
                    <p style={{ margin: 0, fontSize: '24px', fontWeight: '700', color: 'var(--cta)' }}>{infosBox.tarif}</p>
                  </div>
                )}
                {infosBox.horaires && (
                  <div style={{ marginBottom: '24px' }}>
                    <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Horaires</p>
                    <p style={{ margin: 0, fontSize: '16px', fontWeight: '600' }}>{infosBox.horaires}</p>
                  </div>
                )}
                <button className="btn btn-primary" style={{ width: '100%', marginBottom: '12px' }}>
                  S'inscrire maintenant
                </button>
              </SidebarBox>

              {inclus.length > 0 && (
                <SidebarBox title="Inclus">
                  <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {inclus.map((item, idx) => (
                      <li key={idx} style={{ display: 'flex', gap: '12px', fontSize: '14px' }}>
                        <span style={{ flex: '0 0 auto' }}>✓</span>
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                </SidebarBox>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* CTA final */}
      <section style={{ background: 'var(--surface)', padding: '80px 24px', textAlign: 'center', borderTop: '1px solid var(--border)' }}>
        <div style={{ maxWidth: '700px', margin: '0 auto' }}>
          <h2 style={{ marginBottom: '24px' }}>Places limitées</h2>
          <p style={{ fontSize: '17px', color: 'var(--text-muted)', marginBottom: '32px', lineHeight: '1.6' }}>
            Les stages se remplissent vite ! Assure-toi ta place dès maintenant.
          </p>
          <button className="btn btn-primary" style={{ fontSize: '16px', padding: '12px 28px' }}>
            M'inscrire au stage
          </button>
        </div>
      </section>
    </div>
  );
}
