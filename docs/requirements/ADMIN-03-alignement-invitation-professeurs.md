# ADMIN-03 — Alignement de l'invitation des professeurs (PROF-01) sur ADMIN-02

| | |
|---|---|
| **Statut** | ☑ **DoR validée** — Workflow UX + maquettes validés le 2026-10-01 (D1 à D6 acceptées, D3 : email de connexion) |
| **Analyste** | Gregory Pierquin | **UX/UI** | UX Expert (sub-agent) |
| **Liens** | Workflow UX : `docs/mockups/ADMIN-03/WORKFLOW_UX.md` · Maquettes : `docs/mockups/ADMIN-03/` · Précédents : `ADMIN-02-invitation-et-reinitialisation-mot-de-passe.md`, `PROF-01-gestion-comptes-professeurs.md` |

## 1. Contexte et problème
Les professeurs reçoivent encore un mot de passe provisoire en clair (email + réaffichage à l'écran) à la création, à la réactivation et à la réinitialisation ; la réinitialisation écrase l'ancien mot de passe et verrouille le professeur avant qu'il ait reçu le nouveau. Le staff dispose depuis ADMIN-02 d'un lien à usage unique ; ADMIN-02 §8 prévoyait cette tranche pour les professeurs.

## 2. Objectifs et indicateurs
| Objectif | Indicateur | Cible |
|---|---|---|
| Plus aucun mot de passe en clair pour les professeurs | mots de passe affichés/envoyés | 0 |
| Un seul mécanisme d'accès pour tous les rôles | service d'accès partagé | oui |
| Suivi des activations | pastille Accès + filtre « À relancer » | 100 % des professeurs actifs |

**Hors périmètre :** migration/envoi automatique aux comptes provisoires existants, fusion des deux emails (contact / connexion), import CSV, relance automatique, suivi de livraison, modification des pages publiques.

## 3. Rôles et permissions
| Action | Admin | Directeur | Professeur |
|---|---|---|---|
| Créer un professeur (envoie l'invitation) | ✔ | ✔ | — |
| Renvoyer l'invitation / lien de réinitialisation / lien manuel / réactiver | ✔ | ✔ | — |
| Même actions sur un compte staff | ✔ | — | — |

## 4. Glossaire
Inchangé (ADMIN-02) : Invitation, Lien de réinitialisation, Accès. « Email de connexion » = `users.email` (reçoit le lien) ; « email de contact » = `professeurs.email` (profil, non utilisé pour l'accès).

## 5. User stories
- **US1** Directeur/admin : je crée un professeur et le système envoie l'invitation à son email de connexion ; aucun mot de passe n'est affiché ni envoyé.
- **US2** Si l'email échoue, le professeur est créé et je peux réessayer ou copier un lien (affiché une fois).
- **US3** Depuis la fiche : « Renvoyer l'invitation » ou « Envoyer un lien de réinitialisation » avec confirmation en ligne (plus de `window.confirm`) ; l'ancien mot de passe reste valable jusqu'à usage du lien.
- **US4** Dans la liste : pastille Accès, filtre « À relancer », relance rapide.
- **US5** Réactivation : l'ancien mot de passe est invalidé, une invitation est envoyée.
- **US6** Les professeurs existants à mot de passe provisoire apparaissent avec ce statut ; la direction leur envoie un lien au cas par cas.

## 6. Règles métier
Reconduites d'ADMIN-02 (RG-1 à RG-11) : un token = un usage, un seul lien actif, 72 h (envoi par la direction) / 60 min (libre-service), sessions révoquées à la définition, pas de connexion automatique, un message unique pour un lien invalide, 1 envoi/min/compte, aucun envoi pour un compte désactivé. Spécifique :
- RG-A Le lien est envoyé à l'**email de connexion** du compte.
- RG-B La désactivation et le changement d'email de connexion annulent les liens en cours.
- RG-C Un professeur désactivé est « sans statut d'accès » ; son statut est celui de `professeurs.statut`.
- RG-D L'endpoint `POST /professeurs/{id}/reinitialiser-mot-de-passe` est supprimé ; `creer` et `reactiver` ne renvoient plus de mot de passe (`{ data, mail_envoye, lien? }`).
- RG-E Emails : mêmes 3 modèles ; libellé « professeur », mention de la direction pour un envoi par un admin/directeur.

## 7. Données et migration
Aucune migration de schéma (colonnes et table `acces_tokens` d'ADMIN-02). Les professeurs ayant déjà choisi leur mot de passe sont couverts par le rattrapage d'ADMIN-02.

## 8. UX/UI
`docs/mockups/ADMIN-03/` : création, section Compte de la fiche, liste de cartes avec pastille Accès, aperçu de l'email. La liste n'étant pas paginée, le filtre « À relancer » et son compteur s'appuient sur `acces.a_relancer` calculé par le backend.

## 9. Non fonctionnel
Sécurité, accessibilité et messages identiques à ADMIN-02. Pas de N+1 sur les tokens pour la liste.

## 10. Impact technique
Backend : `ProfesseurCompteService` (délègue à `AccesCompteService`), `ProfesseurController` (+ `envoyer-lien`, `generer-lien`, résumé `acces`), emails, suppression de `InvitationProfesseurMail`. Frontend : `CreerProfesseurModal`, `CompteProfesseurSection`, `AdminProfesseursPage` ; suppression de `MotDePasseProvisoireModal`.

## 11. Plan de recette
1. Création → email au login → lien → mot de passe défini → connexion, sans mot de passe visible.
2. Échec d'envoi : professeur créé, lien copiable, « Réessayer ».
3. Fiche : renvoi / réinitialisation avec confirmation en ligne ; ancien mot de passe valable jusqu'à usage ; 2e envoi < 1 min → 429.
4. Liste : pastille, filtre « À relancer », relance rapide.
5. Désactivation annule les liens ; réactivation invalide l'ancien mot de passe et invite.
6. Changement d'email de connexion annule les liens.
7. Professeur existant à mot de passe provisoire : statut dédié, lien de réinitialisation, mot de passe provisoire valable jusque-là.
8. Directeur ne peut pas viser un compte staff.

## 12. Questions ouvertes
Aucune (D1–D6 validées). SMTP de production : voir ADMIN-02 §12.

## 13. Validation (DoR)
☑ Workflow ☑ Maquettes ☑ Décisions D1–D6 (2026-10-01)
