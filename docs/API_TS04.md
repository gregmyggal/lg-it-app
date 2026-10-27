# API — TS-04 Versions soumise / validée (tranches T1a et T1b)

Contexte : `docs/adr/0002-versions-timesheet.md`, `docs/requirements/TS-04-versions-soumise-validee.md`.
T1a pose les instantanés et durcit la validation ; T1b met la validée à jour à chaque modification et ajoute la lecture des versions.

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

## T1b — validée v+1 sur mutation
| Mutation d'un mois | Effet sur les versions |
|---|---|
| `POST /timesheets/{id}/adapter`, `apply-lissage`, `POST /professeurs/{id}/timesheets-mois/lissage` | Si le mois a une validée `active` : validée v+1 dans la même transaction (l'ancienne passe `remplacee`). Sans validée active (mois soumis non encore validé, ou validé avant TS-04) : rien. |
| Remise en brouillon du mois | La validée `active` passe `remplacee` ; la soumise est inchangée. |
| Traitement d'une contestation | Idem : le mois repasse en revue, la validée active passe `remplacee` ; la revalidation (`valider-lot`) crée la validée suivante. |
| Déverrouillage après PDF | Inchangé : le contenu ne change pas ; une adaptation ultérieure crée la validée v+1. |

## T1b — lecture des versions
| Route | Rôle | Contenu |
|---|---|---|
| `GET /api/professeurs/{id}/timesheets-mois/versions?annee=&mois=` (+ `soumise_numero`, `validee_numero` facultatifs) | direction, admin (403 sinon) | `disponibilite`, `soumise`, `validee` (lignes, totaux, `empreinte`, `auteur`), `historique` (toutes les versions), `ecart` |
| `GET /api/timesheets/mes-versions?annee=&mois=` | professeur (ses propres mois uniquement) | Idem, **sans** `empreinte` ni `auteur` |

`ecart` (calculé côté serveur, appariement sur l'identifiant de ligne) : `identique`, `lignes[]` (`etat` = `identique|modifiee|ajoutee|retiree`, `avant`, `apres`, `ecart_heures`, `ecart_eur`, `motif`, `auteur`), `total_avant`, `total_apres`, `ecart_heures`, `ecart_eur`. Une ligne issue d'un lissage est `ajoutee` et reprend le motif de sa ligne d'origine. `ecart` est `null` si une des deux versions manque ou si son détail est effacé (`detail_efface`). Un mois sans version répond 200 avec `disponibilite: { soumise: false, validee: false }` (« Non disponible avec TS-04 »).

## Pas encore couvert (tranches suivantes)
- T1c : garde signature/PDF sur une version validée.
- T3 : rétention.
