# Mise en production — TS-04 (versions soumise / validée)

Références : `docs/requirements/TS-04-versions-soumise-validee.md`, `docs/adr/0002-versions-timesheet.md`, `docs/API_TS04.md`, `docs/DEPLOIEMENT_OVH.md` (§F).
Commits : T1a `2f92a2c`, T1b `f45dfb3`, T1c `976f352`, T2 `28f5c46`, T3 `f76c98d`.

> Statut des vérifications déjà faites : suite backend verte (592 tests), lint et build du front sans erreur.
> **Jamais vérifié** : les écrans dans un navigateur, et les migrations sur une base autre que celle des tests. La section 3 sert à le faire.

## 1. Avant de déployer

- [ ] **Sauvegarde de la base de production** (export complet) et copie hors du serveur.
- [ ] Les 5 commits TS-04 sont sur la branche déployée et **poussés** (`git push`) ; le déploiement se fait avec `./scripts/deploy.sh prod`.
- [ ] Recette locale préalable (section 3) faite sur la base de développement :
  ```bash
  docker compose exec backend php artisan migrate
  ```
- [ ] **Prévenir les directeurs** : la validation se fait désormais sur le **mois entier**, professeur par professeur (plus de validation par saisie ni par séance isolée). Un lot incomplet est refusé avec la liste des saisies manquantes.
- [ ] Choisir le moment : de préférence hors période de validation active (des validations en cours pendant la bascule resteront sans version « soumise »).

> Paramètre « bénévoles » des employeurs : migration `2026_10_28_120000_add_benevoles_to_employeurs` (défaut `true`) ; **l'admin doit décocher « bénévoles » sur les entités dont les animateurs sont employés** (Admin > Entités employeurs) pour que les fiches n'emploient plus « volontaire ».
>
> TS-06 (notes internes) ajoute 2 tables (`2026_10_28_110000_create_timesheet_notes_tables`, voir `docs/API_TS06.md`) et une tâche planifiée `timesheets:purger-notes` (04:15).
>
> TS-05 (soumission forcée) ajoute une 3e migration, `2026_10_28_100000_add_origine_to_timesheet_versions` (voir `docs/API_TS05.md`) ; elle part avec le même déploiement si TS-05 est livré en même temps.

## 2. Migrations (2 pour TS-04, additives, réversibles)

| Migration | Effet |
|---|---|
| `2026_10_27_100000_create_timesheet_versions_table` | Crée `timesheet_versions` (instantanés immuables). Clé étrangère vers `professeurs` en `cascade`, vers `users` en `set null`. |
| `2026_10_27_110000_add_version_to_signatures_and_pdfs` | Ajoute `version_id` (nullable, `set null`) et `version_hash` sur `timesheet_signatures`, `version_id` sur `timesheet_pdfs`. |

- [ ] `php artisan migrate --pretend` : relire le SQL avant d'appliquer.
- [ ] `./scripts/deploy.sh prod` applique les migrations ; vérifier ensuite `php artisan migrate:status` (les 2 migrations en « Ran »).
- Aucune donnée existante n'est modifiée ni reprise : les mois déjà soumis ou validés n'ont pas de version (« Non disponible avec TS-04 »).

## 3. Drapeau de garde `TIMESHEET_GARDE_VERSIONS`

Défaut : `true` (garde active). Il ne concerne que les mois qui ont **une version validée** : les mois déjà validés avant la mise en production ne sont jamais bloqués.

Séquence recommandée (prudente) :
1. [ ] Déployer avec `TIMESHEET_GARDE_VERSIONS=false` dans `backend/.env`, puis `php artisan config:cache`.
2. [ ] Faire la recette (section 4) sur un vrai mois de test.
3. [ ] Passer à `true`, `php artisan config:cache`, refaire les vérifications 4.6 et 4.7.

Le passage `false` → `true` peut se faire à tout moment : les validations faites entre-temps créent déjà leur version validée.

## 4. Vérifications après déploiement (recette R1 à R5 du Canvas)

Utiliser un professeur de test et un mois **non encore soumis**.

