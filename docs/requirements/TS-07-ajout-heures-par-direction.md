# TS-07 — Ajout d'heures par la direction/admin sur un mois en brouillon

**Statut :** livré (backend + UI + tests) ; maquette validée (maquette `docs/mockups/TS-07/01-ajout-heures.html`).

## Contexte
Le professeur oublie ou sous-déclare des heures ; la direction veut les ajouter à sa place avant la soumission du mois, avec une justification par ligne.

## Règles (décisions validées)
- Qui : directeur et admin. Où : détail d'un professeur pour un mois (`DetailProfesseurMois`).
- Quand : le mois du professeur est en brouillon (aucune saisie soumise/confirmée/générée). Sinon : l'adaptation existante (TS-01) s'applique.
- Quoi : heures libres — date (dans le mois), type d'activité, nombre d'heures (0,5 – 24, pas de 15 min), **commentaire obligatoire par ligne**. Plusieurs lignes possibles, chacune avec son commentaire.
- La ligne est créée en brouillon, au nom du professeur, marquée « Ajoutée par la direction » (auteur + date) ; le professeur la voit dans son mois avec le commentaire et soumet le mois entier.
- Traçabilité : colonne `timesheets.ajoute_par_user_id` + trace d'audit `ajout_direction` (commentaire = motif).
- Pas de lien à une séance (v1).
- Droits : le serveur refuse tout autre rôle ; le professeur garde ses droits habituels sur ses brouillons.

## Parcours impactés
| Écran | Changement |
|---|---|
| Détail professeur / mois (direction) | Bouton « Ajouter des heures… » si mois en brouillon ; modale multi-lignes ; badge « Ajoutée par la direction » dans la liste |
| Mon mois (professeur) | Badge « Ajoutée par la direction » + commentaire visibles sur la ligne |
| Historique du mois | Entrée « Heures ajoutées par la direction » |

## Tranche verticale
DB : migration `ajoute_par_user_id`. API : `POST /professeurs/{professeur}/timesheets-mois/ajouter-heures` (lignes[]). UI : modale + badges. Tests : droits, mois non brouillon refusé, commentaire obligatoire, date hors mois, audit, visibilité côté professeur.

## Question ouverte
Le professeur peut-il supprimer/modifier une ligne ajoutée (droit actuel sur ses brouillons) ? Par défaut oui (v1), l'audit garde la trace de l'ajout.
