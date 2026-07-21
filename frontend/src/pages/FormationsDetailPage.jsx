import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import client from '../api/client';
import { DetailSection, DetailCards, SidebarBox } from '../components/DetailPageSection';

export default function FormationsDetailPage() {
  const { slug } = useParams();
  const [formation, setFormation] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client
      .get('/public/formations')
      .then((res) => {
        const found = res.data.find((f) => f.slug === slug);
        if (found) setFormation(found);
        else setError('Formation non trouvée.');
      })
      .catch(() => setError('Impossible de charger cette formation.'));
  }, [slug]);

  if (error) return <p className="error">{error}</p>;
  if (formation === null) return <p>Chargement…</p>;

  const apropos = formation.section_apropos || 'Contenu à venir...';
  const competences = Array.isArray(formation.section_competences) ? formation.section_competences : [];
  const approche = Array.isArray(formation.section_approche) ? formation.section_approche : [];
  const parcours = Array.isArray(formation.section_parcours) ? formation.section_parcours : [];
  const infos = formation.sidebar_infos || { type: '', duree: '', format: '' };
  const public_list = Array.isArray(formation.sidebar_public) ? formation.sidebar_public : [];
  const resultats = Array.isArray(formation.sidebar_resultats) ? formation.sidebar_resultats : [];

  return (
    <div>
      {/* Hero */}
      <section style={{ background: 'linear-gradient(135deg, var(--teal) 0%, var(--accent) 100%)', color: 'white', padding: '80px 24px' }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
          <div style={{ maxWidth: '700px' }}>
            <div style={{ fontSize: '14px', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.06em', opacity: 0.9, marginBottom: '16px' }}>
              Formation professionnelle
            </div>
            <h1 style={{ margin: '0 0 24px 0', fontSize: 'clamp(36px, 5vw, 48px)', lineHeight: '1.15' }}>{formation.titre}</h1>
            <p style={{ margin: '0 0 32px 0', fontSize: '18px', lineHeight: '1.6', opacity: 0.95 }}>
              {formation.description || 'Une formation sur-mesure pour développer vos compétences professionnelles.'}
            </p>
            <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
              <button className="btn" style={{ background: 'white', color: 'var(--teal)', fontWeight: '700' }}>
                Demander un devis
              </button>
              <button className="btn" style={{ background: 'rgba(255,255,255,0.2)', border: '2px solid white', color: 'white' }}>
                Contactez-nous
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* Contenu */}
      <section style={{ maxWidth: '1100px', margin: '0 auto', padding: '80px 24px' }}>
        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '60px', alignItems: 'start' }}>
          <div>
            <DetailSection title="À propos de cette formation">
              <p style={{ fontSize: '17px', lineHeight: '1.8', color: 'var(--text-muted)' }}>
                {apropos}
              </p>
            </DetailSection>

            {competences.length > 0 && (
              <DetailSection title="Compétences développées">
                <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                  {competences.map((comp, idx) => (
                    <li key={idx} style={{ display: 'flex', gap: '12px' }}>
                      <span style={{ color: 'var(--teal)', fontWeight: '700', flex: '0 0 auto' }}>→</span>
                      <span>{comp}</span>
                    </li>
                  ))}
                </ul>
              </DetailSection>
            )}

            {approche.length > 0 && (
              <DetailSection title="Notre approche">
                <div style={{ display: 'grid', gap: '16px' }}>
                  {approche.map((item, idx) => (
                    <div key={idx} style={{ padding: '24px', background: 'var(--teal-bg)', borderRadius: '12px', borderLeft: '4px solid var(--teal)' }}>
                      <h3 style={{ margin: '0 0 8px 0', fontSize: '16px', color: 'var(--accent)' }}>{item.titre}</h3>
                      <p style={{ margin: 0, fontSize: '14px', color: 'var(--text-muted)' }}>{item.contenu}</p>
                    </div>
                  ))}
                </div>
              </DetailSection>
            )}

            {parcours.length > 0 && (
              <DetailSection title="Votre parcours">
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0' }}>
                  {parcours.map((phase, idx) => (
                    <div key={idx} style={{ padding: '20px', borderBottom: idx < parcours.length - 1 ? '1px solid var(--border)' : 'none' }}>
                      <h3 style={{ margin: '0 0 8px 0', fontSize: '16px', fontWeight: '700' }}>{phase.titre}</h3>
                      <p style={{ margin: 0, fontSize: '14px', color: 'var(--text-muted)' }}>{phase.description}</p>
                    </div>
                  ))}
                </div>
              </DetailSection>
            )}
          </div>

          {/* Sidebar */}
          <div>
            <div style={{ position: 'sticky', top: '24px' }}>
              <SidebarBox title="Infos clés">
                {infos.type && (
                  <div style={{ marginBottom: '24px' }}>
                    <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Type</p>
                    <p style={{ margin: 0, fontSize: '16px', fontWeight: '600' }}>{infos.type}</p>
                  </div>
                )}
                {infos.duree && (
                  <div style={{ marginBottom: '24px' }}>
                    <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Durée</p>
                    <p style={{ margin: 0, fontSize: '16px', fontWeight: '600' }}>{infos.duree}</p>
                  </div>
                )}
                {infos.format && (
                  <div style={{ marginBottom: '24px' }}>
                    <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Format</p>
                    <p style={{ margin: 0, fontSize: '16px', fontWeight: '600' }}>{infos.format}</p>
                  </div>
                )}
                <button className="btn btn-primary" style={{ width: '100%', marginBottom: '12px' }}>
                  Demander devis
                </button>
                <button className="btn" style={{ width: '100%' }}>
                  Planifier appel
                </button>
              </SidebarBox>

              {public_list.length > 0 && (
                <SidebarBox title="Pour qui ?">
                  <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {public_list.map((item, idx) => (
                      <li key={idx} style={{ display: 'flex', gap: '12px', fontSize: '14px' }}>
                        <span style={{ flex: '0 0 auto' }}>👤</span>
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                </SidebarBox>
              )}

              {resultats.length > 0 && (
                <div style={{ padding: '32px', background: 'linear-gradient(135deg, var(--teal-bg) 0%, #e7f5f2 100%)', border: '1px solid var(--teal)', borderRadius: '12px' }}>
                  <h3 style={{ margin: '0 0 24px 0', fontSize: '18px', color: 'var(--accent)' }}>Résultats attendus</h3>
                  <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {resultats.map((item, idx) => (
                      <li key={idx} style={{ display: 'flex', gap: '12px', fontSize: '14px' }}>
                        <span style={{ color: 'var(--teal)', flex: '0 0 auto' }}>✓</span>
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* CTA final */}
      <section style={{ background: 'linear-gradient(135deg, var(--teal) 0%, var(--accent) 100%)', color: 'white', padding: '80px 24px', textAlign: 'center' }}>
        <div style={{ maxWidth: '700px', margin: '0 auto' }}>
          <h2 style={{ margin: '0 0 24px 0' }}>Transformez votre carrière</h2>
          <p style={{ margin: '0 0 32px 0', fontSize: '17px', lineHeight: '1.6', opacity: 0.95 }}>
            Nos formations vous préparent aux défis d'aujourd'hui et de demain.
          </p>
          <div style={{ display: 'flex', gap: '16px', justifyContent: 'center', flexWrap: 'wrap' }}>
            <button className="btn" style={{ background: 'white', color: 'var(--teal)', fontWeight: '700' }}>
              Demander un devis
            </button>
            <button className="btn" style={{ background: 'rgba(255,255,255,0.2)', border: '2px solid white', color: 'white' }}>
              Parler à un conseiller
            </button>
          </div>
        </div>
      </section>
    </div>
  );
}
