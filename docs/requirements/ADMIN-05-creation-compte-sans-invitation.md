# ADMIN-05 — Création d'un compte professeur / staff sans envoi d'invitation

| | |
|---|---|
| **Statut** | ☑ DoR validée (D1–D8, 2026-10-02) — ☑ En dev / à recetter |
| **Analyste** | Gregory Pierquin | **UX/UI** | UX Expert (sub-agent) |
| **Liens** | Workflow UX et maquettes : `docs/mockups/ADMIN-05/` · Précédents : `ADMIN-02`, `ADMIN-03`, `PROF-01`, `TS-01-T4` |

## 1. Contexte et problème
- **Situation actuelle :** créer un professeur (`ProfesseurCompteService::creer`) ou un compte staff (`StaffController::store`) envoie immédiatement l'invitation par email. De plus, les notifications de timesheet (`NotificationTimesheet`, canaux `database` + `mail`) partent par email à tout compte, même sans mot de passe défini.
- **Douleur :** la direction ne peut pas préparer la configuration (classes, tarifs, paramètres) avant que la personne reçoive un accès ; la personne reçoit un email pour une application encore vide ou incomplète.
- **Déclencheur :** préparation de la rentrée avant l'ouverture des accès.

## 2. Objectifs et indicateurs
| Objectif | Indicateur | Cible |
|---|---|---|
| Préparer un compte avant l'accès | création sans aucun email | 0 email à la création si l'envoi est décoché |
| Aucun email parasite avant activation | emails de notification vers un compte sans mot de passe défini | 0 |
| Activer sans effort | clics pour envoyer toutes les invitations en attente | ≤ 3 |

**Hors périmètre :** relance automatique, planification d'un envoi à date, import CSV, comptes admin créés hors UI.

## 3. Rôles et permissions
Inchangés (ADMIN-01/03) : admin et directeur créent un professeur ; seul l'admin crée un compte staff.

## 4. Glossaire
| Terme | Définition | Technique |
|---|---|---|
| Accès non envoyé | Compte créé, invitation volontairement non envoyée | `acces.statut = invitation_non_envoyee` (`invitation_envoyee_le` null) |
| Mot de passe défini | L'utilisateur a choisi lui-même son mot de passe via un lien | `users.mot_de_passe_defini_le` non null |

## 5. User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | admin / directeur | créer un professeur sans envoyer l'invitation | le configurer avant son accès | Must |
| US-2 | admin | créer un compte staff sans envoyer l'invitation | idem | Must |
| US-3 | admin / directeur | n'envoyer l'invitation que quand je le décide (fiche, relance rapide) | maîtriser la date d'ouverture | Must |
| US-4 | personne sans mot de passe défini | ne recevoir aucun email de notification | ne pas être sollicité avant l'activation | Must |
| US-5 | admin / directeur | envoyer en lot les invitations non envoyées | ouvrir les accès d'un coup | Should |

## 6. Règles métier
- **RG-1** Création : `envoyer_invitation` (booléen, défaut `true`). Si `false`, aucun email, `invitation_envoyee_le` reste null.
- **RG-2** Aucun email de notification (hors emails d'accès : invitation, réinitialisation, confirmation de changement de mot de passe) n'est envoyé à un compte dont `mot_de_passe_defini_le` est null. La notification in-app (`database`) est conservée et sera visible à la première connexion.
- **RG-3** La règle est portée par le backend, à un seul endroit (canal `mail` conditionné sur l'utilisateur), pour couvrir les notifications actuelles et futures.
- **RG-4** Un compte désactivé reste sans accès ni statut (RG-C d'ADMIN-03).
- **RG-5** Envoi différé : `envoyer-lien` existant, mêmes limites (1/min/compte, 72 h).
- **RG-6** Réactivation : option d'envoi identique à la création (défaut : envoi, comportement actuel).

## 7. Données et migration
Migration `2026_10_06_100000_add_invitation_echec_le_to_users` : `users.invitation_echec_le` (timestamp nullable) distingue l'échec d'envoi (`acces.motif = echec`) de l'accès volontairement non envoyé (`volontaire`). Les comptes existants à mot de passe provisoire hérité (`must_change_password`, `mot_de_passe_defini_le` null) n'ont pas de mot de passe défini par l'utilisateur : RG-2 s'applique (voir §12).

## 8. UX/UI
À remplir avec `docs/mockups/ADMIN-05/WORKFLOW_UX.md`.

## 9. Non fonctionnel
Pas de N+1 dans la liste ; pas de fuite d'information (l'email n'indique pas qu'un autre compte existe) ; journalisation de l'envoi manuel inchangée.

## 10. Impact technique
- Backend : `ProfesseurController::store`, `StaffController::store`, `ProfesseurCompteService::creer/reactiver`, `NotificationTimesheet::via` (+ méthode `User::peutRecevoirEmails()`), éventuel endpoint d'envoi en lot.
- Frontend : `CreerProfesseurModal`, `StaffCreateModal`, listes (pastille, filtre, lot), `utils/acces.js`, `utils/statuts.js`.
- Tests : création sans envoi (aucun `Mail::assertSent`), notification sans mail avant mot de passe défini et avec mail après, envoi différé, lot, 403 selon rôle.

## 11. Plan de recette
1. Créer un professeur, case décochée : aucun email, pastille « Accès non envoyé », classes assignables.
2. Valider ses heures : cloche in-app oui, email non.
3. Envoyer l'invitation depuis la fiche, définir le mot de passe : les notifications suivantes arrivent aussi par email.
4. Idem pour un compte staff.
5. Envoi en lot de plusieurs comptes non envoyés.
6. Case cochée (défaut) : comportement actuel inchangé.

## 12. Questions ouvertes
Tranchées le 2026-10-02 :
- Comptes existants à mot de passe provisoire hérité : **bloqués** par RG-2 (traités comme « sans mot de passe défini »).
- Envoi en lot : **inclus** dans cette tranche (US-5 passe en Must).

## 13. Validation (DoR)
☑ Workflow UX ☑ Maquettes ☑ Décisions D1–D8 (2026-10-02)

## 14. Note de version
Au déploiement, les emails de notification s'arrêtent pour tous les comptes sans `mot_de_passe_defini_le` (invitation en attente, expirée, en échec, mot de passe provisoire). La cloche in-app reste active. Aucun rejeu des emails manqués.
