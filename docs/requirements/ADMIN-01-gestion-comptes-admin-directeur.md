# ADMIN-01 — Gestion des comptes admin et directeur (création, modification, désactivation)

| | |
|---|---|
| **Statut** | ☑ **DoR validée** — Workflow UX + maquettes validées, prêt pour développement |
| **Analyste** | Gregory Pierquin | **UX/UI** | Claude Haiku 4.5 |
| **Date / Version** | 2026-10-01 | **Sprint cible** | À planifier |
| **Liens** | Workflow UX : `docs/mockups/ADMIN-01/WORKFLOW_UX.md` · Mock-ups : `docs/mockups/ADMIN-01/` · Modèle classes : `docs/adr/0001-modele-cours-classe-session.md` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** Les administrateurs et directeurs n'ont pas d'interface pour gérer les autres administrateurs et directeurs. La création/modification d'admins et directeurs se fait uniquement en ligne de commande (`php artisan`) ou manuellement en base. Aucune interface de gestion, aucun contrôle d'accès, aucune piste d'audit.
- **Douleur / coût :** 
  - Impossibilité pour un admin de déléguer ses tâches de gestion à un autre admin
  - Pas de continuité en cas d'absence d'un admin
  - Risque de créer des comptes invalides
  - Pas de traçabilité sur les changements (qui a créé/modifié quoi)
- **Déclencheur :** Croissance de la structure, besoin de délégation, arrivées/départs de staff administratif

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif métier | Indicateur mesurable | Cible | Comment mesurer |
|---|---|---|---|
| Créer un admin/directeur depuis l'UI | clics depuis le menu | ≤ 3 | recette |
| Gérer la continuité | nombre d'admins actifs | ≥ 2 | audit trail |
| Tracer les changements | qui/quand/quoi | 100% loggé | logs/audit |
| Couper l'accès rapidement | tokens révoqués | immédiatement | test de connexion |

**Hors périmètre (explicite) :**
- Envoi d'emails (invitation, reset par lien)
- Délégation granulaire de permissions (tous les admins ont les mêmes droits)
- Historique long terme (garde-fou sur 6 mois comme les professeurs)
- Autoactivation/désactivation à date

## 3. 🔴 Acteurs, rôles et permissions

**Persona(s) concernés :** 
- Admin système : gère tous les comptes, tous les rôles
- Directeur : gère uniquement les professeurs (PROF-01) ; n'a pas accès à la gestion des admins/directeurs

**Matrice rôle × action**

| Ressource / Action | Admin | Directeur | Professeur |
|---|---|---|---|
| Créer admin/directeur | C | — | — |
| Lister admin/directeur | L | — | — |
| Modifier admin/directeur | M | — | — |
| Désactiver / réactiver | V | — | — |
| Reset mot de passe / changer email | M | — | — |
| Supprimer | S | — | — |
| Voir la fiche de détail | L M | — | — |

> 👉 Seul l'**admin** peut créer/gérer d'autres admins et directeurs. Isolement strict : directeur ne voit que la gestion professeurs.

## 4. 🔴 Glossaire métier

| Terme métier (affiché) | Définition | Nom technique |
|---|---|---|
| Admin | Accès total à la gestion (rôle `admin`) | `users.role = admin` |
| Directeur | Accès à la gestion des professeurs et classes (rôle `directeur`) | `users.role = directeur` |
| Compte actif | Peut se connecter et accéder à l'UI | `users.statut = actif` |
| Compte désactivé | Accès coupé, historique conservé | `users.statut = inactif` + `date_sortie` |
| Mot de passe provisoire | Généré, affiché une seule fois au créateur | `users.must_change_password = true` |

## 5. 🔴 Parcours utilisateurs et user stories

### 5.1 User stories

| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | Admin | Créer un nouveau directeur avec un email et un mot de passe | Accélérer le démarrage de l'année | **Must** |
| US-2 | Admin | Voir la liste de tous les admins et directeurs | Connaître qui a accès au système | **Must** |
| US-3 | Admin | Modifier le nom/email d'un directeur | Corriger ou mettre à jour ses infos | **Must** |
| US-4 | Admin | Désactiver un directeur sorti | Couper son accès sans perdre l'historique | **Must** |
| US-5 | Admin | Réactiver un directeur | Lui redonner accès | **Must** |
| US-6 | Admin | Changer le mot de passe d'un admin/directeur | Réinitialiser un accès oublié | **Must** |
| US-7 | Admin | Créer un nouvel admin | Déléguer la gestion à un pair | **Should** |

### 5.2 Parcours (nominal et alternatif)

**Parcours 1 : Créer un directeur**
1. Admin ouvre le menu → clique « Gestion de l'équipe » ou « Staff »
2. Voit la liste des admins/directeurs
3. Clique « Créer un directeur »
4. Remplit formulaire : nom complet, email de connexion
5. Soumet → directeur créé, mot de passe provisoire affiché une fois
6. Admin note le mot de passe et le communique au directeur
7. Retour à la liste

