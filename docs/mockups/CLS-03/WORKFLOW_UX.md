# CLS-03 — Gestion des années scolaires et des dates de période : workflow UX et mock-ups (à valider avant développement)

| | |
|---|---|
| **Auteur** | UX Expert (sub-agent) |
| **Base** | `docs/requirements/CLS-01-modele-classes.md`, `CLS-02-classe-deux-periodes.md`, `docs/mockups/CLS-02/WORKFLOW_UX.md` (14 séances par période, « hors période » = avertissement non bloquant, une classe porte 1 ou 2 périodes, `hors_periode` calculé) |
| **Mock-ups** | `index.html`, `01-liste-annees.html`, `02-creation-annee.html`, `03-modifier-periodes-impact.html`, `04-liens-depuis-classes.html` (HTML statiques, sélecteur d'états en haut, même CSS que CLS-02). Dates illustratives. |
| **Statut** | Révision 1 — **aucune zone de validation n'est remplie** |

## 1. Problème et constat

Ajouter une période 2 à une classe refuse une date de démarrage hors des bornes de la période de l'année (ex. P2 2026-2027 = 22/02/2027 → 02/07/2027). Ces bornes viennent du seed : l'utilisateur ne sait ni pourquoi la date est refusée ni où la changer.

**Trajets actuels (code lu) :**
- Menu admin, groupe « Scolarité » : Classes · Calendrier · Calendrier scolaire (`PortalLayout.jsx`).
- La **création d'année** n'existe que dans une modale (`AnneeScolaireModal.jsx`) ouverte depuis le sélecteur d'année de la page « Calendrier scolaire » (`CalendrierScolaireAdminPage.jsx`) : option « Nouvelle année ». **Aucun écran pour modifier les dates, changer le statut ni supprimer**, alors que l'API existe (`GET/POST/PUT/DELETE /annees-scolaires`, statuts `brouillon|active|archivee`, `can.update/delete/import_fwb`).
- Création de classe (`ClasseCreatePage.jsx`) et modale « Ajouter la période » (`PeriodeModals.jsx`) lisent `annee.periodes` et bornent la date (`min`/`max`) sans expliquer ni proposer de lien.
- Backend : `AnneeScolaireService::modifier` **refuse en 422** si des séances dépassent la nouvelle fin de période. C'est **contradictoire avec CLS-02 (RG-4 : hors période = avertissement)** : voir §8.

## 2. Principes UX

1. **Une seule page de gestion**, nommée comme le métier : « Années scolaires ». Le calendrier scolaire reste séparé (autre sujet : vacances/fériés) mais les deux se renvoient l'un à l'autre.
2. **Proposer, ne pas faire saisir** : à la création, les 4 dates sont pré-remplies depuis le calendrier FWB ; l'utilisateur ne fait que vérifier.
3. **Voir avant de valider** : toute modification de dates affiche l'impact sur les classes et séances existantes avant l'enregistrement.
4. **Jamais de blocage dur sur l'existant** : changer une date ne supprime, ne déplace ni n'annule aucune séance. Les séances qui sortent des bornes deviennent « hors période » (marqueur calculé, réversible). Confirmation explicite seulement si au moins une séance/classe est touchée.
5. **Blocages réservés à l'incohérence des dates elles-mêmes** (fin avant début, P2 non postérieure à P1, chevauchement d'une autre année).
6. **Un lien là où l'utilisateur bute**, avec retour au formulaire et saisie conservée.
7. Admin et directeur ont **exactement les mêmes droits** (voir §5, question 1).

## 3. Workflow par écran

