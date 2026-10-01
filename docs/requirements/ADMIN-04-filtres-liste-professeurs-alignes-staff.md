# ADMIN-04 — Filtres de la liste des professeurs alignés sur la gestion du staff

| | |
|---|---|
| **Statut** | ☑ **DoR validée** — avis UX + maquettes validés le 2026-10-01 (D1 à D6 acceptées ; D4 : « Actifs » par défaut partout) |
| **Liens** | Avis UX et maquettes : `docs/mockups/ADMIN-04/` · Précédents : ADMIN-02, ADMIN-03 |

## 1. Contexte et problème
La page staff filtre par listes déroulantes (Statut, Rôle, Accès) ; la page professeurs par deux rangées de 8 boutons-bascule, sans recherche. Les défauts de Statut diffèrent (« Tous » / « Actifs »). La page staff démontait sa barre quand un filtre serveur ne renvoyait rien (utilisateur coincé, focus perdu), ses champs n'avaient pas de libellé associé, et aucun écran ne conservait ses filtres au retour d'une fiche.

## 2. Objectifs
| Objectif | Indicateur | Cible |
|---|---|---|
| Une seule barre de filtres pour les deux listes | composant partagé | oui |
| Retrouver un professeur | recherche nom/email | ≤ 2 actions |
| Filtres conservés au retour d'une fiche | URL | oui |
| Accessibilité de la barre | libellés associés, région nommée, compteur annoncé | 100 % |

**Hors périmètre :** pagination et filtres serveur supplémentaires, tri, filtre « sans classe », refonte de la carte professeur, optimisation du chargement des tarifs.

## 3. Rôles et permissions
Inchangés (admin pour le staff ; admin et directeur pour les professeurs). Aucune évolution backend.

## 4. Règles
- RG-1 Barre commune : Rechercher, Statut, Rôle (staff) ou Contrat (professeurs), Accès, Réinitialiser, dans cet ordre.
- RG-2 Statut par défaut = **Actifs** sur les deux pages ; « Désactivés » et « Tous » à un clic ; « Inclure les désactivés » proposé quand rien ne correspond.
- RG-3 Statut (et Rôle) = filtres serveur ; Recherche, Contrat, Accès = filtres client ; la différence est invisible.
- RG-4 Recherche : insensible à la casse et aux accents, tous les mots doivent correspondre (nom, email, email de connexion).
- RG-5 Filtres dans l'URL (`q`, `statut`, `role`/`contrat`, `acces`), `replace`, valeurs par défaut omises, valeurs inconnues ignorées.
- RG-6 La barre n'est jamais démontée sauf liste réellement vide (aucun filtre actif) ; pendant un rechargement seule la liste est en chargement.
- RG-7 Compteur annoncé (`role=status`) : « N professeurs » / « N professeurs sur M » ; le compteur « à relancer » devient un raccourci vers Accès = « À relancer ». Badge d'en-tête supprimé.
- RG-8 Réinitialiser remet les valeurs par défaut (désactivé si tout est par défaut) et place le focus sur Rechercher.
- RG-9 ≤ 600 px : recherche visible, autres filtres sous « Filtres (N) » (`aria-expanded`).

## 5. Impact technique
Frontend uniquement : `components/ui/Filters.jsx` (FilterSearch, FilterToolbar, ResultCount, `id` par défaut), `hooks/useFiltresListe.js`, `utils/filtres.js`, `useStaff` (état « chargement » lié aux filtres demandés), `StaffAdminPage`, `AdminProfesseursPage`. Écrans Classes/Calendrier : `FilterField` inchangé visuellement.

## 6. Plan de recette
1. Défauts : Statut « Actifs », Réinitialiser désactivé, compteur « N comptes/professeurs ».
2. Recherche « DIRECT » trouve « Directrice » ; plusieurs mots (« PROF alice ») ; compteur « 1 sur 3 ».
3. Aucun résultat : barre visible, « Réinitialiser les filtres », « Inclure les désactivés ».
4. Filtre serveur sans résultat (Désactivés) : barre conservée.
5. Réinitialiser : valeurs par défaut, URL propre, focus sur Rechercher (y compris après un filtre serveur).
6. « Voir détails » puis retour : filtres conservés.
7. Mobile 375 px : recherche + « Filtres (N) » ; aucun débordement.
8. Écrans Classes et Calendrier inchangés.

## 7. Validation (DoR)
☑ Avis UX ☑ Maquettes ☑ Décisions D1–D6 (2026-10-01)
