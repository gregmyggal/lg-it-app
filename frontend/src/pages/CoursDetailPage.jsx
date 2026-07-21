import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import client from '../api/client';
import { DetailSection, DetailCheckList, DetailCards, SidebarBox } from '../components/DetailPageSection';

export default function CoursDetailPage() {
  const { slug } = useParams();
  const [cours, setCours] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client
      .get('/public/cours')
      .then((res) => {
        const found = res.data.find((c) => c.slug === slug);
        if (found) setCours(found);
        else setError('Cours non trouvé.');
      })
      .catch(() => setError('Impossible de charger ce cours.'));
  }, [slug]);

  if (error) return <p className="error">{error}</p>;
  if (cours === null) return <p>Chargement…</p>;

  const typeNames = cours.types_cours?.map((t) => t.nom).join(', ') || 'Cours';
  const apropos = cours.section_apropos || 'Contenu à venir...';
  const apprendras = Array.isArray(cours.section_apprendras) ? cours.section_apprendras : [];
  const format = Array.isArray(cours.section_format) ? cours.section_format : [];
  const pourqui = cours.section_pourqui || { text: '', items: [] };
  const infosPratiques = cours.sidebar_pratiques || { type: typeNames, niveau: 'Tous niveaux', langue: 'Français' };
  const benefits = Array.isArray(cours.sidebar_benefits) ? cours.sidebar_benefits : [];

  return (
    <div>
      {/* Hero */}
      <section style={{ background: 'linear-gradient(135deg, var(--accent) 0%, var(--cta) 100%)', color: 'white', padding: '80px 24px' }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
          <div style={{ maxWidth: '700px' }}>
            <div style={{ fontSize: '14px', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.06em', opacity: 0.9, marginBottom: '16px' }}>
              Cours régulier
            </div>
            <h1 style={{ margin: '0 0 24px 0', fontSize: 'clamp(36px, 5vw, 48px)', lineHeight: '1.15' }}>{cours.titre}</h1>
            <p style={{ margin: '0 0 32px 0', fontSize: '18px', lineHeight: '1.6', opacity: 0.95 }}>
              {cours.description || 'Un parcours d\'apprentissage structuré et progressif pour tous les niveaux.'}
            </p>
            <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
              <button className="btn" style={{ background: 'white', color: 'var(--accent)', fontWeight: '700' }}>
                S'inscrire maintenant
              </button>
              <button className="btn" style={{ background: 'rgba(255,255,255,0.2)', border: '2px solid white', color: 'white' }}>
                Demander plus d'infos
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* Contenu principal */}
      <section style={{ maxWidth: '1100px', margin: '0 auto', padding: '80px 24px' }}>
        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '60px', alignItems: 'start' }}>
          <div>
            <DetailSection title="À propos de ce cours">
              <p style={{ fontSize: '17px', lineHeight: '1.8', color: 'var(--text-muted)', marginBottom: '20px' }}>
                {apropos}
              </p>
            </DetailSection>

            {apprendras.length > 0 && (
              <DetailSection title="Ce que tu apprendras">
                <DetailCheckList items={apprendras} />
              </DetailSection>
            )}

            {format.length > 0 && (
              <DetailSection title="Format et horaires">
                <DetailCards items={format} />
              </DetailSection>
            )}

            {pourqui && (
              <DetailSection title="Pour qui ?">
                {typeof pourqui === 'object' && pourqui.text && (
                  <p style={{ fontSize: '17px', lineHeight: '1.8', color: 'var(--text-muted)', marginBottom: '20px' }}>
                    {pourqui.text}
                  </p>
                )}
                {pourqui.items && pourqui.items.length > 0 && (
                  <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {pourqui.items.map((item, idx) => (
                      <li key={idx} style={{ display: 'flex', gap: '12px' }}>
                        <span style={{ flex: '0 0 auto' }}>🎯</span>
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </DetailSection>
            )}
          </div>

          {/* Sidebar */}
          <div>
            <div style={{ position: 'sticky', top: '24px' }}>
              <SidebarBox title="Infos pratiques">
                <div style={{ marginBottom: '24px' }}>
                  <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Type</p>
                  <p style={{ margin: 0, fontSize: '16px', fontWeight: '600' }}>{infosPratiques.type || typeNames}</p>
                </div>
                <div style={{ marginBottom: '24px' }}>
                  <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Niveau</p>
                  <p style={{ margin: 0, fontSize: '16px', fontWeight: '600' }}>{infosPratiques.niveau || 'Tous niveaux'}</p>
                </div>
                <div style={{ marginBottom: '24px' }}>
                  <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Langue</p>
                  <p style={{ margin: 0, fontSize: '16px', fontWeight: '600' }}>{infosPratiques.langue || 'Français'}</p>
                </div>
                <button className="btn btn-primary" style={{ width: '100%', marginBottom: '12px' }}>
                  S'inscrire
                </button>
                <button className="btn" style={{ width: '100%' }}>
                  Demander info
                </button>
              </SidebarBox>

              {benefits.length > 0 && (
                <SidebarBox title="Pourquoi ce cours ?">
                  <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {benefits.map((benefit, idx) => (
                      <li key={idx} style={{ display: 'flex', gap: '12px', fontSize: '14px' }}>
                        <span style={{ flex: '0 0 auto' }}>✨</span>
                        <span>{benefit}</span>
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
          <h2 style={{ marginBottom: '24px' }}>Prêt à commencer ?</h2>
          <p style={{ fontSize: '17px', color: 'var(--text-muted)', marginBottom: '32px', lineHeight: '1.6' }}>
            Rejoins des centaines d'élèves qui progressent et s'amusent avec nous.
          </p>
          <button className="btn btn-primary" style={{ fontSize: '16px', padding: '12px 28px' }}>
            M'inscrire maintenant
          </button>
        </div>
      </section>
    </div>
  );
}
