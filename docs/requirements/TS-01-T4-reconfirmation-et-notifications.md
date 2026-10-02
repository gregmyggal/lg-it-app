# TS-01 T4 — Reconfirmation par le professeur, contestation, notifications

**Statut :** ☑ Livré · **Maquette :** `docs/mockups/TS-01/01-validation-mensuelle.html` (écran C)

## Décisions appliquées
1. Reconfirmation après toute adaptation, uniquement sur les lignes modifiées (la signature d'une ligne adaptée ou lissée est retirée ; les autres restent signées).
2. Une contestation bloque la signature et le PDF du mois ; le mois repasse en revue.
5. Statuts de ligne + statut du mois calculé. **Écart assumé :** « à reconfirmer » = saisie `confirme` sans signature (déjà calculé : « Attente professeur ») ; seul `conteste` est un nouveau statut stocké.

## Règles métier
- Le professeur signe son mois (`sign-month`) quand toutes ses saisies sont confirmées (ou générées), qu'au moins une attend sa signature, sans contestation ni dépassement. Correction : une signature partielle n'empêche plus de signer le reste.
- Il conteste (motif obligatoire) ses saisies `confirme` non signées → `conteste` ; une trace `contestation` est écrite par saisie. Impossible sans saisie confirmée non signée.
- La direction traite la contestation (réponse obligatoire) : `conteste` → `soumis`, signature retirée, trace `reponse_contestation`. Elle peut adapter ou lisser une saisie contestée.
- Statut du mois : `conteste` prime ; KPI « Contestés » dans la synthèse.

## Notifications (cloche + email, échec d'envoi sans effet sur l'action métier)
| Événement | Destinataire |
|---|---|
| Mois validé ou ajusté, en attente de signature (validation unitaire/en lot, adaptation, lissage) | Professeur |
| Contestation | Directeurs et admins actifs (lien vers le détail du mois) |
| Contestation traitée | Professeur |
Une notification identique non lue (même type, professeur, mois) n'est pas répétée.

## API
- Professeur : `GET /api/timesheets/ma-confirmation`, `POST /api/timesheets/contester-mois`, `POST /api/timesheets/sign-month` (message d'erreur précis).
- Staff : `POST /api/professeurs/{id}/timesheets-mois/traiter-contestation`.
- Tous : `GET /api/notifications`, `POST /api/notifications/{id}/lue`, `POST /api/notifications/lues` (chacun ne voit que les siennes).

## UI
- Page « Encoder mon mois » : section de reconfirmation (ajustements avant → après + motifs, « Accepter et signer », « Contester… »). L'ancien bloc de signature est remplacé.
- Menu latéral : cloche 🔔 avec compteur, rafraîchie chaque minute.
- Détail professeur : bandeau de contestation + « Traiter la contestation… » ; le lien d'une notification ouvre directement le détail.

## Tests
`backend/tests/Feature/TimesheetConfirmationTest.php` (9 cas).
