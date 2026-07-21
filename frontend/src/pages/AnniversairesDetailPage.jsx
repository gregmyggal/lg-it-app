import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import client from '../api/client';
import { DetailSection, DetailCards, SidebarBox } from '../components/DetailPageSection';

export default function AnniversairesDetailPage() {
  const { slug } = useParams();
  const [anniversaire, setAnniversaire] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    client
      .get('/public/anniversaires')
      .then((res) => {
        const found = res.data.find((a) => a.slug === slug);
        if (found) setAnniversaire(found);
        else setError('Anniversaire non trouvé.');
      })
      .catch(() => setError('Impossible de charger cet anniversaire.'));
  }, [slug]);

  if (error) return <p className="error">{error}</p>;
  if (anniversaire === null) return <p>Chargement…</p>;

  const apropos = anniversaire.section_apropos || 'Contenu à venir...';
  const deroulement = Array.isArray(anniversaire.section_deroulement) ? anniversaire.section_deroulement : [];
  const pourquoi = Array.isArray(anniversaire.section_pourquoi) ? anniversaire.section_pourquoi : [];
  const tarification = anniversaire.sidebar_tarification || { tranche_age: '', tarif: '', duree: '' };
  const inclus = Array.isArray(anniversaire.sidebar_inclus) ? anniversaire.sidebar_inclus : [];
  const options = anniversaire.sidebar_options;

  return (
    <div>
      {/* Hero */}
      <section style={{ background: 'linear-gradient(135deg, #5a4a99 0%, var(--cta) 100%)', color: 'white', padding: '80px 24px' }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
          <div style={{ maxWidth: '700px' }}>
            <div style={{ fontSize: '14px', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.06em', opacity: 0.9, marginBottom: '16px' }}>
              Anniversaires codés 🎉
            </div>
            <h1 style={{ margin: '0 0 24px 0', fontSize: 'clamp(36px, 5vw, 48px)', lineHeight: '1.15' }}>{anniversaire.nom_theme}</h1>
            <p style={{ margin: '0 0 32px 0', fontSize: '18px', lineHeight: '1.6', opacity: 0.95 }}>
              {anniversaire.description || 'Une célébration unique où apprendre à coder se transforme en fête inoubliable !'}
            </p>
            <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
              <button className="btn" style={{ background: 'white', color: '#5a4a99', fontWeight: '700' }}>
                Réserver maintenant
              </button>
              <button className="btn" style={{ background: 'rgba(255,255,255,0.2)', border: '2px solid white', color: 'white' }}>
                Plus d'infos
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* Contenu */}
      <section style={{ maxWidth: '1100px', margin: '0 auto', padding: '80px 24px' }}>
        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '60px', alignItems: 'start' }}>
          <div>
            <DetailSection title="À propos de ce thème">
              <p style={{ fontSize: '17px', lineHeight: '1.8', color: 'var(--text-muted)' }}>
                {apropos}
              </p>
            </DetailSection>

            {deroulement.length > 0 && (
              <DetailSection title="Comment ça se passe ?">
                <DetailCards items={deroulement} />
              </DetailSection>
            )}

            {pourquoi.length > 0 && (
              <DetailSection title="Pourquoi nos anniversaires ?">
                <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '16px' }}>
                  {pourquoi.map((item, idx) => (
                    <li key={idx} style={{ display: 'flex', gap: '12px' }}>
                      <span style={{ flex: '0 0 auto', fontSize: '20px' }}>🎯</span>
                      <span>{item}</span>
                    </li>
                  ))}
                </ul>
              </DetailSection>
            )}
          </div>

          {/* Sidebar */}
          <div>
            <div style={{ position: 'sticky', top: '24px' }}>
              <SidebarBox title="Tarification">
                {tarification.tranche_age && (
                  <div style={{ marginBottom: '24px' }}>
                    <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Tranche d'âge</p>
                    <p style={{ margin: 0, fontSize: '16px', fontWeight: '600' }}>{tarification.tranche_age}</p>
                  </div>
                )}
                {tarification.tarif && (
                  <div style={{ marginBottom: '24px' }}>
                    <p style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Tarif par enfant</p>
                    <p style={{ margin: 0, fontSize: '24px', fontWeight: '700', color: '#5a4a99' }}>{tarification.tarif}€</p>
                    {tarification.duree && (
                      <p style={{ margin: '4px 0 0 0', fontSize: '13px', color: 'var(--text-muted)' }}>{tarification.duree}</p>
                    )}
                  </div>
                )}
                <button className="btn btn-primary" style={{ width: '100%', marginBottom: '12px' }}>
                  Réserver la fête
                </button>
                <button className="btn" style={{ width: '100%' }}>
                  Demander devis
                </button>
              </SidebarBox>

              {inclus.length > 0 && (
                <SidebarBox title="Ce qui est inclus">
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

              {options && (
                <div style={{ padding: '32px', background: '#FDF1E8', border: '1px solid #F0E6D8', borderRadius: '12px' }}>
                  <h3 style={{ margin: '0 0 24px 0', fontSize: '18px', color: 'var(--cta)' }}>Options disponibles</h3>
                  <p style={{ margin: 0, fontSize: '14px', color: 'var(--text-muted)', lineHeight: '1.6' }}>
                    {options}
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* CTA final */}
      <section style={{ background: 'linear-gradient(135deg, #5a4a99 0%, var(--cta) 100%)', color: 'white', padding: '80px 24px', textAlign: 'center' }}>
        <div style={{ maxWidth: '700px', margin: '0 auto' }}>
          <h2 style={{ margin: '0 0 24px 0' }}>Prêt à célébrer ? 🎂</h2>
          <p style={{ margin: '0 0 32px 0', fontSize: '17px', lineHeight: '1.6', opacity: 0.95 }}>
            Réserve maintenant et garantis une fête inoubliable !
          </p>
          <button className="btn" style={{ background: 'white', color: '#5a4a99', fontWeight: '700', fontSize: '16px', padding: '12px 28px' }}>
            Réserver l'anniversaire
          </button>
        </div>
      </section>
    </div>
  );
}
