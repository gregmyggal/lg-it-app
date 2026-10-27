# ADR-0002 — Versions « soumise » et « validée » d'une timesheet (instantanés immuables)

**Date :** 2026-10-27 · **Statut :** Proposé (à valider par l'architecte) · **Décideur métier :** direction (PO) · **Canvas :** `docs/requirements/TS-04-versions-soumise-validee.md`

## Contexte
- `TimesheetService::soumettreMois` fait passer les lignes de `brouillon` à `soumis` **sur place** ; l'adaptation (`TimesheetAdaptationService`) et le lissage (`TimesheetLissageMoisService`, qui crée aussi des lignes) modifient ces mêmes lignes. L'état « tel que soumis » est perdu ; il ne subsiste que des différences champ par champ dans `timesheet_audits`.
- La remise en brouillon (TS-02) rend les lignes modifiables : la resoumission écrase la première version.
- `timesheet_signatures` scelle une **empreinte** du contenu mais pas le contenu ; `TimesheetPdfService` génère depuis les lignes courantes. Rien ne garantit que le PDF corresponde à ce qui a été signé, ni ne permet d'afficher ce qui a été signé après une modification.
- Besoin métier : conserver et afficher côte à côte la soumission et la validation (mois complet), n'autoriser la signature et le PDF que sur la validée. La validation se fait sur le **mois entier**, professeur par professeur ; la soumission est déjà atomique par mois.

## Décision
1. **Table `timesheet_versions`, append-only** : un instantané du mois par version (`professeur_id`, `annee`, `mois`, `type` = `soumise|validee`, `numero`, `statut` = `active|remplacee`, `lignes` JSON canonique, `total_heures`, `total_eur`, `content_hash` SHA-256, `auteur_id`, `created_at`). Unicité `(professeur_id, annee, mois, type, numero)`.
2. **Immuabilité** : aucune mise à jour sauf `statut` (`active` → `remplacee`). Garde dans le modèle (refus d'`update`/`delete` des autres champs) et test dédié.
3. **Création** : la version soumise dans la transaction de `soumettreMois` ; la version validée dans la transaction de validation du mois, qui devient **atomique par mois et par professeur** (`validerLot` refuse une validation partielle, 422).
4. **Aucun nouveau statut sur `timesheets`** : les versions forment un axe séparé ; la machine à états existante (brouillon, soumis, confirmé, contesté, généré) est inchangée.
5. **Signature et PDF** : ajout de `version_id` (nullable pour l'existant) sur `timesheet_signatures` et `timesheet_pdfs`, qui ne pointent que vers une validée `active`. Le payload scellé inclut `version_id` et `version_hash`. Le PDF vérifie que l'empreinte des lignes courantes est égale à celle de la validée ; en cas d'écart, la génération est refusée (le snapshot comme source de rendu est une évolution ultérieure).
6. **Écart calculé côté serveur** : appariement sur l'identifiant de ligne ; modifiée, ajoutée, retirée ; une ligne issue d'un lissage est « ajoutée » et reliée à sa ligne d'origine (le lien, aujourd'hui seulement dans l'audit, est enregistré dans l'instantané).
7. **Format canonique versionné** : le JSON porte un champ `format` pour que toute évolution de la canonisation ne rompe pas les empreintes existantes.
8. **Pas de reprise de l'existant** : un mois sans version affiche « Non disponible avec TS-04 » (date de mise en production : 2026-10-27, soir).
9. **Rétention** : détail des lignes effacé 24 mois après la fin du mois concerné (totaux, empreinte, dates et numéro conservés) ; PDF et signatures conservés 84 mois. Un job planifié idempotent applique ces durées ; il doit être aligné sur `SignatureNumeriqueService::anonymiser` et sur `docs/AUDIT_RGPD.md`.
10. **API** : lecture seule des versions d'un mois (soumise, validée, écart), accessible à la direction et à l'admin, et au professeur pour ses propres mois (sans empreinte ni sceau).

## Conséquences
- (+) Valeur probante : on peut montrer exactement ce qui a été soumis, validé, signé et payé ; le PDF est lié à la version signée.
- (+) Changement additif, sans toucher aux statuts ni aux requêtes existantes ; retour arrière simple (colonnes nullables).
- (+) Volume faible : une à quelques lignes par professeur et par mois.
- (−) Deux sources du détail (lignes courantes et instantané) : elles doivent concorder après validation ; un bandeau d'alerte signale toute divergence.
- (−) Durcissement de `validerLot` : la validation partielle disparaît, l'interface doit l'expliquer.
- (−) Canonisation à maintenir stable (champ `format`, tests d'empreinte).
- (−) Les mois antérieurs à la mise en production restent sans historique.

## Alternatives écartées
- **Colonne `version` et duplication des lignes dans `timesheets`** : toutes les requêtes, totaux, signatures et PDF existants seraient à revoir ; risque élevé pour un gain nul.
- **Event sourcing / journal d'événements** : disproportionné pour ce volume ; l'audit actuel resterait de toute façon à reconstruire.
- **Reconstituer l'existant depuis `timesheet_audits`** : approximatif (lignes créées par lissage, remises en brouillon) ; refusé par le PO.
- **Stocker la version validée dans la signature** : le PDF et l'affichage de la soumise resteraient sans source.

## Suivi
Tranches T1 à T3 : voir Canvas TS-04 §10. Contrat d'API à documenter dans `docs/API_TS04.md` à la livraison de T1.
