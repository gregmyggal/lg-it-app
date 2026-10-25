# UI-M1 — Socle mobile-first (débordements, feuille basse, jetons) — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Première tranche de la refonte mobile-first (UI-M1 → UI-M8, voir `docs/mockups/UX-MOBILE-02/WORKFLOW_UX.md`).

| | |
|---|---|
| **ID / Titre** | UI-M1 — Socle mobile-first : libellés qui restent dans leur cadre, modales en feuilles basses sur smartphone, couleurs sur jetons (mode sombre), dialogues alignés ordinateur |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — maquettes validées par le directeur ☑ En dev (livré, vérifié dans le navigateur) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* (front uniquement) |
| **Date / Version** | 2026-10-25 · v1.0 |
| **Liens** | `docs/mockups/UX-MOBILE-01/index.html`, `docs/mockups/UX-MOBILE-02/index.html` (mobile) et `desktop.html` (ordinateur), `UX-MOBILE-02/WORKFLOW_UX.md`, `UI-01-largeur-et-mode-nuit.md` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** sur smartphone, certains libellés dépassent leur cadre (badges `nowrap` : « Remplacée par… », « Hors période · rattrapage », noms d'employeur ou de classe longs ; titres de page sans coupure des mots longs). Les modales occupent tout l'écran (`100dvh`) : le contexte disparaît, la croix est hors de portée du pouce. Un badge « À venir » a des couleurs codées en dur (`#dbeafe`) illisibles en mode sombre.
- **Douleur / coût :** texte coupé ou page qui défile horizontalement, perte de repère à l'ouverture d'une modale, incohérence entre mobile et ordinateur. Les professeurs utilisent l'app presque uniquement sur smartphone.
- **Déclencheur (verbatim directeur) :** *« Certains libellés ne restent pas dans les cadres, certains flux ne sont pas conformes à l'utilisation sur un smartphone. »*

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif métier | Indicateur mesurable | Cible | Comment mesurer |
|---|---|---|---|
| Aucun libellé hors cadre | débordement horizontal de la page (`scrollWidth > clientWidth`) à 360 px et 390 px | 0 sur les pages professeur et staff principales | script navigateur + recette R1 |
| Modales ergonomiques au pouce | modale ouverte sur ≤ 640 px | feuille basse : poignée, coins arrondis, fond visible, action principale en bas, fermeture par glissement | recette R2 |
| Mode sombre cohérent | couleurs en dur dans les badges d'état | 0 (jetons `--tone-*`) | grep + recette R3 |
| Alignement ordinateur | modale ≥ 641 px | inchangée dans son comportement (dialogue centré), mêmes composants | recette R4 |

**Hors périmètre (tranches suivantes) :** barre d'onglets et menu groupé (UI-M2), page « Aujourd'hui » (UI-M3), cartes « Mon mois » (UI-M4), validation par lot (UI-M5), remplacement atomique et fiche séance (UI-M6), fiches prof/classe mobiles (UI-M7), manifest PWA et transitions (UI-M8). Aucun changement d'API ni de base de données.

## 3. 🔴 Acteurs, rôles et permissions

**Personas :** tous les rôles (professeur sur smartphone surtout ; directeur/admin sur smartphone et ordinateur).

| Ressource / Action | Admin | Staff | Professeur | Élève |
|---|---|---|---|---|
| Voir les écrans et modales (aspect) | L | L | L | — |

Aucune permission nouvelle ni modifiée : la tranche est purement visuelle (les `can.*` et routes restent la source de vérité du backend).

## 4. 🔴 Glossaire métier

| Terme | Définition | Nom technique |
|---|---|---|
| Feuille basse | Modale ancrée en bas de l'écran sur smartphone (≤ 640 px) | `AdminModal` + classes `.adm-modal*` |
| Dialogue | Même composant sur ordinateur, centré | `AdminModal` |
| Jeton de ton | Couleur de fond/texte d'un état, adaptée au mode sombre | `ADMIN_TONES`, variables `--tone-*` |

## 5. 🔴 Parcours utilisateurs et user stories

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | professeur sur smartphone | que les libellés et badges passent à la ligne plutôt que déborder | lire toute l'information sans défiler horizontalement | Must |
| US-2 | professeur/directeur sur smartphone | que les fenêtres s'ouvrent en bas, avec le contexte visible | rester repéré et atteindre les actions au pouce | Must |
| US-3 | utilisateur du mode sombre | que tous les badges d'état soient lisibles | ne pas avoir de zones claires illisibles | Must |
| US-4 | directeur sur ordinateur | que les dialogues restent centrés et cohérents avec le mobile | retrouver les mêmes repères quel que soit l'outil | Should |

### 5.2 Parcours
1. L'utilisateur ouvre une page (ex. « Mes classes », « Encoder mon mois ») sur 360 px → les titres, badges, noms de classe longs passent à la ligne, rien ne dépasse.
2. Il ouvre une modale (ex. « Ajuster la séance ») → feuille qui monte du bas, poignée visible, fond assombri ; il la ferme par la croix (44 px), par un glissement vers le bas sur la poignée/l'en-tête, par le fond ou par Échap.
3. Alternatives : mouvement réduit demandé → pas d'animation ; formulaire long → le corps défile, l'en-tête et le pied restent visibles ; clavier ouvert → hauteur limitée à `90dvh`.

### 5.3 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | un badge au libellé long dans une carte de 320 px de large | la page s'affiche | le libellé passe à la ligne, sans dépasser | Recette R1 + contrôle `scrollWidth` |
| AC-2 | un titre de page contenant un mot de 40 caractères | la page s'affiche à 360 px | le mot est coupé, aucune barre de défilement horizontale | Recette R1 |
| AC-3 | une largeur ≤ 640 px | une `AdminModal` s'ouvre | elle est ancrée en bas, coins supérieurs arrondis, hauteur ≤ 90 dvh, poignée visible, fond visible au-dessus | Recette R2 |
| AC-4 | une feuille basse ouverte | l'utilisateur glisse l'en-tête vers le bas de plus de 100 px | la feuille se ferme (`onClose`) ; sous le seuil, elle revient en place | Recette R2 |
| AC-5 | une feuille basse ouverte | Échap / clic sur le fond / croix | elle se ferme, le focus revient au déclencheur (comportement inchangé) | Recette R2 |
| AC-6 | le mode sombre | le badge « À venir » est affiché | il utilise `--tone-primary-*` et reste lisible | Recette R3 |
| AC-7 | une largeur > 640 px | une `AdminModal` s'ouvre | dialogue centré inchangé, sans poignée | Recette R4 |
| AC-8 | `prefers-reduced-motion` | une modale s'ouvre | aucune animation | Recette R2 |

## 6. 🔴 Règles métier et cycle de vie

- **RG-1 :** aucune règle métier modifiée ; le backend reste la source de vérité.
- **RG-2 :** seuil mobile unique = `max-width: 640px` (déjà utilisé par l'application pour champs, boutons et modales).
- **RG-3 :** cibles tactiles ≥ 44 px (croix, boutons du pied de feuille).
- **RG-4 :** aucune couleur d'état en dur dans un composant : jetons `ADMIN_TONES` / `--tone-*`.

Carte des statuts : aucune création ni modification de statut.

## 7. 🔴 Données et migration

Aucune table, colonne, migration, backfill, seeder ni donnée personnelle. Aucun impact RGPD.

## 8. Exigences UX/UI

> 🚦 Workflow : sub-agent UX Expert (UX-MOBILE-01 et 02). Mock-ups : `docs/mockups/UX-MOBILE-01/`, `docs/mockups/UX-MOBILE-02/`. Validé par : directeur, 2026-10-25 ☑

| Écran | Contenu clé | Composants réutilisés |
|---|---|---|
| Toutes les pages | Libellés qui passent à la ligne | `StatutBadge`, `PeriodeBadge`, `EmployeurBadge`, `AdminPageHeader` |
| Toutes les modales | Feuille basse ≤ 640 px, dialogue centré au-delà | `AdminModal` (même API, aucune page à modifier) |
| Encoder mon mois | Badge « À venir » sur jetons | `SessionsDuMoisTable` |

**4 états :** inchangés (aucun nouvel écran de données).

- **Textes :** inchangés ; la croix garde `aria-label="Fermer la fenêtre"` ; la poignée est décorative (`aria-hidden`).
- **Appareils :** ☑ Mobile (priorité, 360–430 px) ☑ Tablette ☑ Desktop.
- **Accessibilité :** focus piégé et Échap conservés, cibles 44 px, contraste via jetons, `prefers-reduced-motion` respecté. Le glissement est un confort : la croix et le fond restent des alternatives.
- **Charge cognitive :** fermeture en 1 geste ; aucun clic supplémentaire.

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Sécurité | Aucune donnée touchée |
| Performance | Aucune dépendance ajoutée ; animations `transform`/`opacity` uniquement |
| Compatibilité | iOS Safari ≥ 16 (`dvh`, `env(safe-area-inset-*)`), Chrome Android, navigateurs desktop récents ; français |

## 10. Impact technique

| Couche | Changements | Taille |
|---|---|---|
| DB / Back / API | Aucun | — |
| Front | `AdminModal` (poignée + glissement), `index.css` (feuille basse, débordements, jetons), `StatutBadge`, `PeriodeBadge`, `EmployeurBadge`, `AdminPageHeader`, `SessionsDuMoisTable` | S |
| Tests | `npm run lint` + `npm run build` ; vérification navigateur 360/390/768/1280 px, clair et sombre ; contrôle de débordement par script. Le dépôt n'a pas de framework de test composant : aucun ajouté dans cette tranche. | S |
| Docs / ADR | Ce Canvas ; pas d'ADR | S |

- **Vertical slice :** DB → API sans objet (tranche 100 % présentation) ; UI → tests couverts par la recette et le contrôle navigateur.
- **Risques :** le changement de modale plein écran → feuille basse touche toutes les modales : recette sur les 5 modales les plus utilisées (Encoder séance, Ajuster séance, Ma signature, Valider lot, Archiver).
- **Retour arrière :** revert du commit (CSS + 5 composants).
- **ADR requise ?** ☑ non.

## 11. Plan de recette

| # | Rôle | Scénario | Résultat attendu | AC | OK/KO |
|---|---|---|---|---|---|
| R1 | Professeur (360 px) | Parcourir Mes classes, Mes séances, Encoder mon mois | Aucun débordement horizontal, badges à la ligne | AC-1, AC-2 | ☐ |
| R2 | Professeur (390 px) | Ouvrir « Ma signature », fermer par glissement / croix / fond / Échap | Feuille basse conforme | AC-3, AC-4, AC-5, AC-8 | ☐ |
| R3 | Professeur, mode sombre | Encoder mon mois avec séance à venir | Badge « À venir » lisible | AC-6 | ☐ |
| R4 | Directeur (1280 px) | Ouvrir « Ajuster la séance » | Dialogue centré inchangé | AC-7 | ☐ |

**Jeu de données :** base de démonstration `lgit_demo` (`scripts/demo-timesheets.sh`).

## 12. Questions ouvertes
Aucune bloquante.

## 13. Validation (DoR)
Maquettes validées par le directeur le 2026-10-25. ☑ UX Expert ☑ Mock-ups ☑ §1–7 ☑ Permissions (aucune) ☑ AC testables ☑ Impact données (aucun).
