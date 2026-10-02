# DEF-01 — Heures défrayables par séance

**Statut :** toutes les tranches livrées · **Maquette :** `docs/mockups/DEF-01/01-heures-defrayables.html` · Décisions validées (recommandations de l'UX expert) : défaut par année civile ; durée de séance par défaut (1 h 30) paramétrable ; pas de surcharge par classe en v1 ; changement appliqué uniquement aux séances non encodées ; plafond journalier : avertir sans bloquer ; co-enseignants : mêmes heures défrayables chacun.

## Contexte
Une séance de classe dure 1 h 30 au calendrier, mais le professeur est défrayé 2 h (cours + préparation). Jusqu'ici, les heures proposées à l'encodage étaient fin − début. Il faut deux notions distinctes : la **durée de séance** (calendrier) et les **heures défrayables** (base de l'encodage et du défraiement), paramétrables par défaut et par cours.

## Règles
- Résolution : saisie du professeur > cours (T2) > défaut global de l'année > 2 h. Calcul à la volée pour les séances non encodées ; une saisie déjà encodée (brouillon compris) ne change jamais.
- Défaut global par année civile (héritage de l'année précédente, historique avant/après), comme les plafonds. Bornes 0,5 h – 8 h, pas de 15 min.
- La durée de séance par défaut (1,5 h) ne porte aucune règle de défraiement : simple préremplissage de l'heure de fin à la création d'une classe (T3).

## T1 — Défaut global
- Table `timesheet_parametres` : `heures_defrayables` (2,00) et `duree_seance_defaut` (1,50) ; historique `heures_defrayables_*` / `duree_seance_*`.
- `TimesheetParametreService::heuresDefrayables()` / `dureeSeanceDefaut()` ; `PUT /api/timesheet-parametres/{annee}` accepte les deux champs (optionnels : valeur existante conservée). Direction et admin uniquement.
- `TimesheetService::dureeParDefaut()` renvoie désormais les heures défrayables de l'année de la séance (clé API `duree_par_defaut` conservée).
- UI : carte « Heures de séance et heures défrayables » dans « Paramètres timesheets » (affichage « 1 h 30 », calcul « 1 h 30 de séance + 30 min de préparation = 2 h défrayées », historique).
- Tests : `HeuresDefrayablesTest` (7 cas) ; `TimesheetMoisTest` et `TimesheetSessionTest` mis à jour (séance de 3 h au calendrier → 2 h proposées).

## T2 — Surcharge par cours
- Colonne `cours.heures_defrayables` (nullable : vide = défaut global de l'année). Validation 0,5–8 h par pas de 15 min (règle `PasDeQuinzeMinutes`, partagée avec les paramètres). Modification réservée à la direction et à l'admin (policy des cours) ; `null` = retour au défaut.
- Résolution des heures proposées : cours > défaut global de l'année > 2 h. Le défaut global ne joue plus quand le cours a sa valeur ; une saisie déjà encodée ne change jamais.
- UI (modale d'un cours) : champ « Heures défrayables par séance » avec placeholder « Défaut : 2 h », badge « Hérité du défaut » / « Personnalisé », bouton « Revenir au défaut », lien vers le défaut global, erreurs inline ; la carte du cours affiche « Défrayé 2 h 30 par séance » quand il est personnalisé.
- Tests : 4 cas ajoutés à `HeuresDefrayablesTest` (surcharge et retour au défaut, priorité du cours, absence de rétroactivité, validation et droits).

## T3 — Création de classe, détail de classe et encodage
- API : `GET /api/heures-defrayables?annee=&cours_id=` (staff : durée de séance par défaut, heures défrayables, `source` cours|defaut) ; `ClasseResource` expose `duree_seance` et `heures_defrayables` ; `CourseSessionResource` expose `heures_defrayables` par séance ; les API du professeur (`mon-mois`, `mes-classes/{id}/sessions`) ajoutent `duree_seance` (durée du calendrier) à `duree_par_defaut` (heures défrayables). La règle de résolution reste uniquement côté serveur.
- Création de classe : heure de fin préremplie = début + durée de séance par défaut (1 h 30), qui suit le début tant que la fin n'a pas été saisie à la main ; bandeau « Séance 1 h 30 · Défrayé 2 h (défaut global | valeur définie sur le cours) … 14 séances × 2 h = 28 h défrayables ».
- Détail de classe : « Séance 3 h · Défrayé 2 h (défaut global) » dans l'en-tête et colonne « Défrayé » (badge, infobulle sur l'origine ; « — » pour une séance annulée).
- Côté professeur : colonne et champ « Heures défrayées », info-bulle « 1 h 30 de séance + préparation », mention « Valeur standard : 2 h » quand il modifie la valeur (tableau du mois et modale d'encodage).
- Tests : 3 cas ajoutés à `HeuresDefrayablesTest` (endpoint de défauts et droits, ressources classe et séance, `duree_seance` côté professeur).

## T4 — Alerte d'impact sur le plafond journalier (non bloquante)
- `POST /api/heures-defrayables/impact` (staff) : `annee`, `portee` (global | cours), `cours_id`, `heures` → simulation SANS écriture : nombre de jours professeur au-dessus du plafond journalier aujourd'hui (`avant`), avec la valeur proposée (`apres`), jours nouvellement concernés (`nouveaux`) et jusqu'à 5 exemples (professeur, date, montant). Calcul par `HeuresDefrayablesImpactService` : séances non annulées de l'année, hors séances déjà encodées par le professeur, Σ heures défrayables × tarif du professeur ce jour-là.
- UI : bandeau d'avertissement (`ImpactDefrayage`) sous le champ « Heures défrayables » des paramètres (défaut global) et de la fiche d'un cours : « Avec 2 h 30 par séance, 17 jours de plus dépasseraient le plafond de 44,02 € par jour (aujourd'hui : 0, ensuite : 17). Rien n'est bloqué : le lissage permet de répartir ces montants. » + exemples. Rien n'est affiché si aucun jour n'est nouvellement concerné ; une erreur de simulation ne gêne jamais l'enregistrement.
- Tests : 3 cas dans `HeuresDefrayablesTest` (jours au-dessus du plafond et absence d'écriture, portée cours et saisies existantes ignorées, validation et droits).
