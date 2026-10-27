# Canvas de Requirements — TS-04 Versions « soumise » et « validée » d'une timesheet

| | |
|---|---|
| **ID / Titre** | TS-04 — Conserver et afficher côte à côte la timesheet soumise et la timesheet validée ; seule la validée est signée et générée |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR, product owner, 2026-10-27) ☐ En dev ☐ En recette ☐ Livré |
| **Analyste** | Analyste (Claude) + product owner | **UX/UI** | Sub-agent UX Expert ; maquette validée par le PO |
| **Architecte** | À désigner (ADR-0002 proposée) | **Dev back / front** | À désigner |
| **Date / Version** | 2026-10-27 — v0.2 | **Sprint cible** | À planifier |
| **Liens** | Maquette : `docs/mockups/TS-04/01-versions-soumise-validee.html` · ADR : `docs/adr/0002-versions-timesheet.md` · TS-01 (adaptation, lissage, PDF) · TS-02 (remise en brouillon) · SIG-01 (signature) · EMP-01 · `docs/AUDIT_RGPD.md` |

---

## 1. Contexte et problème
- **Situation actuelle :** `TimesheetService::soumettreMois` fait passer les lignes de `brouillon` à `soumis` sur place. L'adaptation (`TimesheetAdaptationService`) et le lissage (`TimesheetLissageMoisService`) modifient ces mêmes lignes (le lissage en crée de nouvelles). L'« avant » n'existe que sous forme de différences champ par champ dans `timesheet_audits`. La remise en brouillon rend les lignes modifiables et la resoumission écrase la première version. `timesheet_signatures` ne scelle que l'empreinte du contenu, pas le contenu. Le PDF est généré depuis les lignes courantes.
- **Douleur / coût :** impossible d'afficher, après coup, ce que le professeur avait réellement soumis face à ce qui a été validé et payé ; impossible de prouver ce qui a été signé une fois le mois modifié ; pas de garantie que le PDF corresponde au contenu signé.
- **Déclencheur :** besoin d'historique et de validation « réel vs ajusté » exprimé par le product owner.

## 2. Objectifs et indicateurs de succès
| Objectif métier | Indicateur | Cible | Mesure |
|---|---|---|---|
| Conserver la soumission telle qu'envoyée | % de soumissions (après mise en production) ayant une version immuable | 100 % | Test feature + recette R1 |
| Voir soumise et validée en entier et comparées | Vues disponibles pour direction/admin | 4 (soumise, validée, côte à côte, écarts) | Recette R2 |
| Signer et générer uniquement la validée | Signatures/PDF pointant vers une version non validée | 0 | Test feature (refus) |
| Comprendre un écart | Écarts accompagnés de leur motif | 100 % | Recette R2 |

**Hors périmètre :** reconstitution des mois antérieurs à la mise en production (affichés « Non disponible avec TS-04 ») ; modification du cycle des statuts de `timesheets` ; validation partielle ; facturation ; nouvelle signature du professeur sur la version soumise.

## 3. Acteurs, rôles et permissions
**Personas :** Directeur (poste, validation mensuelle, professeur par professeur) · Admin · Professeur (poste et smartphone, avant signature).

| Ressource / Action | Admin | Staff (directeur) | Professeur | Élève |
|---|---|---|---|---|
| Version soumise : lire | L | L | L (les siennes) | — |
| Version validée : lire | L | L | L (les siennes) | — |
| Comparaison / écarts | L | L | L (les siennes) | — |
| Modifier ou supprimer une version | — (immuable) | — | — | — |
| Signer | — | — | S (version validée uniquement, la sienne) | — |
| Générer le PDF | V | V | — | — |

> Alimente : `TimesheetVersionPolicy`, `can.*` des Resources, tests 403 (professeur sur un autre professeur, anonyme).

## 4. Glossaire métier
| Terme affiché | Définition | Nom technique |
|---|---|---|
| Soumise | Le mois tel que envoyé par le professeur ; référence immuable, non signable | `timesheet_versions.type = soumise` |
| Validée | Le mois tel que validé par la direction/admin, en entier ; seule version signable et générable | `timesheet_versions.type = validee` |
| Version n | Numéro séquentiel par (professeur, année, mois, type) | `numero` |
| Active / Remplacée | Dernière version du type / version supplantée (reste consultable) | `statut = active\|remplacee` |
| Écart | Différence ligne à ligne : modifiée, ajoutée, retirée | calculé côté serveur |
| Non disponible avec TS-04 | Mois antérieur à la mise en production sans version enregistrée | absence de ligne `timesheet_versions` |

