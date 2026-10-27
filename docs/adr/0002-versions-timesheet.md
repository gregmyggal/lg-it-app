# ADR-0002 — Versions « soumise » et « validée » d'une timesheet (instantanés immuables)

**Date :** 2026-10-27 · **Statut :** Accepté avec réserves (revue d'architecture du 2026-10-27, points bloquants B1 à B3 traités par les décisions 3 à 6 et 9 ci-dessous ; réserves à lever dans les tranches) · **Décideur métier :** direction (PO) · **Canvas :** `docs/requirements/TS-04-versions-soumise-validee.md`

## Contexte
- `TimesheetService::soumettreMois` fait passer les lignes de `brouillon` à `soumis` **sur place** ; l'adaptation (`TimesheetAdaptationService`) et le lissage modifient ces mêmes lignes. L'état « tel que soumis » est perdu.
- La remise en brouillon (TS-02) rend les lignes modifiables : la resoumission écrase la première version.
- `timesheet_signatures` scelle une **empreinte** du contenu mais pas le contenu ; `TimesheetPdfService` génère depuis les lignes courantes : rien ne garantit que le PDF corresponde à ce qui a été signé.
- **Plusieurs chemins modifient ou valident un mois** (relevé du code) :
  - `validerLot` (seul appelé par le front pour valider) ;
  - `TimesheetController::validateEntry` (valide une ligne seule, sans transaction ni contrôle du mois, plus appelé par le front) ;
  - `TimesheetController::applyLissage` (appelé par `TimesheetLissingModal.jsx`) et le lissage de `validerLot`, qui utilisent l'ancien `TimesheetLissingService::executeLissage` : pas de contrôle de statut, ligne créée directement `confirme`, aucun audit, aucun lien vers la ligne d'origine (texte libre dans `commentaire`) ;
  - `TimesheetLissageMoisService::appliquer` (seul à enregistrer `nouvelle_saisie_id` dans l'audit) et `TimesheetAdaptationService::adapter`, qui acceptent des lignes `confirme` ;
  - `TimesheetConfirmationService` (contestation, traitement, remise en brouillon) et `TimesheetPdfService::deverrouiller` (après génération, remet les lignes en `confirme`).
- Besoin métier : conserver et afficher côte à côte la soumission et la validation (mois complet), n'autoriser la signature et le PDF que sur la validée. La validation se fait sur le **mois entier**, professeur par professeur ; la soumission est atomique par mois.

## Décision
1. **Table `timesheet_versions`, append-only** : `professeur_id` (FK `restrict`), `annee`, `mois`, `type` = `soumise|validee`, `numero`, `statut` = `active|remplacee`, `lignes` en **texte canonique** (`LONGTEXT`, relu octet pour octet comme `timesheet_signatures.payload`, jamais en colonne `JSON` native), `format`, `total_heures`, `total_eur`, `content_hash` SHA-256, `auteur_id` (`nullOnDelete`), `created_at` (pas d'`updated_at`), `purge_at`. Index unique `(professeur_id, annee, mois, type, numero)` ; unicité de la version active par une colonne `actif_cle` (unique, renseignée seulement quand `statut = active`) ; index `(annee, mois)` pour la rétention.
2. **Immuabilité** : seul `statut` passe de `active` à `remplacee`. Garde dans le modèle, test dédié qui échoue si du code contourne le modèle (`DB::table`) ; trigger de base si l'hébergeur le permet (à vérifier). La purge de rétention est une exception documentée.
3. **Création de la soumise** : dans la transaction de `soumettreMois`, qui verrouille les lignes du mois **avant** le contrôle « déjà soumis » (suppression de la course actuelle) ; requête par bornes de dates (`whereBetween`), pas `whereYear/whereMonth`. Allocation de `numero` sous verrou de la ligne `professeurs`, avec nouvelle tentative en cas de violation d'unicité.
4. **Création de la validée** : service unique `TimesheetVersionService::figerValidee(professeur, annee, mois, auteur)`, appelé dans la même transaction que :
   - `validerLot` (une seule transaction pour tout le lot, une validée par couple professeur/mois ; un mois incomplet fait échouer tout le lot en 422 avec la liste des mois fautifs) ;
   - toute **mutation de contenu d'un mois ayant une validée `active`** : `adapter`, `appliquer` (lissage du mois), traitement de contestation suivi de nouvelle validation, et `deverrouiller` : validée v+1, l'ancienne passe `remplacee` ;
   - la **remise en brouillon** du mois : la validée `active` passe `remplacee`.
   Le snapshot est pris **après** lissage, sur une requête du mois entier, jamais depuis la liste d'identifiants du lot. Toutes les lignes du mois sont verrouillées avant le contrôle de complétude. « Mois complet » : toutes les lignes non brouillon du mois sont dans le lot et `soumis` (évalué avant lissage).
5. **Unification des chemins (B1)** : `validateEntry` est supprimé (route retirée, test de refus) ; le lissage de `validerLot` et `apply-lissage` passent par `TimesheetLissageMoisService` (statut contrôlé, audit, `nouvelle_saisie_id`, signature remise à null) ; l'ancien `TimesheetLissingService::executeLissage` est retiré ; `TimesheetLissingModal.jsx` est migré vers l'API du mois.
6. **Signature et PDF** (garde active en dernière tranche) : `version_id` (FK `restrict`) et `version_hash` copiés en colonnes sur `timesheet_signatures` ; `version_id` sur `timesheet_pdfs`. Payload de signature en `version: 2` avec `version_timesheet_id` ; `sceauValide` et la vérification publique continuent de lire `contenu_sha256`. `canSignMonth` exige une validée `active` à jour ; `estPerimee` est vrai si la signature ne pointe plus vers la validée active ; `signer` revérifie dans sa transaction que la validée active correspond aux lignes verrouillées ; `TimesheetPdfService::bloquants` (donc aussi `lot`) contrôle que les lignes brutes courantes (id, date, type, heures) égalent la validée et que le total recalculé égale son `total_eur` figé. Le tarif est figé à la validation. IBAN et employeur sont **hors** du hash de version : `estPerimee` continue de les contrôler.
7. **Canonisation unique et versionnée** : une seule fonction, partagée avec `FicheDefraiementLignes` ; décimaux en chaînes, dates `Y-m-d`, clés triées, tri stable (`date`, `id`), `JSON_UNESCAPED_UNICODE|SLASHES` ; champ `format` ; tests d'empreinte à valeur attendue en dur et aller-retour sur la base MySQL de test.
8. **Écart calculé côté serveur** : appariement sur l'identifiant de ligne ; une ligne issue d'un lissage est « ajoutée » et reliée à sa ligne d'origine par le lien enregistré dans l'instantané (rendu fiable par la décision 5).
9. **Mois existants à la mise en production (2026-10-27, soir)** : aucune reprise. Un mois sans version affiche « Non disponible avec TS-04 ». La garde signature/PDF (décision 6) est **conditionnelle** : elle ne s'applique qu'aux mois dont la validation date de la mise en production ou après (porté par la présence d'une validée) ; un mois déjà validé avant reste signable et générable comme aujourd'hui. Un mois partiellement validé par l'ancien `validateEntry` est traité comme un mois existant.
10. **Rétention** : détail des lignes effacé 24 mois après la fin du mois concerné (totaux, empreinte, dates et numéro conservés). **PDF : conservés au minimum 84 mois, sans purge automatique** (aucun job n'en supprime aujourd'hui). Signatures : l'anonymisation existante (`config/signature.php`, 7 ans, sur `signed_at`) est inchangée. Départs : versions = fin du mois ; signatures = `signed_at` ; PDF = `generated_at`. La purge à 24 mois est une exception à l'immuabilité, idempotente, planifiée dans `routes/console.php` ; elle ne purge pas une validée référencée par une signature non anonymisée.
11. **API** : lecture seule des versions d'un mois (soumise, validée, écart) pour la direction et l'admin, et pour le professeur sur ses propres mois (sans empreinte ni sceau).

## Tranches
- **T1a — fondations** : migration, service de version (canonisation, hash, tests d'empreinte), `soumettreMois`, retrait de `validateEntry`, unification du lissage, `validerLot` atomique avec complétude ; déployée sans garde.
- **T1b** : `figerValidee` dans `adapter`, `appliquer`, contestation, remise en brouillon, déverrouillage ; API de lecture ; écran directeur.
- **T1c** : garde signature/PDF (décisions 6 et 9), activée une fois T1b déployée (drapeau de configuration possible).
- **T2** : écran professeur et smartphone.
- **T3** : historique et job de rétention 24 mois.

## Conséquences
- (+) On peut montrer exactement ce qui a été soumis, validé, signé et payé ; le PDF est lié à la version signée.
- (+) Changement additif ; aucun nouveau statut ; retour arrière simple (colonnes nullables).
- (+) Un lissage et une adaptation unifiés et audités, y compris pour l'existant.
- (−) Plus de chemins à instrumenter (décision 4) : un test d'invariant « après chaque mutation, la validée active égale le contenu courant » est obligatoire.
- (−) Suppression de `validateEntry` et refonte de la modale de lissage ; `ValiderLotModal` doit expliquer la fin de la validation partielle.
- (−) Après purge à 24 mois, une empreinte sans contenu est peu probante ; le PDF conservé garde la preuve du contenu.
- (−) La purge des lignes à 24 mois minimise peu les données tant que le PDF correspondant est conservé 84 mois : choix assumé.
- (−) Les mois antérieurs à la mise en production restent sans historique.
- (−) Écart assumé avec `docs/AUDIT_RGPD.md`, qui recommande 12 à 24 mois pour l'IP et le user-agent des signatures (conservés 7 ans) : à reprendre dans l'audit.

## Alternatives écartées
- **Colonne `version` et duplication des lignes dans `timesheets`** : toutes les requêtes, totaux, signatures et PDF existants seraient à revoir.
- **Event sourcing** : disproportionné pour ce volume.
- **Reconstitution de l'existant depuis `timesheet_audits`** : approximative ; refusée par le PO.
- **Validée stockée dans la signature** : le PDF et l'affichage de la soumise resteraient sans source.
- **Valider sur `soumis` seul et ignorer les mutations d'un mois validé** : la validée serait périmée et signature et PDF bloqués sans issue.
- **Valider à la reprise les mois existants** (validée « de reprise ») : contredit le choix « pas de reprise ».
