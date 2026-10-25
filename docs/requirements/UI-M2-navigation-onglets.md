# UI-M2 — Navigation par onglets (smartphone) et barre latérale alignée (ordinateur) — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Deuxième tranche de la refonte mobile-first (après `UI-M1-socle-mobile-first.md`).

| | |
|---|---|
| **ID / Titre** | UI-M2 — Barre d'onglets basse par rôle sur smartphone, entrée « Plus »/« Moi » en feuille basse, barre latérale ordinateur construite sur la même configuration |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — maquettes validées le 2026-10-25 ☑ En dev (livré, vérifié dans le navigateur) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* (front uniquement) |
| **Date / Version** | 2026-10-25 · v1.0 |
| **Liens** | `docs/mockups/UX-MOBILE-01/index.html`, `UX-MOBILE-02/index.html` (mobile) et `desktop.html` (ordinateur), `UI-M1-socle-mobile-first.md`, `frontend/src/layouts/PortalLayout.jsx` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** sous 860 px, la navigation est un bouton « Menu » qui déplie jusqu'à 15 liens et le bloc compte au-dessus du contenu (2 taps par page, perte de repère, en-tête qui passe sur deux lignes). Sur ordinateur, la barre latérale mélange emojis et libellés sans icône et n'a pas la même organisation que le mobile.
- **Douleur :** professeurs et direction naviguent au pouce, en mobilité ; chaque changement de page coûte deux gestes et masque le contenu.
- **Déclencheur :** maquettes UX-MOBILE-01/02 validées (« onglets bas par rôle, « Plus » pour le reste, ordinateur aligné »).

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif | Indicateur | Cible | Mesure |
|---|---|---|---|
| Naviguer en un geste | taps pour changer de page principale | 1 (au lieu de 2) | recette R1 |
| Repère permanent | page courante visible dans la barre | 100 % des pages principales | recette R1, R2 |
| Cohérence mobile / ordinateur | libellés et regroupements identiques | 1 seule configuration de navigation | revue de code |
| Aucun contenu masqué | barre d'actions collante et notifications visibles au-dessus de la barre d'onglets | 0 chevauchement | recette R3 |

**Hors périmètre :** page « Aujourd'hui » (UI-M3 : elle deviendra le premier onglet professeur), « Aujourd'hui » direction (UI-M6), recherche globale, badges de compteur sur les onglets, transitions entre pages (UI-M8).

## 3. 🔴 Acteurs, rôles et permissions

**Personas :** Professeur (smartphone), Directeur/Staff et Admin (smartphone et ordinateur).

| Entrée de navigation | Admin | Staff | Professeur |
|---|---|---|---|
| Onglets/entrées professeur (Classes, Mon mois, Liens) | — | — | L |
| Onglets/entrées direction (Timesheets, Classes, Calendrier, Professeurs) | L | L | — |
| « Équipe » | L | — | — |
| Autres entrées du « Plus » | L | L | — |

Les routes restent protégées par `ProtectedRoute` ; la configuration de navigation ne fait que refléter les droits (aucune nouvelle permission).

## 4. 🔴 Glossaire métier

| Terme | Définition | Nom technique |
|---|---|---|
| Onglets | Barre fixe en bas d'écran (≤ 860 px) | `.portal-tabs`, `NavigationOnglets` |
| Plus / Moi | Dernier onglet : feuille basse avec les autres entrées et le compte (« Moi » pour le professeur) | `FeuillePlus` |
| Configuration de navigation | Liste unique des entrées par rôle, partagée par onglets, feuille et barre latérale | `layouts/navigation.jsx` |

## 5. 🔴 Parcours et critères d'acceptation

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | professeur sur smartphone | des onglets en bas : Classes · Mon mois · Liens · Moi | changer de page d'un pouce | Must |
| US-2 | directeur sur smartphone | des onglets : Timesheets · Classes · Calendrier · Professeurs · Plus | accéder aux écrans du quotidien en 1 tap | Must |
| US-3 | directeur sur smartphone | « Plus » avec les entrées regroupées (Opérationnel, Scolarité, Contenu) et le compte | atteindre les écrans denses sans menu de 15 liens | Must |
| US-4 | utilisateur sur ordinateur | la même organisation dans la barre latérale | retrouver mes repères d'un appareil à l'autre | Must |

