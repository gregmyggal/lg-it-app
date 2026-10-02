# TS-01 T3 — Détail professeur × mois et lissage

**Statut :** ☑ Livré · **Maquette :** `docs/mockups/TS-01/01-validation-mensuelle.html` (écran B)

## Définition validée du « lissage »
Répartir des heures d'un jour sur d'autres jours du **même mois**, sans changer le total d'heures (donc le total en euros), pour respecter le plafond journalier paramétré (TS-00). Invisible sur le PDF ; sert à assurer le règlement des heures prestées.

## Règles métier
- Deux modes : **auto** (proposition pour tous les jours au-delà du plafond, jours les plus proches d'abord) et **manuel** (saisie d'origine + date cible + montant).
- Heures déplacées = montant ÷ tarif (arrondi au centième) ; tarif identique aux deux dates ; l'origine garde des heures (> 0).
- Un jour cible ne peut pas dépasser le plafond après lissage ; date cible dans le mois.
- Seules les saisies `soumis` / `confirme` sont lissables. Une nouvelle saisie (libre, même statut) reçoit les heures déplacées ; l'origine perd sa signature (reconfirmation, T4).
- Application atomique, motif obligatoire, une ligne d'audit `lissage` par déplacement (heures, montant, date cible, saisie créée).
- Le plafond lu est celui de l'année (TS-00) ; le frontend n'a plus de 44,02 € en dur. Bug corrigé dans l'ancienne proposition de lissage (le jour était passé comme identifiant de professeur).

## API (staff uniquement)
- `GET /api/professeurs/{id}/timesheets-mois?annee&mois` : résumé, saisies, jauge par jour, historique.
- `POST …/timesheets-mois/lissage/apercu` : `mode=auto|manuel` (+ `deplacements`).
- `POST …/timesheets-mois/lissage` : `deplacements`, `motif`.

## UI
« Ouvrir » depuis la synthèse → détail : saisies (badge « modifiée »), calendrier avec jauge rouge, historique, « Adapter », « Lisser », « Lisser le mois… », « Valider le mois ».

## Tests
`backend/tests/Feature/TimesheetLissageMoisTest.php` (7 cas).
