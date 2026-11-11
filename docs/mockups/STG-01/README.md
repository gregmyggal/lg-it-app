# Mock-ups STG-01 — Stages sur une semaine

**Créé :** 2026-11-11 · **Auteur :** sub-agent UX Expert · **Statut :** Brouillon v0.1 — en attente de validation du directeur
**Mise à jour 2026-11-11 :** le champ « Cours » devient « Thème du stage » (voir Canvas §11).
**Canvas :** `docs/requirements/STG-01-stages-sur-une-semaine.md`

Données d'exemple : stage « Scratch » du lundi 17 au vendredi 21 août 2026, Salle A. Défauts : Matin 9:00–12:00, Après-midi 13:30–16:30.

## 0. Workflow
```
Classes & stages  →  [+ Nouveau ▾] → Stage
   → cours + semaine (+ lieu, créneau par défaut)  → grille des 5 jours (cocher / matin-après-midi)
   → aperçu des séances  → [Créer le stage (5 séances)]
   → bandeau : [Assigner des professeurs] [Ouvrir le stage]
```
Parcours nominal = **4 actions**.

## Écran 1 — Liste « Classes & stages »
```
┌──────────────────────────────────────────────────────────────┐
│ Classes & stages                      [+ Nouveau ▾]          │
│                                        ├ Classe              │
│ [ Tous | Classes | Stages ]            └ Stage               │
│                                                              │
│ À venir                                                      │
│ ┌──────────────────────────────────────────────────────────┐ │
│ │ [Stage] Scratch — 17–21 août 2026        5 séances       │ │
│ │ Salle A · Alice D.                                       │ │
│ └──────────────────────────────────────────────────────────┘ │
│ ┌──────────────────────────────────────────────────────────┐ │
│ │ Scratch — mercredi 14:00–15:30           P1 · 14 séances │ │
│ └──────────────────────────────────────────────────────────┘ │
│ États : chargement · vide (« Aucun stage ») · erreur · données│
└──────────────────────────────────────────────────────────────┘
```
Menu « Contenu » : l'entrée existante devient « Stages (site public) ».

## Écran 2 — Création d'un stage
```
┌──────────────────────────────────────────────────────────────┐
│ Nouveau stage                                                │
│ 1 · Stage                                                    │
│   Thème du stage [ Minecraft Redstone ▾]  (catalogue Stages) │
│   Semaine    [ 17 au 21 août 2026     ▾]  (ramenée au lundi) │
│   Lieu       [ Salle A ]  (dernier lieu repris)              │
│   Créneau par défaut  (•) Matin 9:00–12:00  ( ) Après-midi   │
│                       13:30–16:30   · Autre horaire…         │
│ 2 · Jours                                                    │
│   ☑ Lun 17/8   (•) Matin  ( ) Après-midi                     │
│   ☑ Mar 18/8   (•) Matin  ( ) Après-midi                     │
│   ☑ Mer 19/8   ( ) Matin  (•) Après-midi   ← modifié         │
│   ☐ Jeu 20/8   [Férié]  ⚠ décoché automatiquement            │
│   ☑ Ven 21/8   (•) Matin  ( ) Après-midi                     │
│ Aperçu (en direct)                                           │
│   1 Lun 17/8 · 9:00–12:00      2 Mar 18/8 · 9:00–12:00       │
│   3 Mer 19/8 · 13:30–16:30     4 Ven 21/8 · 9:00–12:00       │
│   4 séances de 3 h                                           │
│   ⚠ Si le jeudi est recoché : « Jeudi 20/8 est férié »       │
│ [Annuler]                       [Créer le stage (4 séances)] │
└──────────────────────────────────────────────────────────────┘
```
Aucun jour coché → bouton désactivé. Congé scolaire : aucune alerte.

## Écran 3 — Fiche du stage
```
┌──────────────────────────────────────────────────────────────┐
│ Classes & stages › Scratch — stage 17–21 août                │
│ [Stage] Scratch · Salle A     [Active]                       │
│ [⧉ Dupliquer] [Archiver] [Supprimer]                         │
│ Séances                                                      │
│  1 Lun 17/8 Matin   Alice D.   [Déplacer][Annuler][Remplacer]│
│  2 Mar 18/8 Matin   Alice D.   …                             │
│  3 Mer 19/8 Après-midi  Bob L.  (remplaçant)  …              │
│ Professeurs                                                  │
│  Alice D. (principale) — 4 séances            [Retirer]      │
│  [+ Assigner un professeur] → récap : « Bob sera assigné     │
│   aux 4 séances » [Confirmer]                                │
│ Liens du cours · Heures : inchangés                          │
└──────────────────────────────────────────────────────────────┘
```

## Écran 4 — Vue professeur (mobile)
```
Mes classes                         Aujourd'hui
┌───────────────────────┐          ┌───────────────────────┐
│ [Stage] Scratch       │          │ Lun 17 août           │
│ 17–21 août · Salle A  │          │ Scratch — Séance 1    │
│ 4 séances             │          │ Matin 9:00–12:00      │
└───────────────────────┘          │ [Saisir mes heures]   │
                                   └───────────────────────┘
```
La saisie des heures et le défraiement sont inchangés.

## Écran 5 (optionnel) — Dupliquer un stage
Création pré-remplie (`?source=`), seule la semaine est à changer ; professeurs non repris (comme CLS-07).