### 3.1 Entrée de menu et page « Années scolaires » (mock-up 01)
- Menu : **Scolarité › Années scolaires** (badge « nouveau »), entre « Calendrier » et « Calendrier scolaire ». Route `/admin/annees-scolaires` ; admin + directeur (`AnneeScolairePolicy` : `isStaff()` déjà en place).
- En-tête : titre, rappel (« Modifier une période ne déplace et ne supprime jamais de séance »), boutons **＋ Nouvelle année scolaire** et lien « Calendrier scolaire ».
- Tableau (une ligne par année, la plus récente en premier) : **Année** (+ badge « en cours » si la date du jour est dedans) · **Statut** (Brouillon / Active / Archivée) · **P1** et **P2** (du → au, repère « P1 en cours ») · **Classes** (nombre) · **Calendrier** (« 38 dates » ou ⚠ « Vide » + « Importer FWB ») · **Dernière modification** (qui, quand) · **Actions**.
- Actions par ligne : **Modifier les périodes** (principale) · **Calendrier** (ouvre la page calendrier sur l'année) · **Archiver / Réactiver / Activer** · **Supprimer** (grisé mais cliquable si des classes existent : la modale explique et propose d'archiver, plutôt qu'un bouton mort).
- Bandeau jaune quand une année a un calendrier vide : le bouton d'import FWB est accessible **depuis la liste** (même action que dans la page calendrier).
- Filtre « Afficher » : Actives et brouillons (défaut) · Toutes · Archivées.
- **Archiver** : modale listant ce qui change (plus proposée à la création de classe ni dans les alertes « P2 à planifier », dates verrouillées, consultable) et ce qui **ne change pas** (classes, séances, calendrier, **encodage des heures par les professeurs**). Avertissement supplémentaire si l'année est en cours. Toast avec « Annuler l'archivage ».
- **Supprimer** : possible uniquement sans classe ; modale rouge listant ce qui disparaît (périodes, dates de calendrier), action irréversible. Avec classes : message « contient 14 classes (412 séances) » + bouton « Archiver à la place ».
- **Réactiver** : remet l'année en « Active » ; aucune confirmation lourde.

### 3.2 Création d'une année (mock-up 02) — page dédiée plutôt que modale
La modale actuelle est remplacée par une page `/admin/annees-scolaires/nouvelle` (la frise et les contrôles de cohérence ne tiennent pas bien dans une modale). Les anciens points d'entrée (option « Nouvelle année » du calendrier scolaire, état vide de la création de classe) pointent vers cette page.
- Champs : **Libellé** (proposé : année suivante de la dernière année existante, « 2027-2028 ») · **Statut** (Active par défaut ou Brouillon) · **P1 du/au** · **P2 du/au** · case « Importer le calendrier FWB à la création » (déjà existante).
- L'année **s'étend du début de P1 à la fin de P2** (aucune saisie séparée, comme aujourd'hui).
- **Pré-remplissage intelligent** (règle proposée, §5 question 3) :
  1. Si le calendrier FWB de l'année est disponible : P1 = rentrée → vendredi précédant la vacance qui sépare les deux moitiés d'année (Carnaval) ; P2 = lundi de reprise → dernier jour de l'année scolaire. Exemple 2026-2027 conforme au seed : P1 01/09/2026 → 12/02/2027, P2 22/02/2027 → 02/07/2027.
  2. Sinon : P1 01/09 → 31/01, P2 01/02 → fin juin (1er juillet si le calendrier de l'année est inconnu), avec la mention « proposition par défaut, calendrier FWB non importé ».
  Bouton « ↺ Rétablir la proposition ». Si l'utilisateur a modifié une date, on ne la réécrase jamais.
- **Frise de l'année** (P1 bleu, P2 violet, trou hachuré, chevauchement rouge) + message de cohérence en direct (région `aria-live`) : ✓ « Dates cohérentes · 9 jours entre les périodes (vacances de Carnaval) ».
- Bouton « Créer l'année 2027-2028 » désactivé tant qu'un blocage existe ; la saisie reste conservée en cas d'erreur serveur.
- **Succès** : récapitulatif + prochaines étapes (« Créer une classe sur 2027-2028 », « Voir le calendrier scolaire », « Retour à la liste »). Pas de retour automatique : l'étape suivante logique est la création d'une classe ou l'import du calendrier.

### 3.3 Modifier les périodes avec aperçu d'impact (mock-up 03)
Écran `/admin/annees-scolaires/:id/periodes` (ouvert depuis la liste, la page calendrier, la création/fiche classe).
- Haut : **frise « Avant / Après »** et tableau Période · Actuellement (grisé) · Du · Au · Évolution (« fin −14 j », « inchangée »). La ligne modifiée est surlignée. L'année suit P1 début → P2 fin (affiché).
- Milieu : bloc **« Impact sur l'existant »**, recalculé (debounce ~400 ms) à chaque changement valide via l'API d'aperçu (§7). Contenu :
  - 4 compteurs : **classes touchées · séances « hors période » en plus · classes qui démarrent avant la nouvelle date · blocages**.
  - Bandeau jaune (non bloquant) ou vert (aucun effet) et **tableau par classe** (période, nombre de séances, dates, lien « Ouvrir la classe »).
  - Rappel permanent : « aucune séance n'est déplacée, annulée ou supprimée ; les heures encodées ne changent pas ; "hors période" disparaît si les dates sont rétablies ».
- Bas : **Enregistrer les dates** (désactivé si aucun changement, blocage, calcul en cours ou erreur d'aperçu : par prudence on n'enregistre pas sans impact connu).
- **Confirmation** : si l'impact est nul ou positif → enregistrement direct (toast). Si ≥ 1 séance ou classe est touchée → **modale de confirmation** (ce qui change, conséquences chiffrées, noms des classes, « rétablir supprime les marqueurs ») avec **case à cocher obligatoire** « Je confirme : 6 séances seront marquées "hors période" ». Le bouton « Enregistrer » reste grisé tant que la case n'est pas cochée.
- **Succès** : récapitulatif + lien « Voir les 3 classes concernées » (liste des classes filtrée sur l'année et « avec séances hors période »).

#### Règles d'impact par type de changement
| Changement | Effet calculé et affiché | Blocage ? |
|---|---|---|
| **Allonger** une période (fin plus tard) | Séances hors période qui **redeviennent dans la période** (positif) ; aucun effet négatif | Non |
| **Raccourcir** une période (fin plus tôt) | N séances de M classes deviennent « hors période » (liste datée) | **Non** : confirmation avec case |
| **Avancer** le début d'une période | Aucun effet sur les séances (le début n'est qu'une borne de démarrage) ; permet aux classes de démarrer plus tôt | Non |
| **Retarder/décaler** le début d'une période | Classes dont la 1re séance de la période est avant le nouveau début : « démarre avant le début de la période » (même avertissement que CLS-02 « avant le début officiel ») | Non : confirmation |
| Changer la **fin de P1** | Séances P1 hors période ; **alertes « P2 à planifier »** (seuil 28 j) qui apparaissent/disparaissent pour les classes sans P2 : chiffrées | Non |
| Dates qui rendent P2 ≤ fin de P1 | Aucun aperçu, message rouge « La période 2 doit commencer après la fin de la période 1. Au plus tôt le … » | **Oui** |
| Dates sortant de l'année (cas impossible via l'UI, l'année suit les périodes) | Entrées du calendrier scolaire hors de l'année : « n dates conservées mais hors année » (info) | Non |

### 3.4 Liens depuis les trajets existants (mock-up 04)
- **Création de classe** : sous le champ « Date de démarrage » d'une période, le message de refus devient « Le 07/07/2027 est après la fin de la période 2 (02/07/2027) … » suivi du lien **« Modifier les dates de la période 2 (2026-2027) »** et de l'alternative « ou choisissez une date plus tôt ». L'en-tête du bloc période affiche en permanence les bornes (« 22/02/2027 → 02/07/2027 ») avec le lien « Modifier les dates ».
- **Modale « Ajouter la période 2 »** : même message et même lien ; le lien ferme la modale et ouvre l'écran 03 ; au retour, la modale se rouvre avec cours et date déjà saisis.
- **Retour au formulaire en conservant la saisie** : le lien transmet `retour` (chemin de la page d'origine) ; le formulaire de création de classe sauvegarde son brouillon dans `sessionStorage` (clé par page) avant la navigation et le restaure au retour (restauration aussi pour la modale : `{classeId, numero, coursId, date}`). L'écran 03 affiche une bande « Vous modifiez les dates depuis Nouvelle classe. Votre saisie est conservée. ← Retour au formulaire » ; après l'enregistrement, **retour automatique** à la page d'origine avec bandeau vert « Dates mises à jour : la période 2 se termine maintenant le … ». Sans `retour` (accès via le menu), le comportement reste celui du §3.3.
- **Fiche classe** : dans l'en-tête de chaque bloc période, « Bornes : 22/02/2027 → 02/07/2027 · Modifier les dates » (lien discret, `lnk`, avec `aria-label` complet) et une phrase d'explication : « Ces bornes viennent de l'année scolaire 2026-2027 et valent pour toutes les classes de l'année ». Le message « 1 séance hors période » reste inchangé.
- **Page « Calendrier scolaire »** : sous le sélecteur d'année, ligne « P1 … P2 … · Modifier les dates des périodes · Gérer les années scolaires » ; l'option « ＋ Nouvelle année scolaire… » du sélecteur ouvre la page 02. Le bouton d'import FWB reste là (et dans la liste des années).
- **Création de classe sans année** : état vide « Aucune année scolaire disponible » avec « Créer une année scolaire » (retour avec saisie conservée).
- **Droits** : le lien n'est affiché que si `can.update` est vrai (sinon texte brut des bornes).

## 4. Cas limites et règle UX proposée

| Cas | Règle proposée | Message / comportement |
|---|---|---|
| **P1 et P2 se chevauchent** (début P2 ≤ fin P1) | Bloquant, partout (création, modification) | « La période 2 doit commencer après la fin de la période 1 (18/02/2028). Au plus tôt le 19/02/2028. » Frise : zone rouge ; bouton désactivé |
| **Fin avant début** d'une période | Bloquant | « La fin de la période doit être postérieure à son début. » |
| **Trou entre les périodes** | Normal (vacances) ; **avertissement non bloquant si > 6 semaines** | « 44 jours sans période : aucune classe ne pourra démarrer dans cet intervalle. » Sinon « 9 jours entre les périodes (vacances de Carnaval) » en info verte |
| **L'année chevauche une autre année** | Bloquant (la détection de l'« année en cours » et la création de classe deviendraient ambiguës) | « Cette année chevauche 2026-2027 (qui se termine le 02/07/2027). Ajustez le début de la période 1 ou modifiez la fin de 2026-2027. » + lien |
| **Année sans P2** | Impossible : une année a toujours 2 périodes (contrat API : `periodes` de taille 2). Une classe peut elle ne porter qu'une période (CLS-02) | Si l'école ne donne pas de cours en P2, on ouvre simplement des classes en P1 seule |
| **Raccourcir/allonger/décaler avec des classes existantes** | Voir §3.3 : jamais bloquant, aperçu + confirmation | « 6 séances seraient hors période (3 classes). Non bloquant. » |
| **Classes sans P2 et fin de P1 modifiée** | Aperçu chiffre les alertes « P2 à planifier » créées/supprimées | « 2 classes passeraient en alerte "P2 à planifier" » |
| **Supprimer une année avec classes** | Refus ; proposition d'archiver | « Contient 14 classes (412 séances) : archivez-la plutôt. » |
| **Supprimer une année sans classe** | Autorisé (admin et directeur), irréversible, modale rouge | « Seront supprimés : 2 périodes, 36 dates de calendrier. » |
| **Archiver l'année en cours** | Autorisé avec avertissement ; n'affecte ni séances ni timesheets | « Cette année est en cours (14 classes actives). » |
| **Modifier les dates d'une année archivée** | Verrouillé ; « Réactivez l'année pour modifier ses dates » | Champs en lecture seule (`.ro`) |
| **Modification concurrente** (2 utilisateurs) | Verrou optimiste : l'API refuse si la version a changé (409) | Bandeau rouge « Ces dates ont été modifiées par Sophie Martin à 14:32 : P2 fin 02/07 → 25/06. Vos modifications n'ont pas été enregistrées. » + « Recharger ses dates et reprendre ma saisie » / « Abandonner » |
| **Changement de dates pendant la saisie d'une classe** (autre onglet) | La liste d'années est rechargée au retour sur l'onglet ; si les bornes ont changé, la validation côté serveur fait foi | Message de refus avec les bornes à jour |
| **Aperçu d'impact en erreur / lent** | « Enregistrer » désactivé ; bouton « Réessayer » ; état « Calcul de l'impact… » avec squelette | Jamais d'enregistrement sans impact connu |
| **Libellé déjà utilisé** | Bloquant (unicité API) | « Une année 2027-2028 existe déjà. » |
| **Calendrier FWB indisponible pour l'année** | Dates proposées par défaut, mention explicite | « Proposition par défaut : calendrier FWB non importé. » |
| **Droits** | Admin et directeur identiques sur toutes les actions ; professeur : aucun accès (403, entrée de menu masquée) | Lien contextuel masqué sans `can.update` |
| **Statuts : plusieurs années actives** | Autorisé (N en cours + N+1 en préparation), à condition de dates sans chevauchement | Voir question 5 |

## 5. Questions ouvertes pour le directeur

1. **Droits** : le directeur peut-il créer, modifier, archiver **et supprimer** (années vides) comme l'admin ? *Reco :* oui, identiques (la suppression n'est possible que sans classe, et « dernière modification » trace l'auteur).
2. **Chevauchement d'années** : refuser (bloquant) qu'une année démarre avant la fin de la précédente ? *Reco :* oui, bloquant ; l'année suivante se prépare en « Brouillon » avec ses dates exactes.
3. **Dates proposées** : P1 = rentrée → vendredi avant les vacances de Carnaval, P2 = reprise → fin d'année (calendrier FWB), ou plutôt septembre → janvier / février → juillet ? *Reco :* FWB si importé (correspond à votre seed 22/02/2027), sinon 01/09 → 31/01 et 01/02 → fin juin ; toujours modifiable.
4. **Confirmation** : exiger la case à cocher dès qu'une séance est touchée, ou seulement au-delà d'un seuil ? *Reco :* dès la première séance touchée ; aucun clic supplémentaire si l'impact est nul.
5. **Statuts** : brouillon / active / archivée : plusieurs années actives en parallèle, et dates verrouillées une fois archivée ? *Reco :* oui aux deux (statut « Active » = proposée à la création de classes ; « Brouillon » = en préparation, non proposée ; « Archivée » = dates verrouillées, séances et timesheets intactes).

## 6. États (textes à reprendre)

- **Liste** : chargement « Chargement des années scolaires… » (squelette) · vide « Aucune année scolaire — Créez votre première année : vous indiquez les dates des deux périodes (nous vous les proposons)… » · erreur « Impossible de charger les années scolaires. Vérifiez votre connexion puis réessayez. » [Réessayer] · succès « Année 2026-2027 archivée. Ses 14 classes, séances et heures encodées sont inchangées. » [Annuler l'archivage].
- **Création** : succès « Année 2027-2028 créée. P1 : … · P2 : … Calendrier FWB importé : 41 dates. » ; import FWB échoué : année créée, avertissement distinct (comportement actuel conservé) ; erreur serveur « L'année n'a pas pu être créée … Votre saisie est conservée. »
- **Modification** : aucun changement (« Modifiez une date pour voir ce qui changerait ») · calcul (« Calcul de l'impact… ») · erreur (« L'impact n'a pas pu être calculé. Par prudence, l'enregistrement est désactivé. ») · succès « Dates de l'année 2026-2027 enregistrées. P2 : … 6 séances de 3 classes sont maintenant marquées "hors période". »

## 7. Accessibilité et responsive

- Tous les champs ont un `label` (les libellés « Du/Au » des tableaux sont `sr-only` mais associés) ; erreurs liées par `aria-describedby` et `role="alert"` ; zone d'impact et message de cohérence en `aria-live="polite"`.
- La période est toujours identifiée par du texte (« P1 »/« P2 ») en plus de la couleur ; la frise a un `role="img"` avec `aria-label`, et les mêmes informations existent en texte (tableau, dates, messages).
- Modales : focus piégé à l'ouverture, Échap ferme, retour du focus au déclencheur ; boutons destructifs jamais en focus initial.
- Contraste ≥ 4.5:1 (mêmes jetons que CLS-02), cibles ≥ 36 px, `prefers-reduced-motion` respecté (squelettes).
- Responsive : sous 860 px la barre latérale passe au-dessus du contenu, les grilles de dates s'empilent, les tableaux défilent horizontalement (`.tw`). Vérifié en largeur 375 px pour la page de création.

## 8. Impacts techniques et API à prévoir (information, pas de décision UX)

**Endpoint manquant (nouveau)**
- `POST /annees-scolaires/{annee}/apercu-impact` — corps : `{ periodes: [{numero, date_debut, date_fin}] }` (et `date_debut/date_fin` de l'année déduits). Réponse : `{ bloquants: [...], periodes: [{numero, avant, apres}], classes_touchees, seances_hors_periode_en_plus, seances_redevenant_dans_periode, classes_demarrant_avant_debut, classes_sans_p2, alertes_p2_creees, alertes_p2_supprimees, calendrier_hors_annee, par_classe: [{classe_id, titre, creneau, periode, nb_seances, dates[]}] }`. Lecture seule, `isStaff`, aucun effet de bord ; doit utiliser les mêmes règles de calcul que `hors_periode` / `alerte_periode_2` (une seule source de vérité).
- `GET /annees-scolaires/proposition?libelle=2027-2028` — dates P1/P2 proposées (calendrier FWB de l'année si importé, sinon règle par défaut) et indicateur `source: fwb|defaut`.
- `POST /annees-scolaires/{annee}/archiver` et `/reactiver` (ou `PUT` statut) avec règle « dates verrouillées si archivée ».

**Modifications de l'existant**
- `AnneeScolaireService::modifier` : **supprimer le 422 « Des sessions dépassent la nouvelle fin de la période »** (contredit CLS-02 RG-4) ; `hors_periode` reste calculé (donc réversible). Conserver les blocages d'incohérence de dates (`ValidePeriodes`).
- Ajouter la **validation de non-chevauchement entre années** (Store/Update) et la **version optimiste** (`updated_at` renvoyé par la ressource, `If-Match`/champ `version` en entrée ; 409 avec `modifie_par` + `modifie_a`).
- `AnneeScolaireResource` / liste : ajouter `classes_count`, `calendrier_count`, `updated_by`, `updated_at`, et `can.delete` avec raison (`raison_non_supprimable`) pour griser le bouton avec explication.
- Erreur de la création de classe / ajout de période pour date hors bornes : code stable `date_hors_bornes_periode` + `{annee_id, numero, debut, fin}` pour que le front affiche le lien contextuel.
- Front : page `AnneesScolairesPage`, `AnneeCreatePage`, `AnneePeriodesPage`, hook de brouillon (`sessionStorage`) pour le retour ; ne passer que par `frontend/src/api/client.js`.
- Tests à prévoir : chevauchement P1/P2 et entre années, aperçu cohérent avec le calcul réel après enregistrement, modification concurrente (409), suppression/archivage avec classes, droits admin/directeur/professeur.
