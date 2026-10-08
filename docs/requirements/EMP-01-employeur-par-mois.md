# Canvas de Requirements — EMP-01 Employeur par mois (ASBL / L-IT Solutions)

| | |
|---|---|
| **ID / Titre** | EMP-01 — Définir, mois par mois, l'employeur d'un animateur (ASBL Code IT Bryan ou L-IT Solutions) |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR, product owner) ☐ En dev ☑ En recette (T1, T2, T3 livrées) ☐ Livré |
| **Analyste** | Sub-agent UX Expert + analyste | **UX/UI** | Sub-agent UX Expert |
| **Architecte** | À désigner | **Dev back / front** | À désigner |
| **Date / Version** | 2026-10-08 — v0.2 (implémentation T1–T3) | **Sprint cible** | À planifier |
| **Liens** | Maquette : `docs/mockups/EMP-01/index.html` · API : `docs/API_EMP01.md` · `docs/AUDIT_RGPD.md` §0, §3.8, reco 13 et 20bis · TS-01-T5 (PDF) · TS-02 (verrous) · SIG-01 (signature) |

---

## 1. Contexte et problème
- **Situation actuelle :** rien dans le modèle n'indique qui emploie l'animateur. `professeurs.type_contrat` (salarie|freelance|prestataire) n'est que la nature du contrat. La fiche de défraiement PDF (`TimesheetPdfService`, pied de page) et le payload de signature (`SignatureNumeriqueService`, champ `emetteur`) lisent `config/logiscool.php > association` : l'ASBL est l'entité unique, codée en configuration.
- **Douleur / coût :** un animateur peut être sous contrat ASBL un mois et L-IT Solutions le suivant. Aujourd'hui toutes les fiches portent le nom, le RPM et le compte de l'ASBL, ce qui est faux pour les mois L-IT ; le responsable de traitement des heures et le destinataire des demandes de droits ne sont pas identifiables (AUDIT_RGPD §0, reco 20bis).
- **Déclencheur :** audit RGPD (employeur par mois = base de la qualification des responsables de traitement) et besoin comptable d'émettre des fiches au bon nom.

## 2. Objectifs et indicateurs de succès
| Objectif métier | Indicateur | Cible | Mesure |
|---|---|---|---|
| Fiche PDF au nom de la bonne entité | % de fiches avec entité conforme au mois | 100 % | Recette R3 |
| Saisie rapide de l'employeur du mois | Clics pour fixer 12 animateurs sur un mois | ≤ 4 clics (bulk) | Recette chronométrée R2 |
| Traçabilité des changements | Changements journalisés avec motif | 100 % | Test audit |
| Reprise des données existantes | Mois historiques sans employeur | 0 | Test migration |

**Hors périmètre :** durées de conservation par employeur (politique unique, reco 13) ; rôle « paie ASBL » distinct (abandonné, RGPD §0) ; mise à jour du `type_contrat` ; employeur par ligne/séance ; contrats juridiques et notice d'information (livrables RGPD distincts) ; facturation entre L-IT et l'ASBL.

## 3. Acteurs, rôles et permissions
**Personas :** Directeur (staff) et Admin, poste de travail, une fois par mois (début de période de paie). Professeur : consultation seulement.

| Ressource / Action | Admin | Staff (directeur) | Professeur | Élève |
|---|---|---|---|---|
| Employeur d'un mois : voir | L | L | L (ses propres mois, nom de l'entité seulement) | — |
| Employeur d'un mois : définir / modifier (mois non verrouillé) | M | M | — | — |
| Modifier un mois verrouillé (PDF généré / signé) | M via déverrouillage (motif) | — | — | — |
| Historique des changements | L | L | — | — |
| Entités employeurs : voir | L | L | — | — |
| Entités employeurs : créer / modifier (nom, RPM, IBAN, adresse) | C M | — | — | — |
| IBAN de l'entité en clair | L | L | — | — |

> Alimente : `EmployeurMoisPolicy`, `can.*` dans les Resources, tests 403 (professeur, anonyme).

