# TS-00 — Plafonds de défraiement paramétrables et compte bancaire du professeur

| | |
|---|---|
| **Statut** | ☑ Livré (tranche 0 de la validation mensuelle TS-01) |
| **Liens** | Maquette `docs/mockups/TS-01/01-validation-mensuelle.html` (écran E), modèle PDF « Fiche de défraiement – Volontariat » |

## 1. Contexte
Les plafonds (44,02 €/jour, 1 760,83 €/an) étaient codés en dur dans 5 fichiers alors qu'ils changent chaque année. L'IBAN, imprimé sur la fiche PDF, n'avait pas de colonne ni de saisie. Prérequis du lissage (TS-01 T3) et du PDF (TS-01 T5).

## 2. Objectifs
- Directeur et admin modifient les plafonds par année civile, sans intervention technique.
- Directeur et admin encodent l'IBAN d'un professeur dans sa fiche.

**Hors périmètre :** tarifs par objet (les tarifs horaires restent par professeur, `professeur_tarifs`) ; PDF (T5) ; blocage du PDF si IBAN manquant (T5).

## 3. Permissions
| Action | Admin | Directeur | Professeur |
|---|---|---|---|
| Lire / modifier les plafonds | oui | oui | 403 |
| Lire / modifier l'IBAN | oui | oui | lecture de son propre profil, modification 403 |

## 4. Glossaire
| Terme | Définition | Technique |
|---|---|---|
| Plafond journalier | Montant max. de défraiement par jour | `timesheet_parametres.plafond_journalier_eur` |
| Plafond annuel | Montant max. par an et par professeur | `timesheet_parametres.plafond_annuel_eur` |
| Compte bancaire | IBAN imprimé sur la fiche | `professeurs.compte_bancaire` |

## 5. Règles métier
- Une valeur par année civile ; une année sans paramétrage hérite de la plus récente antérieure (puis 44,02 / 1 760,83).
- Plafond annuel ≥ plafond journalier ; valeurs > 0.
- Chaque modification est historisée (avant/après, auteur, date).
- IBAN : structure + clé modulo 97, espaces tolérés, stocké normalisé en majuscules sans espaces ; vide autorisé.
- Le plafond n'est pas imprimé sur le PDF.

## 6. API
- `GET /api/timesheet-parametres/{annee}` → valeurs effectives + historique.
- `PUT /api/timesheet-parametres/{annee}` → `plafond_journalier_eur`, `plafond_annuel_eur`.
- `PUT /api/professeurs/{id}` accepte `compte_bancaire`.

## 7. UI
- Menu « ⚙️ Paramètres timesheets » → `/admin/timesheets/parametres`.
- Fiche professeur : carte « 🏦 Compte bancaire ».

## 8. Critères d'acceptation et tests
`backend/tests/Feature/TimesheetParametresTest.php` : rôles (403), validation, héritage d'année, historique, IBAN valide/invalide/normalisé, lissage et PDF lisent le paramètre.
