# ADMIN-05 — Créer un compte professeur / staff sans envoyer l'invitation

> Statut : **proposition UX à valider**. Aucun code applicatif modifié ; documentation et maquettes statiques uniquement (ouvrir `index.html`, bascule clair / nuit / auto en haut de chaque page).

| Fichier | Rôle |
|---|---|
| `WORKFLOW_UX.md` | Constats, parcours, états, règle « aucun email avant mot de passe », cas limites, **décisions à valider (§9)** |
| `index.html` | Navigation |
| `01-creation-professeur.html` | Case « Envoyer l'invitation maintenant », résultat sans envoi (lien vers la fiche), envoyé, échec, erreur |
| `02-creation-staff.html` | Même case pour le staff, résultat sans envoi |
| `03-liste-pastilles-filtre-lot.html` | Pastilles « Accès non envoyé » vs « Invitation en échec », « À relancer », sélection, envoi en lot, résultat |
| `04-fiche-compte.html` | Section Compte : accès non envoyé, mention emails, échec, mot de passe provisoire hérité, réactivation, règle de notification |

Pages publiques et emails d'accès : **inchangés** (voir `docs/mockups/ADMIN-02/` et `ADMIN-03/`).
