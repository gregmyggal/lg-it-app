# ✨ Refactorisation Homepage — Guide d'Intégration

**Status:** ✅ **CODE COMPLETE**  
**Date:** 2026-07-22  
**Files Modified:** `src/pages/HomePage.jsx`, `src/pages/HomePage.css`  

---

## 📋 Résumé des Modifications

Refonte complète de la page d'accueil selon le design handoff haute-fidélité, transformant une mise en page basique en un design **éditorial, asymétrique et photo-driven** professionnel.

### Ce qui a changé

| Section | Avant | Après |
|---------|-------|-------|
| **Header** | Statique dans Layout | Transparent overlay fixe sur hero |
| **Hero** | Simple 2-col | Full-bleed photo + gradient overlay |
| **Stat Strip** | N/A | ✨ Nouvelle section (4-up grid) |
| **Enfants/Adultes/Entreprises** | Grid simple + icônes | Asymétrique avec photos rotées + ghost numbers |
| **Catalog** | 4 icônes | Photos plein-bleed avec gradients + offset |
| **Pourquoi Logiscool** | Grid 3-col | Sticky 2-col éditorial avec liste numérotée |
| **CTA Banner** | Simple | Photo plein-bleed + dark overlay |

---

## 🎨 Design Tokens Implémentés

### Couleurs
```css
--cta: #d9531e (orange)
--teal: #0e8f79
--accent: #14568f (navy)
--brand-deep: #0b3550 (dark navy)
--text: #16212e
--bg: #f4f6f8
```

### Typographie
- **H1 Hero:** `clamp(44px, 7.2vw, 108px)`, weight 800
- **H2:** `clamp(30px, 3.4vw, 44px)`, weight 800
- **Eyebrow:** 13px, uppercase, letter-spacing 0.08/0.1em
- **Body:** 16.5px, line-height 1.6
- **Labels:** 13.5px, muted color

### Spacing
- Padding sections: 140px/120px (desktop), 60px (mobile)
- Gaps asymétrique: 0–64px
- Container max-width: 1400px

### Visual Effects
- **Rotations photo:** ±1.4°
- **Border-radius asymétrique:** `4px 32px 4px 32px` / `32px 4px 32px 4px`
- **Shadows:** `0 32px 70px rgba(11,53,80,0.2)`
- **Gradients:** Hero & CTA dark overlay, catalog scrim colorés
- **Clip-path:** Dark business section avec diagonal slant

---

## 📸 Images — Configuration

### Emplacements Requis

| Nom | Usage | Dimensions | Format |
|-----|-------|-----------|--------|
| `hero.jpg` | Section hero | 1920×1080+ | JPG/WebP |
| `kids.jpg` | Enfants & ados section | 1200×1020 | JPG/WebP |
| `adults.jpg` | Adultes section (mirrored) | 1200×1020 | JPG/WebP |
| `business.jpg` | Entreprises dark section | 1400×875 | JPG/WebP |
| `cours.jpg` | Catalog card 1 | 900×1200 | JPG/WebP |
| `stages.jpg` | Catalog card 2 | 900×1200 | JPG/WebP |
| `formations.jpg` | Catalog card 3 | 900×1200 | JPG/WebP |
| `anniversaires.jpg` | Catalog card 4 | 900×1200 | JPG/WebP |
| `cta-banner.jpg` | CTA banner final | 1920×900 | JPG/WebP |

### Critères de Photos

1. **Hero:** Kids/teens actively coding, workshop/classroom, natural light, engaging
2. **Kids section:** Close-up teens/child at screen, hands on keyboard, code visible
3. **Adults section:** Adult learner at laptop, small group setting, daytime, relaxed
4. **Business section:** Team collaborating, screen/whiteboard visible, business-casual
5. **Catalog cards:** 
   - Cours: Classroom wide shot
   - Stages: Outdoor summer energy
   - Formations: Professional training room
   - Anniversaires: Kids celebrating with laptop
6. **CTA Banner:** Group of smiling students, wide format, legible under dark overlay

### Comment Ajouter les Images

#### Option 1: Images Locales (Recommandé)
```bash
# 1. Placez vos images dans:
mkdir -p src/assets/images
# Copiez:
# - hero.jpg
# - kids.jpg
# - adults.jpg
# - business.jpg
# - cours.jpg
# - stages.jpg
# - formations.jpg
# - anniversaires.jpg
# - cta-banner.jpg

# 2. Dans HomePage.jsx, changez IMAGES object:
import heroImg from '../assets/images/hero.jpg';
import kidsImg from '../assets/images/kids.jpg';
// ... etc

const IMAGES = {
  hero: heroImg,
  kids: kidsImg,
  // ... etc
};
```

#### Option 2: URLs Web (Pour Dev/Testing)
Le code actuel utilise des placeholders Unsplash. À remplacer quand images locales sont ready.

