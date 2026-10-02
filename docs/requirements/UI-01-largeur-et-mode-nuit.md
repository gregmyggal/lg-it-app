# UI-01 — Largeur des écrans et mode nuit (portail)

**Statut :** toutes les tranches livrées (le balayage des couleurs en dur de la T3 a été fait avec la T2) · **Maquette :** `docs/mockups/UI-01/01-largeur-et-mode-nuit.html` · Décisions validées (recommandations de l'UX expert) : conteneur 1760 px, bascule manuelle Auto/Clair/Nuit, menu latéral identique dans les deux thèmes, tableaux compacts à partir de 1280 px.

## T1 — Largeur
`.portal-main` n'est plus plafonné à 1100 px ; un conteneur `.portal-page` (`--page-max: 1760px`) est centré, avec des gouttières fluides (`clamp(16px, 2.5vw, 48px)`). Menu 220 px, 248 px dès 1536 px. Les plafonds de 1400 px codés en dur (`AdminPageLayout`, `AdminDesignSystem`, pages de contenu) utilisent la même variable.

## T2 — Jetons de couleur et palette nuit
- `index.css` : jetons `--c-*` (surfaces, textes, bordures, tons), `--tone-*-bg/fg/bd` et `--b-*` (badges) ; valeurs claires = anciennes valeurs (aucun changement en mode clair), valeurs nuit dans `@media (prefers-color-scheme: dark)` et `:root[data-theme='dark']` (prêt pour la bascule T5). `color-scheme: light dark`.
- `ADMIN_COLORS`, `ADMIN_TONES`, `ADMIN_FOCUS_RING` renvoient à ces variables : tous les composants qui les utilisent basculent sans changement.
- Composants de base migrés : `AdminButton` (texte `--c-on-solid`), `AdminModal`, `AdminPageLayout`, `AdminFormField`, `NotificationsBell`.
- Balayage mécanique des couleurs en dur dans les `style` inline (≈ 460 occurrences, 42 fichiers : fonds blancs/gris, textes gris, bandeaux rouge/vert/ambre/bleu, bordures) vers les jetons ; les couleurs d'accent pleines (boutons bleus, etc.) restent inchangées. Concaténations hex + opacité remplacées par `color-mix`.

## Vérification
Contrôle automatisé du contraste des textes (≥ 4,5:1) sur 15 écrans en mode nuit : 0 défaut. Mode clair : mêmes valeurs qu'avant (les faibles contrastes existants, ex. texte vert `#10b981` sur blanc, sont antérieurs).

## T4 — Tableaux et grilles
- `ui/Table` : la largeur minimale ne s'applique plus qu'en dessous de 1280 px (défilement horizontal réservé aux petits écrans) ; cellules compactes (8/12 px) dès 1280 px ; colonnes secondaires (`xl` sur `Th`/`Td`) visibles dès 1440 px (synthèse : « H. préparation », « Dernière action »).
- Détail professeur : deux colonnes dès 1440 px (saisies fluides + colonne latérale de 300 à 460 px, collante : résumé, calendrier, historique), une colonne en dessous ; la mention « ✎ modifiée » passe sous le badge pour ne pas élargir le tableau.
- Cartes d'indicateurs (`.kpi-grid`) et barre de filtres des timesheets flexibles ; grilles `1fr 1fr` de la fiche professeur en `auto-fit`.
- Mesures : synthèse sans défilement horizontal à 1280 px (8 colonnes), 1440 px et 1920 px (10 colonnes, 6 cartes sur une ligne) ; détail sans défilement à 1280, 1440 et 1920 px.

## T5 — Bascule Auto / Clair / Nuit
- Bouton à trois états (`ThemeToggle`) dans le menu latéral, au-dessus de la cloche ; choix mémorisé dans `localStorage` (`lgit_theme`, protégé par try/catch ; « Auto » supprime la clé).
- Un script dans `index.html` applique le choix avant le premier affichage (pas de flash). `data-theme="light|dark"` force le thème ; sans attribut, le système décide (`:root:not([data-theme='light'])` dans le média sombre). `color-scheme` suit le choix pour les contrôles natifs.
- Vérifié : système sombre + « Clair » (persiste après rechargement), « Nuit » seul, « Auto » (suit le système, clé supprimée), système clair + « Nuit » (0 défaut de contraste).
