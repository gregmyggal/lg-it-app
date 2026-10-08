# MOB-01 — Optimisation pour un usage mobile

| | |
|---|---|
| **Statut** | Livré (front uniquement ; DB → API sans changement) |
| **Liens** | Maquette validée (3 écrans : encodage en cartes, modale plein écran, validation directeur), avis UX Expert |

## 1. Contexte et problème
Les professeurs encodent leurs heures et signent leur mois depuis leur téléphone. Les tableaux à défilement horizontal, les cibles tactiles de 28-34 px, les champs en 14 px (zoom iOS) et les modales de 90 % de large rendaient la saisie pénible.

## 2. Objectifs
- Aucun défilement horizontal sur 375 px pour les parcours professeur et validation directeur.
- Cibles tactiles ≥ 44 px, champs en 16 px.
- Hors périmètre : refonte des écrans de gestion admin lourds (consultation et actions simples seulement), gestes (swipe), test e2e automatisé.

## 3. Acteurs
Professeur (prioritaire), directeur/staff (valider un mois), admin (consultation).

## 4. Décisions validées
- Tableaux de saisie/validation en cartes empilées sous 640 px (`Table cards` + `Td label`).
- Modales en plein écran sous 640 px, pied collant avec bouton principal pleine largeur.
- Stepper − / + (pas de 0,5 h) pour les heures (`HeuresStepper`).
- Barre d'actions collante avec total du mois (`TimesheetsPage`).
- Cloche dans la barre du haut collante sous 860 px (volet pleine largeur).
- Seuils : 860 px (menu, barre du haut), 640 px (cartes, modales, tailles tactiles).

## 5. Critères d'acceptation
- [x] Viewport `viewport-fit=cover`, marges `env(safe-area-inset-*)`, `100dvh`.
- [x] Connexion : `autocomplete`, `inputMode`, champs 16 px / 44 px.
- [x] `AdminModal` et `ui/Modal` plein écran sous 640 px.
- [x] 5 tableaux en cartes (sessions du mois, autres heures, sessions sans heures, heures par session, synthèse de validation, détail professeur).
- [ ] Recette manuelle sur iPhone Safari et Android Chrome.