## 4. Glossaire métier
| Terme affiché | Définition | Nom technique |
|---|---|---|
| Employeur | Entité (ASBL ou L-IT Solutions) qui emploie et paie l'animateur pour un mois donné | `employeurs` (table), `employeur_id` |
| Employeur du mois | Employeur applicable à un animateur pour un mois civil | `professeur_employeurs_mois` |
| Par défaut | Employeur utilisé quand rien n'est défini et qu'aucun mois antérieur n'existe (ASBL) | `employeurs.par_defaut = true` |
| Hérité | Valeur reprise du mois précédent faute de définition explicite | `source = herite` (calculé, non stocké) |
| Figé | Employeur copié sur la fiche PDF et la signature au moment de leur création | `timesheet_pdfs.employeur_snapshot` |
| Nature du contrat | salarié / freelance / prestataire, sans lien avec l'employeur | `professeurs.type_contrat` |

## 5. Parcours utilisateurs

### 5.1 User stories
| ID | En tant que | Je veux | Afin de | MoSCoW |
|---|---|---|---|---|
| US-1 | directeur | voir l'employeur de chaque animateur dans la vue mensuelle | savoir sous quelle entité sera émise chaque fiche | Must |
| US-2 | directeur | appliquer ASBL ou L-IT à plusieurs animateurs pour un mois | ne pas éditer animateur par animateur | Must |
| US-3 | directeur | éditer l'employeur d'un mois depuis la fiche d'un animateur (frise des mois) | corriger un cas isolé et voir l'historique | Must |
| US-4 | directeur | reprendre le mois précédent en un clic | traiter le cas courant (pas de changement) | Must |
| US-5 | directeur | voir pourquoi un mois est verrouillé | comprendre quoi faire (déverrouillage admin) | Must |
| US-6 | admin | gérer les entités (nom, RPM, IBAN, adresse) | ne plus dépendre du `.env` | Must |
| US-7 | directeur | prévisualiser l'entité qui figurera sur la fiche | éviter une fiche au mauvais nom | Should |
| US-8 | professeur | voir quelle entité est mon employeur pour chaque mois | savoir à qui adresser mes demandes | Should |
| US-9 | admin | consulter le journal des changements avec motif | audit RGPD / comptable | Must |

### 5.2 Parcours
Intégration aux trajets existants : pas de nouvelle entrée de menu principale.
1. **Vue mensuelle direction** (`AdminTimesheetsPage`, synthèse du mois) : nouvelle colonne « Employeur » (badge ASBL / L-IT, mention « hérité » ou cadenas). Cases à cocher + barre d'actions groupées (déjà utilisée pour la validation en lot / PDF en lot) : « Définir l'employeur… » ouvre une modale (choix de l'entité, motif facultatif si le mois n'avait pas de valeur explicite, obligatoire sinon), récapitulatif des animateurs touchés / ignorés (verrouillés). Bouton « Reprendre le mois précédent » dans la même modale.
2. **Fiche animateur** (`AdminProfesseurDetail`, détail mois `TimesheetDetailMoisController`) : bloc « Employeur » dans l'en-tête du mois (même place que les boutons TS-02) avec « Modifier ». Onglet / section « Employeur par mois » : frise des 12 mois de l'année (puces ASBL / L-IT / hérité / verrouillé) ; clic sur un mois ouvre la modale ; sélection de plusieurs mois pour appliquer en une fois ; historique des changements.
3. **Génération PDF** : l'aperçu et le PDF affichent l'entité du mois ; la modale de génération rappelle « Fiche émise au nom de : … ». L'employeur est figé sur la fiche.
4. **Professeur** : « Encoder mon mois » et « Ma confirmation » affichent « Employeur ce mois : … » en lecture seule.
5. **Alternatives :** mois verrouillé (message + lien déverrouillage admin) ; animateur sans heures ce mois (modifiable quand même, utile pour planifier) ; conflit concurrent (409 « modifié par X à HH:MM, recharger ») ; liste vide / chargement / erreur réseau (voir §8).

