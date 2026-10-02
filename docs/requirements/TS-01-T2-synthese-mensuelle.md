# TS-01 T2 — Synthèse mensuelle « Validation du mois »

**Statut :** ☑ Livré · **Maquette :** `docs/mockups/TS-01/01-validation-mensuelle.html` (écran A)

## Objectif
Donner au directeur et à l'admin une vue mois par mois : un professeur par ligne, statut du mois, totaux, alertes, et validation en lot.

## Règles métier
- Statut du mois **calculé** (rien n'est stocké), le plus bas des saisies du mois l'emporte : `brouillon` (aucune saisie ou un brouillon) < `a_valider` (une saisie soumise) < `attente_prof` (confirmée, au moins une non signée) < `pret_pdf` (confirmée et signée) < `genere`.
- Inclut les professeurs sans saisie ayant des sessions terminées sans heures.
- Alertes : jours > plafond journalier (paramètre TS-00), sessions sans heures, compte bancaire manquant, saisies sans tarif, plafond annuel dépassé.
- « Lignes ajustées » = saisies distinctes adaptées (TS-01 T1) ; « Dernière action » = dernier audit, validation ou signature.

## Permissions
Staff uniquement (professeur : 403).

## API
`GET /api/timesheets/mois-synthese?annee=&mois=` → `{ periode, kpis, professeurs[] }`.

## UI
Onglet « ✅ Validation » (par défaut) de `/admin/timesheets` : sélecteur de mois, KPI, filtres (nom, statut), tableau avec sélection, « Valider la sélection » (validation en lot existante avec lissage), « Exporter CSV », « Ouvrir » (détail des saisies du professeur).

## Hors périmètre
Génération des PDF en lot (T5), états « Ajusté – attente prof » / « Contesté » (T4).

## Tests
`backend/tests/Feature/TimesheetSyntheseMoisTest.php` (statuts, totaux, alertes, ajustées, droits).
