# ADMIN-01 — UX Design : Gestion des comptes admin et directeur

## Résumé des livrables

Cette analyse UX complète valide le workflow de gestion des comptes admin et directeur et fournit des maquettes interactives pour les 4 états clés d'affichage.

### Fichiers livrés

1. **`WORKFLOW_UX.md`** — Document d'analyse UX complet
   - Constats et parcours actuels
   - Parcours cibles détaillés (créer, lister, désactiver, réactiver, reset mot de passe)
   - Conséquences d'une désactivation (tokens, historique, assignations)
   - Intégration dans le menu et les routes React
   - Recommandations d'accessibilité (WCAG AA)
   - Nombre de clics optimisé (3-4 clics par action)

2. **`index.html`** — Hub de navigation
   - Index des maquettes
   - Notes de conception clés

3. **`01-liste-staff-filtre.html`** — Liste du staff avec filtres
   - ✅ État **Succès** (données avec admins/directeurs)
   - ⏳ État **Chargement** (skeleton screens)
   - 📭 État **Vide** (aucun résultat avec CTA)
   - ❌ État **Erreur** (message d'erreur réseau)
   - Filtres par rôle (Admin/Directeur/Tous) et statut (Actifs/Inactifs/Tous)
   - Statistiques (compteurs par rôle/statut)
   - Actions rapides (Voir, Désactiver/Réactiver)

4. **`02-creation-modale.html`** — Création d'un admin/directeur
   - État **Formulaire** (saisie rôle, nom, email)
   - État **Erreur** (email déjà pris avec validation de champ)
   - État **Succès** (mot de passe généré affiché + bouton Copier)
   - Mot de passe généré par le serveur (12 caractères)
   - Validation unicité email en temps réel
   - Note sur changement forcé à la 1ère connexion

5. **`03-fiche-detail-compte.html`** — Fiche détail avec bloc Compte
   - État **Staff actif** (email modifiable, boutons Reset + Désactiver)
   - État **Staff inactif** (réactivation possible, info sur désactivation)
   - État **Propre compte admin** (bouton Désactiver grisé + message explicatif)
   - Bloc Profil (nom, rôle, dates)
   - Bloc Compte (email + action Modifier, statut badge, actions)
   - Protection contre l'auto-désactivation

6. **`04-confirmation-desactivation.html`** — Modale d'impact et confirmation
   - État **Désactivation normale** (classes assignées, sessions à venir, heures en brouillon)
   - État **Dernier directeur** (avertissement non bloquant)
   - État **Auto-désactivation** (action bloquée, message explicatif)
   - Liste d'impact détaillée avec accès aux ressources associées
   - Boîtes d'information et d'avertissement intégrées

## Validation DoR

✅ **Definition of Ready** validée :

- ✅ Workflow UX complet → `WORKFLOW_UX.md`
- ✅ Mock-ups interactifs → 4 écrans + 10+ états
- ✅ 4 états clés affichés (chargement, vide, erreur, succès)
- ✅ Accessibilité (WCAG AA, focus, Échap, contraste)
- ✅ Isolement strict admin (directeur n'a pas accès)
- ✅ Intégration menu/routes proposées
- ✅ Nombre de clics optimisé (≤ 4)
- ✅ Réponses aux questions ouvertes (Q1–Q5)

## Décisions clés validées

| Sujet | Décision | Rationale |
|---|---|---|
| **Gestionnaires** | Admins seuls | Moindre privilège ; directeurs gèrent uniquement professeurs |
| **Mot de passe** | Généré serveur, affiché une fois | Sécurité ; changement forcé à la connexion |
| **Suppression** | Désactivation uniquement (v1) | Audit trail ; historique conservé |
| **Email** | Unique, modifiable via admin | Identifiant de connexion distinct du profil |
| **Auto-désactivation** | Bloquée (v2) | Pas de planification ; admin requiert confirmation |
| **Notification email** | Non v1 (hors périmètre) | Mot de passe communiqué manuellement |
| **Filtre statut** | Actifs/Inactifs/Tous | Permet filtrage personnalisé |
| **Dernier directeur** | Avertissement non bloquant | Cas exceptionnel toléré temporairement |

## Design System utilisé

- **Palette** : Bleus (2563eb, 1d4ed8), Vert (10b981), Rouge (ef4444), Gris (6b7280)
- **Typographie** : -apple-system, Segoe UI (system fonts)
- **Espacements** : 8px, 12px, 16px, 24px, 32px
- **Rayons** : 6px, 8px, 12px
- **Transitions** : 0.2s ease pour hover/focus

## Accessibilité garantie

✅ **Conformité WCAG AA** :

- Focus visible sur tous les éléments interactifs
- `role="dialog"`, `aria-modal`, `aria-labelledby` sur modales
- Échap ferme les modales et restitue le focus
- Contraste 4.5:1 minimum (texte)
- Statut jamais porté par la couleur seule (texte présent)
- Champs de formulaire liés par `aria-describedby`
- Erreurs annoncées avec `role="alert"`
- Bouton « Copier » annoncé en `role="status"`

## Intégration proposée

**Routes React** :
```jsx
<Route path="admin/staff" element={
  <ProtectedRoute roles={['admin']}>
    <AdminStaffPage />
  </ProtectedRoute>
} />
<Route path="admin/staff/:id" element={
  <ProtectedRoute roles={['admin']}>
    <AdminStaffDetailPage />
  </ProtectedRoute>
} />
```

**Menu** : Nouvel élément « Gestion du staff » sous Admin (visible admins seuls)

## Prochaines étapes

1. **Recette des maquettes** : Valider les workflows et états auprès des stakeholders
2. **Développement backend** : POST/PUT/DELETE endpoints, logique de mot de passe, révocation tokens
3. **Développement frontend** : Pages React, modales, filtres, intégration API
4. **Tests** : Feature tests E2E pour AC-1 à AC-13
5. **Mise en ligne** : Déploiement coordonné back + front

## Questions pour le client

Avant développement, valider auprès de la direction :

- **Q1** : Qui peut créer un nouvel admin ? (Admin seul pour l'instant)
- **Q2** : Motif de désactivation requis ? (Non pour v1)
- **Q3** : Qui change email/mot de passe ? (Admin seul)
- **Q4** : Email de notification à la création ? (Non, hors périmètre)
- **Q5** : Durée de conservation de l'audit ? (6 mois, comme PROF-01)

---

**Statut** : ✅ **DoR Validée** — Prêt pour la planification et le développement.

**Dernière mise à jour** : 2026-10-01  
**Auteur UX** : Claude Haiku 4.5  
**Analyste** : Gregory Pierquin