### 5.3 Critères d'acceptation
| ID | Étant donné | Quand | Alors | Test |
|---|---|---|---|---|
| AC-1 | un mois sans ligne dans `professeur_employeurs_mois` et un mois antérieur défini L-IT | on lit l'employeur du mois | résultat L-IT, `source = herite` | Feature |
| AC-2 | aucun mois antérieur défini | on lit l'employeur | entité par défaut (ASBL), `source = defaut` | Feature |
| AC-3 | un directeur, un mois non verrouillé | `PUT` employeur L-IT avec motif | ligne créée/mise à jour, entrée d'audit (avant, après, motif, auteur) | Feature |
| AC-4 | un mois avec PDF généré | `PUT` par directeur ou admin | 422 « mois verrouillé », rien n'est modifié | Feature |
| AC-5 | un mois signé sans PDF | `PUT` par directeur | 422 verrouillé ; par admin avec motif : autorisé et signature marquée « à re-signer » | Feature |
| AC-6 | 5 animateurs dont 1 verrouillé | bulk L-IT | 4 appliqués, 1 listé dans `ignores` avec raison, réponse 200 | Feature |
| AC-7 | un professeur | `PUT` employeur | 403 ; `GET` de ses propres mois autorisé, de ceux d'un autre 403/404 | Feature |
| AC-8 | un mois L-IT | génération du PDF | pied de page avec nom, RPM, compte, adresse de L-IT ; snapshot enregistré | Feature + test contenu PDF |
| AC-9 | un PDF généré, puis changement d'entité (config) | téléchargement | le PDF stocké est inchangé (stocké en fichier) ; snapshot inchangé | Feature |
| AC-10 | données historiques | migration | chaque mois ayant timesheets/PDF/signature a une ligne ASBL, idempotent | Migration |
| AC-11 | directeur sur la vue mensuelle | sélectionne 3 animateurs, applique L-IT, motif | badges mis à jour, toast de confirmation, 4 clics | Front + Recette R2 |
| AC-12 | un mois futur | `PUT` | accepté (jusqu'à 12 mois à l'avance) | Feature |
| AC-13 | admin | crée une entité avec IBAN invalide | 422 par champ | Feature |

