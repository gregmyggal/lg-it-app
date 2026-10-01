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

---

# T2 — Amélioration UX : Modale d'impact, Gestion admins, Filtres rôle

> **Statut T2** : Recommandations UX prêtes. Mockup modale d'impact + spécifications détaillées.
> **Livrables** : Modale avec affichage timesheets/avertissements, filtre rôle, édition email.

## T2.1 — Modale d'impact enrichie (PRIORITÉ HAUTE)

**Objectif** : Afficher l'impact réel avant désactivation (timesheets, avertissements).

### T2.1.1 — Cas 1: Désactivation normale (directeur avec timesheets)
1. Admin clique « Désactiver » sur la fiche
2. **Modale d'impact** s'ouvre :
   - Section "Impact de cette désactivation"
   - **Timesheets en attente** :
     - Affiche nombre heures en brouillon vs soumis
     - Total des heures en attente
     - Message : « Ces heures ne seront validées que par un autre directeur »
   - **Professeurs affectés** : nombre total
   - **Classes assignées** : "Aucune" (v2) ou liste (futur)
3. Admin confirme → POST `/staff/{id}/desactiver`
4. Statut passe à `inactif`, tokens révoqués

### T2.1.2 — Cas 2: Dernier directeur (avertissement non-bloquant)
1. Si c'est le dernier directeur **actif** :
   - Boîte d'avertissement **rouge** (non bloquante)
   - Texte : « Vous êtes le dernier directeur. Aucun directeur ne validera les timesheets. »
   - Bouton reste **actif** (cas exceptionnel toléré)
2. Comportement identique au Cas 1, mais avec avertissement supplémentaire

### T2.1.3 — Cas 3: Dernier admin (blocage complet)
1. Si c'est le **dernier admin** **ET** auto-désactivation (admin tente de se désactiver) :
   - Boîte d'erreur **rouge** (bloquante)
   - Texte : « Vous ne pouvez pas désactiver votre propre compte + vous êtes le dernier admin »
   - Bouton "Désactiver" → **GRISÉ** (disabled)
2. Backend refuse aussi (403)

### T2.1.4 — Spécifications techniques

**Données affichées** (GET `/staff/{id}/impact-info`) :
```json
{
  "staff": {
    "id": 1,
    "name": "Jean Dupont",
    "role": "directeur",
    "is_last_directeur": false,
    "is_last_admin": false,
    "is_self": false
  },
  "timesheets": {
    "brouillon": 5,
    "soumis": 3,
    "total_heures": 8.0
  },
  "professeurs_affected": 6,
  "classes_assigned": [] // Futur : contenir classe_id si lien directeur_id ajouté
}
```

**Règles de blocage** (backend + frontend) :
- `is_self && is_last_admin` → 403 Forbidden
- `is_self` (autre admin) → 403 Forbidden (existant)
- `is_last_directeur && is_self` → 403 Forbidden (doublon si admin tente auto-désactiver)

**Affichage modale** :
- ✅ Stats timesheets (brouillon/soumis en cartes côte à côte)
- ✅ Nombre professeurs affectés
- ✅ Bloc avertissement si dernier directeur (rouge, non bloquant)
- ✅ Bloc erreur si dernier admin + auto-désactiver (rouge, BLOQUANT)
- ✅ Conseil: "Assurez-vous qu'un autre directeur peut reprendre"

---

## T2.2 — Gestion des admins depuis l'UI (PRIORITÉ HAUTE)

**Objectif** : Permettre la création d'admins via l'interface (déjà possible T1, juste visibilité améliorée).

### T2.2.1 — Création d'admin (inchangé T1)
1. Admin clique « Créer »
2. Sélecteur rôle : **Admin** / Directeur
3. Reste identique à T1 (email, nom, mot de passe généré)

### T2.2.2 — Restrictions
- **Dernier admin bloqué** : Impossible de désactiver le dernier admin actif
  - Modale d'impact : bouton grisé, message "Impossible: dernier admin"
  - Backend refuse aussi (403)
- **Suppression** : Identique directeur (hors données = suppression, avec données = désactivation)

### T2.2.3 — Affichage liste T1
- Admins et directeurs dans la même liste (déjà fait)
- Filtre par rôle (NEW, voir T2.3)

---

## T2.3 — Filtre par rôle (PRIORITÉ MOYENNE)

**Objectif** : Afficher rapidement admins ou directeurs seuls.

