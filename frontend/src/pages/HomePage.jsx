import './HomePage.css';

// Images — Placeholder URLs (remplacez avec vos images locales)
// Pour utiliser vos propres images:
// 1. Placez les fichiers dans src/assets/images/
// 2. Importez-les: import heroImg from '../assets/images/hero.jpg'
// 3. Remplacez les URLs ci-dessous
const IMAGES = {
  // Placeholder: kids/teens coding, bright workshop, hands on keyboard, engaged
  hero: 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=1920&q=80',

  // Placeholder: teens at computer, coding/Scratch visible, close-up hands
  kids: 'https://images.unsplash.com/photo-1633356122544-f134324ef6db?w=1200&q=80',

  // Placeholder: adult learner at laptop, small group, relaxed daytime light
  adults: 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1200&q=80',

  // Placeholder: team collaborating around screen/whiteboard, business-casual
  business: 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1400&q=80',

  // Placeholder: classroom wide shot
  coursCard: 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=900&q=80',

  // Placeholder: outdoor-summer energy, kids
  stagesCard: 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=900&q=80',

  // Placeholder: professional training room
  formationsCard: 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=900&q=80',

  // Placeholder: kids celebrating with laptop
  annivCard: 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=900&q=80',

  // Placeholder: group of smiling students
  ctaBanner: 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1920&q=80',
};

