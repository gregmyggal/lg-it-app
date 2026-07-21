export default function AboutPage() {
  const values = [
    {
      icon: '💡',
      title: 'Innovation',
      description: 'Nous repoussons les limites du possible. La technologie évolue, nos méthodes aussi.',
    },
    {
      icon: '🤝',
      title: 'Bienveillance',
      description: 'Chacun apprend à son rythme. Pas de jugement, juste du soutien et de l\'encouragement.',
    },
    {
      icon: '🎯',
      title: 'Excellence',
      description: 'Nous mettons tout en œuvre pour offrir une expérience de qualité supérieure.',
    },
    {
      icon: '🌱',
      title: 'Croissance',
      description: 'Que ce soit personnel ou professionnel, on grandit ensemble et sans limite.',
    },
    {
      icon: '🔧',
      title: 'Pragmatisme',
      description: 'Pas de théorie vide — du pratique, du concret, de l\'applicable au quotidien.',
    },
    {
      icon: '🌍',
      title: 'Inclusivité',
      description: 'Tous les âges, toutes les expériences, tous les objectifs. Tout le monde a sa place.',
    },
  ];

  return (
    <div>
      {/* Hero */}
      <section style={{ background: 'linear-gradient(135deg, var(--accent) 0%, var(--cta) 100%)', color: 'white', padding: '100px 24px', textAlign: 'center' }}>
        <div style={{ maxWidth: '700px', margin: '0 auto' }}>
          <h1 style={{ margin: '0 0 24px 0', fontSize: 'clamp(36px, 5vw, 48px)' }}>L'esprit LG-IT</h1>
          <p style={{ margin: 0, fontSize: '18px', lineHeight: '1.6', opacity: 0.95 }}>
            Deux entités, une seule vision : rendre le numérique accessible, utile et inspirant pour tous.
          </p>
        </div>
      </section>

      {/* Mission & Vision */}
      <section style={{ maxWidth: '1100px', margin: '0 auto', padding: '80px 24px' }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '60px', alignItems: 'start' }}>
          <div>
            <h2 style={{ marginBottom: '24px' }}>Notre mission</h2>
            <p style={{ fontSize: '17px', lineHeight: '1.8', color: 'var(--text-muted)', marginBottom: '20px' }}>
              Logiscool Pays Vert enseigne la programmation et la pensée informatique aux enfants et adolescents, en les mettant au cœur d'une aventure ludique et structurée.
            </p>
            <p style={{ fontSize: '17px', lineHeight: '1.8', color: 'var(--text-muted)' }}>
              LG-IT accompagne les entreprises et les adultes dans leur transformation numérique, en fournissant expertise, solutions sur-mesure et conseil stratégique.
            </p>
          </div>
          <div style={{ background: 'var(--surface)', padding: '40px', borderRadius: '12px' }}>
            <h2 style={{ marginBottom: '24px' }}>Notre vision</h2>
            <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '16px' }}>
              <li style={{ display: 'flex', gap: '12px' }}>
                <span style={{ color: 'var(--teal)', fontWeight: '700', flex: '0 0 auto' }}>✓</span>
                <span>Un monde où la technologie n'intimide personne</span>
              </li>
              <li style={{ display: 'flex', gap: '12px' }}>
                <span style={{ color: 'var(--teal)', fontWeight: '700', flex: '0 0 auto' }}>✓</span>
                <span>Des générations créatives et confiantes dans leurs compétences numériques</span>
              </li>
              <li style={{ display: 'flex', gap: '12px' }}>
                <span style={{ color: 'var(--teal)', fontWeight: '700', flex: '0 0 auto' }}>✓</span>
                <span>Des entreprises responsables et innovantes</span>
              </li>
              <li style={{ display: 'flex', gap: '12px' }}>
                <span style={{ color: 'var(--teal)', fontWeight: '700', flex: '0 0 auto' }}>✓</span>
                <span>Une communauté d'apprentissage sans frontières</span>
              </li>
            </ul>
          </div>
        </div>
      </section>

      {/* Values */}
      <section style={{ background: 'var(--surface)', padding: '80px 24px', borderTop: '1px solid var(--border)', borderBottom: '1px solid var(--border)' }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
          <h2 style={{ textAlign: 'center', marginBottom: '60px' }}>Nos valeurs fondamentales</h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '32px' }}>
            {values.map((v, idx) => (
              <div key={idx} style={{ textAlign: 'center' }}>
                <div style={{ fontSize: '48px', marginBottom: '16px' }}>{v.icon}</div>
                <h3 style={{ margin: '0 0 12px 0', fontSize: '18px' }}>{v.title}</h3>
                <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px', lineHeight: '1.6' }}>{v.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Why LG-IT */}
      <section style={{ maxWidth: '1100px', margin: '0 auto', padding: '80px 24px' }}>
        <h2 style={{ textAlign: 'center', marginBottom: '60px' }}>Pourquoi nous choisir ?</h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '32px' }}>
          <div style={{ padding: '32px', border: '1px solid var(--border)', borderRadius: '12px' }}>
            <h3 style={{ margin: '0 0 16px 0', fontSize: '18px', color: 'var(--accent)' }}>Expérience reconnue</h3>
            <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px', lineHeight: '1.6' }}>
              Plus de 20 ans d'expertise dans le réseau Logiscool international, adaptée au contexte local belge.
            </p>
          </div>
          <div style={{ padding: '32px', border: '1px solid var(--border)', borderRadius: '12px' }}>
            <h3 style={{ margin: '0 0 16px 0', fontSize: '18px', color: 'var(--cta)' }}>Approche personnalisée</h3>
            <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px', lineHeight: '1.6' }}>
              Chaque parcours est unique. Nous concevons des solutions adaptées à vos besoins spécifiques.
            </p>
          </div>
          <div style={{ padding: '32px', border: '1px solid var(--border)', borderRadius: '12px' }}>
            <h3 style={{ margin: '0 0 16px 0', fontSize: '18px', color: 'var(--teal)' }}>Passionnés par l'impact</h3>
            <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px', lineHeight: '1.6' }}>
              Nous mesurons notre succès par la croissance et la confiance que nous créons.
            </p>
          </div>
          <div style={{ padding: '32px', border: '1px solid var(--border)', borderRadius: '12px' }}>
            <h3 style={{ margin: '0 0 16px 0', fontSize: '18px', color: '#5a4a99' }}>Apprentissage continu</h3>
            <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px', lineHeight: '1.6' }}>
              Les technologies évoluent, notre équipe reste à la pointe de l'innovation.
            </p>
          </div>
          <div style={{ padding: '32px', border: '1px solid var(--border)', borderRadius: '12px' }}>
            <h3 style={{ margin: '0 0 16px 0', fontSize: '18px', color: 'var(--accent)' }}>Communauté active</h3>
            <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px', lineHeight: '1.6' }}>
              Partage, entraide, événements — vous rejoignez une vraie famille d'apprenants.
            </p>
          </div>
          <div style={{ padding: '32px', border: '1px solid var(--border)', borderRadius: '12px' }}>
            <h3 style={{ margin: '0 0 16px 0', fontSize: '18px', color: 'var(--cta)' }}>Accessibilité</h3>
            <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px', lineHeight: '1.6' }}>
              Formats flexibles, tarifs transparents, aucune barrière pour commencer.
            </p>
          </div>
        </div>
      </section>

      {/* CTA */}
      <section style={{ background: 'linear-gradient(135deg, var(--accent) 0%, var(--cta) 100%)', color: 'white', padding: '80px 24px', textAlign: 'center' }}>
        <div style={{ maxWidth: '700px', margin: '0 auto' }}>
          <h2 style={{ margin: '0 0 24px 0' }}>Prêt à nous rejoindre ?</h2>
          <p style={{ margin: '0 0 32px 0', fontSize: '17px', lineHeight: '1.6' }}>
            Que vous soyez enfant, adulte ou entreprise, il y a une place pour vous chez Logiscool Pays Vert et LG-IT.
          </p>
          <div style={{ display: 'flex', gap: '16px', justifyContent: 'center', flexWrap: 'wrap' }}>
            <a href="/" className="btn" style={{ background: 'white', color: 'var(--accent)', fontWeight: '700' }}>
              Voir nos offres
            </a>
            <a href="/contact" className="btn" style={{ background: 'rgba(255,255,255,0.2)', border: '2px solid white', color: 'white' }}>
              Nous contacter
            </a>
          </div>
        </div>
      </section>
    </div>
  );
}