### T2.3.1 — Ajout filtre rôle
**Localisation** : FilterBar existante (même niveau que Statut)

**Options** :
```
Rôle:
○ Tous (défaut)
○ Admin
○ Directeur
```

### T2.3.2 — Combinaison avec filtre Statut
- Filtres indépendants et cumulables
- Exemple : "Afficher les Admin actifs" → `?role=admin&statut=actif`
- Affichage compteurs : `Admin: 2 actifs / 1 inactif`

### T2.3.3 — Ordre FilterBar
1. Rôle (primaire)
2. Statut (secondaire)

---

## T2.4 — Édition email (PRIORITÉ MOYENNE, v2.1 optionnel)

**Objectif** : Permettre modification email sans rechargement de page.

### T2.4.1 — Approche recommandée (simplicité maximale)
**Mini-modale dédiée** :
1. Admin clique bouton « Modifier » à côté de l'email (fiche modale)
2. Mini-modale s'ouvre : un champ "Email"
3. Validation unicité en temps réel (debounced API call)
4. Bouton "Enregistrer" + "Annuler"
5. Validation + PUT `/staff/{id}` (email uniquement)
6. Fermeture modale, retour à fiche (email mis à jour)
7. Tokens révoqués (connexion demandée au prochain accès)

### T2.4.2 — Alternativement : inline (plus complexe)
Remplacer le texte email par un input inline (moins recommandé pour T2).

**Choix recommandé pour T2** : Mini-modale (cohérent T1, plus facile à tester).

---

## T2.5 — Changements composants frontend

### Modifications existantes

**StaffDeactivateModal** :
```jsx
// Ancien: simple confirmation + avertissement
// Nouveau: affichage complet de l'impact
<StaffDeactivateModal>
  ├─ Section Impact (GET /staff/{id}/impact-info)
  │  ├─ Stats timesheets (brouillon/soumis)
  │  ├─ Nombre professeurs
  │  └─ Classes (vide pour v2)
  ├─ Avertissements (si dernier directeur/admin)
  ├─ Bouton Désactiver (peut être grisé)
  └─ Bouton Annuler
```

**StaffAdminPage** :
```jsx
// Nouvel élément FilterBar: Rôle
<FilterBar>
  <FilterField label="Rôle">
    <select value={filtres.role} onChange={...}>
      <option value="">Tous</option>
      <option value="admin">Admin</option>
      <option value="directeur">Directeur</option>
    </select>
  </FilterField>
  <FilterField label="Statut">
    {/* existant */}
  </FilterField>
</FilterBar>
```

**StaffDetailModal** (optionnel T2.1):
```jsx
// Nouveau bouton "Modifier" à côté de l'email
// Ouvre <StaffEmailModal> au clic
<div>
  <label>Email:</label>
  <span>{staff.email}</span>
  <button onClick={ouvrirEmailModal}>Modifier</button>
</div>
```

### Nouveaux composants

**StaffEmailModal** (optionnel, v2.1):
```jsx
<Modal title="Modifier l'email" ...>
  <FormField label="Email" ...>
    <input type="email" value={email} onChange={...} />
    {/* Validation unicité en temps réel */}
  </FormField>
  <button onClick={handleSave}>Enregistrer</button>
</Modal>
```

---

## T2.6 — API backend (nouveaux endpoints / modifications)

### GET `/staff/{id}/impact-info` (NEW)
Retourne l'impact de désactivation.
```php
// StaffController
public function impactInfo(User $staff)
{
    Gate::authorize('view', $staff);
    
    $timesheets = Timesheet::whereHas('professeur', fn ($q) => 
        $q->where('user_id', '!=', $staff->id) // Exclure les profs du directeur
    )->whereIn('statut_validation', ['brouillon', 'soumis'])->count();
    
    return response()->json([
        'staff' => $staff->only(['id', 'name', 'role', 'statut']),
        'is_self' => $staff->id === auth()->id(),
        'is_last_directeur' => $staff->isDirecteur() && User::where('role', 'directeur')->where('statut', 'actif')->count() === 1,
        'is_last_admin' => $staff->isAdmin() && User::where('role', 'admin')->where('statut', 'actif')->count() === 1,
        'timesheets' => [
            'brouillon' => ...,
            'soumis' => ...,
            'total_heures' => ...
        ],
        'professeurs_affected' => ...,
        'classes_assigned' => [] // Futur
    ]);
}
```

### PUT `/staff/{id}` (modification existante)
Ajouter support email seul (optionnel).