export default function HomePage() {
  return (
    <div className="home-page">
      {/* ============================================================
          HEADER — Transparent Overlay
          ============================================================ */}
      <header className="header--transparent">
        <a href="/" className="logo">
          <span className="logo-dot"></span>
          LG-IT
        </a>
        <nav>
          <a href="#" className="active">Accueil</a>
          <a href="#cours">Cours</a>
          <a href="#stages">Stages</a>
          <a href="#formations">Formations</a>
          <a href="#pourquoi">L'esprit LG-IT</a>
          <a href="#contact">Contact</a>
        </nav>
        <a href="/connexion" className="login-btn">Connexion</a>
      </header>

      {/* ============================================================
          HERO SECTION
          ============================================================ */}
      <section className="hero-section">
        <img src={IMAGES.hero} alt="Enfants codant en atelier" className="hero-section__bg" />

        <div className="hero-section__content">
          <div>
            <div className="hero-eyebrow">Logiscool Pays Vert — Ath, Belgique</div>
            <h1>Coder,<br />en s'amusant.</h1>
          </div>

          <div style={{ paddingBottom: '10px' }}>
            <p className="lede">
              Du premier scratch à l'autonomie complète — des parcours structurés pour enfants, ados, adultes et entreprises.
            </p>
            <div className="hero-actions">
              <a href="#catalogue" className="btn btn-primary">Voir nos offres</a>
              <a href="/connexion" className="btn btn-secondary">Espace professeur</a>
            </div>
          </div>
        </div>
      </section>

      {/* ============================================================
          STAT STRIP
          ============================================================ */}
      <section className="stat-strip">
        <div className="stat-strip__grid">
          <div className="stat-strip__item">
            <div className="stat-strip__number">20+</div>
            <div className="stat-strip__label">ans d'expérience Logiscool</div>
          </div>
          <div className="stat-strip__item">
            <div className="stat-strip__number">500+</div>
            <div className="stat-strip__label">élèves & adultes formés / an</div>
          </div>
          <div className="stat-strip__item">
            <div className="stat-strip__number">50+</div>
            <div className="stat-strip__label">projets entreprise livrés</div>
          </div>
          <div className="stat-strip__item">
            <div className="stat-strip__number">100%</div>
            <div className="stat-strip__label">clients satisfaits</div>
          </div>
        </div>
      </section>

      {/* ============================================================
          ENFANTS & ADOS (Asymmetric Section 01)
          ============================================================ */}
      <section className="asymmetric-section">
        <div className="asymmetric-grid">
          <div className="asymmetric-content">
            <div className="ghost-number ghost-number--left">01</div>
            <div className="asymmetric-eyebrow asymmetric-eyebrow--kids">Enfants & Ados</div>
            <h2>Du premier jeu Scratch aux vrais projets en Python.</h2>
            <p>Une progression pensée pour chaque âge — cours à l'année ou stages intensifs, toujours en petit groupe.</p>
            <div className="asymmetric-tags">
              <span className="tag tag--teal">7–11 ans · Scratch & jeux</span>
              <span className="tag tag--blue">12–18 ans · Python & web</span>
            </div>
          </div>

          <div className="asymmetric-photo asymmetric-photo--right">
            <img src={IMAGES.kids} alt="Jeunes devant un écran, atelier code" />
          </div>
        </div>
      </section>

      {/* ============================================================
          ADULTES (Asymmetric Section 02 — Mirrored)
          ============================================================ */}
      <section className="asymmetric-section asymmetric-section--adults">
        <div className="asymmetric-grid asymmetric-grid--reversed">
          <div className="asymmetric-photo asymmetric-photo--left">
            <img src={IMAGES.adults} alt="Adulte en formation devant ordinateur" />
          </div>

          <div className="asymmetric-content asymmetric-content--right-aligned">
            <div className="ghost-number ghost-number--right">02</div>
            <div className="asymmetric-eyebrow asymmetric-eyebrow--adults">Pour les adultes</div>
            <h2>Comprenez et maîtrisez le digital.</h2>
            <p>Cours et conférences pour utiliser les outils numériques au quotidien — petits groupes, rythme flexible.</p>
            <a href="#" className="btn btn-primary" style={{ marginLeft: 'auto' }}>Voir nos cours</a>
          </div>
        </div>
      </section>

      {/* ============================================================
          ENTREPRISES (Asymmetric Section 03 — Dark Diagonal)
          ============================================================ */}
      <section className="asymmetric-section asymmetric-section--business">
        <div className="asymmetric-grid asymmetric-grid--business">
          <div className="asymmetric-content asymmetric-content--business">
            <div className="asymmetric-eyebrow asymmetric-eyebrow--business">Pour les entreprises</div>
            <h2>Développez vos solutions numériques.</h2>
            <p>LG-IT accompagne le développement web, la bureautique et les infrastructures des entreprises.</p>
            <a href="#" className="btn btn-primary">Demander un devis</a>
          </div>

          <div className="asymmetric-photo asymmetric-photo--business">
            <img src={IMAGES.business} alt="Équipe en réunion/développement" />
          </div>
        </div>
      </section>

      {/* ============================================================
          CATALOG — Nos Formats d'Apprentissage
          ============================================================ */}
      <section id="catalogue" className="catalog-section">
        <div style={{ maxWidth: '1400px', margin: '0 auto', paddingLeft: '40px', paddingRight: '40px' }}>
          <h2>Nos formats d'apprentissage</h2>

          <div className="catalog-grid">
            <a href="#cours" className="catalog-card catalog-card--cours">
              <img src={IMAGES.coursCard} alt="Cours réguliers" />
              <div className="catalog-card__label">Cours<br />réguliers</div>
            </a>

            <a href="#stages" className="catalog-card catalog-card--stages">
              <img src={IMAGES.stagesCard} alt="Stages vacances" />
              <div className="catalog-card__label">Stages<br />vacances</div>
            </a>

            <a href="#formations" className="catalog-card catalog-card--formations">
              <img src={IMAGES.formationsCard} alt="Formations pro" />
              <div className="catalog-card__label">Formations<br />pro</div>
            </a>

            <a href="#anniversaires" className="catalog-card catalog-card--anniv">
              <img src={IMAGES.annivCard} alt="Anniversaires codés" />
              <div className="catalog-card__label">Anniversaires<br />codés</div>
            </a>
          </div>
        </div>
      </section>

      {/* ============================================================
          POURQUOI LOGISCOOL ? (Editorial Numbered List)
          ============================================================ */}
      <section id="pourquoi" className="why-section">
        <div className="why-grid">
          <h2>Pourquoi<br />Logiscool ?</h2>

          <div className="why-list">
            <div className="why-item">
              <div className="why-number">01</div>
              <div className="why-content">
                <h3>Pédagogie éprouvée</h3>
                <p>Réseau international, 20+ ans d'expérience en apprentissage du code pour tous les âges.</p>
              </div>
            </div>

            <div className="why-item">
              <div className="why-number">02</div>
              <div className="why-content">
                <h3>Petits groupes</h3>
                <p>Encadrement individuel garanti, un ratio prof/élève qui favorise l'apprentissage réel.</p>
              </div>
            </div>

            <div className="why-item">
              <div className="why-number">03</div>
              <div className="why-content">
                <h3>Progression structurée</h3>
                <p>Du Scratch junior au Python, adaptée à chaque étape du développement.</p>
              </div>
            </div>

            <div className="why-item">
              <div className="why-number">04</div>
              <div className="why-content">
                <h3>Accessibilité</h3>
                <p>Zéro prérequis — juste la curiosité et l'envie d'apprendre.</p>
              </div>
            </div>

            <div className="why-item">
              <div className="why-number">05</div>
              <div className="why-content">
                <h3>Cadre bienveillant</h3>
                <p>Un environnement où l'erreur est constructive et chacun progresse à son rythme.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ============================================================
          CTA BANNER — Final Call-to-Action
          ============================================================ */}
      <section className="cta-banner">
        <img src={IMAGES.ctaBanner} alt="Élèves souriants, salle de classe" className="cta-banner__bg" />

        <div className="cta-banner__content">
          <h2>Prêt à commencer votre voyage numérique ?</h2>
          <a href="#catalogue" className="btn btn-primary">Découvrir nos formations</a>
        </div>
      </section>
    </div>
  );
}