## 5. Parcours utilisateurs et user stories

### 5.1 User stories
| ID | En tant que | Je veux | Afin de | MoSCoW |
|---|---|---|---|---|
| US-1 | directeur/admin | voir la soumission complète d'un mois | savoir ce que le professeur a réellement envoyé | Must |
| US-2 | directeur/admin | voir la validation complète du mois | connaître la version payée | Must |
| US-3 | directeur/admin | comparer les deux (côte à côte et écarts avec motifs) | comprendre et justifier les ajustements | Must |
| US-4 | professeur | comparer ma soumission à la version validée avant de signer | signer en connaissance de cause | Must |
| US-5 | direction/admin | être certain que seule la validée est signée et générée | garantir la valeur probante | Must |
| US-6 | direction/admin | retrouver l'historique des versions d'un mois | auditer remises en brouillon et révisions | Should |

### 5.2 Parcours
1. **Soumission** : le professeur soumet son mois → une version soumise v1 est créée avec toutes les lignes, les totaux et une empreinte.
2. **Validation** : la direction ou l'admin valide le mois entier du professeur (ajustements et lissages éventuels dans la même transaction) → une version validée v1 est créée ; la soumise reste inchangée.
3. **Consultation** : écran de détail du mois → sélecteur Soumise (complète) / Validée (complète) / Côte à côte / Écarts (par défaut).
4. **Signature** : le professeur voit soumise et validée côte à côte, puis signe la validée ; la signature référence l'identifiant et l'empreinte de la version validée.
5. **PDF** : généré depuis la version validée active signée.
6. **Alternatives** : remise en brouillon puis resoumission → soumise v2 (v1 reste consultable) ; la validée `active` passe `remplacee` à la remise en brouillon. Toute modification d'un mois validé (adaptation, lissage, contestation traitée puis nouvelle validation, déverrouillage après génération du PDF) → validée v+1 ; la v1 passe `remplacee`, la signature associée devient « remplacée » et une nouvelle signature est demandée. Mois sans ajustement → « Identique à la soumission ». Mois antérieur à TS-04 → « Non disponible avec TS-04 ».

