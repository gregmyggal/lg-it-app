# PROF-01 — Workflow UX : ajouter, activer/désactiver un professeur et son compte

> Statut : **proposition à valider** (aucun développement avant validation des maquettes).
> Maquettes : `index.html`, `01` à `05` (ce dossier). Références : `docs/DEVELOPMENT_STANDARDS.md`, `docs/mockups/CLS-01/WORKFLOW_UX.md`.

## 1. Constats vérifiés

- Route `/admin/professeurs` = `pages/AdminProfesseursPage.jsx` (liste **tarifs** : cartes + lien vers la fiche). `/admin/professeurs/:id` = `AdminProfesseurDetail` (fiche, section Classes T2, bouton « Supprimer » = `DELETE`).
- `pages/admin/ProfesseursAdminPage.jsx` (cartes + modales création/édition) est **importé dans `App.jsx` mais jamais routé** : le formulaire de création n'est donc atteignable par aucun menu. Les maquettes CLS-01-T5 le décrivent comme s'il l'était.
- Backend : `store` crée User(role=professeur)+Professeur en transaction (mot de passe saisi, min 8) ; `update` sans email de connexion ni mot de passe ; `destroy` supprime le User (cascade). `statut`/`date_sortie` sans effet ; `AuthController::login` ne teste pas le statut ; aucune révocation de tokens.

## 2. Parcours actuels

| Besoin | Aujourd'hui |
|---|---|
| Créer un prof | Impossible depuis l'UI routée (page orpheline). |
| Désactiver | Éditer `statut` (page orpheline) : aucun effet, le prof se connecte. |
| Reset mot de passe / email de connexion | Impossible. |
| Retirer un prof | `Supprimer` sur la fiche : destruction du compte et cascade ; risque pour timesheets, assignations, sessions. |

## 3. Parcours cibles

**Principe : une seule page liste (`/admin/professeurs`) → fiche (`/:id`).** On fusionne la liste tarifs et la liste gestion en une liste unique (tableau + filtre) ; les modales de `ProfesseursAdminPage` sont réutilisées, la page orpheline est supprimée. Le bouton « Tarifs » reste sur la fiche (inchangé).

1. **Créer** (liste → « Nouveau professeur », maquette 02) : bloc Compte (email de connexion) + bloc Profil. **Mot de passe généré par le serveur** (12 car.), renvoyé **une seule fois** dans la réponse et affiché dans un écran de confirmation avec « Copier ». Compte créé avec `must_change_password=true` (changement forcé à la 1re connexion). Statut = actif. Ensuite → fiche pour ajouter classes et tarif.
2. **Désactiver** (fiche → bloc Compte → « Désactiver… », maquette 04) : modale d'impact, date de sortie (défaut = aujourd'hui), choix du sort des assignations, confirmation. Un seul bouton, une seule action API : `POST /professeurs/{id}/desactiver`.
3. **Réactiver** (fiche d'un inactif → « Réactiver ») : `POST /professeurs/{id}/reactiver` : statut actif, `date_sortie` effacée, mot de passe temporaire régénéré et affiché une fois (l'ancien est périmé). Les classes ne sont **pas** réaffectées automatiquement.
4. **Réinitialiser le mot de passe** (fiche → « Réinitialiser », maquette 03) : confirmation simple, nouveau mot de passe temporaire affiché une fois, tokens révoqués, changement forcé.
5. **Modifier l'email de connexion** (fiche → « Modifier » à côté de l'email, formulaire inline) : unicité vérifiée, tokens révoqués, l'email de contact du profil n'est pas touché.
6. **Liste** (maquette 01) : filtre « Actifs (défaut) / Inactifs / Tous » avec compteurs, recherche, colonnes statut, classes actives (badge « à affecter » si 0 pour un actif), état de la connexion.

## 4. Conséquences d'une désactivation (décisions)