**Alternative : Email déjà pris**
- Formulaire affiche erreur sur le champ email
- AC-2 applies

**Parcours 2 : Désactiver un directeur**
1. Admin clique sur un directeur dans la liste
2. Voit un bloc « Compte » avec bouton « Désactiver »
3. Clique → modale d'impact affiche :
   - Classes actuelles assignées
   - Sessions à venir
   - Heures en attente de validation
4. Admin confirme → directeur inactif, tokens révoqués
5. Retour à la liste, badge = « Désactivé »

**Alternative : Directeur dernier de son rôle**
- Avertissement non bloquant (pas de contrainte)

### 5.3 Critères d'acceptation (Given / When / Then)

| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | un admin connecté sur la page de gestion du staff | il crée un directeur (nom + email) | User + enregistrement créés, mot de passe provisoire affiché une seule fois, must_change_password = true | Feature test back |
| AC-2 | un email de connexion déjà pris | création ou modification | 422 + erreur spécifique sur le champ email | Feature test back |
| AC-3 | un directeur actif | admin clique « Désactiver » et confirme | statut = `inactif`, `date_sortie` renseignée, tokens révoqués | Feature test back |
| AC-4 | un directeur désactivé | tente de se connecter avec bon mot de passe | refus, message « Ce compte est désactivé. Contactez l'administrateur. » | Feature test back |
| AC-5 | un directeur désactivé avec token | appelle l'API | 403 Forbidden | Feature test back |
| AC-6 | un directeur désactivé | admin clique « Réactiver » | statut = `actif`, nouveau mot de passe provisoire généré, `date_sortie` = null | Feature test back |
| AC-7 | un admin connecté | tente de désactiver son propre compte | 403 Forbidden | Feature test back |
| AC-8 | un directeur assigné à des classes/sessions | tentative de suppression | 409 Conflict (doit désactiver) | Feature test back |
| AC-9 | un directeur jamais assigné | tentative de suppression | 204 No Content | Feature test back |
| AC-10 | un directeur | admin change son mot de passe ou email | tokens révoqués, must_change_password = true si reset mot de passe | Feature test back |
| AC-11 | la page de gestion du staff | chargement | liste des admins ET directeurs, filtrés par statut (défaut : actifs) | Feature test front |
| AC-12 | un professeur | tente d'accéder à la page de gestion du staff | 403 Forbidden, pas de menu | Feature test back + front |
| AC-13 | un directeur | tente d'accéder à la page de gestion du staff | 403 Forbidden, pas de menu | Feature test back + front |

## 6. 🔴 Règles métier et cycle de vie

**Règles métier**
- **RG-1 :** Seul un **admin** peut créer/gérer des admins et directeurs. Aucune délégation au directeur.
- **RG-2 :** Un admin ne peut pas désactiver son propre compte (403).
- **RG-3 :** La désactivation ne supprime jamais de donnée (classes assignées, sessions, heures, tarifs).
- **RG-4 :** Un admin/directeur désactivé n'est plus assignable à une classe.
- **RG-5 :** La modale d'impact affiche classes actives et sessions à venir sans remplaçant.
- **RG-6 :** Le mot de passe provisoire n'est jamais stocké en clair ni renvoyé après la réponse initiale.
- **RG-7 :** Chaque créateur/modification/désactivation est loggée (qui, quand, rôle).

**Cycle de vie du compte**

| Statut | Libellé | Badge | Modifiable par | Transitions | Déclencheur |
|---|---|---|---|---|---|
| `actif` | Actif | vert | Admin | → `inactif` | « Désactiver » |
| `inactif` | Désactivé | gris | Admin | → `actif` | « Réactiver » |

## 7. 🔴 Données et migration

**Nouvelles tables / colonnes :**
- `users` déjà existe avec `role`, `statut`, `email`, `password`
- Ajouter colonne `date_sortie` si elle n'existe pas
- Ajouter colonne `must_change_password` si elle n'existe pas (déjà fait pour PROF-01)

**Données existantes impactées :**
- Comptes existants de type `admin` et `directeur` gardent leur statut
- Aucun backfill nécessaire (statut = actif par défaut pour les nouveaux)

**Seeders / factories :**
- Factory `UserFactory` pour créer des comptes de test (admin, directeur, professeur)

## 8. Exigences UX/UI *(À valider avec UX Expert)*

> 🚦 **En attente** : le workflow UX doit être défini par un **sub-agent UX Expert** et matérialisé par des **mock-ups validés** avant développement.

**Écrans concernés (prévisionnel) :**
- Page « Gestion du staff / Équipe administrateur » (liste)
- Modale « Créer un admin/directeur »
- Fiche détail admin/directeur
- Modale « Désactiver » avec impact

