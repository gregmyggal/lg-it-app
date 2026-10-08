# TS-02 — Remise en brouillon d'un mois par la direction

**Statut :** ☑ Livré · **Maquette :** `docs/mockups/TS-02/01-remise-en-brouillon.html`

## Contexte
Une fois le mois soumis/validé, le professeur ne peut plus corriger. La direction doit pouvoir lui rendre la main sur son mois.

## Décisions (directeur, 2026-10-08)
1. **Mois entier** : une seule action pour toutes les lignes éligibles du mois (pas de ligne par ligne).
2. **Seules les lignes simplement soumises** (`soumis`, sans signature) sont rouvrables. Une ligne validée, signée, contestée ou incluse dans un PDF (`genere`) ne l'est jamais : les corrections se font dans la timesheet du mois suivant. Pas de renvoi vers le déverrouillage admin.
3. **Directeur ou admin**, motif obligatoire.

## Règles
- `soumis` sans signature → `brouillon`. Les autres lignes du mois ne sont pas touchées ; 422 s'il n'y a aucune ligne éligible (rien n'est modifié).
- Trace `remise_brouillon` par ligne (statut avant/après, motif, auteur). Notification cloche + email au professeur (non dédoublonnée).
- Le professeur corrige puis utilise le flux existant : soumettre → validation → reconfirmation/signature.

## API
- Staff : `POST /api/professeurs/{id}/timesheets-mois/remettre-en-brouillon` `{annee, mois, motif}` → `{remises_en_brouillon}`.
- `GET /api/professeurs/{id}/timesheets-mois` expose `remise_brouillon: {possible, lignes, raison}`.
- `GET /api/timesheets/ma-confirmation` expose `remise_brouillon: {motif, auteur, created_at} | null`.

## UI
- Détail professeur/mois (staff) : bouton « Remettre en brouillon… » + modale (conséquences, motif), message explicatif si PDF généré.
- « Encoder mon mois » (professeur) : bandeau avec motif, auteur et date jusqu'à la re-soumission.

## Tests
`backend/tests/Feature/TimesheetRemiseBrouillonTest.php`.
