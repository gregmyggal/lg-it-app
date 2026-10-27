# API — TS-04 Versions soumise / validée (tranche T1a)

Contexte : `docs/adr/0002-versions-timesheet.md`, `docs/requirements/TS-04-versions-soumise-validee.md`.
La lecture des versions (API et écran) arrive en T1b ; T1a pose les instantanés et durcit la validation.

## Changements de contrat

| Route | Changement |
|---|---|
| `POST /api/timesheets/soumettre-mois` | Crée en plus une version **soumise** (numéro suivant, l'ancienne passe `remplacee`). Le contrôle « mois déjà soumis » se fait sous verrou : deux soumissions simultanées, une seule réussit. |
| `POST /api/timesheets/valider-lot` | **Valide des mois entiers**, professeur par professeur, en une transaction. Toute saisie `soumis` d'un mois concerné doit figurer dans `ids`, sinon 422 avec `invalides: [{id, raison}]` ; une saisie `conteste` du mois refuse aussi la validation. Les lissages passent par le service du mois (plafond, audit, lien vers la ligne d'origine). Crée une version **validée**. Réponse : `{ validees, mois_valides, lissages_appliques }` (`mois_valides` est nouveau). |
| `POST /api/timesheets/{id}/apply-lissage` | Passe par `TimesheetLissageMoisService::appliquer` : saisie soumise, confirmée ou contestée seulement, date cible dans le mois, plafond respecté, audit « lissage ». La ligne créée garde le statut de l'origine (plus « confirme » d'emblée). Paramètre optionnel `motif`. Erreur : 422 `{ error, message, errors }`. |
| `POST /api/timesheets/{id}/validate` | **Supprimée** (404). Utiliser `valider-lot` (mois entier). |

## Table `timesheet_versions`
Append-only : `professeur_id`, `annee`, `mois`, `type` (`soumise|validee`), `numero`, `statut` (`active|remplacee`), `actif_cle` (unique tant que la version est active), `format`, `lignes` (texte canonique), `total_heures`, `total_eur`, `content_hash` (SHA-256 de `lignes`), `auteur_id`, `created_at`, `purge_at`.
Seul `statut` (et `actif_cle`) peut changer après création ; une suppression est refusée par le modèle.

## Pas encore couvert (tranches suivantes)
- T1b : version validée v+1 lors d'une adaptation, d'un lissage direct, d'une remise en brouillon ou d'un déverrouillage ; API de lecture ; écran directeur.
- T1c : garde signature/PDF sur une version validée.
- T3 : rétention.