---

## 🔧 Structure CSS

### Nouveaux Fichiers
- `src/pages/HomePage.css` — Styles complets pour toutes les sections

### Classes Principales
```css
.hero-section           /* Full-bleed hero + overlay */
.stat-strip            /* Dark navy stat strip */
.asymmetric-section    /* Kids/adults/business sections */
.asymmetric-grid       /* Grid 2-col asymétrique */
.ghost-number          /* Grand nombre en arrière-plan */
.catalog-section       /* Grid 4-up avec offset */
.catalog-card          /* Cartes avec gradients colorés */
.catalog-card--cours   /* Variants per card type */
.why-section           /* Sticky layout numéroté */
.cta-banner            /* Full-bleed final CTA */
.header--transparent   /* Header fixe transparent overlay */
```

### Responsive Breakpoints
- **Desktop:** 1400px max-width
- **Tablet:** `@media (max-width: 1024px)` — Stacks 2-col → 1-col
- **Mobile:** `@media (max-width: 640px)` — Optimisé pour petit écran

---

## 📋 Header Transparent — Configuration

La nouvelle homepage a un **header transparent fixe** qui s'affiche sur le hero. Deux approches possibles:

### Approche 1: Header Interne (Actuel)
Le header est défini dans HomePage.jsx comme `<header className="header--transparent">`.

**Avantage:** Isolation — uniquement sur homepage
**Inconvénient:** Dupliqué du header global

### Approche 2: Header Global (Alternative)
Modifier `Layout.jsx` pour avoir un variant transparent:

```jsx
<header className={isHomePage ? 'header--transparent' : 'header--default'}>
```

**À décider:** Quel approche préférez-vous?

---

## ✅ Checklist d'Intégration

- [ ] Images locales sourcées et placées dans `src/assets/images/`
- [ ] IMAGES object dans HomePage.jsx mis à jour avec imports locaux
- [ ] `npm run dev` lancé et vérifier page d'accueil
- [ ] Vérifier responsive sur desktop (1920px), tablet (1024px), mobile (640px)
- [ ] Vérifier header transparent position/z-index sur hero
- [ ] Vérifier photos load correctly (pas 404)
- [ ] Vérifier CTAs link correctly (#catalogue, /connexion)
- [ ] Vérifier stat strip display sur fond navy
- [ ] Vérifier asymmetric sections layout + photo rotations
- [ ] Vérifier catalog offset (cards 2 & 4 décalées)
- [ ] Vérifier "Pourquoi Logiscool" sticky header sur desktop
- [ ] Vérifier CTA banner visibility + readability
- [ ] Test dark mode (CSS variables ready)

---

## 🚀 Performance Notes

- ✅ Pas de dépendances externes (Unsplash placeholders peuvent être remplacées)
- ✅ Lazy loading images est recommandé (native HTML5 `loading="lazy"`)
- ✅ CSS utilise CSS variables pour theming futur
- ✅ Inline SVG emojis (aucun asset externe)

### Optimisation Images Recommandée
```jsx
// Ajouter loading lazy
<img src={...} alt="..." loading="lazy" />

// Ou avec srcset pour responsive:
<img 
  src={heroImg} 
  alt="..." 
  srcset="hero-480w.jpg 480w, hero-1200w.jpg 1200w, hero-1920w.jpg 1920w"
  sizes="(max-width: 640px) 480px, (max-width: 1024px) 1200px, 1920px"
/>
```

---

## 📚 Fichiers Référence

| Fichier | Contenu |
|---------|---------|
| `/Users/greg/Downloads/design_handoff_home_redesign/LG-IT Home Redesign.dc.html` | Prototype HTML haute-fidélité (source de vérité) |
| `src/pages/HomePage.jsx` | Code React refactorisé |
| `src/pages/HomePage.css` | Styles complets |
| `src/index.css` | Design tokens (colors, fonts) |

---

## 🎯 Prochaines Étapes

1. **Importer images locales** et tester dans dev server
2. **Vérifier responsive** sur tous les breakpoints
3. **Optimiser images** (compression, WebP, srcset)
4. **Tester CTAs** et navigation
5. **Vérifier accessibility** (alt text, contrast, keyboard nav)
6. **Déployer en staging** et tester en live

---

## 💬 Questions/Problèmes?

Si quelque chose ne s'affiche pas comme prévu:

1. Vérifiez chemins images (console F12 pour 404s)
2. Vérifiez CSS charge (vérifiez HomePage.css importé)
3. Vérifiez layout responsive (testez sur mobile breakpoint)
4. Comparez avec `LG-IT Home Redesign.dc.html` pour fidelité

---

**✨ Status: READY FOR TESTING**

Le code est complet et prêt. Il ne manque que les images réelles pour être production-ready!
