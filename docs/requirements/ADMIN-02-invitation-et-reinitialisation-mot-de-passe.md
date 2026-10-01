# ADMIN-02 — Invitation par email et réinitialisation du mot de passe

| | |
|---|---|
| **Statut** | ☑ **DoR validée** — Workflow UX + maquettes validés le 2026-10-01 (décisions D1 à D6 acceptées) |
| **Analyste** | Gregory Pierquin | **UX/UI** | UX Expert (sub-agent) |
| **Date / Version** | 2026-10-01 | **Sprint cible** | À planifier |
| **Liens** | Workflow UX : `docs/mockups/ADMIN-02/WORKFLOW_UX.md` · Maquettes : `docs/mockups/ADMIN-02/` · Précédent : `ADMIN-01-gestion-comptes-admin-directeur.md` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** à la création, à la réactivation ou à la réinitialisation d'un compte staff, le serveur génère un mot de passe provisoire affiché une fois à l'admin, qui doit le transmettre à la main. La réinitialisation écrase l'ancien mot de passe et déconnecte l'utilisateur immédiatement. Il n'existe pas de « Mot de passe oublié ».
- **Douleur :** secret en clair qui circule hors du système ; l'admin connaît le mot de passe d'autrui ; utilisateur verrouillé avant d'avoir reçu son nouveau mot de passe ; chaque oubli mobilise un admin ; aucun suivi de l'activation des comptes.
- **Déclencheur :** demande du client — « envoyer par email une invitation à créer son mot de passe, ainsi que la réinitialisation ». Lève l'exclusion « envoi d'emails » d'ADMIN-01.

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif métier | Indicateur | Cible | Mesure |
|---|---|---|---|
| Plus aucun mot de passe en clair pour le staff | mots de passe affichés/envoyés | 0 | recette + tests |
| Onboarding autonome | créer un compte → mot de passe défini sans action manuelle de l'admin | oui | recette |
| Self-service de l'oubli | « Mot de passe oublié » disponible pour tous les rôles | oui | recette |
| Suivi des activations | statut d'accès visible dans la liste et la fiche | 100 % des comptes actifs | recette |
| Aucun blocage si l'email échoue | création possible avec lien de repli | oui | tests |

**Hors périmètre :** alignement de l'invitation des professeurs (PROF-01, tranche suivante via un service partagé) ; changement d'email avec re-vérification ; 2FA, politique de complexité renforcée ; invitation en masse ; relance automatique ; suivi de livraison (bounces) ; personnalisation graphique des emails ; table d'audit persistante (traçabilité par journal applicatif en v1, table dédiée prévue avec ADMIN-01 T3).

## 3. 🔴 Acteurs, rôles et permissions