### 5.2 Parcours
1. Smartphone : barre du haut réduite (nom de l'app, cloche), onglets en bas, l'onglet de la page courante est mis en évidence. « Plus » ouvre une feuille basse : entrées par groupe, puis compte (nom, rôle, apparence, ma signature pour le professeur, déconnexion). Toucher une entrée ferme la feuille et ouvre la page.
2. Ordinateur : barre latérale avec les entrées principales puis les groupes, icônes à la place des emojis, compte en bas (inchangé).
3. Alternatives : page hors onglets (ex. fiche classe) → l'onglet parent reste actif ; page d'une entrée du « Plus » → l'onglet « Plus » est actif ; clavier ouvert → la barre ne masque pas un champ (elle reste sous le contenu défilant).

### 5.3 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | un professeur, largeur ≤ 860 px | n'importe quelle page du portail | 4 onglets visibles en bas : Classes, Mon mois, Liens, Moi ; le menu « Menu » a disparu | R1 |
| AC-2 | un directeur/admin, ≤ 860 px | n'importe quelle page | 5 onglets : Timesheets, Classes, Calendrier, Professeurs, Plus | R2 |
| AC-3 | un onglet touché | — | la page s'ouvre en 1 tap, l'onglet est actif (`aria-current="page"`) | R1, R2 |
| AC-4 | `/mes-classes/12` ou `/admin/classes/3` | affichage | l'onglet « Classes » est actif | R1, R2 |
| AC-5 | « Plus » touché (staff) | — | feuille basse : groupes Opérationnel/Scolarité/Contenu (+ Équipe pour admin seulement), puis compte ; un lien ferme la feuille | R2 |
| AC-6 | une page d'une entrée du « Plus » (ex. Cours) | affichage | l'onglet « Plus » est actif | R2 |
| AC-7 | « Encoder mon mois » avec barre collante « Total / Soumettre » | affichage ≤ 860 px | la barre collante est au-dessus des onglets, sans chevauchement ; les toasts aussi | R3 |
| AC-8 | largeur > 860 px | n'importe quelle page | pas d'onglets ; barre latérale basée sur la même configuration, regroupements identiques | R4 |
| AC-9 | écran avec encoche / barre d'accueil | affichage | la barre respecte `env(safe-area-inset-bottom)` | R1 |
| AC-10 | clavier | Tab sur les onglets | focus visible ; ordre logique ; « Plus » ouvert au clavier, Échap ferme | R2 |

## 6. 🔴 Règles métier et cycle de vie
- **RG-1 :** une seule configuration de navigation par rôle (`navigationPour(user)`), utilisée par les onglets, la feuille et la barre latérale.
- **RG-2 :** seuil mobile unique = `max-width: 860px` (celui du portail aujourd'hui).
- **RG-3 :** au plus 5 onglets ; le dernier est « Plus » (direction) ou « Moi » (professeur).
- **RG-4 :** cibles ≥ 44 px, libellés ≤ 12 caractères sur une ligne.
- Aucun statut créé.

## 7. 🔴 Données et migration
Aucune table, colonne, migration, seeder ni donnée personnelle.

## 8. Exigences UX/UI
> Workflow : sub-agent UX Expert (UX-MOBILE-01/02). Mock-ups : `docs/mockups/UX-MOBILE-01/`, `UX-MOBILE-02/`. Validé par le directeur le 2026-10-25 ☑

| Écran | Contenu | Composants |
|---|---|---|
| Portail (toutes pages) | Barre du haut (marque + cloche), onglets bas, feuille « Plus/Moi » | `PortalLayout`, `AdminModal` (feuille basse UI-M1), `NotificationsBell`, `ThemeToggle`, `MaSignatureModal` |

- **Textes exacts :** onglets professeur *Classes · Mon mois · Liens · Moi* ; direction *Timesheets · Classes · Calendrier · Professeurs · Plus* ; groupes *Opérationnel · Scolarité · Contenu* ; feuille titrée « Plus » ou « Moi ». Le premier onglet professeur « Aujourd'hui » arrivera avec UI-M3.
- **4 états :** sans objet (navigation statique).
- **Appareils :** ☑ Mobile (priorité) ☑ Tablette ☑ Desktop.
- **Accessibilité :** `<nav aria-label>`, `aria-current`, cibles 44 px, focus visible, icônes `aria-hidden`, contraste via jetons.
- **Charge cognitive :** 1 geste par page principale (contre 2).

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Performance | Aucune dépendance ; icônes SVG en ligne |
| Compatibilité | iOS Safari ≥ 16, Chrome Android, navigateurs desktop ; français |
| Sécurité | Les routes restent protégées côté `ProtectedRoute` et backend |

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB / Back / API | Aucun | — |
| Front | `layouts/navigation.jsx` (configuration + icônes), `layouts/PortalLayout.jsx` (barre latérale, barre du haut, onglets, feuille), `index.css` (onglets, safe-area, barres collantes et toasts remontés, suppression du menu déroulant) | M |
| Tests | `npm run lint`, `npm run build`, vérification navigateur 390/768/1280 px, professeur et directeur | S |
| Docs | Ce Canvas | S |

- **Risques :** barre d'actions collante, toasts et cloche doivent rester accessibles (traité via `--tabbar-h`) ; les pages qui avaient un `position: sticky; bottom: 0` en dehors de `.sticky-actions` seraient masquées (aucune trouvée).
- **Retour arrière :** revert du commit. **ADR :** non.

## 11. Plan de recette
| # | Rôle | Scénario | Résultat | AC | OK/KO |
|---|---|---|---|---|---|
| R1 | Professeur (390 px) | Parcourir les 4 onglets, ouvrir une classe | Onglets actifs, 1 tap, parent actif | AC-1, 3, 4, 9 | ☐ |
| R2 | Directeur (390 px) | Parcourir les 5 onglets, « Plus » → Cours, Échap | Feuille groupée, onglet Plus actif | AC-2, 5, 6, 10 | ☐ |
| R3 | Professeur (390 px) | Encoder mon mois, faire défiler | Barre « Total/Soumettre » au-dessus des onglets | AC-7 | ☐ |
| R4 | Directeur (1280 px) | Observer la barre latérale | Mêmes regroupements, icônes | AC-8 | ☐ |

**Jeu de données :** base de démonstration `lgit_demo` (`scripts/demo-timesheets.sh`).

## 12. Questions ouvertes
Aucune bloquante. Le premier onglet « Aujourd'hui » du professeur et l'onglet d'accueil direction arrivent avec UI-M3 et UI-M6 ; en attendant, « Calendrier » occupe cette place côté direction.

## 13. Validation (DoR)
Maquettes validées par le directeur le 2026-10-25. ☑ UX Expert ☑ Mock-ups ☑ §1–7 ☑ Permissions (aucune nouvelle) ☑ AC testables ☑ Données (aucun impact).
