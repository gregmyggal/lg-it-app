# API — TS-04 Versions soumise / validée (tranches T1a, T1b, T1c et T3)

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

## T1c — garde signature / PDF
Règle (ADR-0002, RG-11) : un mois qui a au moins une version **validée** ne se signe et ne se génère en PDF que si la validée **active** correspond exactement au contenu courant (lignes et montants figés à la validation). Sinon : 422, message « Le contenu ou les montants du mois ont changé depuis sa validation : la direction doit valider le mois à nouveau » (ou « Aucune version validée à jour pour ce mois… »).
- **Signature** (`POST /timesheets/sign-month`) : `canSignMonth` renvoie `can_sign: false` avec l'erreur ci-dessus (visible dans `erreurs_signature` de `GET /timesheets/ma-confirmation`). La signature enregistre `version_id` et `version_hash` ; son payload scellé passe en `version: 2` avec `version_timesheet_id` et `version_hash`. `contenu_sha256` reste lu par la vérification du sceau.
- **Périmée** : une signature liée à une version est périmée dès que cette version n'est plus la validée active (adaptation, lissage, revalidation). Une nouvelle signature du professeur est alors requise ; l'ancienne passe `remplacee`.
- **PDF** (`POST /professeurs/{id}/timesheet-pdfs`) : le contrôle est ajouté aux `bloquants` (aperçu, lot et génération). Le PDF enregistre `version_id`.
- **Mois antérieurs à TS-04** (aucune validée) : non concernés, signature et PDF comme avant, `version_id` nul.
- **Drapeau de déploiement** : `config('logiscool.garde_versions')`, variable `TIMESHEET_GARDE_VERSIONS` (défaut `true`). À `false`, la garde est inactive.

## T3 — rétention et historique
- **Commande** `php artisan timesheets:purger-versions [--simulation]`, planifiée tous les jours à 04:00 (`routes/console.php`). Elle efface `lignes` (et renseigne `purge_at`) des versions dont le mois s'est terminé il y a plus de 24 mois ; totaux, empreinte, dates et numéro sont conservés. Idempotente. C'est la seule écriture hors du modèle immuable.
- Une version purgée est lue avec `detail_efface: true` et `lignes: []` ; `ecart` est alors `null`.
- **Frise du mois** (écran directeur) : `FriseMois` rassemble versions soumises/validées (API `versions`), adaptations, lissages, remises en brouillon, contestations, signature et PDF, du plus ancien au plus récent.
- Conservation : PDF et signatures, 84 mois minimum, sans purge automatique ; l'anonymisation de la signature (IP, appareil, payload) reste à 7 ans (`signatures:anonymiser`).

## Pas encore couvert
- Rien de prévu pour TS-04 ; question RGPD Q5 (IP des signatures à 12–24 mois) à trancher.
