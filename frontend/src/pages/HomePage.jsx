// Page d'accueil marketing de Logiscool

const ICONS = {
  code: (
    <svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M16 10L6 20L16 30M40 10L50 20L40 30M33 6L23 50"
        stroke="currentColor"
        strokeWidth="3"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  ),
  calendar: (
    <svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="8" y="12" width="40" height="36" rx="3" stroke="currentColor" strokeWidth="3" />
      <path d="M16 4V20M40 4V20" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
      <path d="M8 24H48" stroke="currentColor" strokeWidth="3" />
    </svg>
  ),
  building: (
    <svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="12" y="14" width="32" height="32" rx="2" stroke="currentColor" strokeWidth="3" />
      <path d="M18 20H26V28H18V20ZM30 20H38V28H30V20ZM18 30H26V38H18V30ZM30 30H38V38H30V30Z" fill="currentColor" />
      <path d="M12 46H44" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
    </svg>
  ),
  gift: (
    <svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="8" y="14" width="40" height="28" rx="2" stroke="currentColor" strokeWidth="3" />
      <path d="M28 14V42M8 24H48M20 14H36V20H20V14Z" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
      <circle cx="16" cy="18" r="2.5" fill="currentColor" />
      <circle cx="40" cy="18" r="2.5" fill="currentColor" />
    </svg>
  ),
  users: (
    <svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="18" cy="16" r="8" stroke="currentColor" strokeWidth="3" />
      <path d="M6 38C6 31.4 11.4 26 18 26C24.6 26 30 31.4 30 38V42C30 44.2 28.2 46 26 46H10C7.8 46 6 44.2 6 42V38Z" fill="currentColor" opacity="0.3" stroke="currentColor" strokeWidth="3" />
      <circle cx="38" cy="18" r="7" stroke="currentColor" strokeWidth="3" />
      <path d="M28 40C28 34.5 32.5 30 38 30C43.5 30 48 34.5 48 40V44C48 45.1 47.1 46 46 46H30C28.9 46 28 45.1 28 44V40Z" fill="currentColor" opacity="0.3" stroke="currentColor" strokeWidth="3" />
    </svg>
  ),
  rocket: (
    <svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M28 4C28 4 36 12 36 24C36 36 28 48 28 48C28 48 20 36 20 24C20 12 28 4 28 4Z" fill="currentColor" opacity="0.3" stroke="currentColor" strokeWidth="3" />
      <circle cx="28" cy="22" r="4" fill="currentColor" />
      <path d="M22 44L20 52M34 44L36 52" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
    </svg>
  ),
};

function CatalogBrick({ icon, label, color }) {
  return (
    <a
      href="#"
      className="catalog-brick"
      style={{
        '--brick-color': color,
      }}
    >
      <div className="catalog-brick__icon">{icon}</div>
      <h3 className="catalog-brick__label">{label}</h3>
    </a>
  );
}

