# DEF-02 — Défraiement d'une séance à durée variable (normale vs bis)

**Statut :** livré (backend + UI + tests) ; maquette validée (maquette `docs/mockups/DEF-02/01-defraiement.html`) · remplace la notion « heures défrayables fixes » de DEF-01.

## Contexte
La durée d'une séance varie (séances bis, horaires différents). Le défraiement ne peut plus être une valeur fixe (2 h) : il dépend de la durée réelle de la séance.

## Règles (décisions validées)
- **Séance normale** (`bis_rang = 0`) : défrayé = durée de la séance (`heure_fin − heure_debut`, quart d'heure) + **temps de préparation**.
- **Séance bis** (`bis_rang > 0`) : défrayé = durée de la séance seule, **sans préparation**.
- Le « temps de préparation » remplace « heures défrayables » : défaut global par année (0,5 h = 2 h − 1,5 h), héritage d'année, historique ; surcharge par cours conservée (vide = défaut). Bornes 0 – 4 h, pas de 15 min.
- Le professeur peut toujours modifier la valeur proposée à l'encodage (0,5 – 24 h).
- Saisies déjà encodées (brouillons compris) : jamais modifiées. Seules les séances non encodées sont recalculées à la volée.
- Durée d'une séance : `TimesheetService::dureeSeance()` (bornée 0,5 – 24 h).

## Parcours impactés
| Écran | Changement |
|---|---|
| Paramètres timesheets (direction/admin) | Champ « Heures défrayables » → « Temps de préparation par séance normale » (0,5 h) ; explication « séance bis : pas de préparation » |
| Fiche cours | « Heures défrayables par séance » → « Temps de préparation (séance normale) », badge hérité/personnalisé |
| Création de classe / détail de classe | « Séance 1 h 30 · Défrayé 2 h » devient « Séance 1 h 30 + prépa 30 min = 2 h » ; colonne « Défrayé » par séance (bis : durée seule) |
| Encodage prof (mois + modale) | Valeur proposée = durée + prépa (normale) ou durée (bis) ; aide « 1 h 30 de séance + 30 min de préparation » / « Bis : sans préparation » |
| Alerte d'impact plafond | Simulation sur le nouveau calcul par séance |

## Tranche verticale
DB : renommer `heures_defrayables` → `temps_preparation` (paramètres + cours), valeurs migrées (`ancienne − duree_seance_defaut`, min 0, ex. 2 − 1,5 = 0,5 ; surcharge cours idem). API : `TimesheetService::heuresDefrayables()` calcule par séance ; ressources exposent `duree_seance`, `temps_preparation`, `heures_defrayables` (calculé). UI : écrans ci-dessus. Tests : normale (durée+prépa), bis (durée seule), surcharge cours, saisie existante inchangée, impact.

## Questions ouvertes
- Migration de la surcharge cours : `ancienne − 1,5 h` (hypothèse) ?
- `duree_seance_defaut` reste purement un préremplissage de l'heure de fin.
