# ADMIN-02 — Invitation par email et réinitialisation du mot de passe

> Statut : **proposition UX à valider**. Aucun code applicatif modifié ; ce dossier ne contient que de la documentation et des maquettes statiques (ouvrir `index.html` dans un navigateur).

## Contenu

| Fichier | Rôle |
|---|---|
| `WORKFLOW_UX.md` | Constats vérifiés, parcours actuels/cibles, états, accessibilité, règles métier, hors périmètre, **décisions à valider (§11)** |
| `index.html` | Navigation entre les maquettes |
| `01-creation-modale.html` | Création staff : formulaire, envoi, email pris, invitation envoyée, échec d'envoi + lien copiable |
| `02-fiche-detail.html` | Fiche : statut d'accès, renvoi d'invitation, lien de réinitialisation, échec, compte désactivé / réactivation |
| `03-liste-statut-acces.html` | Liste : colonne « Accès », filtre « À relancer », chargement/erreur |
| `04-connexion-oubli.html` | Connexion + « Mot de passe oublié ? », confirmation neutre, 429 |
| `05-definir-mot-de-passe.html` | Page publique de définition du mot de passe : vérification, formulaire, erreurs, succès |
| `06-lien-expire.html` | Lien expiré / utilisé / invalide, rebond vers une nouvelle demande |
| `07-apercu-emails.html` | Invitation, réinitialisation, notification de changement |

## Décisions à valider (résumé, détail dans `WORKFLOW_UX.md` §11)

1. **D1** Lien à usage unique à la place du mot de passe provisoire (staff) : recommandé oui.
2. **D2** Périmètre : staff + « oublié » pour tous les rôles maintenant, PROF-01 aligné ensuite via service partagé.
3. **D3** Repli lien copiable (une fois, audité) + « Réessayer » en cas d'échec d'envoi.
4. **D4** Durées : 72 h (invitation / envoi admin), 60 min (self-service).
5. **D5** Réactivation : ancien mot de passe invalidé + lien envoyé.
6. **D6** Pas de connexion automatique après définition du mot de passe.

## Écart à connaître

Les maquettes ADMIN-01 représentaient la fiche comme une page `/admin/staff/:id` ; l'UI livrée est une **modale** (`StaffDetailModal`). ADMIN-02 suit l'UI livrée.

## Style visuel

Identique à ADMIN-01 (palette bleue `#1d4ed8`, polices système, rayons 8/12 px) ; contrastes relevés pour le texte (AA). Les maquettes sont autonomes (CSS inline, JS minimal pour changer d'état).
