# TS-02 — Remise en brouillon d'un mois par la direction

**Statut :** ☑ Livré · **Maquette :** `docs/mockups/TS-02/01-remise-en-brouillon.html` (la maquette montre aussi un « ligne par ligne » et un déverrouillage PDF écartés, voir décisions)

## Contexte
Une fois le mois soumis/validé, le professeur ne peut plus corriger. La direction doit pouvoir lui rendre la main sur son mois.

## Décisions (directeur, 2026-10-08)
1. **Mois entier uniquement** (pas de remise ligne par ligne).
2. **Impossible dès qu'un PDF est généré** (aucune ligne `genere`) : les corrections se font dans la timesheet du mois suivant. Pas de renvoi vers le déverrouillage admin.
3. **Directeur ou admin** peuvent le faire (staff), motif obligatoire.
4. Signatures retirées sur toutes les lignes rouvertes (hypothèse par défaut : une ligne corrigée ne reste pas signée).

## Règles
- `soumis`, `confirme`, `conteste` → `brouillon` ; `signature_professeur`, `validated_at`, `validated_by` remis à null. Les lignes déjà `brouillon` ne sont pas touchées.
- 422 si une ligne du mois est `genere` ou si le mois n'a aucune ligne rouvrable. Rien n'est modifié en cas d'erreur (transaction).
- Trace `remise_brouillon` par ligne (avant : statut + signée, après, motif, auteur). Notification cloche + email au professeur (non dédoublonnée).
- Le professeur corrige, puis utilise le flux existant : soumettre → validation → reconfirmation/signature.

## API
- Staff : `POST /api/professeurs/{id}/timesheets-mois/remettre-en-brouillon` `{annee, mois, motif}` → `{remises_en_brouillon}`.
- `GET /api/professeurs/{id}/timesheets-mois` expose `remise_brouillon: {possible, lignes, signatures, raison}`.
- `GET /api/timesheets/ma-confirmation` expose `remise_brouillon: {motif, auteur, created_at} | null`.

## UI
- Détail professeur/mois (staff) : bouton « Remettre en brouillon… » + modale (conséquences, motif), message explicatif si PDF généré.
- « Encoder mon mois » (professeur) : bandeau avec motif, auteur et date jusqu'à la re-soumission.

## Tests
`backend/tests/Feature/TimesheetRemiseBrouillonTest.php`.
