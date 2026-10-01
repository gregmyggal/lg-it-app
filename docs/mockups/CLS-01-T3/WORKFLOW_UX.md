# CLS-01 / T3 — Workflow UX : heures (timesheets) rattachées aux sessions

| | |
|---|---|
| **Auteur** | UX Expert (sub-agent) |
| **Source de vérité métier** | `docs/requirements/CLS-01-modele-classes.md` : RG-4, RG-5, RG-9, AC-3, AC-14, AC-25, tranche T3 (§10) ; décisions de la direction **Q19 à Q22** (voir §0) |
| **Mock-ups** | `index.html` (sommaire) + `01` à `03` dans ce dossier, HTML statiques autonomes, sélecteur d'états en haut de chaque page |
| **Statut** | **Révision 2, à valider avant tout développement** : intègre Q19 (encodage libre normal), Q20 (encodage mensuel), Q21 (validation avec lissage), Q22 (euros visibles du professeur). Aucune case de validation n'est remplie (§10) |
| **Périmètre** | Aucun code applicatif modifié. Seuls les fichiers de `docs/mockups/CLS-01-T3/` sont créés |

> Données fictives : classe **« Scratch Junior — mercredi 14h–17h »**, 2026-2027, période 1, salle 3. Alice Dupont (principale), Bob Leroy (co-enseignant), Carol Simon (remplaçante, séance 5). Date simulée : **jeudi 03/12/2026**. Séances : 1 = 07/10, 2 = 14/10, 3 = 21/10, 4 = 18/11, 5 = 25/11 (Carol remplace Alice), 6 = 02/12 **annulée**, **6 bis** = 16/12, 7 = 06/01/2027 (vacances d'automne et férié du 11/11 sautés). Durée d'une séance : 3 h. **Montants illustratifs** : animation 12,00 € / h, préparation 10,00 € / h, plafond journalier existant 44,02 €.

## 0. Principes (décisions de la direction)

1. **Chaque professeur a toujours sa propre timesheet**, jamais partagée, même en co-enseignement (RG-5, AC-3).
2. Le **remplacement n'a aucune contrainte de timesheet** (RG-9, AC-25) : les heures déjà encodées par le remplacé ne bougent pas, le remplaçant encode les siennes.
3. Le **rôle** (principale / co-enseignant / remplaçante) est **indicatif** : il s'affiche, sans effet sur les heures, les droits d'encodage ni la rémunération. Aucun tarif n'est montré au professeur pour les **classes, sessions et calendrier**.
4. **Q22 : le professeur voit les montants en euros de ses propres heures** (jamais ceux d'un autre professeur), dans ses timesheets.
5. **Q19 : l'encodage libre est une fonction normale.** Des heures peuvent être encodées sans cours ni session (ex. une préparation faite un autre jour). Champs : type d'activité (préparation / animation), date, durée, cours **facultatif**, séance **facultative** (recommandée quand les heures concernent une séance), commentaire **facultatif**. Ni motif obligatoire, ni libellé d'exception.
6. **Q20 : le parcours principal du professeur est mensuel** (« Encoder mon mois »). L'encodage séance par séance depuis « Mes classes » reste possible comme **raccourci**. On ne peut encoder qu'à partir du **début de la séance** : l'écran mensuel ne propose que les sessions commencées.
7. **Q21 : la validation (admin ou directeur) propose le lissage dans le même flux**, y compris en lot : aucune saisie n'est exclue du lot parce qu'elle est à lisser.
8. Le workflow existant est conservé : **brouillon → soumis → confirmé → généré**, lissage, signature mensuelle, PDF.

---

## 1. Cartographie « aujourd'hui → demain »

> Établie d'après `App.jsx`, `PortalLayout.jsx`, `TimesheetsPage.jsx`, `AdminTimesheetsPage.jsx`, `TimesheetLissingModal.jsx`, `TimesheetController.php` et la maquette validée 06. On **étend** les écrans existants, aucun parcours parallèle.

### 1.1 Menu

| Aujourd'hui | Demain | Rôles |
|---|---|---|
| « Mes classes » | inchangé ; chaque session affiche le statut de mes heures (raccourci d'encodage) | Professeur |
| « Timesheets » | inchangé dans le menu ; la page devient **« Encoder mon mois »** | Professeur |
| « Gestion opérationnelle › Timesheets » | inchangé ; sélecteur de vue **Par professeur / Par classe et séance** | Directeur, Admin |

**Aucune nouvelle entrée de menu.** Le professeur garde 2 entrées.

### 1.2 Écrans

| Écran existant | Ce qui change | Route | Mock-up |
|---|---|---|---|
| `TimesheetsPage` | Devient l'**écran mensuel** : navigation mois précédent/suivant ; **1. Mes sessions du mois** (préremplies, durée modifiable, état) ; **2. Mes autres heures** (encodage libre) ; **3. Total du mois** (heures, montant, jours) ; **Soumettre le mois** ; « Confirmer et signer ce mois » et PDF **inchangés**. Le tableau plat et `NewTimesheetForm` (date + heures) y sont absorbés. | `/timesheets` (+ `?mois=2026-11`) | 02 |
| `TimesheetMontantDisplay` | **Conservé** : le montant du mois se lit dans « Total du mois » (heures, euros, jours encodés, dépassements) | — | 02 |
| `MesClasseSessionsPage` (`mes-classes/:id`) | Statut d'encodage par session et **feuille d'encodage** préremplie : **raccourci** (2 clics) ; lien « Encoder tout mon mois » vers l'écran principal | `/mes-classes/:id` | 01 |
| `MesClassesPage` | Le bloc « À encoder » de 06 ouvre la même feuille (raccourci) | `/mes-classes` | 06 (à mettre à jour) |
| `AdminTimesheetsPage` | Sélecteur de vue ; vue **Par classe et séance** : sessions passées sans heures, grille séances × professeurs, **validation en lot avec lissage dans le même flux**. Vue Par professeur, montants, PDF **inchangés** (colonne Classe · Séance ajoutée au détail). | `/admin/timesheets` (+ `?vue=classe&classe={id}`) | 03 |
| `TimesheetLissingModal` | Son contenu (calcul `propose-lissage`, `apply-lissage`) est **repris dans la modale de validation** : aperçu avant/après, montant à déplacer, date cible | — | 03 |

### 1.3 Contrat d'API attendu (pour l'architecte, indicatif)

- `GET /timesheets/mon-mois?annee=&mois=` : sessions **commencées** du mois de l'utilisateur avec `encodage` calculé (`a_encoder | brouillon | soumis | confirme | genere`), `mes_timesheets`, durée par défaut, `remplace_par` ; plus les saisies libres du mois ; plus la synthèse (heures, montant, jours, dépassements).
- `GET /mes-classes/{classe}/sessions` : mêmes champs `encodage` et `mes_timesheets` par session.
- `POST /timesheets` : `course_session_id` **facultatif** (si présent : `professeur_id`, `date_prestation`, `cours_id` déduits, droit vérifié, unicité) ; sans session : `date_prestation`, `type_activite`, `nombre_heures`, `cours_id` facultatif, `commentaire` facultatif. `422` doublon (professeur, session, type).
- `POST /timesheets/soumettre-mois` `{annee, mois}` : brouillons → soumis, en une opération.
- `GET /admin/sessions-sans-heures?classe_id=&annee=` pour le panneau directeur.
- `POST /timesheets/valider-lot` `{ids:[…], lissages:[{timesheet_id, date_to, montant_to_move}]}` : applique les lissages demandés puis valide, **dans une seule transaction** ; réutilise les règles de `propose-lissage` / `apply-lissage` (plafond 44,02 € par jour).

---

## 2. Parcours

Convention : « clic » = clic/tap sur un bouton ou lien ; les champs préremplis ne comptent pas.

### 2.1 Professeur

| Parcours | Chemin nominal | Clics | Objectif |
|---|---|---|---|
| **Encoder mon mois** (principal) | Menu Timesheets › vérifier les durées préremplies › **« Soumettre le mois »** › « Soumettre les heures » | **3** pour N sessions | Un seul passage en fin de mois (Q20) |
| Ajouter des heures libres | « Ajouter des heures » › type, date, durée › « Ajouter ces heures » | 2 (+ champs) | Préparation, etc. (Q19) |
| Revenir sur un autre mois | « Mois précédent » / « Mois suivant » | 1 | — |
| Fin de mois | « Confirmer et signer ce mois » (inchangé, actif quand tout est soumis) | 1+ | Inchangé |
| **Raccourci : encoder une séance tout de suite** | Mes classes › « Encoder mes heures » › « Soumettre les heures » | **2** | Depuis la session (≤ 3 clics, US-5) |
| Enregistrer sans soumettre | « Enregistrer en brouillon » (écran mensuel ou feuille) | 1 | Reprise ultérieure |

**Valeurs préremplies** : date, classe et séance, **durée = durée de la session** (3 h, modifiable de 0,5 h à 24 h), **type Animation**. Le professeur n'a qu'à vérifier la durée. Une session peut être **laissée « À encoder »** en décochant sa case. **Montants** : chaque ligne et le total montrent les euros du professeur uniquement.

**Cas particuliers** (01 : bouton « Vue de » Alice / Bob / Carol ; 02 : Bob / Alice)

| Cas | Ce que voit le professeur | Mock-up |
|---|---|---|
| **Co-enseignement** (AC-3) | Chacun voit *son* état sur la même session ; « Vos heures sont indépendantes de celles d'Alice/Bob » ; jamais les heures ni les montants de l'autre | 01, 02 |
| **Remplaçante** (Carol, séance 5) | Ne voit que la séance 5 ; « Vous : remplaçante sur la séance 5 » ; même encodage | 01 |
| **Remplacé ayant déjà encodé** (Alice, séance 5, AC-25) | Ligne « Remplacée par Carol Simon », saisie « Soumis » verrouillée, texte « Vos heures déjà encodées restent inchangées ; Carol encode les siennes » ; **aucune** nouvelle saisie possible | 01, 02 (vue Alice, novembre) |
| **Remplacé sans heures** | La session n'est **pas** proposée dans l'écran mensuel et ne compte pas dans « à encoder » | 06 |
| **Session annulée** (séance 6) | Absente de l'écran mensuel (note : « La séance 6 (02/12) est annulée : rien à encoder ») ; visible « Annulée » dans Mes classes | 01, 02 (décembre) |
| **Session non commencée** (6 bis, 16/12) | Pas proposée dans l'écran mensuel ; note « sera proposée dès son début » ; dans Mes classes bouton désactivé avec explication | 01, 02 |
| **Session passée non encodée** | Ligne préremplie « À encoder » ; bandeau « N sessions à encoder » | 02, 01 |
| **Bis** | Session distincte, encodage séparé | 01 |
| **Heures libres** | Section « Mes autres heures du mois » : ajout, suppression du brouillon, verrou après soumission | 02 |
| **Saisie verrouillée** | « 🔒 Verrouillé » avec le statut (Soumis, Confirmé, Généré) | 01, 02 |

### 2.2 Directeur / Admin

| Parcours | Chemin nominal | Clics |
|---|---|---|
| **Repérer les sessions passées sans heures** | Timesheets › vue « Par classe et séance » : panneau en tête | 1 |
| **Voir les heures d'une classe / d'un professeur** | filtre Classe ou Professeur ; **grille séances × professeurs** (une cellule = une timesheet indépendante) | 1 |
| **Valider en lot, lissage compris** | cocher les saisies (y compris celles en dépassement) › « Valider la sélection (n) » › [si dépassement : choisir « Lisser puis valider » ou « Valider sans lisser »] › « Appliquer le lissage et valider » | 2 + cases |
| **Valider une saisie seule** | « Valider » sur la ligne › même modale | 2 |
| **PDF / signature** | Inchangés, vue « Par professeur » | — |
| **Remplacer un professeur sur une session** | Parcours CLS-01 sans aucun message lié aux heures (AC-25) | 3 |

Après un remplacement (AC-25) : Alice « Soumis · 3 h — Remplacée par Carol : heures conservées », Carol « ⚠ Sans heures » jusqu'à ce qu'elle encode. **Aucun avertissement bloquant** au moment du remplacement.

---

## 3. Décisions UX justifiées

### D1 — Parcours principal : un écran mensuel « Encoder mon mois » (Q20)
- **Décision** : la page Timesheets devient un écran **mensuel** : navigation « Mois précédent / Mois suivant » ; section **1. Mes sessions du mois** (préremplies : date, classe · séance, activité Animation, **durée = durée de la session, modifiable**, montant, état) avec une case « Inclure » par session à encoder ; section **2. Mes autres heures du mois** (encodage libre, D6) ; section **3. Total du mois** (heures, montant, jours encodés, déjà soumis) ; boutons « Enregistrer en brouillon » et **« Soumettre le mois »** (récapitulatif : nombre de saisies, heures, euros). La signature mensuelle existante reste en bas, active quand tout est soumis.
- **Pourquoi** : les professeurs encodent en fin de mois ; un seul passage, un seul total, pas de navigation entre 4 à 8 séances.
- **Ne propose que les sessions commencées** : ni futures, ni annulées, ni celles où le professeur est remplacé (D4, D5).
- **Écarté** : liste plate de saisies avec formulaire en haut (ressaisie date/classe) ; un écran par séance comme parcours principal.

### D1bis — Raccourci : feuille d'encodage depuis la session
- **Décision** : dans « Mes classes › sessions », le bouton de la session ouvre une **feuille** (mobile : bas d'écran) préremplie (date, classe, séance, durée, type, montant) : « Soumettre les heures » en 2 clics. Même formulaire, mêmes règles que l'écran mensuel ; lien « Encoder tout mon mois ».
- **Écart avec la maquette validée 06** (qui ouvrait la page Timesheets) : la feuille évite de quitter le contexte ; voir Q16.
- **Écarté** : encodage en ligne dans la carte (perd verrous et statuts) ; formulaire libre avec choix de classe puis séance.

### D2 — Statut d'encodage = statut existant de la timesheet + « À encoder » (dérivé)
- **Décision** : on réutilise **Brouillon, Soumis, Confirmé, Généré** (même libellés, mêmes couleurs que `STATUT_LABELS/COLORS` : amber, bleu, indigo, vert). Le seul ajout est **« À encoder »**, qui n'est **pas un statut de timesheet** : c'est un état *calculé* d'une session passée sans aucune saisie du professeur attendu. « À venir », « Annulée » et « Remplacée par … » sont des états de **session** (T1/T2), pas de timesheet.
- **Pourquoi** : aucune migration de workflow, aucune nouvelle règle de transition, et la carte des statuts du Canvas §6 n'est pas modifiée.
- **Écarté** : un statut « manquant/non encodé » persistant (créerait des lignes vides, ambigu avec 0 h) ; un statut « rejeté » (non demandé, voir Q7).
- Texte **et** couleur toujours ensemble ; chaque statut verrouillé dit pourquoi (« 🔒 Soumis : en attente de confirmation par la direction »).

### D3 — Préremplissage et unicité (saisies liées à une session)
- **Décision** : date, classe, séance, **durée de la session**, **type Animation** préremplis. Une saisie **liée à une session** est unique par **(professeur, session, type d'activité)** : un professeur peut donc avoir, pour une même session, **une Animation et une Préparation**, jamais deux Animations. Le formulaire le dit (« Une seule saisie par session et par type d'activité »). Une durée différente de celle de la séance déclenche un message **non bloquant** « Cette durée diffère de celle de la séance (3 h). Ajoutez un commentaire pour la direction ».
- **Écarté** : durée figée (cas réel : séance écourtée ou prolongée) ; unicité (professeur, session) seule (empêche d'encoder la préparation) ; pas d'unicité (doublons par double-tap sur mobile).

### D4 — Qui peut encoder pour une session
- **Décision** : le professeur **assigné à la session et non remplacé**, ou le **remplaçant** sur cette session (`ma_situation` de l'API T2 : `assignee` ou `remplacant_de`). Le **remplacé** (`remplace_par`) ne peut plus **créer** de saisie pour cette session. Il garde **toutes ses saisies existantes**, visibles, avec leur workflow habituel (RG-9 : « la correction éventuelle se fait via leur propre workflow »). Le rôle ne joue aucun rôle dans le droit d'encoder.
- **Pourquoi** : évite que deux personnes soient payées pour la même présence par erreur tout en respectant « jamais de contrainte de timesheet » (rien n'est supprimé ni bloqué).
- **Écarté** : autoriser le remplacé à encoder encore (ambigu : qui a enseigné ?) ; transférer ses heures au remplaçant (interdit par RG-9) ; avertir la direction au moment du remplacement (interdit par AC-25).

### D5 — Fenêtre d'encodage (Q1 tranchée)
- **Décision** : on encode **à partir du début de la séance** ; sans limite vers le passé. Écran mensuel : seules les sessions commencées sont proposées (une note signale la séance annulée ou à venir). Raccourci Mes classes : session à venir = bouton **désactivé** avec explication ; session annulée = aucun encodage.
- **Écarté** : encodage anticipé ; délai maximal de saisie.

### D6 — Encodage libre : une fonction à part entière (Q19)
- **Décision** : « Ajouter des heures » est présenté comme un **encodage normal**, au même niveau que les sessions. Champs : **type d'activité** (Préparation par défaut / Animation), **date**, **durée**, **cours facultatif**, **séance facultative** (activée seulement si un cours est choisi), **commentaire facultatif**. Aucun motif obligatoire, aucun avertissement, aucun libellé d'exception. Texte d'aide : « Reliez les heures à une séance quand elles la concernent : la direction les retrouve alors dans la classe. Sinon, laissez vide. »
- **Cas d'usage** : une préparation faite le 12/11 pour les séances 4 à 6 ; une réunion ; du temps d'animation hors classe.
- **Données** : `course_session_id` et `cours_id` NULL possibles ; unicité (professeur, session, type) **uniquement** quand une session est liée. Les anciennes saisies (sans session) s'affichent dans cette même section.
- **Côté directeur** : ces heures apparaissent dans la vue Par professeur et dans la liste à valider (« Heures libres (sans séance) » si aucun cours/séance, sinon rattachées à la classe).
- **Écarté** : motif obligatoire ou fenêtre « hors séance » (ton d'exception) ; obliger à choisir une session (impossible pour une préparation à une autre date).

### D7 — Co-enseignement et remplacement : indépendance visible
- **Décision** : sur la session, le professeur ne voit que **son** statut ; une phrase fixe « Vos heures sont indépendantes de celles de Bob » rappelle la règle. Le directeur voit **une colonne par professeur** dans la grille : chaque cellule est une timesheet distincte, modifier l'une ne touche jamais l'autre (AC-3).
- **Écarté** : une ligne par session avec « heures totales » (suggère le partage) ; afficher les heures du co-enseignant au professeur (confidentialité, RG-5).

### D8 — Vue directeur « par classe et séance » + sessions sans heures
- **Décision** : même page, **sélecteur de vue** (ne casse pas la vue par professeur du traitement de fin de mois). En tête : panneau **« Sessions passées sans heures encodées »** (séance, date, professeur attendu avec son rôle, retard en jours : orange au-delà de 7 jours). Dessous : **grille séances × professeurs**, puis liste des **saisies soumises** à confirmer. Un professeur est « attendu » s'il est assigné non remplacé, ou remplaçant ; jamais sur une session annulée ni à venir. **Aucune notification** envoyée (décision Q9 de CLS-01) : l'action est « Ouvrir la séance ».
- **Écarté** : relance automatique par e-mail (hors décision Q9) ; un nouveau menu « Heures manquantes » (parcours parallèle) ; tableau plat sans grille (impossible de voir d'un coup d'œil le co-enseignement).

### D9 — Validation en lot avec lissage dans le même flux (Q21)
- **Décision** : l'**administrateur ou le directeur** coche des saisies **Soumis** (y compris celles en dépassement journalier, signalées par « Jour en dépassement » et « Lissage proposé à la validation ») › **« Valider la sélection (n) »** › une **modale unique** : récapitulatif (nombre, heures, euros, liste) ; **si au moins un jour dépasse** (plafond existant 44,02 €), une zone **« Lissage proposé »** reprend `TimesheetLissingModal` : tableau **Avant / Après** (ex. 21/10 : 46,00 € → 44,02 € et 22/10 : 0,00 € → 1,98 €), « Montant à déplacer » et « Date cible » modifiables, choix **« Lisser puis valider »** (défaut) ou **« Valider sans lisser »** ; bouton « **Appliquer le lissage et valider** » (ou « Valider les heures »). Toast : « 6 saisies validées (16 h). Lissage appliqué : 1,98 € déplacés du 21/10 au 22/10. Les professeurs ne peuvent plus les modifier. »
- **Pas d'exclusion** : aucune saisie n'est retirée du lot parce qu'elle est à lisser ; chaque ligne garde son bouton « Valider » individuel (même modale).
- **Écarté** : exclure les saisies à lisser du lot et renvoyer vers une autre modale (double passage) ; lisser automatiquement sans aperçu (change des montants sans contrôle).

### D10 — Verrous après soumission
- **Brouillon** : modifiable et supprimable par le professeur propriétaire. **Soumis** : verrouillé pour le professeur, confirmable par le directeur. **Confirmé / Généré** : verrouillé pour tous sauf les actions existantes (lissage, génération PDF). Les verrous s'appliquent à la saisie, **indépendamment** de l'état de la session ou du remplacement.
- **Point technique** : `Timesheet::isLocked()` teste `['soumis','valide']` alors que les statuts sont `confirmé` / `généré` ; voir Q13 (normalisation prévue en CLS-01 §6).

---

## 4. Règles proposées (à valider)

| # | Règle |
|---|---|
| **R-T3-1** | Une timesheet liée à une session est **unique par (professeur, session, type d'activité)** ; les saisies sans session n'ont pas cette contrainte. |
| **R-T3-2** | Peut **créer** une saisie pour une session : le professeur assigné à cette session **non remplacé**, ou son **remplaçant**. Le remplacé ne peut pas en créer, mais conserve et peut poursuivre le workflow de ses saisies existantes. Le rôle n'a aucun effet. |
| **R-T3-3** | Encodage possible **à partir du début** de la session ; jamais pour une session **annulée** ou **non commencée**. L'écran mensuel ne propose que les sessions commencées. |
| **R-T3-4** | À la création : `professeur_id` = utilisateur connecté, `date_prestation` et `cours_id` = ceux de la session (le client ne les fournit pas) ; durée par défaut = durée de la session. |
| **R-T3-5** | **Encodage libre** = fonction normale : `course_session_id` et `cours_id` facultatifs, commentaire facultatif, **aucun motif obligatoire**. Lien à une session/cours recommandé quand les heures concernent une séance. |
| **R-T3-6** | **Verrous** : seules les saisies au statut Brouillon sont modifiables/supprimables par le professeur. |
| **R-T3-7** | **AC-25 / RG-9** : remplacer un professeur ne crée, ne modifie, ne supprime, ne transfère et ne signale aucune timesheet. |
| **R-T3-8** | Une session ayant des saisies **ne peut pas être supprimée** (AC-7, RG-7) ; **déplacer/annuler** une session passée ou ayant des heures reste interdit (D4 de CLS-01) ; le message l'explique. |
| **R-T3-9** | « Session passée sans heures » = session terminée, non annulée, pour laquelle **au moins un professeur attendu** n'a **aucune** saisie (tous types confondus). Une saisie en brouillon compte comme encodée (mais apparaît dans « Brouillons à soumettre »). |
| **R-T3-10** | Le professeur ne voit **jamais** les heures ni les montants d'un autre professeur (RG-5, isolation 403). Il voit les **euros de ses propres heures** (Q22). Les tarifs des classes, sessions et calendrier restent masqués. |
| **R-T3-11** | **Soumettre le mois** passe en « Soumis » toutes les saisies en brouillon du mois (et enregistre d'abord les sessions préremplies incluses) ; la signature mensuelle existante reste inchangée. |
| **R-T3-12** | **Validation** par admin ou directeur, unitaire ou en lot ; le **lissage est proposé dans le même flux** (aperçu avant/après, plafond journalier existant), appliqué puis validé en **une transaction** ; aucune saisie n'est exclue du lot pour cause de lissage. |

---

## 5. Les 4 états par écran (textes exacts)

Légende : **Ch.** chargement (squelette, `role="status" aria-busy="true"`, texte réservé aux lecteurs d'écran) · **Vide** · **Err.** (`role="alert"`, bouton « Réessayer ») · **Succ.** (toast `role="status" aria-live="polite"`).

### 01 — Mes classes › sessions (mobile) et feuille d'encodage
| État | Texte |
|---|---|
| Ch. | squelette ; lecteur d'écran : « Chargement des sessions… » |
| Vide | **Aucune session à afficher** — « Vous n'êtes assigné à aucune session de cette classe pour l'instant. Dès que la direction vous y assignera, vos sessions apparaîtront ici avec leur statut d'encodage. » + « Retour à mes classes » |
| Err. | **Impossible de charger les sessions de cette classe.** « Vérifiez votre connexion puis réessayez. » + « Réessayer » |
| Succ. (soumission) | « Heures de la séance 4 soumises (3 h). Elles sont visibles par la direction. » |
| Succ. (brouillon) | « Brouillon enregistré pour la séance 4. Pensez à le soumettre. » |
| Bandeau | « **1 session à encoder** — 3 h par session, prérempli. » / « **2 sessions à encoder** … » (absent s'il n'y en a pas) |
| Erreur de saisie | durée hors 0,5–24 h : « La durée doit être comprise entre 0,5 h et 24 h. » ; doublon (`422`) : « Vous avez déjà encodé de l'animation pour cette séance. Choisissez « Préparation » ou modifiez la saisie existante. » ; droit refusé (`403`) : « Vous n'êtes plus assigné à cette session : vous ne pouvez plus y encoder d'heures. » |

### 02 — Encoder mon mois (professeur)
| État | Texte |
|---|---|
| Ch. | « Chargement de votre mois… » (lecteur d'écran) |
| Vide | **Rien à encoder pour ce mois** — « Aucune de vos sessions n'a encore commencé ce mois-ci et vous n'avez encodé aucune heure. Vous pouvez tout de même ajouter des heures (préparation, réunion…) à la date de votre choix. » + « Ajouter des heures », « Aller à Mes classes » |
| Vide (section sessions) | « Aucune session commencée ce mois-ci. » ; note décembre : « La séance 6 (02/12) est annulée : rien à encoder. La « Séance 6 bis » (16/12) sera proposée dès son début. » |
| Vide (section heures) | « Aucune autre heure ce mois-ci. Utilisez « Ajouter des heures » pour votre préparation, par exemple. » |
| Bandeaux | « **2 sessions à encoder** ce mois-ci : durée préremplie, vérifiez-la puis soumettez. » / « **2 brouillons** à soumettre. » / « ✔ **Rien à encoder.** Toutes les sessions commencées de ce mois ont des heures. » |
| Err. | **Impossible de charger votre mois.** « Vos saisies ne sont pas perdues. Vérifiez votre connexion puis réessayez. » + « Réessayer » |
| Succ. | « Heures de novembre 2026 soumises (7 h). Elles sont visibles par la direction. » ; « 2 sessions enregistrées en brouillon. Pensez à soumettre le mois. » ; « Heures ajoutées en brouillon (1 h de préparation). » ; « Brouillon supprimé. » |
| Désactivés | « Désactivé : rien à soumettre pour ce mois. » ; signature : « Désactivé : il reste des sessions à encoder ou des brouillons à soumettre, ou rien n'a été soumis ce mois-ci. » ; mois précédent/suivant : infobulle « Octobre est le premier mois de l'année scolaire » / « Aucun mois suivant n'est encore ouvert » |

### 03 — Timesheets directeur, vue « Par classe et séance »
| État | Texte |
|---|---|
| Ch. | « Chargement des heures de la classe… » |
| Vide | **Aucune heure encodée pour cette classe** — « Aucun professeur n'a encore encodé d'heures pour « Scratch Junior — mercredi 14h–17h » sur la période choisie. Les séances passées apparaîtront ici dès qu'elles auront eu lieu ; vérifiez aussi l'assignation des professeurs dans la fiche de la classe. » + « Ouvrir la classe » |
| Vide (lot) | « Plus aucune saisie soumise à traiter. » |
| Err. | **Impossible de charger les heures de cette classe.** « Aucune donnée n'a été modifiée. Réessayez dans un instant ; si le problème persiste, contactez l'administrateur. » + « Réessayer » |
| Succ. | « 6 saisies validées (16 h). Lissage appliqué : 1,98 € déplacés du 21/10 au 22/10. Les professeurs ne peuvent plus les modifier. » (sans lissage : « 3 saisies validées (9 h). Les professeurs ne peuvent plus les modifier. ») |
| Lissage | « **Lissage proposé — Bob Leroy, 21/10/2026** : le total du jour dépasse le plafond journalier (max 44,02 €). » ; Avant 46,00 € → Après 44,02 € / 1,98 € ; boutons « Appliquer le lissage et valider » / « Valider les heures » |
| Sans heures | « ⚠ Sans heures » ; « Aucune notification n'est envoyée au professeur (décision Q9 de CLS-01) : la direction le contacte directement. » |

---

## 6. Accessibilité et mobile

- **Mobile 375 px** (01) : une colonne, boutons **48 px** de haut pleine largeur, feuille d'encodage collée en bas (max. 94 % de la hauteur, défilement interne), pas de tableau (cartes), aucune action à deux doigts. 02 et 03 sont des écrans de bureau mais **sans débordement horizontal de la page** (tableaux dans un conteneur défilant) et utilisables à 375 px.
- **Clavier** : tous les boutons atteignables ; feuilles/modales = `role="dialog" aria-modal="true"`, focus piégé, **Échap** ferme, le focus revient sur le bouton d'origine ; focus visible 3 px.
- **Lecteurs d'écran** : statut = **texte + couleur** (badge) ; tableaux avec `caption` et `scope` ; cases à cocher nommées (« Sélectionner Alice Dupont Séance 3 ») ; boutons désactivés reliés à leur explication par `aria-describedby` ; erreurs `role="alert"`, confirmations `aria-live="polite"`.
- **Pas de bouton mort** : tout bouton visible fait quelque chose ou est désactivé **avec le motif affiché** (session à venir, saisie à lisser, signature mensuelle). Actions non autorisées au professeur (valider, remplacer) : **masquées**.
- Contrastes AA (palette du design system existant), `prefers-reduced-motion` respecté (squelettes sans animation).

---

## 7. Questions ouvertes (chacune avec ma recommandation par défaut)

| # | Question | Recommandation par défaut |
|---|---|---|
| **Q1** | ~~Encoder avant la séance ?~~ | **Tranchée (Q20) : sans objet.** Encodage dès le début de la séance ; l'écran mensuel ne propose que les sessions commencées. |
| **Q2** | La **durée** préremplie est-elle modifiable ? | **Oui**, 0,5–24 h (champ modifiable dans l'écran mensuel et la feuille) ; message non bloquant si ≠ durée de la séance. |
| **Q3** | Un professeur peut-il avoir **Animation + Préparation** sur la même session ? | **Oui**, unicité (professeur, session, type) (R-T3-1). |
| **Q4** | Le professeur **remplacé** qui a un **brouillon** peut-il encore le modifier/soumettre ? | **Oui** (workflow propre, RG-9), mais **pas de nouvelle création**. |
| **Q5** | ~~Conserve-t-on l'encodage libre ?~~ | **Tranchée (Q19)** : fonction à part entière, sans motif obligatoire ni ton d'exception. |
| **Q6** | ~~Validation en lot, saisies à lisser exclues ?~~ | **Tranchée (Q21)** : validation admin/directeur, lissage proposé dans le même flux, aucune exclusion. |
| **Q7** | Comment le professeur **corrige-t-il une saisie soumise** par erreur ? (aucun « rejet » aujourd'hui) | **Hors T3** : contacter la direction. Option à décider : action directeur « Renvoyer en brouillon » (soumis → brouillon, sans nouveau statut). |
| **Q8** | Peut-on **annuler/déplacer** une session passée qui a déjà des heures ? | **Non** (409 expliqué), cohérent avec CLS-01 D4 et AC-7 ; remplacement de professeur toujours possible. |
| **Q9** | ~~Masquer les euros au professeur ?~~ | **Tranchée (Q22)** : euros visibles, ses propres heures uniquement. |
| **Q10** | **Seuil de retard** pour mettre en alerte une session sans heures ? | Toute session **terminée** est listée ; badge orange à **plus de 7 jours**. |
| **Q11** | Le **directeur peut-il encoder pour un professeur** ? | **Non** en T3 (le professeur reste responsable de sa saisie). |
| **Q12** | **Anciennes timesheets** sans session : rattachement rétroactif ? | **Non** (pas de migration, CLS-01 Q6) ; elles s’affichent dans « Mes autres heures du mois ». |
| **Q13** | **Normaliser** les statuts sans accent (`confirme`, `genere`) et corriger `isLocked()` en T3 ? | **Oui**, dans la même tranche ; libellés affichés inchangés. |
| **Q14** | Annuler une session qui a déjà **un brouillon** : bloquer ou supprimer le brouillon ? | **Bloquer** (R-T3-8) ; la direction demande d'abord au professeur de supprimer son brouillon. |
| **Q15** | Deux sessions **simultanées** pour le même professeur (conflit RG-4) : l'encodage est-il bloqué ? | **Non** : le conflit est traité à l'assignation (RG-4) ; l'encodage ne re-vérifie pas. |
| **Q16** | Valider l'**écart avec la maquette 06** : feuille d'encodage plutôt qu'ouverture de la page Timesheets ? | **Oui** (D1) ; 06 sera mise à jour en conséquence. |

---

## 8. Matrice AC → mock-up

| AC / règle | Comportement vérifié | Mock-up et élément |
|---|---|---|
| **AC-3** (2 profs, même session) | Alice et Bob encodent chacun la séance ; 2 timesheets indépendantes | 01 (vue Alice / Bob), 03 (grille, colonnes Alice et Bob) |
| **AC-14** (remplacement ponctuel) | La timesheet de la séance 5 est celle de Carol | 01 (vue Carol), 03 (cellule Carol séance 5) |
| **AC-25** (remplacé ayant déjà encodé) | Aucun blocage ; heures d'Alice intactes ; Carol encode les siennes | 01 (vue Alice : « Remplacée par Carol », « Soumis · 3 h »), 02 (vue Alice, novembre : ligne séance 5 « Remplacée par Carol Simon »), 03 (cellule Alice « Remplacée par Carol : heures conservées ») |
| **AC-2** (isolation par classe) | Le professeur ne voit que ses sessions ; Carol que la séance 5 | 01 (vue Carol) |
| **AC-20 / AC-24** (annulée, bis) | Séance 6 annulée sans encodage ; séance 6 bis encodable séparément | 01, 02 (décembre : note), 03 |
| **AC-7** (suppression/annulation avec heures) | Règles R-T3-8, texte d'explication | WORKFLOW (§4) ; message à intégrer à 03 de CLS-01 |
| **AC-6** (assignation terminée) | Lecture seule des timesheets historiques | 01 (lecture « Voir mes heures »), 02 (lignes verrouillées d'octobre) |
| **RG-5** (timesheet = prof + session) | Rattachement à la session, jamais partagée ; chaque professeur ne voit que ses propres heures et euros | 01, 02, 03 |
| **Q19** (encodage libre) | Heures sans cours ni session, comme encodage normal | 02 (section 2, modale « Ajouter des heures ») |
| **Q20** (encodage mensuel) | Mois précédent/suivant, sessions préremplies, total, soumission du mois ; séance par séance = raccourci | 02, 01 |
| **Q21** (validation avec lissage) | Lot incluant les saisies à lisser, aperçu avant/après, validation dans le même flux | 03 (modale « Valider les heures sélectionnées ») |
| **Q22** (euros visibles) | Euros du professeur, siens uniquement | 02 (montants et total), 01 (feuille) |
| **US-5 / T3** (encodage ≤ 3 clics) | 3 clics pour tout le mois (02) ; 2 clics pour une séance (01) ; valeurs préremplies | 01, 02 |
| **US-4** (voir ses sessions) | Statut d'encodage par session | 01 |
| Critères **proposés** (à ajouter au §5.2 de CLS-01 après validation) | **AC-26** une session passée sans saisie du professeur attendu est listée « sans heures » côté directeur ; **AC-27** le remplacé ne peut plus créer de saisie sur la session mais garde les siennes ; **AC-28** unicité (professeur, session, type) ; **AC-29** une session annulée ou à venir n'accepte aucune saisie | 03, 01 |

---

## 9. Écarts et points d'attention

1. Maquette validée **06** : sa fenêtre d'encodage « illustrative » est remplacée par la feuille de **01**, désormais **raccourci** (D1bis, Q16) ; 06 devra être mise à jour.
2. **TimesheetsPage** change de nature (écran mensuel) : le tableau plat et `NewTimesheetForm` disparaissent ; `TimesheetMontantDisplay` est conservé (montants visibles, Q22).
3. `Timesheet::isLocked()` ne reconnaît pas `confirmé`/`généré` (Q13). `TimesheetController::update` ne valide pas `type_activite`.
4. Aucun mécanisme de **rejet** d'une saisie soumise n'existe (Q7).
5. `TimesheetLissingModal` est calculé en **euros** (plafond 44,02 €/jour) : la validation en lot doit appliquer plusieurs lissages dans une transaction (contrat §1.3). Les montants des mock-ups sont illustratifs.
6. Les dates de vacances de l'exemple sont indicatives (fichier FWB à venir).
7. Nouvelles capacités à cadrer par l'architecte : soumission du mois, validation en lot avec lissage, liste des sessions sans heures.

## 10. Validation

☐ Direction (workflow et règles R-T3) ☐ Analyste (AC-26 à AC-29) ☐ Architecte (contrat d'API §1.3) ☐ UX (mock-ups 01 à 03)

*Aucune case n'est cochée : la validation appartient aux personnes concernées.*
