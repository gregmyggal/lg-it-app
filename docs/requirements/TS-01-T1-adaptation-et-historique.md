# TS-01 T1 — Adaptation d'une saisie par le directeur/admin et historique

**Statut :** ☑ Livré · **Maquette :** `docs/mockups/TS-01/01-validation-mensuelle.html` (écran B, modale « Adapter »)

## Contexte et objectif
Le directeur ne pouvait pas corriger une saisie (seul l'admin le pouvait, sans trace). Il doit pouvoir adapter heures, type et date (saisie libre), avec motif obligatoire et historique.

## Permissions
| Action | Admin | Directeur | Professeur |
|---|---|---|---|
| Adapter une saisie `soumis` / `confirme` | oui | oui | 403 |
| Lire l'historique | oui | oui | 403 |
| `brouillon` / `genere` | non adaptables (403) |

## Règles métier
- Motif obligatoire (≥ 3 caractères) ; une adaptation sans changement est refusée (422).
- La date d'une saisie liée à une session n'est pas modifiable ; sinon elle reste dans le mois d'origine.
- Adapter une saisie `confirme` retire `signature_professeur` (reconfirmation ; statuts dédiés en T4).
- Chaque adaptation crée une ligne `timesheet_audits` (auteur, avant/après, motif), atomique avec la modification.

## API
- `POST /api/timesheets/{id}/adapter` : `nombre_heures?`, `date_prestation?`, `type_activite?`, `motif`.
- `GET /api/timesheets/{id}/historique`. `can.adapt` exposé par `TimesheetResource`.

## UI
Colonne « Actions » du détail mensuel (`/admin/timesheets`) : bouton « Adapter » → modale (aperçu avant/après, motif, historique).

## Tests
`backend/tests/Feature/TimesheetAdaptationTest.php` (8 cas : trace, motif, no-op, signature retirée, statuts, date, 403, `can.adapt`).