### 5.3 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | un mois en brouillon | le professeur soumet | une version soumise v1 existe avec les lignes figées, totaux et hash | Feature |
| AC-2 | une soumise v1, mois remis en brouillon puis resoumis | le professeur soumet | soumise v2 `active`, v1 `remplacee` et toujours lisible | Feature |
| AC-3 | un mois soumis | la direction valide | validée v1 créée avec toutes les lignes ; soumise inchangée | Feature |
| AC-4 | une validation avec ajustements/lissage | la direction consulte | les 4 vues sont disponibles, écarts corrects (modifiée/ajoutée/retirée) avec motifs | Feature + Recette R2 |
| AC-5 | une version de type soumise | une signature ou un PDF est demandé sur elle | refus 422, rien n'est créé | Feature |
| AC-6 | une validée signée | une tentative de mise à jour d'une version est faite | refus (immutabilité) | Unit |
| AC-7 | un mois soumis avant la mise en production | la direction ouvre le détail | « Non disponible avec TS-04 » sur la version absente, sans erreur | Feature + Front |
| AC-8 | une validation partielle (lignes du mois non toutes sélectionnées, ou mois contenant d'anciennes lignes `confirme`) | l'API de validation est appelée | refus 422 de tout le lot avec la liste des mois fautifs ; la route `validateEntry` n'existe plus | Feature |
| AC-11 | un mois validé (validée v1) | une ligne est adaptée ou lissée | validée v2 `active` dans la même transaction, v1 `remplacee`, signature remplacée | Feature |
| AC-12 | un mois validé (validée active) | il est remis en brouillon | la validée passe `remplacee` ; la soumise est inchangée | Feature |
| AC-13 | un mois validé avant la mise en production | la direction génère le PDF ou le professeur signe | autorisé comme aujourd'hui, sans validée | Feature |
| AC-14 | deux soumissions simultanées du même mois | les deux requêtes arrivent | une seule réussit, une seule version soumise v1 | Feature |
| AC-15 | une validée v2 existante | le PDF est généré | refusé si les lignes courantes ou le total diffèrent de la validée | Feature |
| AC-16 | toute mutation de mois validé | après chaque chemin applicatif | la validée `active` égale le contenu courant (test d'invariant) | Feature |
| AC-9 | un professeur A | il lit les versions du professeur B | 403 | Feature |
| AC-10 | des versions de plus de 24 mois | le job de rétention s'exécute | le contenu détaillé est effacé, totaux, empreinte et dates conservés | Feature |

## 6. Règles métier et cycle de vie
- **RG-1 :** une version soumise est créée dans la transaction de `soumettreMois`, une seule par soumission.
- **RG-2 :** une version validée est créée dans la transaction de validation du mois ; la validation porte sur le mois entier, professeur par professeur ; la validation partielle est refusée et la route `validateEntry` est supprimée. Le lot est tout ou rien : un mois incomplet fait échouer tout le lot (422, liste des mois fautifs).
- **RG-3 :** une version est immuable ; seul `statut` peut passer de `active` à `remplacee`.
- **RG-4 :** signature et PDF ne ciblent qu'une version validée `active` ; le PDF vérifie que l'empreinte des lignes courantes égale celle de la validée.
- **RG-5 :** toute mutation de contenu d'un mois ayant une validée `active` (adaptation, lissage, contestation traitée puis revalidation, déverrouillage après PDF) crée validée v+1 dans la même transaction ; l'ancienne reste consultable.
- **RG-6 :** soumise et validée sont strictement indépendantes du statut de `timesheets` (aucun nouveau statut).
- **RG-7 :** pas de reconstitution pour l'existant : tout mois sans version enregistrée affiche « Non disponible avec TS-04 ». Un mois déjà soumis à la mise en production a sa soumise « non disponible » ; sa validée sera créée normalement à sa validation. Un mois déjà validé avant la mise en production (2026-10-27, soir), y compris partiellement, n'a pas de validée : il reste signable et générable comme aujourd'hui (garde conditionnelle, RG-11).
- **RG-8 :** rétention : détail des versions effacé 24 mois après la fin du mois concerné (totaux et empreinte conservés) ; PDF et signatures conservés 84 mois (voir §7).
- **RG-9 :** l'écart est calculé côté serveur par appariement sur l'identifiant de ligne ; une ligne issue d'un lissage est « ajoutée » et reliée à sa ligne d'origine.
- **RG-10 :** à la remise en brouillon du mois, la validée `active` passe `remplacee` ; la soumise reste inchangée.
- **RG-11 :** la garde « signature et PDF uniquement sur une validée `active` à jour » s'applique aux mois validés à partir de la mise en production ; elle est activée en dernière tranche (T1c). Le PDF contrôle que les lignes brutes courantes égalent la validée et que le total recalculé égale son total figé (tarif figé à la validation).
- **RG-12 :** le lissage (validation, adaptation, lissage direct) passe par un seul service du mois, avec contrôle de statut, audit et lien vers la ligne d'origine.

**Carte des statuts (axe version) :**
| Valeur | Libellé | Badge | Modifiable | Transition | Déclencheur |
|---|---|---|---|---|---|
| `active` | Active | vert (validée) / gris (soumise) | non | → `remplacee` | nouvelle version du même type |
| `remplacee` | Remplacée | gris clair | non | — | — |

**Calculs :** montants calculés avec les tarifs en vigueur au moment du snapshot (via `TarifResolver`) et figés ; arrondis à 2 décimales ; fuseau Europe/Brussels pour l'affichage.

## 7. Données et migration
| Entité / champ | Type | Oblig. | Contraintes | Exemple |
|---|---|---|---|---|
| `timesheet_versions.id` | bigint | oui | PK | |
| `professeur_id`, `annee`, `mois` | FK, smallint, tinyint | oui | index composite | 12, 2026, 10 |
| `type` | string(10) | oui | `soumise` ou `validee` | validee |
| `numero` | smallint | oui | unique (prof, année, mois, type, numero) | 1 |
| `statut` | string(10) | oui | `active` ou `remplacee` | active |
| `lignes` | LONGTEXT (texte canonique, pas de JSON natif) | oui (nul après purge) | immuable ; décimaux en chaînes ; champ `format` | [{id, date, type, heures, tarif, montant, session, origine_id…}] |
| `actif_cle` | string nullable | non | unique ; renseignée seulement si `statut = active` | |
| `purge_at` | timestamp nullable | non | index de rétention `(annee, mois)` | |
| `timesheet_signatures.version_hash` | char(64) nullable | non | copie de l'empreinte de version (survit à l'anonymisation) | |
| `total_heures`, `total_eur` | decimal | oui | | 11.00, 231.00 |
| `content_hash` | char(64) | oui | SHA-256 | |
| `auteur_id`, `created_at` | FK users, timestamp | oui | | |
| `timesheet_signatures.version_id`, `timesheet_pdfs.version_id` | FK nullable | oui pour les nouveaux | pointe vers une validée | |

- **Nouvelles tables / colonnes :** `timesheet_versions` ; `version_id` sur signatures et PDF (nullable pour l'existant).
- **Données existantes impactées :** aucune reprise ; mois antérieurs « Non disponible avec TS-04 ».
- **Backfill :** aucun.
- **Rétention / RGPD :** détail des versions : 24 mois après la fin du mois concerné, puis `lignes` est vidé ; restent totaux, empreinte, dates, numéro. PDF et signatures : 84 mois (7 ans). Job planifié, idempotent, à aligner sur `SignatureNumeriqueService::anonymiser` et sur `docs/AUDIT_RGPD.md` (à mettre à jour).
- **Seeders / factories :** `TimesheetVersionFactory` ; scénario de test « mois ajusté + lissé ».

## 8. Exigences UX/UI
> 🚦 Workflow défini par le **sub-agent UX Expert** et maquette (5 écrans A à E) **validée par le product owner le 2026-10-27** : ☑

### Workflow UX (sub-agent UX Expert)
Aucune nouvelle page : tout s'insère dans les écrans existants.
- **Directeur/admin** : composant `VersionsMois` (+ sélecteur de vues) dans `DetailProfesseurMois.jsx`, entre les bandeaux et la grille de détail ; il remplace le tableau « Saisies du mois » quand le mois est soumis ou validé. Le chaînage Soumise → Validée → Signée remplace le badge de statut de l'en-tête. L'historique enrichit la section « Historique » du panneau latéral.
- **Professeur** : dans `ReconfirmationMois.jsx` (écran Mon mois), la comparaison Soumise / Validée avec motifs remplace le tableau « Ajustements de la direction » ; les boutons « Signer la version validée… » et « Contester… » restent ceux de la modale existante (`SignerMoisModal` reçoit le récapitulatif de la validée). Les bandeaux employeur, contestation et remise en brouillon existants restent avant la comparaison.
- **Smartphone** : même composant en mode mobile (`useMediaQuery('(max-width: 860px)')`), bouton de signature dans le conteneur collant de Mon mois.
- **Données** : un hook `useVersionsMois` dans `hooks/useTimesheets.js`, via `client.js`.
- **Parcours** : directeur 2 clics (synthèse → détail), vue **Écarts par défaut**, 1 clic pour une vue complète ; professeur desktop : comparaison visible sans clic, 2 à 3 clics pour signer ; smartphone : segments Écarts (par défaut quand il y a des ajustements), Validée, Soumise.
- **Textes exacts :** « Seule la version validée est signée et générée en PDF. » (sous-texte : « La version soumise est conservée comme référence ; elle ne peut être ni modifiée ni signée. ») · sélecteur (`aria-label="Affichage des versions"`) : « Écarts », « Soumise », « Validée », « Côte à côte » · « Identique à la soumission » (« Aucun écart : le mois a été validé tel que soumis. ») · « Non disponible avec TS-04 » (« Ce mois a été soumis avant la mise en place des versions : la soumission d'origine n'a pas été conservée. ») · « Ce que j'ai soumis » / « Version validée (à signer) » / « Pourquoi ces changements ? » · « Signer la version validée » · « En signant, vous signez uniquement la version validée. Votre version soumise reste conservée. » · « Détail effacé après 24 mois. Totaux et empreinte conservés. » · « Impossible de charger les versions de ce mois. Réessayez. » · validation : « La validation porte toujours sur le mois entier du professeur. »
- **Cas limites :** mois antérieur à TS-04 (segments Soumise/Écarts/Côte à côte désactivés, validée affichée, aucune erreur) ; identique à la soumission ; soumise v2 / validée v2 (sélecteur de version, badge « Remplacée », bandeau de remplacement de signature) ; version purgée (totaux et empreinte seulement) ; mois soumis non encore validé (seule la soumise est proposée).
- **Accessibilité :** boutons `aria-pressed` dans un `role="group"`, vue annoncée en `aria-live`, écarts nommés en texte (« Modifiée », « Ajoutée », « Retirée », « avant : ») et pas par la couleur seule, cibles tactiles ≥ 44 px, vue côte à côte en cartes sous 640 px, pas d'empreinte ni de sceau affichés au professeur.
- **Risques UX :** `ValiderLotModal` doit expliquer la fin de la validation partielle ; deux sources du détail (bandeau d'alerte si le contenu courant diverge de la validée) ; `docs/AUDIT_UIUX.md` obsolète (non utilisé).

| Écran | Contenu clé | Actions | Maquette |
|---|---|---|---|
| Détail mois (directeur/admin) | Chaînage Soumise → Validée → Signée ; sélecteur 4 vues ; motifs | Générer PDF, Remettre en brouillon | Écran A |
| Ma confirmation (professeur) | Soumise vs validée avec motifs | Signer la version validée, Contester | Écran B |
| Historique & documents | Frise des versions, empreintes, documents | Télécharger | Écran C |
| Smartphone (professeur) | Sélecteur Soumise / Validée / Écarts, bouton de signature collé en bas | Signer | Écran D |

**Les 4 états :**
| Écran | Chargement | Vide | Erreur | Succès |
|---|---|---|---|---|
| Versions | squelette de tableau | « Non disponible avec TS-04 » (mois antérieurs) ; « Identique à la soumission » si aucun écart | message + « Réessayer » | — |

- **Textes exacts :** « Seule la version validée est signée et générée en PDF. » · « Signer la version validée » · « Non disponible avec TS-04 ».
- **Appareils :** ☑ Desktop ☑ Tablette ☑ Mobile (professeur).
- **Accessibilité :** sélecteur en groupe de boutons avec `aria-pressed`, écarts signalés par un libellé (pas la couleur seule).
- **Charge cognitive :** vue « Écarts » par défaut ; ≤ 1 clic pour passer à une vue complète.

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Sécurité | accès limité au professeur concerné, à la direction et à l'admin ; versions immuables |
| Performance | une ligne par version et par mois (volume faible) ; chargement < 500 ms |
| Traçabilité | auteur, date, empreinte de chaque version ; historique visible |
| Notifications / export | PDF généré depuis la validée uniquement ; aucune nouvelle notification |
| Compatibilité | FR, Europe/Brussels |

## 10. Impact technique
| Couche | Changements prévus | Taille |
|---|---|---|
| **DB** | migration `timesheet_versions` ; `version_id` sur signatures et PDF | S |
| **Back** | `TimesheetVersionService` (canoniser, hasher, `figerValidee`, écart) ; `soumettreMois` (verrou avant contrôle) ; `validerLot` atomique avec complétude ; `TimesheetAdaptationService`, `TimesheetLissageMoisService`, `TimesheetConfirmationService` (remise en brouillon, contestation), `TimesheetPdfService` (déverrouillage, `bloquants`), `TimesheetSignatureService::canSignMonth`, `SignatureNumeriqueService` (`version: 2`, `estPerimee`) ; suppression de `TimesheetController::validateEntry` et de `TimesheetLissingService::executeLissage` ; `apply-lissage` repris par le service du mois ; policy ; job de rétention 24 mois | L |
| **API** | `GET` versions d'un mois (soumise, validée, écart) pour direction/admin et pour le professeur (les siennes) ; rétrocompatible | S |
| **Front** | sélecteur 4 vues, vue côte à côte, bandeau « non disponible », écran professeur avant signature, via `client.js` ; migration de `TimesheetLissingModal.jsx` vers l'API du mois ; `ValiderLotModal` explique la validation du mois entier | M |
| **Tests** | feature (AC-1 à AC-10), unit (hash, écart, immutabilité), recette | M |
| **Docs / ADR** | `docs/API_TS04.md` ; ADR « instantanés de version vs lignes versionnées » | S |

- **Risques :** dérive de la canonisation (la versionner) ; lissage créant des lignes (conserver le lien d'origine, aujourd'hui seulement dans l'audit) ; durcissement de `validerLot` à valider avec l'UX.
- **Tranches (ADR-0002) :** T1a = fondations (migration, service de version, `soumettreMois`, retrait de `validateEntry`, unification du lissage, `validerLot` atomique), sans garde ; T1b = validée v+1 sur toute mutation, API de lecture, écran directeur (4 vues) ; T1c = garde signature/PDF, activée après T1b ; T2 = écran professeur avant signature + smartphone ; T3 = historique, job de rétention 24 mois.
- **Déploiement / retour arrière :** migration additive ; retour arrière possible (colonnes nullables) ; date de mise en production à tracer pour le message « Non disponible ».
- **ADR requise :** ☑ oui → `docs/adr/0002-versions-timesheet.md`.

## 11. Plan de recette
| # | Rôle | Scénario | Attendu | AC |
|---|---|---|---|---|
| R1 | Professeur | Soumettre le mois | Soumise v1 visible côté direction | AC-1 |
| R2 | Directeur | Ajuster une ligne, lisser, valider le mois, ouvrir les 4 vues | Écarts et motifs exacts | AC-3, AC-4 |
| R3 | Professeur | Comparer puis signer | Signature sur la validée | AC-5 |
| R4 | Directeur | Remise en brouillon puis resoumission | Soumise v2, v1 consultable | AC-2 |
| R5 | Directeur | Ouvrir un mois antérieur à TS-04 | « Non disponible avec TS-04 » | AC-7 |

**Jeu de données :** mois sans ajustement, mois ajusté + lissé, mois remis en brouillon, mois antérieur sans version.

## 12. Questions ouvertes
| # | Question | Propriétaire | Avant | Réponse |
|---|---|---|---|---|
| Résolue (Q1) | Départ des 24 mois | PO | — | Fin du mois concerné (2026-10-27) |
| Résolue (Q2) | Après 24 mois | PO | — | Le détail des lignes est effacé ; totaux et empreinte conservés (2026-10-27) |
| Résolue (Q3) | PDF et signature : durée de conservation | PO | — | 84 mois (7 ans), confirmé par le PO le 2026-10-27 |
| Résolue (Q4) | Date de mise en production | PO | — | 2026-10-27 (soir) : toute version absente pour un mois soumis avant cette date affiche « Non disponible avec TS-04 » |
| Résolue | Reconstitution des mois existants | PO | — | Non : « Non disponible avec TS-04 » (2026-10-27) |
| Résolue | Moment de la validée | PO | — | Validation du mois entier, professeur par professeur (2026-10-27) |
| Résolue | Conservation | PO | — | 24 mois (2026-10-27) |
| Résolue | PDF à 84 mois | PO | — | Durée minimale, sans purge automatique (2026-10-27) |
| Résolue | Revue d'architecture B1 à B3 | PO | — | `validateEntry` supprimé et lissage unifié ; validée v+1 sur toute mutation ; garde conditionnelle pour les mois déjà validés ; tranches T1a/T1b/T1c (2026-10-27) |
| Q5 | Écart avec `docs/AUDIT_RGPD.md` : IP et user-agent des signatures conservés 7 ans au lieu de 12 à 24 mois | PO / RGPD | T3 | non bloquante ; à trancher avant la rétention |

## 13. Validation (Definition of Ready)
| Rôle | Nom | Date | ☐ Validé | Réserves |
|---|---|---|---|---|
| Analyste fonctionnel | | | ☐ | |
| UX/UI | Sub-agent UX Expert | 2026-10-27 | ☑ | Workflow §8 |
| Architecte | Sub-agent architecte | 2026-10-27 | ☑ avec réserves | ADR-0002 révisée (« Accepté avec réserves ») ; réserves à lever par tranche |
| Directeur / Sponsor métier | PO | 2026-10-27 | ☑ | Maquette, règles, rétention et date de mise en production validées |

**DoR :** ☑ Workflow UX (sub-agent) + mock-ups validés ☑ §1–7 complets ☑ Matrice des permissions ☑ Carte des statuts ☑ 4 états d'écran ☑ AC testables ☑ Impact données ☑ Questions bloquantes résolues (Q1 à Q4)

> Statut « Validé (DoR) » prononcé par le PO le 2026-10-27. L'ADR-0002 a été relue par l'architecte (verdict initial « À revoir », trois points bloquants traités par le PO le 2026-10-27), puis révisée.