- [ ] **4.1** `/up` et `/` répondent ; `storage/logs/laravel.log` sans erreur après un parcours complet.
- [ ] **4.2 Soumission (R1)** : le professeur soumet son mois → une ligne `timesheet_versions` (`type = soumise`, `numero = 1`, `statut = active`). Une seconde soumission est refusée.
- [ ] **4.3 Validation (R2)** : côté directeur, valider le mois entier (Synthèse : « Valider le mois »). Tenter de valider une saisie seule (vue « par séance », sélection d'un professeur) : c'est le mois entier du professeur qui est validé. Une version `validee` v1 existe ; la version soumise est inchangée.
- [ ] **4.4 Écran directeur** : dans le détail du mois, les vues Écarts, Soumise, Validée et Côte à côte s'affichent ; « Identique à la soumission » si rien n'a changé ; la frise « Historique » liste soumission et validation.
- [ ] **4.5 Modification d'un mois validé** : adapter une saisie (motif) → validée v2 `active`, v1 `remplacee` ; la vue Écarts montre la ligne modifiée avec son motif.
- [ ] **4.6 Signature (R3)** : côté professeur, « Mon mois » montre la comparaison soumise / validée ; le bouton « Signer la version validée (… €) » signe ; `timesheet_signatures.version_id` pointe la validée active. Smartphone : segments Écarts / Validée / Soumise, bouton collé en bas.
- [ ] **4.7 PDF** : générer la fiche → `timesheet_pdfs.version_id` renseigné. Après une adaptation ultérieure, la génération est refusée tant que le professeur n'a pas signé de nouveau.
- [ ] **4.8 Remise en brouillon (R4)** : la validée passe `remplacee`, la soumise reste ; resoumission → soumise v2, v1 consultable via le sélecteur de version.
- [ ] **4.9 Mois antérieur (R5)** : ouvrir un mois déjà soumis avant la mise en production → « Non disponible avec TS-04 » sans erreur ; signature et PDF d'un mois déjà validé restent possibles.
- [ ] **4.10** `POST /api/timesheets/{id}/validate` répond 404 (route supprimée) ; la modale de lissage d'une saisie fonctionne (`apply-lissage`, désormais sous contrôle de statut et du plafond, ligne créée au statut de l'origine).
- [ ] **4.11 Tâche planifiée** : `php artisan schedule:list` montre `timesheets:purger-versions` à 04:00 ; `php artisan timesheets:purger-versions --simulation` renvoie « 0 version(s) seraient purgée(s) » (aucune version n'a plus de 24 mois ; la première purge réelle n'aura lieu qu'à partir de novembre 2028). Le cron OVH est horaire : la tâche de 04:00 pile ne part que si le cron passe à cette heure (comme `signatures:anonymiser` à 03:00).

## 5. Retour arrière

- **Désactiver seulement la garde** : `TIMESHEET_GARDE_VERSIONS=false` + `php artisan config:cache` (effet immédiat, sans code ni migration).
- **Revenir au code précédent** : redéployer le commit antérieur à `2f92a2c`. Les tables et colonnes ajoutées restent en place et sont ignorées par l'ancien code (migrations additives). Attention : l'ancien code permet de nouveau la validation partielle, ce qui laisserait les versions incohérentes avec les lignes ; ne pas réactiver TS-04 sans reprendre de zéro les mois concernés.
- **Annuler les migrations** (en dernier recours, perte des versions enregistrées) : `php artisan migrate:rollback --step=2`.

## 6. Points d'attention connus

- **Interface jamais testée en navigateur** : les écrans `VersionsMois`, `FriseMois`, `ComparaisonVersions` et la validation par mois entier sont à regarder en vrai (ordinateur et smartphone) avant d'ouvrir le service.
- **Validation par séance** (`TimesheetsParSession`) : cocher une heure sélectionne tout le mois du professeur ; vérifier que le libellé « Valider le mois (n professeur…) » est clair pour les directeurs.
- **Montants** : un changement de tarif après la validation d'un mois bloque la signature et le PDF de ce mois jusqu'à une nouvelle validation (tarif figé à la validation).
- **Mois contesté** : toute validation est refusée tant que la contestation n'est pas traitée.
- **Question RGPD ouverte (Q5)** : l'IP et l'appareil des signatures restent conservés 7 ans (recommandation de l'audit : 12 à 24 mois). À trancher avant la première purge des preuves.
- **Conservation** : détail des versions 24 mois après la fin du mois ; PDF et signatures 84 mois minimum, sans purge automatique.
