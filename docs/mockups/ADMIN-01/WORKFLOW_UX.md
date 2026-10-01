# ADMIN-01 — Workflow UX : créer et gérer les comptes admin et directeur

> Statut : **proposition à valider** (aucun développement avant validation des maquettes).
> Maquettes : `index.html`, `01` à `04` (ce dossier). Références : `docs/DEVELOPMENT_STANDARDS.md`, `docs/mockups/PROF-01/WORKFLOW_UX.md`.

## 1. Constats vérifiés

- **Route** : `/admin/staff` = `pages/AdminStaffPage.jsx` (page à créer) - liste des admins et directeurs, fusion d'une possible gestion séparée.
- **Backend** : Actuellement aucune interface ; comptes créés en CLI. La création devra utiliser les mêmes patterns que PROF-01 (User + enregistrement lié, mot de passe temporaire, `must_change_password`).
- **Différence clé vs PROF-01** : Seul l'**admin** peut gérer d'autres admins et directeurs ; les directeurs **n'ont pas accès** à ce menu (pas de cross-role management).
- **Statut** : Comptes actif/inactif ; la désactivation coupe immédiatement l'accès via token et middleware.

## 2. Parcours actuels

| Besoin | Aujourd'hui |
|---|---|
| Créer un directeur/admin | Impossible depuis l'UI (CLI uniquement). |
| Désactiver | Impossible (pas d'interface). |
| Reset mot de passe | Impossible. |
| Modifier email de connexion | Impossible. |

## 3. Parcours cibles

**Principe : une seule page `/admin/staff` (liste des admins et directeurs avec filtre par rôle/statut).**

### 3.1 Créer un directeur/admin (modale « Nouveau staff », maquette 02)
1. Admin clique « Créer un directeur » ou « Créer un admin »
2. Modale s'ouvre : bloc Compte (email de connexion) + bloc Profil (nom complet)
3. Email est **unique** (vérification backend)
4. Mot de passe **généré par le serveur** (12 caractères), renvoyé **une seule fois** dans une réponse de confirmation
5. Affichage du mot de passe avec bouton « Copier » (annoncé en `role=status`)
6. Nouveau compte avec `must_change_password=true` (changement forcé à la 1ère connexion)
7. Statut = `actif`
8. Retour à la liste, nouveau compte visible (filtré actifs par défaut)

### 3.2 Lister et filtrer (maquette 01)
- **Liste unique** : admins + directeurs, triables/filtrables
- **Filtre par rôle** : Admin, Directeur, Tous (défaut : Tous)
- **Filtre par statut** : Actifs (défaut), Inactifs, Tous
- **Compteurs** : nombre actifs/inactifs par rôle
- **Colonnes** : Nom | Email | Rôle | Statut (badge vert/gris) | Actions (voir, désactiver/réactiver)
- **Recherche** : par nom ou email
- **États** : Chargement, Vide, Erreur, Succès (donnés)

### 3.3 Voir la fiche de détail (maquette 03)
- Admin clique sur un directeur/admin dans la liste
- Page `/admin/staff/:id` affiche :
  - Bloc Profil (nom complet, date de création)
  - Bloc Compte de connexion :
    - Email de connexion (avec bouton « Modifier » inline)
    - Statut (badge Actif/Désactivé)
    - Bouton « Réinitialiser le mot de passe »
    - Bouton « Désactiver » (ou « Réactiver » s'il est inactif)
  - **Note importante** : Un admin **ne peut pas désactiver son propre compte** (bouton grisé + message)

### 3.4 Désactiver un directeur/admin (maquette 04)
1. Admin clique « Désactiver » sur la fiche (bloc Compte)
2. Modale d'impact s'ouvre :
   - Affiche les classes actuelles assignées au directeur
   - Affiche les sessions à venir sans remplaçant
   - Affiche les heures en brouillon / à valider
   - Avertissement si c'est le dernier directeur/admin (non bloquant)
   - Avertissement si l'admin tente de se désactiver (blocage, bouton « Désactiver » désactivé)
3. Admin confirme → POST `/staff/{id}/desactiver`
4. Statut passe à `inactif`, `date_sortie` renseignée, tokens révoqués
5. Retour à la liste, badge = Désactivé

### 3.5 Réactiver
1. Admin clique « Réactiver » sur la fiche d'un inactif
2. Confirmation simple (pas de modale d'impact)
3. POST `/staff/{id}/reactiver`
4. Statut passe à `actif`, `date_sortie` = null, mot de passe provisoire régénéré
5. Retour à la liste, badge = Actif

### 3.6 Réinitialiser le mot de passe
1. Admin clique « Réinitialiser le mot de passe » sur la fiche
2. Confirmation simple : « Êtes-vous sûr ? Un nouveau mot de passe sera généré. »
3. POST `/staff/{id}/reinitialiser-mot-de-passe`
4. Nouveau mot de passe temporaire affiché une fois, tokens révoqués
5. Changement forcé à la prochaine connexion

### 3.7 Modifier l'email de connexion
1. Admin clique « Modifier » à côté de l'email de connexion (maquette 03, formulaire inline ou modale légère)
2. Entre un nouvel email
3. Vérification unicité
4. PUT `/staff/{id}/compte` (email)
5. Tokens révoqués
6. Retour à la fiche, email mis à jour

## 4. Conséquences d'une désactivation

| Élément | Décision |
|---|---|
| Connexion | Bloquée. `login` : après vérification du mot de passe correct seulement, si inactif → erreur 403 « Ce compte est désactivé. Contactez l'administrateur pour le réactiver. » (mauvais mot de passe = « Identifiants invalides. », pas de fuite d'info). |
| Tokens | Tous révoqués immédiatement ; middleware de garde : 401 + « Votre accès a été désactivé. » |
| Historique | Données liées (classes assignées, sessions, timesheets) : **conservés, intacts**, consultables par d'autres admins/directeurs. |
| Assignations de classes | Les classes perte d'assignation ; avertissement dans la modale. Pas de réaffectation automatique. |
| Sessions futures | Non réaffectées. Modale compte les sessions à venir sans remplaçant. |
| Apparaît dans les sélecteurs | Les inactifs sont exclus des listes d'affectation et de remplacement ; visibles dans l'historique/audit. |

## 5. Messages d'erreur et cas limites

### Messages d'erreur
- **Email déjà pris** (création / changement) : bandeau « Cet email de connexion est déjà utilisé » + message de champ ; validation backend 422.
- **Auto-désactivation** : bouton désactivé, message « Vous ne pouvez pas désactiver votre propre compte » ; backend refuse aussi (403).
- **Dernier admin/directeur** : avertissement orange non bloquant dans la modale (« Vous êtes le dernier admin du système »). Non bloquant car une structure peut vouloir ce cas temporairement ; mais UX avertit.
- **Échec réseau** : bandeau `role=alert` dans la modale, bouton réactivé, rien n'est changé silencieusement.

### Cas limites
- **Suppression de compte** : Hors périmètre v1 ; seul la désactivation est proposée. Backend refuse `DELETE` pour tout compte ayant des données.
- **Réactivation d'un compte inactif** : Email est toujours réservé au compte (aucun conflit possible).
- **Désactivation deux fois** : Action idempotente ; pas d'erreur.
- **Date de sortie** : Renseignée automatiquement = aujourd'hui ; pas de date future planifiée (v2).

## 6. Intégration dans le menu et les routes

**Menu latéral (PortalLayout)** :
- Nouvel élément sous « Admin » : « Gestion du staff » ou « Équipe administrateur »
- Visible uniquement pour les admins (`role === 'admin'`)
- Route `/admin/staff` → `pages/AdminStaffPage.jsx`
- Route `/admin/staff/:id` → `pages/AdminStaffDetailPage.jsx`

**ProtectedRoute** :
```jsx
<Route
  path="admin/staff"
  element={
    <ProtectedRoute roles={['admin']}>
      <AdminStaffPage />
    </ProtectedRoute>
  }
/>
<Route
  path="admin/staff/:id"
  element={
    <ProtectedRoute roles={['admin']}>
      <AdminStaffDetailPage />
    </ProtectedRoute>
  }
/>
```

## 7. Accessibilité

- **Focus initial** sur « Annuler » dans les modales destructrices ; `role=alertdialog` pour les avertissements, `role=dialog` pour les neutres.
- **Aria** : `aria-modal="true"`, `aria-labelledby`, `aria-describedby` pour les erreurs, `aria-invalid` + `aria-pressed` pour les filtres.
- **Échap** ferme les modales et restitue le focus au déclencheur.
- **Statut** : Jamais porté par la couleur seule (badge + texte : « Actif » ou « Désactivé »).
- **Mot de passe** : Sélectionnable, bouton « Copier » annoncé en `role=status` (« Mot de passe copié »).
- **Contraste** : 4.5:1 minimum (WCAG AA).
- **Clavier** : Tab, Shift+Tab, Entrée, Échap ; tous les boutons et liens accessibles.

## 8. Nombre de clics

| Parcours | Nombre de clics | Chemin |
|---|---|---|
| Créer un directeur | 3 | Menu « Gestion du staff » → Bouton « Créer » → Remplir + Soumettre |
| Lister | 1 | Menu « Gestion du staff » |
| Désactiver | 4 | Liste → Clic directeur → Bloc Compte → Bouton « Désactiver » → Confirmer |
| Réinitialiser mot de passe | 3 | Fiche → Bloc Compte → Bouton « Reset » → Confirmer |
| Modifier email | 3-4 | Fiche → Bloc Compte → Bouton « Modifier » → Entrer email → Soumettre |

## 9. Recommandations tranchées

1. **Une page liste unique** pour admins + directeurs (pas de séparation par rôle dès le départ, mais filtre disponible).
2. **Seul l'admin** peut créer/gérer d'autres admins et directeurs ; isolement strict : directeur n'a pas accès à ce menu.
3. **Mot de passe généré serveur** (12 car.), affiché une fois, changement forcé à la 1ère connexion.
4. **Désactivation = action dédiée** (pas un simple champ formulaire), avec modale d'impact.
5. **Pas de suppression dure** ; seule la désactivation (ou suppression logique pour comptes vierges) est proposée.
6. **Statut vérifié côté backend** (login + middleware), jamais seulement côté UI.
7. **Audit trail** : chaque action loggée (qui, quand, action, ancien/nouveau statut).

## 10. Questions tranchées (vs PROF-01)

| Question | Réponse | Raison |
|---|---|---|
| Qui peut gérer ? | Admins seuls. | Principe de moindre privilège ; directeurs gèrent uniquement les professeurs. |
| Auto-désactivation à une date ? | Non (v2). | Pas de feature de planification à court terme. |
| Supprimer compte ? | Non (sauf cas exceptionnel, hors UI). | Traçabilité ; toujours désactiver pour l'audit. |
| Email de contact = email de connexion ? | Non. | Separabilité ; l'email de connexion est l'identifiant unique, email de contact ne change pas. |
| Reset motif requis ? | Non. | Motif dans l'audit trail (toute action loggée), pas besoin d'input utilisateur. |
| Notifier par email ? | Non (v1). | Hors périmètre ; directeur reçoit le mot de passe de la main à la main. |

## 11. Cas d'usage avancés (pas de développement spécifique, gérés par les règles existantes)

- **Compte staff sans donnée liée** : Peut être supprimé (backend 204) ; lien discret « Supprimer définitivement » sur la fiche.
- **Directeur assigné à des classes** : Désactivation possible, mais avertissement dans la modale d'impact.
- **Création de compte par erreur** : Suppressible immédiatement si aucune donnée ; sinon, désactiver.

---

## Maquettes

- **index.html** : Hub et navigation
- **01-liste-staff-filtre.html** : Liste avec filtres rôle/statut, états (chargement, vide, erreur, succès)
- **02-creation-modale.html** : Création admin/directeur, affichage mot de passe, erreur email
- **03-fiche-detail-compte.html** : Fiche avec bloc Compte (email, statut, actions)
- **04-confirmation-desactivation.html** : Modale d'impact + confirmation
