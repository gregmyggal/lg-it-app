# PROF-01 — Gestion des professeurs et de leur compte (ajout, activation, désactivation)

| | |
|---|---|
| **Statut** | ☑ Brouillon — en attente de validation des maquettes et des questions ouvertes (§12) |
| **Liens** | Workflow et maquettes : `docs/mockups/PROF-01/` (`WORKFLOW_UX.md`, `index.html`) |

## 1. Contexte et problème
- **Situation actuelle :** l'API permet de créer, modifier et supprimer un professeur et son compte. La page de création (`ProfesseursAdminPage.jsx`) n'est reliée à aucun menu. La liste routée est celle des tarifs. Le champ `statut` est sans effet : un professeur « inactif » peut se connecter. Il n'y a ni reset de mot de passe ni changement d'email de connexion. La suppression est définitive et emporte le compte.
- **Douleur :** impossible de couper l'accès d'un professeur sorti sans perdre son historique ; risque de suppression accidentelle ; la direction ne peut pas créer un compte depuis l'UI.
- **Déclencheur :** démarrage de l'année, arrivées et départs de professeurs.

## 2. Objectifs
| Objectif | Indicateur | Cible |
|---|---|---|
| Créer un professeur avec compte | clics depuis le menu | ≤ 3 |
| Couper l'accès d'un professeur sorti | connexion et tokens | refusés immédiatement |
| Conserver l'historique | timesheets, tarifs, sessions passées | intacts |

**Hors périmètre :** envoi d'emails (invitation, reset par lien), désactivation automatique à la date de sortie, auto-inscription, rôles autres que professeur.

## 3. Rôles et permissions
| Action | Admin | Directeur | Professeur |
|---|---|---|---|
| Lister, créer, modifier | ✔ | ✔ | — |
| Désactiver, réactiver | ✔ | ✔ (Q4) | — |
| Reset mot de passe, changer email de connexion | ✔ | ✔ | — |
| Supprimer (uniquement sans données liées) | ✔ | ✔ | — |
| Se désactiver soi-même | — | — | — |

## 4. Glossaire
| Terme | Définition | Technique |
|---|---|---|
| Professeur actif | peut se connecter et être assigné | `professeurs.statut = actif` |
| Professeur désactivé | accès coupé, historique conservé | `statut = inactif` + `date_sortie` |
| Compte de connexion | identifiants plateforme | `users` (role=professeur) |
| Mot de passe provisoire | généré, affiché une fois | `users.must_change_password` (nouveau) |

## 5. Parcours et critères d'acceptation
| ID | Étant donné… | Quand… | Alors… |
|---|---|---|---|
| AC-1 | un directeur sur la liste | il crée un professeur (profil + email de connexion) | User + Professeur créés, mot de passe provisoire affiché une seule fois, changement forcé à la première connexion |
| AC-2 | un email de connexion déjà pris | création ou changement | 422 avec erreur sur le champ |
| AC-3 | un professeur actif | désactivation confirmée via la modale d'impact | statut `inactif`, `date_sortie` renseignée, tokens révoqués, assignations actives terminées si l'option est cochée (défaut : oui) |
| AC-4 | un professeur désactivé | il se connecte avec un mot de passe correct | refus, message « Ce compte est désactivé. Contactez la direction. » |
| AC-5 | un professeur désactivé avec un token existant | il appelle l'API | 403 |
| AC-6 | un professeur désactivé | réactivation | statut `actif`, nouveau mot de passe provisoire, `date_sortie` effacée |
| AC-7 | un utilisateur connecté | il tente de désactiver son propre compte | 403 |
| AC-8 | un professeur avec heures, assignations ou sessions | tentative de suppression | 409, il faut désactiver |
| AC-9 | un professeur créé par erreur, sans donnée liée | suppression | 204 |
| AC-10 | un professeur | reset du mot de passe ou changement d'email | tokens révoqués, mot de passe provisoire pour le reset |
| AC-11 | un professeur désactivé | consultation de la liste | masqué par défaut ; filtre actifs / inactifs / tous |
| AC-12 | un professeur | il tente de désactiver un professeur | 403 |

## 6. Règles métier
- **RG-1 :** seul le backend décide : `login` et un middleware d'authentification refusent tout professeur `inactif`.
- **RG-2 :** le message de refus n'est montré que si le mot de passe est correct (pas de fuite d'existence de compte).
- **RG-3 :** la désactivation ne supprime jamais de donnée (timesheets, tarifs, sessions passées).
- **RG-4 :** la modale d'impact affiche classes actives, séances à venir sans remplaçant, heures en attente. Dernier professeur d'une classe : avertissement non bloquant.
- **RG-5 :** un professeur désactivé n'est plus assignable à une classe.
- **RG-6 :** le mot de passe provisoire n'est jamais stocké en clair ni renvoyé après la réponse initiale.

| Statut | Libellé | Badge | Transitions | Déclencheur |
|---|---|---|---|---|
| `actif` | Actif | vert | → `inactif` | « Désactiver » |
| `inactif` | Désactivé | gris | → `actif` | « Réactiver » |

## 7. Données et migration
- `users.must_change_password` boolean, défaut false.
- Aucun backfill : les comptes existants gardent leur statut. Migration réversible (`down()`).

## 10. Impact technique (prévisionnel)
- **Back :** `POST /professeurs/{id}/desactiver`, `/reactiver`, `/reinitialiser-mot-de-passe`, `PUT /professeurs/{id}/compte` (email), `GET /professeurs/{id}/impact-desactivation` ; contrôle du statut dans `AuthController::login` et un middleware ; `destroy` en 409 s'il y a des données liées ; filtre `statut` sur l'index ; garde sur l'assignation.
- **Front (via `api/client.js`) :** fusion liste tarifs et page de gestion, route et menu, filtre statut, modale de création, bloc « Compte de connexion » sur la fiche, modale d'impact, changement de mot de passe forcé, message sur `LoginPage`.
- **Tests :** feature backend sur AC-1 à AC-12 via `scripts/test-backend.sh`, puis `npm run lint && npm run build`.

## 12. Questions ouvertes (à trancher avant dev)
1. Invitation ou reset par email plutôt que mot de passe communiqué à la main ? (reco : non pour l'instant, hors périmètre)
2. Terminer automatiquement les assignations à la désactivation ? (reco : oui, décochable)
3. Le professeur sorti garde-t-il une lecture de ses heures passées ? (reco : non)
4. Qui peut désactiver : directeur et admin, ou admin seul ? (reco : les deux)
5. Une date de sortie future désactive-t-elle automatiquement ? (reco : non, v2)