**Textes exacts** *(à affiner avec UX)* :
- Titre menu : « Gestion de l'équipe » ou « Staff »
- Bouton créer : « Créer un directeur » / « Créer un admin »
- Bouton désactiver : « Désactiver ce compte »
- Message de confirmation : « Êtes-vous sûr ? Cette action coupera l'accès immédiatement. »
- Message d'erreur email : « Cet email est déjà utilisé. Veuillez en choisir un autre. »

## 9. Exigences non fonctionnelles

| Domaine | Exigence |
|---|---|
| **Sécurité** | Seul admin peut accéder ; isolation stricte ; pas de fuite d'existence de compte |
| **Performance** | < 200ms pour lister staff (< 100 comptes) |
| **Traçabilité** | Chaque action loggée (créateur, timestamp, rôle modifié) |
| **Audit** | Historique visible : qui a créé/modifié/désactivé et quand |
| **Compatibilité** | Desktop (prioritaire) ; FR ; Europe/Brussels |

## 10. Impact technique *(Prévisionnel)*

| Couche | Changements prévus | Résp. | Taille |
|---|---|---|---|
| **DB** | Confirmer `date_sortie` et `must_change_password` existent ; index sur `role` et `statut` | — | S |
| **Back** | Modèle User + Policy ; endpoints POST/PUT/DELETE users (staff) ; controller StaffController ; validation email unique ; middleware de vérification statut | — | M |
| **API** | GET /staff (list), POST /staff (create), GET /staff/{id}, PUT /staff/{id}, DELETE /staff/{id}, POST /staff/{id}/desactiver, POST /staff/{id}/reactiver, POST /staff/{id}/reinitialiser-mot-de-passe | — | M |
| **Front** | Page StaffAdminPage, modale création, fiche détail, filtres statut, route /admin/staff, menu | — | M |
| **Tests** | Feature tests pour AC-1 à AC-13 | — | M |
| **Logs** | Activity log (créateur, timestamp, action) | — | S |

**Risques et dépendances :**
- Dépend de l'existence de `date_sortie` et `must_change_password` (déjà fait en PROF-01)
- Risque : création accidentelle d'admin sans supervision

**Découpage en tranches verticales :**
- **T1 (MVP)** : Créer/lister/modifier directeurs ; page de gestion staff ; test backend
- **T2** : Désactiver/réactiver + impact modal ; gestion admins
- **T3** : Audit trail ; changement mot de passe

## 11. Plan de recette *(Données réalistes)*

| # | Rôle joué | Scénario | Résultat attendu | AC liés |
|---|---|---|---|---|
| R1 | Admin | Créer un directeur via le formulaire | Directeur actif, connecté à la première tentative | AC-1 |
| R2 | Directeur | Tenter d'accéder au menu de gestion du staff | Pas d'accès, pas de menu | AC-13 |
| R3 | Admin | Créer avec email déjà utilisé | Erreur affichée | AC-2 |
| R4 | Admin | Désactiver un directeur puis essayer de le connecter | Accès refusé | AC-3, AC-4 |
| R5 | Admin | Réactiver un directeur | Peut se connecter avec nouveau mot de passe | AC-6 |
| R6 | Admin | Tenter de désactiver son propre compte | Bouton grisé ou 403 | AC-7 |

## 12. Questions ouvertes

| # | Question | Propriétaire | Avant | Réponse / date |
|---|---|---|---|---|
| Q1 | Faut-il pouvoir créer un nouvel admin depuis l'UI ou seulement via CLI ? | *UX Expert* | DoR | *TBD* |
| Q2 | Motif de désactivation requis (texte libre) ? | *UX Expert* | DoR | *TBD* |
| Q3 | Qui peut changer l'email/mot de passe : l'admin ou l'utilisateur lui-même ? | *Analyste* | DoR | *Admin seul pour l'instant* |
| Q4 | Faut-il envoyer un email de notification lors de création/modification ? | *Sponsor* | DoR | *Non v1 (hors périmètre)* |
| Q5 | Durée de conservation du log d'audit ? | *Analyste* | DoR | *6 mois (comme PROF-01)* |

---

## 13. Validation (Definition of Ready)

| Rôle | Nom | Date | ☑ Validé | Réserves |
|---|---|---|---|---|
| Analyste | Gregory Pierquin | 2026-10-01 | ☑ | — |
| UX/UI | Claude Haiku 4.5 | 2026-10-01 | ☑ | Workflow + maquettes validées |
| Architecte | — | — | — | À planifier |
| Dev back | — | — | — | À planifier |
| Dev front | — | — | — | À planifier |

**DoR :** 
- ☑ Workflow UX (sub-agent) + mock-ups validés → `docs/mockups/ADMIN-01/`
- ☑ Réponses aux Q1–Q5 → voir §10 WORKFLOW_UX.md
- ☑ Matrice permissions finalisée → §3 du Canvas
- ☑ 4 états d'écran (chargement, vide, erreur, succès) → voir 01-liste-staff-filtre.html