| Action | Admin | Directeur | Professeur | Anonyme |
|---|---|---|---|---|
| Créer un compte staff (envoie l'invitation) | ✔ | — | — | — |
| Renvoyer l'invitation / envoyer un lien de réinitialisation à un compte staff actif | ✔ | — | — | — |
| Générer un lien à transmettre (repli) | ✔ | — | — | — |
| Réactiver un compte (envoie l'invitation) | ✔ | — | — | — |
| Demander « Mot de passe oublié » | — | — | — | ✔ (tous rôles) |
| Définir son mot de passe via un lien valide | — | — | — | ✔ |

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Technique |
|---|---|---|
| Invitation | Email avec lien à usage unique permettant de définir son premier mot de passe | `type=invitation` |
| Lien de réinitialisation | Lien à usage unique pour choisir un nouveau mot de passe | `type=reinitialisation` |
| Accès | Statut d'activation du compte (hors Actif/Désactivé) | `acces` |

## 5. 🔴 Parcours utilisateurs et user stories

- **US1** — En tant qu'admin, je crée un compte staff et le système envoie l'invitation ; je ne vois ni ne communique aucun mot de passe.
- **US2** — En tant qu'admin, si l'email échoue, je peux réessayer ou copier un lien (affiché une fois) à transmettre.
- **US3** — En tant qu'admin, depuis la fiche, je renvoie l'invitation (mot de passe jamais défini) ou j'envoie un lien de réinitialisation (déjà défini), avec confirmation ; l'ancien mot de passe reste valable jusqu'à usage du lien.
- **US4** — En tant qu'admin, je vois dans la liste l'état d'accès de chaque compte, je filtre « À relancer » et je relance en un clic.
- **US5** — En tant qu'utilisateur (tous rôles), depuis la connexion, je demande un lien « Mot de passe oublié » ; la réponse est identique que l'email existe ou non.
- **US6** — En tant qu'utilisateur, via le lien, je définis mon mot de passe (8 caractères min.) ; je suis redirigé vers la connexion (pas de connexion automatique) et reçois un email de notification.
- **US7** — En tant qu'utilisateur, un lien expiré/utilisé/invalide affiche un message unique et me permet de demander un nouveau lien.
- **US8** — En tant qu'admin, la réactivation invalide l'ancien mot de passe et envoie une invitation.

## 6. 🔴 Règles métier et cycle de vie

- RG-1 Un token = un usage ; un nouvel envoi annule les liens précédents ; le token est supprimé après usage.
- RG-2 Durées : invitation et lien envoyé/généré par un admin = **72 h** ; self-service = **60 min**.
- RG-3 Seul le hash du token est stocké ; le token n'est jamais journalisé ; le lien porte token et email en **fragment d'URL** (`#`) pour échapper aux journaux serveur et au `Referer`.
- RG-4 Lien invalide (expiré, utilisé, remplacé, falsifié, compte désactivé) : un seul message, sans révéler la cause ni l'existence du compte.
- RG-5 « Mot de passe oublié » : réponse identique dans tous les cas ; aucun email pour un compte inexistant ou désactivé ; envoi après la réponse HTTP ; 5 demandes/min/IP ; 1 email/min/compte.
- RG-6 Envoi par un admin : 1/min/compte (429 sinon) ; jamais pour un compte désactivé.
- RG-7 Définition du mot de passe : minimum 8 caractères, confirmation ; révoque **tous** les tokens Sanctum du compte ; `must_change_password=false` ; email de notification ; pas de connexion automatique.
- RG-8 Création et réactivation : mot de passe aléatoire inutilisable ; `must_change_password=false` ; la désactivation annule les liens en cours.
- RG-9 Échec d'envoi : le compte est créé/réactivé quand même ; statut « Invitation non envoyée » tant qu'aucun envoi n'a réussi ; lien copiable affiché une fois et journalisé (qui, quand).
- RG-10 Statut d'accès calculé par le backend : `mot_de_passe_defini`, `invitation_en_attente`, `invitation_expiree`, `invitation_non_envoyee`, `mot_de_passe_provisoire` (comptes existants tant que le provisoire n'est pas changé) ; `null` pour un compte désactivé.
- RG-11 L'endpoint staff `reinitialiser-mot-de-passe` (écrasement + affichage) est supprimé et remplacé par l'envoi de lien.

## 7. 🔴 Données et migration

- `users` : + `invitation_envoyee_le` (timestamp nullable), + `mot_de_passe_defini_le` (timestamp nullable). Rattrapage : `mot_de_passe_defini_le = created_at` pour les comptes dont `must_change_password = false`.
- Nouvelle table `acces_tokens` : `id`, `user_id` (FK, cascade), `type` (`invitation`/`reinitialisation`), `token_hash` (sha256, unique), `expire_le`, `created_at`. Un seul token actif par utilisateur (suppression à l'émission d'un nouveau).
- Réversible via `down()` ; noms d'index ≤ 64 caractères (compatibilité préfixe de tables).
- Config : `config('app.frontend_url')` (`FRONTEND_URL`) ; `MAIL_FROM_*` à renseigner en production.

## 8. Exigences UX/UI

Voir `docs/mockups/ADMIN-02/WORKFLOW_UX.md` (parcours, états, accessibilité) et maquettes `01` à `07`. Pages publiques : `/mot-de-passe-oublie`, `/definir-mot-de-passe` ; lien « Mot de passe oublié ? » sur la connexion ; colonne **Accès**, filtre « À relancer » et actions d'envoi dans `/admin/staff`.

## 9. Exigences non fonctionnelles

- **Sécurité :** pas d'énumération de comptes ; throttling ; token haute entropie (64 caractères aléatoires) haché ; aucun secret dans les journaux ; messages d'erreur génériques.
- **Accessibilité :** libellés, `role=alert`/`status`, statut jamais par la couleur seule (cf. §7 du workflow).
- **Messages :** français, sans jargon.
- **Envoi :** synchrone pour l'admin (détection d'échec), différé pour le self-service.

## 10. Impact technique

- **Backend :** migrations, modèle `AccesToken`, `AccesCompteService`, `AccesController` (public) + extensions `StaffController`, 3 Mailables (invitation, réinitialisation, notification), vues email texte + HTML, throttle nommé, `FRONTEND_URL`.
- **Frontend :** 2 pages publiques, lien sur `LoginPage`, hooks `useStaff`, `StaffCreateModal`, `StaffDetailModal`, `StaffAdminPage` (colonne Accès, filtre, relance rapide, réactivation).
- **Compatibilité :** `/me/mot-de-passe` et PROF-01 inchangés ; `must_change_password` conservé pour l'existant.

## 11. Plan de recette

1. Admin crée un directeur → email (journal en dev) → lien → mot de passe défini → connexion OK, aucun mot de passe affiché à l'admin.
2. Échec d'envoi simulé → compte créé, lien copiable affiché une fois, statut « Invitation non envoyée » ; « Réessayer » met le statut à jour.
3. Fiche : « Renvoyer l'invitation » (jamais défini) / « Envoyer un lien de réinitialisation » (déjà défini) avec confirmation ; l'ancien mot de passe reste valable jusqu'à usage du lien ; 2e envoi < 1 min → 429.
4. Liste : colonne Accès, filtre « À relancer », bouton Renvoyer.
5. « Mot de passe oublié » : même réponse pour email existant / inconnu / désactivé ; aucun email pour les deux derniers ; 6e demande en 1 min → 429.
6. Lien expiré, déjà utilisé, remplacé, falsifié → même message ; « Recevoir un nouveau lien » préremplit l'email.
7. Définition : < 8 caractères / confirmation différente → erreurs de champ ; succès → sessions révoquées, email de notification, redirection connexion + bandeau.
8. Désactivation annule les liens ; réactivation invalide l'ancien mot de passe et envoie une invitation.
9. Professeur : « Mot de passe oublié » fonctionne.

## 12. Questions ouvertes

- Configuration SMTP de production (OVH) et `MAIL_FROM_ADDRESS` : à fournir avant mise en ligne (en dev, `MAIL_MAILER=log`).
- Table d'audit persistante : reportée à ADMIN-01 T3 ; v1 = journal applicatif.
- Alignement PROF-01 : tranche suivante.

## 13. Validation (Definition of Ready)

☑ Workflow UX validé · ☑ Maquettes validées · ☑ Décisions D1–D6 validées (2026-10-01) · ☑ Périmètre : staff + « Mot de passe oublié » tous rôles