## 6. Règles métier et cycle de vie
- **RG-1 (un employeur par animateur et par mois civil).** Choix tranché pour le cas « à cheval » : le mois de paie est l'unité ; un animateur ne peut avoir qu'un employeur par mois. Si le contrat change en cours de mois, la direction choisit l'employeur qui paie ce mois-là ; le prorata se règle par ajustement dans le mois suivant (même logique que les corrections TS-02). Voir Q1.
- **RG-2 (résolution).** Employeur effectif du mois M = ligne explicite de M, sinon valeur effective du mois précédent (remontée jusqu'à la première ligne), sinon entité `par_defaut` (ASBL). La reprise est calculée (jamais écrite) tant que la direction n'a pas confirmé.
- **RG-3 (matérialisation).** L'employeur est écrit en base (ligne explicite, `source = fige`) à la première de ces occasions : édition manuelle, génération d'un PDF, signature du professeur. Un mois « hérité » devient donc explicite avant tout acte à valeur légale.
- **RG-4 (mois passés existants).** La migration crée une ligne ASBL pour chaque couple (professeur, mois) ayant au moins une timesheet, un PDF ou une signature. Les mois sans activité restent non définis (résolus par RG-2).
- **RG-5 (reprise du mois précédent).** Action explicite « Reprendre le mois précédent » (bulk et par animateur) : écrit la valeur effective du mois précédent pour le mois visé.
- **RG-6 (qui).** Admin et directeur (`isStaff()`). Jamais le professeur, même sur son propre mois.
- **RG-7 (verrou).** Un mois est verrouillé dès qu'un PDF existe (`timesheet_pdfs`) ou qu'une signature `valide` existe (`timesheet_signatures`). Staff : modification refusée. Admin : uniquement via le déverrouillage existant (`POST /professeurs/{id}/timesheets-mois/deverrouiller`) ; pour un mois signé sans PDF, l'admin peut modifier avec motif, ce qui invalide la signature (re-signature requise, via `SignatureNumeriqueService::aResigner`).
- **RG-8 (impact sur PDF existants).** Un PDF déjà généré n'est jamais régénéré ni modifié (fichier stocké). Son employeur est figé (`employeur_snapshot`). Si l'admin déverrouille puis change l'employeur, la nouvelle version du PDF porte la nouvelle entité ; l'ancienne version reste archivée avec son snapshot.
- **RG-9 (audit).** Chaque création/modification/suppression écrit une entrée `employeur_mois_audits` : professeur, mois, employeur avant/après, motif, auteur, date. Motif obligatoire pour toute modification d'une valeur explicite et pour tout mois antérieur au mois courant ; facultatif pour une première définition d'un mois courant ou futur.
- **RG-10 (lecture seule professeur).** Le professeur voit le nom de l'entité de ses mois, jamais l'IBAN de l'entité ni l'historique.
- **RG-11 (mois futurs).** Définissables jusqu'à 12 mois à l'avance, sans effet avant le mois concerné.
- **RG-12 (entités).** Deux entités amorcées (ASBL, L-IT Solutions). Une seule `par_defaut`. Une entité utilisée par au moins un mois ne peut pas être supprimée, seulement désactivée (`actif=false`, plus sélectionnable). Changer les coordonnées d'une entité n'affecte pas les PDF déjà générés (snapshot) mais affecte les aperçus et les futures générations.
- **RG-13 (conservation).** Aucune règle de durée dépendante de l'employeur (politique unique, reco 13).
- **RG-14 (nature du contrat).** `type_contrat` inchangé et indépendant.
- **RG-15 (concurrence).** Écriture avec `updated_at`/version : 409 si modifié entre-temps.

**Carte des statuts (employeur du mois)**
| Statut affiché (`source`) | Libellé | Badge | Modifiable par | Transitions → | Déclencheur |
|---|---|---|---|---|---|
| `defaut` | ASBL (par défaut) | gris | Staff | explicite | Définition manuelle |
| `herite` | Hérité du mois précédent | gris pointillé | Staff | explicite | Définition / reprise / génération PDF / signature |
| `explicite` | ASBL ou L-IT | bleu (ASBL) / violet (L-IT) | Staff | explicite (autre valeur), verrouillé | Modification avec motif |
| `verrouille` | cadenas + libellé | idem + cadenas | Admin seulement (RG-7) | explicite (après déverrouillage) | PDF généré ou signature |

## 7. Données et migration
**Modèle proposé** (colonne sur la période de paie écartée : il n'existe pas de table « période de paie », la période est implicite = (professeur, annee, mois)).

`employeurs`
| Champ | Type | Oblig. | Contraintes | Exemple |
|---|---|---|---|---|
| id | bigint | oui | PK | 1 |
| code | string(20) | oui | unique, snake | `asbl`, `lit_solutions` |
| nom | string | oui | | Code IT Bryan ! asbl |
| rpm | string | oui | format BE + 10 chiffres | BE0770.479.710 |
| compte_bancaire | string (chiffré) | oui | IBAN valide | BE02 1431 1606 4140 |
| adresse | string | oui | | Rampe Sainte Waudru 8 – 7000 MONS |
| par_defaut | boolean | oui | une seule à true | true |
| actif | boolean | oui | défaut true | true |
| couleur_badge | string(10) | non | | `bleu` |

`professeur_employeurs_mois`
| Champ | Type | Oblig. | Contraintes | Exemple |
|---|---|---|---|---|
| id | bigint | oui | PK | |
| professeur_id | FK | oui | cascade | 12 |
| annee, mois | smallint, tinyint | oui | mois 1-12 ; unique (professeur_id, annee, mois) | 2026, 10 |
| employeur_id | FK employeurs | oui | restrict | 2 |
| source | enum | oui | `explicite` \| `migration` \| `fige` | explicite |
| updated_by | FK users | non | | |
| timestamps | | | | |

`employeur_mois_audits` : id, professeur_id, annee, mois, employeur_avant_id (nullable), employeur_apres_id, motif (nullable), user_id, created_at. Sans PII dans les logs applicatifs (reco 10).

Colonnes ajoutées : `timesheet_pdfs.employeur_id` + `employeur_snapshot` (json : nom, rpm, compte, adresse) ; payload de signature `emetteur` = nom de l'employeur du mois (le sceau couvre le payload).

- **Données existantes impactées :** toutes les fiches/signatures/timesheets historiques.
- **Backfill :** seeder des deux entités depuis `config('logiscool.association')` (ASBL) et L-IT Solutions (coordonnées à fournir, Q3) ; lignes ASBL (`source = migration`) pour chaque (professeur, mois) ayant timesheet, PDF ou signature ; `timesheet_pdfs` existants : `employeur_id` = ASBL et snapshot depuis la config actuelle. Idempotent (`insertOrIgnore` sur l'unique), réversible (`down()` supprime tables et colonnes), sauvegarde DB avant déploiement.
- **Rétention / RGPD :** politique unique ; l'audit suit la durée des timesheets du professeur ; anonymisé avec lui (art. 17) ; l'IBAN de l'entité n'est pas une donnée personnelle (personne morale), mais reste masqué hors écrans admin/directeur.
- **Seeders / factories :** `EmployeurFactory`, `ProfesseurEmployeurMoisFactory`, seeder des deux entités.
- `config/logiscool.php > association` devient valeur d'amorçage uniquement, plus lue à l'exécution.

## 8. Exigences UX/UI
> 🚦 Workflow UX : sections 5.2 ci-dessus · Mock-ups : `docs/mockups/EMP-01/index.html` · Validé par : product owner ☑

| Écran | Contenu clé | Actions primaires | Composants réutilisés | Maquette |
|---|---|---|---|---|
| Synthèse mensuelle (direction) | colonne Employeur, filtre par employeur, sélection multiple | « Définir l'employeur… » en lot | tableau + barre d'actions groupées existants, `AdminModal` | A, B |
| Fiche animateur > détail mois | bloc Employeur, frise 12 mois, historique | Modifier, Reprendre le mois précédent | `AdminProfesseurDetail`, `AdminModal` | C |
| Mois verrouillé | cadenas, raison, lien déverrouillage (admin) | — | bandeau d'alerte TS-02 | D |
| Aperçu / génération PDF | entité qui figurera au pied de la fiche | Générer | modale PDF existante | E |
| Entités employeurs (admin) | liste, formulaire nom/RPM/IBAN/adresse | Créer, modifier, désactiver | page paramètres (TS-00) | F |
| Professeur | ligne « Employeur ce mois : … » | — | bandeau « Encoder mon mois » | C |

| Écran | Chargement | Vide | Erreur | Succès |
|---|---|---|---|---|
| Colonne / frise | squelette de puces | « Aucun mois défini : ASBL appliquée par défaut » + Définir | « Impossible de charger les employeurs. Réessayer » | toast « Employeur mis à jour pour N animateur(s) » |
| Modale lot | bouton désactivé + spinner | — | liste des ignorés avec raison | récapitulatif appliqués / ignorés |

**Textes exacts :** « Définir l'employeur du mois », « Reprendre le mois précédent », « Motif (obligatoire pour une modification) », « Ce mois est verrouillé : une fiche PDF a été générée. Un administrateur peut le déverrouiller. », « Ce mois est signé : l'employeur ne peut plus être modifié par la direction. », « Modifier l'employeur invalidera la signature : l'animateur devra signer à nouveau. », « 4 animateur(s) mis à jour, 1 ignoré (mois verrouillé). ». Conséquence énoncée dans toute confirmation (fiche émise au nom de X, responsable de traitement du mois).
**Appareils :** desktop prioritaire (direction), tablette OK ; professeur : mobile (affichage seul). **Accessibilité :** badges avec texte (pas seulement couleur), cadenas avec `aria-label`, `aria-live` sur les toasts, modale piégée au clavier.
**Charge cognitive :** nominal bulk ≤ 4 clics (cocher, Définir, choisir l'entité, Appliquer) ; pré-rempli : entité du mois précédent.

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Sécurité | Policies backend ; IBAN entité réservé admin/directeur et chiffré ; aucune confiance dans le front ; 403 testés |
| Performance | Synthèse du mois : une seule requête pour tous les employeurs (pas de N+1) ; ≤ 200 animateurs par lot |
| Traçabilité | `employeur_mois_audits` visible par staff ; accès à l'IBAN journalisé (reco existante) |
| Notifications | Aucun email au professeur (affichage lecture seule) ; option Q6 |
| Compatibilité | FR, Europe/Brussels, mois civil |

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB | 3 tables + 2 colonnes sur `timesheet_pdfs`, backfill idempotent | M |
| Back | modèles `Employeur`, `ProfesseurEmployeurMois`, `EmployeurMoisAudit` ; `EmployeurMoisService` (résolution RG-2, verrous RG-7, écriture, bulk) ; `EmployeurMoisPolicy` ; FormRequests ; `TimesheetPdfService` (pied de page + snapshot) ; `SignatureNumeriqueService` (`emetteur`) ; `TimesheetSyntheseMoisService` (champ `employeur`) ; `TimesheetDetailMoisController` (bloc employeur, `can`) | L |
| API | voir ci-dessous | M |
| Front | colonne + modale lot (synthèse), frise + historique (fiche animateur), bandeau professeur, page entités ; tout via `api/client.js` ; libellés depuis le backend (pas d'enum en dur) | L |
| Tests | voir ci-dessous | M |
| Docs / ADR | `docs/API_EMP01.md` ; ADR recommandé : « employeur résolu par mois et figé sur l'acte » | S |

**Endpoints**
- `GET /api/employeurs` (staff) : liste. `POST /api/employeurs`, `PUT /api/employeurs/{id}` (admin) : gestion des entités.
- `GET /api/professeurs/{id}/employeurs-mois?annee=YYYY` (staff ; professeur : son propre id, champs restreints) : 12 mois `{mois, employeur, source, verrouille, raison_verrou}`.
- `PUT /api/professeurs/{id}/employeurs-mois/{annee}/{mois}` (staff) `{employeur_id, motif?, version}` : 200 / 403 / 409 / 422.
- `POST /api/employeurs-mois/lot` (staff) `{annee, mois, professeur_ids[], employeur_id | reprendre_precedent: true, motif?}` : `{appliques[], ignores[{professeur_id, raison}]}`.
- `GET /api/professeurs/{id}/employeurs-mois/historique` (staff).
- `GET /api/timesheets/mois-synthese` : ajoute `employeur` par ligne. `GET /api/timesheets/ma-confirmation` et `mon-mois` : ajoute `employeur` (nom seulement).
- Erreurs : 422 « Mois verrouillé » (`RegleMetierException`), 409 conflit, 403 rôle.

**Tranches verticales :** T1 = entités + résolution + vue mensuelle (lecture + édition par animateur) + PDF/signature/snapshot + migration (usage réel minimal : fiches au bon nom) ; T2 = édition en lot + reprise du mois précédent + frise sur fiche animateur + historique ; T3 = page admin des entités + vue professeur. Chaque tranche livre DB → API → UI → tests.
**Risques :** PDF et signatures déjà émis (backfill snapshot) ; mois signé sans PDF (Q2) ; coordonnées L-IT inconnues (Q3).
**Déploiement / retour arrière :** migration puis seeder ; `down()` supprime ; sauvegarde préalable. **ADR requis :** ☐ non ☑ oui (recommandé).

## 11. Plan de recette
| # | Rôle | Scénario | Résultat attendu | AC | OK/KO |
|---|---|---|---|---|---|
| R1 | Directeur | Ouvrir la synthèse d'octobre | colonne Employeur, tout ASBL (migration) | AC-10 | ☐ |
| R2 | Directeur | Cocher 3 animateurs, L-IT, motif, Appliquer | badges L-IT, toast, audit | AC-3, 6, 11 | ☐ |
| R3 | Directeur | Générer le PDF d'un animateur L-IT | pied de page L-IT, snapshot | AC-8 | ☐ |
| R4 | Directeur | Modifier un mois avec PDF | refus avec message | AC-4 | ☐ |
| R5 | Admin | Déverrouiller puis modifier | nouvelle version PDF à la nouvelle entité | AC-5, RG-8 | ☐ |
| R6 | Professeur | Ouvrir « Encoder mon mois » | voit l'employeur, aucun contrôle d'édition | AC-7 | ☐ |
| R7 | Directeur | Mois suivant non défini | « hérité », puis confirmation | AC-1 | ☐ |

**Jeu de données :** animateur ASBL puis L-IT (oct. → nov.), animateur verrouillé (PDF), animateur signé sans PDF, animateur sans heures, mois futur.

## 12. Questions ouvertes
| # | Question | Proprio | Avant | Réponse |
|---|---|---|---|---|
| Q1 | Animateur à cheval sur deux employeurs dans un même mois : confirmer « un seul employeur par mois, prorata en ajustement le mois suivant » ? (alternative : employeur par ligne, hors périmètre, coûteux) | Directeur / comptable | DoR | Retenu (PO) : un seul employeur par animateur et par mois ; prorata en ajustement le mois suivant. |
| Q2 | Mois signé sans PDF : verrouillé pour la direction (retenu) ou modifiable avec re-signature ? | Directeur | DoR | Retenu (PO) : mois signé sans PDF verrouillé pour la direction ; l'admin peut modifier avec motif, la signature devient « à re-signer » (l'employeur entre dans l'empreinte signée). |
| Q3 | Nom, RPM, IBAN, adresse exacts de L-IT Solutions ; la fiche PDF L-IT a-t-elle le même modèle (logo, texte « Volontariat ») ? | Sponsor | dev | Inconnu à ce jour. L-IT Solutions est amorcée avec le nom « L-IT Solutions » et des valeurs de remplacement « À COMPLÉTER » (RPM, adresse) et IBAN vide ; à renseigner via `LIT_*` (env) ou la page Entités (admin). Aucune fiche PDF ne peut être générée tant que les coordonnées de l'employeur du mois sont incomplètes. Modèle de fiche : identique (non confirmé). |
| Q4 | Valeur par défaut d'un mois sans définition : ASBL (retenu) ; l'héritage du mois précédent est-il souhaité ou préfère-t-on un mois « non défini » bloquant la génération ? | Directeur | DoR | Retenu (PO) : héritage du mois précédent, puis ASBL par défaut ; l'employeur est figé à la génération du PDF ou à la signature. |
| Q5 | Les contrats existants doivent-ils être revus avec le comptable pour confirmer que tout l'historique = ASBL ? | Comptable | migration | Retenu (PO) : tout l'historique existant = ASBL (backfill `source = migration`). Revue comptable à prévoir hors dev. |
| Q6 | Notifier le professeur lors d'un changement d'employeur d'un mois à venir ? | Directeur / DPO | recette | Retenu (PO) : aucune notification au professeur. |
| Q7 | Le professeur doit-il voir l'entité avant la signature (mention légale sur l'écran de signature) ? | DPO | dev | Retenu (PO) : aucun affichage sur l'écran de signature (l'émetteur figure dans le payload scellé et la fiche). |

## 13. Validation (Definition of Ready)
| Rôle | Nom | Date | ☐ Validé | Réserves |
|---|---|---|---|---|
| Analyste fonctionnel | | | ☐ | |
| UX/UI | | | ☐ | |
| Architecte | | | ☐ | |
| Dev back | | | ☐ | |
| Dev front | | | ☐ | |
| Directeur / Sponsor | | | ☐ | |

**DoR :** ☑ mock-ups validés ☑ §1–7 complets ☑ Q1–Q4 tranchées ☑ matrice des permissions ☑ AC testables ☑ impact données

## Tests à écrire
- Back (`backend/tests/Feature/EmployeurMoisTest.php`, base `lgit_test` via `scripts/test-backend.sh`) : résolution (défaut, hérité, explicite, chaîne), droits (admin/directeur/professeur/anonyme), verrous (PDF, signature, admin), bulk avec ignorés, audit + motif obligatoire, 409, mois futur/hors plage, entités (IBAN/RPM invalides, désactivation, unicité `par_defaut`), PDF (contenu du pied de page par entité, snapshot, version après déverrouillage), signature (`emetteur`, `aResigner`), synthèse (champ `employeur`, pas de N+1), migration/backfill idempotent.
- Front : `npm run lint && npm run build` ; tests composants (modale lot, frise, états verrouillés).

## 14. Écarts et choix d'implémentation (v0.2)
- **Verrou PDF** : un mois est verrouillé par PDF tant que ses saisies sont au statut « généré » ; le déverrouillage admin existant les repasse en « confirmé » et rouvre l'employeur (RG-8), sans effacer les PDF ni leur snapshot.
- **Empreinte signée** : l'employeur n'entre dans le contenu signé que s'il n'est pas l'ASBL (les signatures antérieures à EMP-01, toutes ASBL, restent valides). L'`emetteur` du payload scellé est le nom de l'employeur du mois.
- **Verrou optimiste** : colonne `version` entière (0 = aucune ligne explicite) plutôt que `updated_at`.
- **Snapshot PDF** : `timesheet_pdfs.employeur_snapshot` est un texte JSON chiffré (cast `encrypted:array`), IBAN de l'entité compris.
- **Génération bloquée** si l'employeur du mois a des coordonnées incomplètes (« À COMPLÉTER » ou IBAN absent/invalide) : l'aperçu reste possible.
- **Lot** : motif exigé pour l'ensemble si au moins un animateur appliqué remplace une valeur explicite, ou si le mois est passé ou signé ; les mois verrouillés sont ignorés avec raison.
- **Tests front** : le dépôt n'a pas de Vitest (standards §5.6) ; couverture front = lint + build ; recette R1–R7 à dérouler.
