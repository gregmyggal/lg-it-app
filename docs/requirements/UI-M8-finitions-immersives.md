# UI-M8 — Finitions immersives (installable, plein écran, transitions, états vides illustrés) — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Huitième et dernière tranche de la refonte mobile-first (après UI-M1 à UI-M7). Tranche 100 % interface : aucune donnée, aucune API, aucune permission. Maquettes : `docs/mockups/UX-MOBILE-01/index.html` (section « immersif »).

| | |
|---|---|
| **ID / Titre** | UI-M8 — Application installable sur l'écran d'accueil (manifest, icônes, `theme-color`, plein écran), transitions fluides entre écrans, illustrations d'états vides, retouches « standalone » |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — recommandations « immersif raisonnable » validées avec les maquettes le 2026-10-25 ☑ En dev (livré, vérifié dans le navigateur) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* (front uniquement) |
| **Date / Version** | 2026-10-25 · v1.0 |
| **Liens** | `UX-MOBILE-01/index.html`, `UI-M1` à `UI-M7`, `frontend/index.html`, `frontend/public/` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** l'application s'ouvre dans l'interface du navigateur (barre d'adresse, onglets), sans icône sur l'écran d'accueil du téléphone ; l'icône de l'onglet est celle par défaut de l'outil de build ; la langue déclarée de la page est l'anglais ; chaque changement d'écran est un « saut » sec ; les états vides sont des emojis seuls, sans cohérence avec le reste du design (et non adaptés au mode sombre).
- **Douleur :** pour un usage terrain quotidien (professeurs, direction), l'application ressemble à un site web de plus et non à un outil ; ouvrir l'application demande de retrouver l'adresse ; la barre du navigateur mange de la hauteur utile.
- **Déclencheur :** demande du directeur d'une proposition « immersive » ; recommandation UX : installable sans mode hors ligne, transitions natives sans bibliothèque, illustrations en SVG en ligne.

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif | Indicateur | Cible | Mesure |
|---|---|---|---|
| Installable | manifest valide (nom, icônes 192/512, `display: standalone`, `start_url`) + `theme-color` + icône iOS | présents et reconnus par le navigateur | vérification navigateur, recette R1 |
| Plein écran | une fois installée, aucune barre de navigateur ; zones sûres respectées (encoche, barre d'accueil) | OK | recette R1 |
| Écrans fluides | changements de page principaux avec transition native (≈ 180 ms), désactivée si « réduire les animations » | 100 % des liens de navigation du portail | recette R2 |
| États vides cohérents | illustrations SVG (clair/sombre) à la place des emojis pour les états vides courants | ≥ 8 des 11 icônes utilisées | recette R3 |
| Aucun risque | aucune dépendance ajoutée, aucun service worker, aucun cache hors ligne | 0 | revue de code |

**Hors périmètre :** mode hors ligne et service worker (décision UX : « à éviter »), notifications push, parallaxe, carrousels, bibliothèque d'animation, écran de démarrage personnalisé iOS, installation proposée par bannière, raccourcis d'application par rôle.

## 3. 🔴 Acteurs, rôles et permissions

**Personas :** tous (professeur, directeur, admin), surtout sur smartphone. Aucune permission : les pages gardent leurs protections existantes ; `start_url` mène à `/aujourdhui`, qui redirige vers la connexion si besoin.

## 4. 🔴 Glossaire métier

| Terme | Définition | Nom technique |
|---|---|---|
| Installer l'application | Ajouter l'icône à l'écran d'accueil ; l'application s'ouvre sans barre de navigateur | `manifest.webmanifest`, `display: standalone` |
| Transition d'écran | Fondu/glissement court entre deux pages | View Transitions API (`viewTransition` du routeur) |
| Illustration d'état vide | Dessin SVG expliquant « rien ici » | `Illustration` |

## 5. 🔴 Parcours et critères d'acceptation

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | professeur | ajouter l'application à mon écran d'accueil | l'ouvrir en un tap, en plein écran | Must |
| US-2 | utilisateur mobile | une barre système aux couleurs de l'application | une expérience continue, sans bande blanche | Should |
| US-3 | utilisateur | des changements d'écran fluides | garder mes repères | Should |
| US-4 | utilisateur sensible aux animations | qu'elles soient supprimées si je le demande | ne pas être gêné | Must |
| US-5 | utilisateur | des états vides illustrés et lisibles en mode sombre | comprendre qu'il n'y a rien, sans impression d'erreur | Should |

### 5.2 Parcours
1. Sur Android/Chrome : menu « Installer l'application » ; sur iOS/Safari : « Sur l'écran d'accueil ». L'icône « Logiscool » s'affiche ; l'application s'ouvre en plein écran sur « Aujourd'hui » (ou la connexion).
2. Navigation entre onglets et liens principaux : transition courte (fondu + léger glissement du contenu ; barre du haut et onglets restent fixes).
3. Écran sans donnée : illustration + message + action (inchangés), adaptés au thème.
4. Alternatives : navigateur sans View Transitions → navigation instantanée comme avant ; « réduire les animations » → aucune transition ; hors connexion → comportement actuel (aucun cache ajouté).

### 5.3 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | la page `index.html` | chargement | `<html lang="fr">`, `<link rel="manifest">`, `theme-color`, `apple-touch-icon`, balises « web app » iOS | Vérif. navigateur |
| AC-2 | `/manifest.webmanifest` | requête | JSON valide : `name`, `short_name`, `start_url`, `scope`, `display: standalone`, `theme_color`, `background_color`, icônes 192 et 512 (dont maskable) | Vérif. navigateur |
| AC-3 | les icônes | requête | PNG 192, 512, 180 servis, aux couleurs de la marque ; favicon SVG de la marque | Vérif. navigateur |
| AC-4 | un lien de navigation du portail (onglets, barre latérale) | clic, navigateur compatible, mouvement non réduit | `document.startViewTransition` est utilisé ; le contenu principal s'anime ; barre du haut et onglets ne bougent pas | Vérif. navigateur |
| AC-5 | `prefers-reduced-motion: reduce` | navigation | aucune animation de transition | Vérif. CSS |
| AC-6 | navigateur sans View Transitions | navigation | fonctionne comme avant | Revue de code |
| AC-7 | un état vide courant (✅ 🗓️ 🏫 📅 📭 🔎 …) | affichage clair et sombre | illustration SVG lisible dans les deux thèmes ; texte inchangé ; décorative (`aria-hidden`) | Recette R3 |
| AC-8 | application installée (`display-mode: standalone`) | affichage | marges de sécurité respectées ; pas de rebond de page (« pull to refresh ») | Recette R1 |
| AC-9 | build | `npm run build` | aucune nouvelle dépendance ; manifest et icônes copiés dans `dist/` | Build |

## 6. 🔴 Règles métier et cycle de vie
Aucune règle métier, aucun statut. Règles de conception : pas de service worker (RG-1), animations ≤ 250 ms (RG-2), jamais d'animation si le système demande de la réduire (RG-3), illustrations décoratives et sans texte (RG-4).

## 7. 🔴 Données et migration
Aucune donnée, migration ni impact RGPD. Le manifest ne contient aucune donnée personnelle.

## 8. Exigences UX/UI
> Maquette : `docs/mockups/UX-MOBILE-01/index.html`. Validé par le directeur le 2026-10-25 ☑

| Élément | Contenu | Composants |
|---|---|---|
| Installation | manifest, icônes, `theme-color` `#0b3550`, `lang="fr"` | `index.html`, `public/` |
| Transitions | `viewTransition` sur les liens du portail, CSS `::view-transition-*` | `PortalLayout`, `index.css` |
| États vides | illustrations SVG | `Illustration`, `EmptyBlock` |

**4 états :** sans objet (finitions) ; les états vides existants gardent leurs textes et actions.

- **Accessibilité :** illustrations `aria-hidden`, contraste via jetons, `prefers-reduced-motion` respecté.
- **Charge cognitive :** inchangée ; aucune action nouvelle.

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Performance | Icônes ≤ 30 ko chacune ; aucun JavaScript ajouté au chargement hors le composant d'illustrations (quelques ko) |
| Compatibilité | Android Chrome (installation + transitions), iOS Safari 16+ (écran d'accueil ; transitions dès iOS 18, dégradation sans risque avant) |
| Sécurité | Aucun cache ni worker ; manifest statique ; HTTPS requis en production pour l'installation |

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB / Back / API | Aucun | — |
| Front | `index.html`, `public/manifest.webmanifest`, `public/icons/*`, `favicon.svg`, `PortalLayout` et liens principaux (`viewTransition`), `Illustration.jsx`, `EmptyBlock`, `index.css` | M |
| Tests | `npm run lint`, `npm run build`, vérification navigateur (manifest, icônes, transitions, états vides clair/sombre) | S |
| Docs | Ce Canvas ; note de déploiement ci-dessous | S |

- **Déploiement :** servir `/manifest.webmanifest` en `application/manifest+json` (le type `application/json` fonctionne aussi) ; l'installation exige HTTPS (déjà le cas en production OVH).
- **Risques :** aucun impact sur les fonctions ; si une transition posait problème, retrait du seul attribut `viewTransition`. **Retour arrière :** revert du commit. **ADR :** non.

## 11. Plan de recette
| # | Rôle | Scénario | Résultat | AC | OK/KO |
|---|---|---|---|---|---|
| R1 | Professeur (Android ou iOS) | Ajouter à l'écran d'accueil, ouvrir | Icône de marque, plein écran, barre système bleu marine | AC-1 à 3, 8 | ☐ |
| R2 | Direction | Naviguer entre onglets, avec puis sans « réduire les animations » | Transition douce / aucune | AC-4, 5 | ☐ |
| R3 | Tous | Écrans vides (Mes classes sans classe, Tout est à jour…) en clair et en sombre | Illustrations lisibles | AC-7 | ☐ |

## 12. Questions ouvertes
Aucune bloquante. Le mode hors ligne reste volontairement exclu.

## 13. Validation (DoR)
☑ UX Expert ☑ Mock-ups ☑ §1–7 ☑ Aucune permission ☑ AC testables ☑ Aucun impact données.