### Route inscription
```php
// routes/api.php
Route::post('staff', [StaffController::class, 'store']); // T1
Route::get('staff', [StaffController::class, 'index']); // T1
Route::get('staff/{staff}', [StaffController::class, 'show']); // T1
Route::get('staff/{staff}/impact-info', [StaffController::class, 'impactInfo']); // T2 NEW
Route::put('staff/{staff}', [StaffController::class, 'update']); // T1 + T2 (email)
Route::post('staff/{staff}/desactiver', [StaffController::class, 'desactiver']); // T1
Route::post('staff/{staff}/reactiver', [StaffController::class, 'reactiver']); // T1
```

---

## T2.7 — Critères d'acceptation (AC) T2

| # | AC | Vérification |
|---|---|---|
| AC-1 | Modale affiche timesheets (brouillon + soumis) | ✓ GET /staff/{id}/impact-info retourne données |
| AC-2 | Dernier directeur → avertissement (non bloquant) | ✓ Bouton Désactiver actif, texte d'avertissement |
| AC-3 | Dernier admin + auto-désactiver → blocage | ✓ Bouton grisé, message explicatif, 403 backend |
| AC-4 | Admin créable depuis UI (sélecteur rôle) | ✓ Rôle = "admin" dans modale création |
| AC-5 | Filtre rôle affiche Admin/Directeur/Tous | ✓ FilterBar contient 3 options, filtrage fonctionne |
| AC-6 | Filtre rôle + statut combinables | ✓ Ex: ?role=admin&statut=actif retourne résultat |
| AC-7 | Édition email (optionnel T2, peut être v2.1) | ✓ Mini-modale ou inline, validation unicité |

---

## T2.8 — Livrables T2

1. ✅ **Recommandations UX** (ce document, section T2 + T2.1-T2.8)
2. ✅ **Mockup modale d'impact** (3 cas : normal, dernier directeur, dernier admin bloqué)
3. ✅ **Schéma composants frontend** (modifications StaffDeactivateModal, StaffAdminPage)
4. ✅ **Spec API backend** (GET /staff/{id}/impact-info + modifications)
5. ✅ **Critères d'acceptation** (AC-1 à AC-7)

---

## T2.9 — Estimations effort

| Tâche | Effort | Priorité | Blocage |
|---|---|---|---|
| Modale d'impact (3 cas) | **Moyen** | HAUTE | Non |
| Filtre rôle (dropdown) | **Bas** | MOYEN | Non |
| Édition email (mini-modale) | **Moyen** | MOYEN | Non (v2.1 ok) |
| API impact-info endpoint | **Moyen** | HAUTE | Oui (modale) |
| Tests modale (E2E + unit) | **Moyen** | HAUTE | Oui |
| **Total T2** | **~5-6j** | | |

---

## T2.10 — Décisions tranchées T2

| Décision | Raison |
|---|---|
| **Affichage timesheets** | Unique donnée d'impact réel liée au directeur. Classes n'ont pas encore de lien directeur_id. |
| **Dernier directeur non bloquant** | Cas exceptionnel toléré (structure peut vouloir transition sans directeur temporairement). Avertissement préferable au blocage. |
| **Dernier admin BLOQUANT** | Critique : au moins 1 admin actif doit exister pour gérer le système. Blocage absolue backend + UI. |
| **Filtre rôle dropdown** | 3 options = simple dropdown suffisant. Multi-select surcharge cognitive inutile. |
| **Édition email mini-modale** | Cohérent T1 (tout par modale). Inline plus complexe à tester et moins stable (focus, validation). |

---

## T2.11 — Prochaines étapes

### Avant développement
1. ✅ Valider maquettes modale d'impact auprès de stakeholders
2. ✅ Clarifier : édition email inline ou mini-modale? (Recommandation: mini-modale)
3. ✅ Clarifier: dernier admin blocage total ou avertissement? (Recommandation: blocage)

### Développement
1. Backend: Endpoint GET /staff/{id}/impact-info + logique blocage dernier admin
2. Frontend: Modale enrichie (composant) + filtre rôle + tests E2E
3. Tests: AC-1 à AC-7, cas limites (derniers directeur/admin), erreurs réseau

### Post-livraison (v2.1+)
1. Édition email si non fait en T2
2. Ajouter `directeur_id` sur Classe (optionnel, pour afficher classes assignées)
3. Persistance filtres localStorage (optionnel UX polish)