export default function HomePage() {

  return (
    <div>
      <section className="hero">
        <div className="hero__wrap">
          <div>
            <div className="hero-eyebrow">Logiscool Pays Vert — Ath, Belgique</div>
            <h1>Apprendre à coder en s'amusant, du premier scratch à l'autonomie complète.</h1>
            <p className="lede">
              Qu'on soit enfant en quête d'aventure, adolescent prêt à explorer la logique, professionnel en reconversion ou entreprise cherchant à former ses équipes — Logiscool propose des parcours structurés et adaptés à chaque âge. Découvrez nos écoles de code et commencez votre voyage numérique.
            </p>
            <div className="hero-actions">
              <a href="#catalogue" className="btn btn-primary">
                Voir nos offres
              </a>
              <a href="/connexion" className="btn">
                Espace professeur
              </a>
            </div>
          </div>

          <div className="chrome">
            <div className="chrome-bar">
              <span></span>
              <span></span>
              <span></span>
              <span className="url">logiscool.be/nos-formats</span>
            </div>
            <div className="chrome-body">
              <div className="chrome-title">Nos formats pédagogiques</div>
              <div className="mini-row">
                <span>🧒 Enfants 7-11 ans</span>
                <span className="pill pill-ok">Inscriptions ouvertes</span>
              </div>
              <div className="mini-row">
                <span>👨‍💻 Ados 12-18 ans</span>
                <span className="pill pill-ok">Inscriptions ouvertes</span>
              </div>
              <div className="mini-row">
                <span>☀️ Stages d'été</span>
                <span className="pill pill-sent">Places limitées</span>
              </div>
              <div className="mini-row">
                <span>🎓 Formations entreprises</span>
                <span style={{ color: 'var(--text-muted)' }}>Sur demande</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="adults-section" style={{ padding: '80px 24px', background: 'var(--surface)', borderTop: '1px solid var(--border)' }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '56px', alignItems: 'start' }}>
            <div>
              <div style={{ color: 'var(--accent)', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '16px' }}>
                Pour les adultes
              </div>
              <h2 style={{ fontSize: 'clamp(28px, 3vw, 36px)', lineHeight: '1.15', letterSpacing: '-0.01em', marginBottom: '20px' }}>
                Comprenez et maîtrisez le digital
              </h2>
              <p style={{ fontSize: '17px', color: 'var(--text-muted)', lineHeight: '1.6', marginBottom: '28px' }}>
                Cours et conférences pour apprendre à utiliser les outils numériques au quotidien, comprendre le digital sans être programmeur. Sessions en petit groupe, rythme flexible (jour, soir, weekend).
              </p>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '12px' }}>
                <li style={{ display: 'flex', gap: '12px', fontSize: '15px' }}>
                  <span style={{ color: 'var(--teal)', fontWeight: '700', flex: '0 0 auto' }}>✓</span>
                  <span>Apprendre à utiliser les outils essentiels</span>
                </li>
                <li style={{ display: 'flex', gap: '12px', fontSize: '15px' }}>
                  <span style={{ color: 'var(--teal)', fontWeight: '700', flex: '0 0 auto' }}>✓</span>
                  <span>Comprendre le web et l'internet</span>
                </li>
                <li style={{ display: 'flex', gap: '12px', fontSize: '15px' }}>
                  <span style={{ color: 'var(--teal)', fontWeight: '700', flex: '0 0 auto' }}>✓</span>
                  <span>Petits groupes et ambiance bienveillante</span>
                </li>
                <li style={{ display: 'flex', gap: '12px', fontSize: '15px' }}>
                  <span style={{ color: 'var(--teal)', fontWeight: '700', flex: '0 0 auto' }}>✓</span>
                  <span>Aucune expérience préalable requise</span>
                </li>
              </ul>
              <a href="#" style={{ display: 'inline-block', marginTop: '28px' }} className="btn btn-primary">Voir nos cours</a>
            </div>
            <div style={{ textAlign: 'center' }}>
              <div style={{ color: 'var(--teal)', marginBottom: '20px' }}>
                {ICONS.users}
              </div>
              <div style={{ fontSize: '56px', fontWeight: '700', color: 'var(--accent)', marginBottom: '12px' }}>500+</div>
              <div style={{ fontSize: '16px', color: 'var(--text-muted)', marginBottom: '32px' }}>adultes formés chaque année</div>
              <div style={{ background: 'linear-gradient(135deg, rgba(14, 143, 121, 0.1) 0%, rgba(20, 86, 143, 0.05) 100%)', border: '1px solid var(--border)', borderRadius: '12px', padding: '28px', textAlign: 'left' }}>
                <h4 style={{ margin: '0 0 16px 0', fontSize: '16px', fontWeight: '700' }}>Format flexible</h4>
                <p style={{ margin: 0, fontSize: '14px', color: 'var(--text-muted)', lineHeight: '1.6' }}>Cours le jour, conférences le soir, ou sessions intensives le weekend. Adaptez votre apprentissage à votre vie.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="business-section" style={{ padding: '80px 24px' }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '56px', alignItems: 'start' }}>
            <div style={{ order: 2 }}>
              <div style={{ color: 'var(--cta)', fontSize: '13px', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '16px' }}>
                Pour les entreprises
              </div>
              <h2 style={{ fontSize: 'clamp(28px, 3vw, 36px)', lineHeight: '1.15', letterSpacing: '-0.01em', marginBottom: '20px' }}>
                Développez vos solutions numériques
              </h2>
              <p style={{ fontSize: '17px', color: 'var(--text-muted)', lineHeight: '1.6', marginBottom: '28px' }}>
                LG-IT accompagne les entreprises pour tout le développement de leurs solutions web, bureautique, et infrastructures. Conseils personnalisés et expertise métier.
              </p>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '16px' }}>
                <li style={{ display: 'flex', gap: '12px', fontSize: '15px' }}>
                  <span style={{ color: 'var(--cta)', fontWeight: '700', flex: '0 0 auto' }}>→</span>
                  <span><strong>Développement web sur-mesure</strong><br/><span style={{ fontSize: '13px', color: 'var(--text-muted)' }}>Sites, applications, intégrations API</span></span>
                </li>
                <li style={{ display: 'flex', gap: '12px', fontSize: '15px' }}>
                  <span style={{ color: 'var(--cta)', fontWeight: '700', flex: '0 0 auto' }}>→</span>
                  <span><strong>Solutions bureautique</strong><br/><span style={{ fontSize: '13px', color: 'var(--text-muted)' }}>Suite Office, gestion documentaire, RH</span></span>
                </li>
                <li style={{ display: 'flex', gap: '12px', fontSize: '15px' }}>
                  <span style={{ color: 'var(--cta)', fontWeight: '700', flex: '0 0 auto' }}>→</span>
                  <span><strong>Infrastructure & matériel</strong><br/><span style={{ fontSize: '13px', color: 'var(--text-muted)' }}>Serveurs, réseau, support technique</span></span>
                </li>
                <li style={{ display: 'flex', gap: '12px', fontSize: '15px' }}>
                  <span style={{ color: 'var(--cta)', fontWeight: '700', flex: '0 0 auto' }}>→</span>
                  <span><strong>Conseils & expertise</strong><br/><span style={{ fontSize: '13px', color: 'var(--text-muted)' }}>Diagnostic, roadmap, accompagnement</span></span>
                </li>
              </ul>
              <a href="#" style={{ display: 'inline-block', marginTop: '28px' }} className="btn btn-primary">Demander un devis</a>
            </div>
            <div style={{ order: 1, textAlign: 'center' }}>
              <div style={{ color: 'var(--cta)', marginBottom: '20px' }}>
                {ICONS.rocket}
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                <div style={{ background: 'linear-gradient(135deg, rgba(217, 83, 30, 0.1) 0%, rgba(90, 74, 153, 0.05) 100%)', border: '1px solid var(--border)', borderRadius: '12px', padding: '20px', textAlign: 'center' }}>
                  <div style={{ fontSize: '32px', fontWeight: '700', color: 'var(--cta)', marginBottom: '8px' }}>50+</div>
                  <div style={{ fontSize: '13px', color: 'var(--text-muted)' }}>projets livrés</div>
                </div>
                <div style={{ background: 'linear-gradient(135deg, rgba(90, 74, 153, 0.1) 0%, rgba(14, 143, 121, 0.05) 100%)', border: '1px solid var(--border)', borderRadius: '12px', padding: '20px', textAlign: 'center' }}>
                  <div style={{ fontSize: '32px', fontWeight: '700', color: '#5a4a99', marginBottom: '8px' }}>100%</div>
                  <div style={{ fontSize: '13px', color: 'var(--text-muted)' }}>clients satisfaits</div>
                </div>
                <div style={{ background: 'linear-gradient(135deg, rgba(20, 86, 143, 0.1) 0%, rgba(217, 83, 30, 0.05) 100%)', border: '1px solid var(--border)', borderRadius: '12px', padding: '20px', textAlign: 'center' }}>
                  <div style={{ fontSize: '32px', fontWeight: '700', color: 'var(--accent)', marginBottom: '8px' }}>10+</div>
                  <div style={{ fontSize: '13px', color: 'var(--text-muted)' }}>ans d'expérience</div>
                </div>
                <div style={{ background: 'linear-gradient(135deg, rgba(14, 143, 121, 0.1) 0%, rgba(20, 86, 143, 0.05) 100%)', border: '1px solid var(--border)', borderRadius: '12px', padding: '20px', textAlign: 'center' }}>
                  <div style={{ fontSize: '32px', fontWeight: '700', color: 'var(--teal)', marginBottom: '8px' }}>24/7</div>
                  <div style={{ fontSize: '13px', color: 'var(--text-muted)' }}>support disponible</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="catalogue" className="catalog-section">
        <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '0 24px' }}>
          <h2 style={{ textAlign: 'center', marginBottom: '48px', fontSize: 'clamp(28px, 4vw, 36px)', letterSpacing: '-0.01em' }}>
            Nos formats d'apprentissage
          </h2>
          <div className="catalog-grid">
            <CatalogBrick
              icon={ICONS.code}
              label="Cours réguliers"
              color="#14568f"
            />
            <CatalogBrick
              icon={ICONS.calendar}
              label="Stages vacances"
              color="#d9531e"
            />
            <CatalogBrick
              icon={ICONS.building}
              label="Formations pro"
              color="#0e8f79"
            />
            <CatalogBrick
              icon={ICONS.gift}
              label="Anniversaires codés"
              color="#5a4a99"
            />
          </div>
        </div>
      </section>

      <section className="section" style={{ background: 'var(--surface)', borderTop: '1px solid var(--border)', borderBottom: '1px solid var(--border)' }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '0 24px' }}>
          <div className="section-head" style={{ marginBottom: '28px' }}>
            <h2>Pourquoi Logiscool ?</h2>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '20px' }}>
            <div className="card card--feature">
              <h3>Pédagogie éprouvée</h3>
              <p>Réseau Logiscool international avec 20+ ans d'expérience en apprentissage du code pour tous les âges.</p>
            </div>
            <div className="card card--feature">
              <h3>Petits groupes</h3>
              <p>Encadrement individuel garanti avec un ratio prof/élève qui favorise l'apprentissage réel.</p>
            </div>
            <div className="card card--feature">
              <h3>Progression structurée</h3>
              <p>Progression clairement définie du Scratch junior au Python, adaptée à chaque étape du développement.</p>
            </div>
            <div className="card card--feature">
              <h3>Accessibilité</h3>
              <p>Cours adaptés à tous les niveaux — zéro prérequis, juste la curiosité et l'envie d'apprendre.</p>
            </div>
            <div className="card card--feature">
              <h3>Flexibilité</h3>
              <p>Cours réguliers, stages vacances, formations à la demande — chacun trouve son format.</p>
            </div>
            <div className="card card--feature">
              <h3>Cadre bienveillant</h3>
              <p>Un environnement où l'erreur est constructive et où chacun progresse à son rythme.</p>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
