export default function FondersPage() {
  const founders = [
    {
      name: 'Grégory Pierquin',
      role: 'Fondateur & Directeur',
      bio: 'Développeur passionné avec plus de 10 ans d\'expérience dans le web. Co-créateur du réseau Logiscool Pays Vert.',
      expertise: 'Web, Leadership, Innovation',
    },
  ];

  return (
    <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '60px 24px' }}>
      <div style={{ maxWidth: '700px', margin: '0 auto' }}>
        <h1 style={{ textAlign: 'center', marginBottom: '16px' }}>Les fondateurs</h1>
        <p style={{ textAlign: 'center', fontSize: '17px', color: 'var(--text-muted)', marginBottom: '60px', lineHeight: '1.6' }}>
          Découvrez les visionnaires derrière Logiscool Pays Vert et LG-IT. Des passionnés du numérique qui ont décidé de partager leur savoir.
        </p>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '40px' }}>
          {founders.map((founder, idx) => (
            <div key={idx} style={{ textAlign: 'center' }}>
              <div
                style={{
                  width: '160px',
                  height: '160px',
                  margin: '0 auto 24px',
                  background: 'linear-gradient(135deg, var(--accent) 0%, var(--cta) 100%)',
                  borderRadius: '50%',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  color: 'white',
                  fontSize: '64px',
                  fontWeight: '700',
                }}
              >
                {founder.name.charAt(0)}
              </div>
              <h2 style={{ margin: '0 0 4px 0', fontSize: '24px' }}>{founder.name}</h2>
              <p style={{ margin: '0 0 16px 0', color: 'var(--cta)', fontWeight: '700', fontSize: '14px' }}>{founder.role}</p>
              <p style={{ margin: '0 0 16px 0', fontSize: '15px', lineHeight: '1.6', color: 'var(--text-muted)' }}>{founder.bio}</p>
              <p style={{ margin: '0', fontSize: '13px', padding: '12px 16px', background: 'var(--surface)', borderRadius: '6px' }}>
                <strong>Expertise:</strong> {founder.expertise}
              </p>
            </div>
          ))}
        </div>

        <div style={{ marginTop: '80px', padding: '40px', background: 'var(--surface)', borderRadius: '12px' }}>
          <h2 style={{ marginBottom: '24px' }}>Notre parcours</h2>
          <div style={{ display: 'grid', gap: '20px' }}>
            <div style={{ paddingLeft: '24px', borderLeft: '3px solid var(--accent)' }}>
              <h3 style={{ margin: '0 0 8px 0', fontSize: '16px' }}>2015 — Fondation</h3>
              <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px' }}>Création du réseau Logiscool avec pour mission de rendre la programmation accessible à tous.</p>
            </div>
            <div style={{ paddingLeft: '24px', borderLeft: '3px solid var(--cta)' }}>
              <h3 style={{ margin: '0 0 8px 0', fontSize: '16px' }}>2018 — Expansion</h3>
              <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px' }}>Ouverture de Logiscool Pays Vert à Ath, Belgique. Premiers cours pour enfants et adolescents.</p>
            </div>
            <div style={{ paddingLeft: '24px', borderLeft: '3px solid var(--teal)' }}>
              <h3 style={{ margin: '0 0 8px 0', fontSize: '16px' }}>2020 — Diversification</h3>
              <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px' }}>Lancement des formations pour adultes et services B2B via LG-IT.</p>
            </div>
            <div style={{ paddingLeft: '24px', borderLeft: '3px solid #5a4a99' }}>
              <h3 style={{ margin: '0 0 8px 0', fontSize: '16px' }}>2024 — Innovation</h3>
              <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px' }}>Plateforme numérique nouvelle génération et renforcement de nos services entreprises.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