| Élément | Décision |
|---|---|
| Connexion | Bloquée. `login` : après vérification du mot de passe correct seulement, si inactif → erreur 403 « Ce compte est désactivé. Contactez la direction de votre école pour le réactiver. » (mauvais mot de passe = « Identifiants invalides. », pas de fuite d'info). |
| Tokens | Tous révoqués dans la même transaction ; middleware de garde (`professeur.statut=actif`) pour le cas résiduel → 401 + « Votre accès a été désactivé. » |
| Historique | Timesheets, heures, tarifs, sessions passées : **conservés, intacts**, consultables et exportables par la direction. |
| Assignations de classes actives | La modale propose : terminer à la date de sortie (**défaut**) ou laisser ouvertes. Pas de blocage. |
| Sessions futures | Non réaffectées automatiquement. La modale compte les séances à venir sans remplaçant et renvoie vers le calendrier filtré (réutilise le flux « remplacement » T2). |
| Heures en brouillon / à valider | Comptées dans la modale ; la direction peut toujours les valider ensuite. Le prof ne peut plus en saisir. |
| Apparaît dans les sélecteurs | Les inactifs sont exclus des listes d'affectation et de remplacement ; visibles dans l'historique. |

## 5. Messages d'erreur

- Email déjà pris (création / changement) : bandeau « Cet email de connexion est déjà utilisé » + message de champ ; **ne pas** divulguer le détail à un non-staff (inutile ici, staff only).
- Auto-désactivation : bouton désactivé, message « Vous ne pouvez pas désactiver votre propre compte » (backend : 422 aussi). Un professeur n'est de toute façon pas un utilisateur staff ; le cas concerne un compte à double casquette (staff + fiche professeur).
- Prof désactivé qui se connecte : maquette 05.
- Échec réseau sur les actions : bandeau `role=alert` dans la modale, bouton réactivé, rien n'est changé silencieusement.

## 6. Cas limites

- **Dernier prof d'une classe** : avertissement orange non bloquant dans la modale (la classe sera « sans professeur ») avec lien vers la classe. Non bloquant car un départ est un fait ; la direction doit pouvoir l'enregistrer.
- **Suppression vs désactivation** : **remplacer la suppression dure par la désactivation.** Supprimer le bouton `Supprimer` et la route `DELETE` pour tout professeur ayant des données (timesheets, assignations, sessions). Garder, en lien discret « Supprimer définitivement », uniquement pour un professeur créé par erreur **sans aucune donnée liée** (le backend refuse sinon, 409).
- **Réactivation avec un email de connexion devenu pris** : impossible (l'email reste réservé au compte) ; rien à gérer.
- **Désactivation déjà faite / double clic** : action idempotente.
- **Date de sortie dans le futur** : le statut passe à inactif à cette date (tâche planifiée) ; v1 recommandée : date = aujourd'hui uniquement, pas de planification.

## 7. Accessibilité

Focus initial sur « Annuler » dans les modales destructrices ; `role=alertdialog`/`dialog`, `aria-modal`, Échap ferme ; focus restitué au déclencheur ; erreurs de champ liées par `aria-describedby` + `aria-invalid` ; filtre en `role=group` avec `aria-pressed` ; statut jamais porté par la couleur seule (texte dans le badge) ; mot de passe sélectionnable et bouton « Copier » annoncé (`role=status`).

## 8. Recommandations tranchées

1. Unifier liste tarifs + gestion sur une page ; supprimer la page orpheline.
2. Désactivation = action dédiée (pas un simple champ de formulaire), avec modale d'impact.
3. Mot de passe généré serveur, affiché une fois, changement forcé.
4. Suppression dure retirée (sauf professeur vierge).
5. Statut vérifié côté backend (login + middleware), jamais seulement côté UI.

## 9. Questions ouvertes pour le client

1. Le professeur doit-il recevoir un email d'invitation / de reset (lien) plutôt qu'un mot de passe communiqué de la main à la main ? (Nécessite un service mail.)
2. Par défaut, faut-il terminer automatiquement les assignations à la désactivation ?
3. Un professeur sorti doit-il garder un accès en lecture à ses heures passées ?
4. Qui peut désactiver : directeur et admin, ou admin seul ?
5. Une date de sortie future doit-elle désactiver automatiquement à cette date ?
