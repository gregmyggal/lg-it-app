# API — TS-05 Soumission forcée du mois (tranche T1)

Contexte : `docs/requirements/TS-05-soumission-forcee.md`, `docs/API_TS04.md`.

## Soumettre le mois d'un professeur à sa place
`POST /api/professeurs/{professeur}/timesheets-mois/forcer-soumission` — **admin et direction** (403 pour le professeur, 401 sans authentification).

Corps : `{ annee, mois, motif }` — `motif` obligatoire, 3 à 500 caractères (message au professeur).

Effet, en une transaction : toutes les heures en brouillon du mois passent en « soumis » ; une trace d'audit `soumission_forcee` par saisie (avec le message) ; une version **soumise** `origine = direction`, au nom de l'auteur. Le professeur reçoit une notification et un e-mail (code `soumission_forcee`) ; la direction n'est pas prévenue de sa propre action.

Réponse 200 : `{ soumises, lignes, heures, total_eur }`.
Erreurs : **409** mois déjà soumis (ou plus avancé, ou soumis entre-temps par le professeur) ; **422** motif absent/trop court/trop long, ou aucune heure en brouillon.

## Éligibilité et avertissements
`GET /api/professeurs/{professeur}/timesheets-mois?annee=&mois=` renvoie en plus :

```
soumission_forcee: {
  possible, raison_impossible,        // null | "deja_soumis" | "aucune_heure"
  lignes, heures, total_eur,          // heures en brouillon
  depassements: ["2026-10-09"],       // jours au-dessus du plafond journalier (information)
  seances_sans_heures: ["2026-10-17"],// séances passées sans heures, non incluses
  mois_termine                        // faux : le mois n'est pas terminé (avertissement)
}
```

## Autres changements
- `GET /api/timesheets/mon-mois` (professeur) : `soumis_par_direction: { date, auteur, message, lignes, heures } | null`.
- Versions (`/timesheets-mois/versions`, `/timesheets/mes-versions`) : `soumise.origine` = `professeur` ou `direction`.
- Synthèse du mois : alerte `brouillon_non_soumis` (« Brouillon non soumis · N h ») pour un professeur dont toutes les lignes sont en brouillon.
- Migration : `2026_10_28_100000_add_origine_to_timesheet_versions` (colonne `origine`, défaut `professeur`).

## Pas encore couvert
- T2 : soumission forcée groupée depuis la synthèse.
