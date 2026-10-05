# Mock-ups CLS-07 — Dupliquer une classe sur un autre jour et/ou un autre horaire

**Créé :** 2026-10-04 · **Auteur :** sub-agent UX Expert · **Statut :** Brouillon v0.2 — en attente de validation des mock-ups. Arbitrages du directeur intégrés : démarrage dans la **même semaine que la source, même passé** (avertissement non bloquant), **aucun professeur repris**, congés forcés de la source signalés sans pré-cocher, autre année = dates vidées. Doublon = avertissement (à valider ici).
**Canvas :** `docs/requirements/CLS-07-dupliquer-classe.md`
**Écrans touchés :** fiche classe (1 bouton) et écran existant « Nouvelle classe » en **mode duplication** (`/admin/classes/nouvelle?source={id}`). Aucun nouvel écran.

Données d'exemple (à vérifier sur la base de recette) : année 2026-2027, P1 01/09/2026 → 19/02/2027, P2 08/03/2027 → 02/07/2027, calendrier FWB importé. Aujourd'hui : 04/10/2026.
Classe source **S** : « Scratch — mercredi 14:00–15:30 », Salle A. P1 Scratch dès le 14/10 : 14/10, 04/11, 18/11, 25/11, 02/12, 09/12, 16/12, 06/01, 13/01, 20/01, 27/01, 03/02, 10/02, 17/02. P2 Python dès le 10/03 : 10/03 … 23/06. Ajustements : P1 · 2 déplacée au jeudi 05/11 ; P1 · 6 (09/12) annulée, bis le samedi 12/12. **S2** : identique, P1 démarrée le 16/09.

---

## 0. Workflow en un coup d'œil

```
Fiche classe « Scratch — mercredi 14:00–15:30 »
   └─ [⧉ Dupliquer la classe]                                   ← NOUVEAU (en-tête)
        └─ « Dupliquer une classe » = écran Nouvelle classe pré-rempli
             1 · Groupe et créneau   → focus sur Jour (seul choix attendu)
             2 · Périodes            → dates transposées au nouveau jour (même semaine)
             Aperçu : N° · Source · Copie · Écart + cases Forcer   ← colonnes NOUVELLES
             Avertissements non bloquants : démarrage passé, doublon
             [Annuler]                      [Créer la copie (28 séances)]
        └─ Bandeau succès : [Assigner des professeurs] [Ouvrir la nouvelle classe]
                            [Revenir à la classe source]
```

Parcours nominal = **4 actions** : Dupliquer → choisir le jour → lire l'aperçu (forcer si besoin) → Créer la copie.

---

## Parcours A — Fiche classe : point d'entrée

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ Scolarité › Classes › Scratch — mercredi 14:00–15:30                         │
│ 🏫 Scratch — mercredi 14:00–15:30                                            │
│ 2026-2027 · Salle A                                                          │
│                                                                              │
│ [Active]  [Voir dans le calendrier]  [⧉ Dupliquer la classe]                 │
│           [Archiver la classe (P1 et P2)]  [Supprimer la classe]             │
└──────────────────────────────────────────────────────────────────────────────┘
```
- Visible pour admin et staff, y compris sur une classe archivée. Infobulle : « Créer une nouvelle classe avec les mêmes cours et le même lieu, sur un autre jour ou un autre horaire. »
- C'est un lien (pas une modale) : ouverture dans un nouvel onglet possible.

---

## Parcours B — Écran de duplication

### B1 · Chargement

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ Scolarité › Classes › Scratch — mercredi 14:00–15:30 › Dupliquer             │
│ 🏫 Dupliquer une classe                                                      │
│                                                                              │
│   ⏳ Préparation de la copie de « Scratch — mercredi 14:00–15:30 »…           │
│   ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░                                               │
└──────────────────────────────────────────────────────────────────────────────┘
```

### B2 · Ouverture : tout est pré-rempli, doublon signalé

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ 🏫 Dupliquer une classe                                                      │
│ ℹ Copie de « Scratch — mercredi 14:00–15:30 » (2026-2027, Salle A).          │
│   Choisissez le nouveau jour et/ou le nouvel horaire : les dates de          │
│   démarrage suivent. Les 14 séances de chaque période sont régénérées ;      │
│   les déplacements, annulations et bis de la classe source ne sont pas       │
│   copiés. Les professeurs ne sont pas repris : vous les assignerez après     │
│   la création.                                    [Voir la classe source ↗]  │
├────────────────────────────────────┬─────────────────────────────────────────┤
│ 1 · Le groupe et le créneau        │ ⚠ Doublon probable (non bloquant)       │
│ Année scolaire * [2026-2027 ▾]     │ Même cours, même jour et même horaire   │
│ Jour *  [Mercredi ▾] ◀ focus       │ que « Scratch — mercredi 14:00–15:30 »  │
│ Lieu    [Salle A        ]          │ (la classe source). Changez le jour ou  │
│ Début * [14:00]  Fin * [15:30]     │ l'horaire, ou créez quand même s'il     │
│                                    │ s'agit d'un second groupe.              │
│ 2 · Périodes à ouvrir maintenant   │                                         │
│ ☑ Période 1  ☑ Ajouter la période 2│ Aperçu (P1 · Scratch, P2 · Python)      │
│ ┌ P1 · 14 séances ──────────────┐  │ … tableau identique à la source …       │
│ │ Cours * [Scratch ▾]           │  │                                         │
│ │ Démarrage * [14/10/2026]      │  │                                         │
│ │ Même semaine que la classe    │  │                                         │
│ │ source (mercredi 14/10).      │  │                                         │
│ └───────────────────────────────┘  │                                         │
│ ┌ P2 · 14 séances ──────────────┐  │                                         │
│ │ Cours * [Python ▾]            │  │                                         │
│ │ Démarrage * [10/03/2027]      │  │                                         │
│ └───────────────────────────────┘  │                                         │
│                                    │                                         │
│ [Annuler]  [Créer quand même (28 séances)]                                   │
└────────────────────────────────────┴─────────────────────────────────────────┘
```
- **À valider (Q3) :** le doublon est un avertissement, jamais un refus. Il est visible dès l'ouverture, ce qui indique ce qu'il reste à faire, et disparaît dès que le jour ou l'horaire ne chevauche plus. Le bouton devient « Créer quand même (N séances) » tant qu'il est affiché.
- « Annuler » ramène à la fiche de la classe source.

### B3 · Jour changé en jeudi : aperçu comparatif

Colonne de gauche après le choix « Jeudi » :
```
│ Jour *  [Jeudi ▾]                                                            │
│ P1 · Démarrage * [15/10/2026]                                                │
│      Même semaine que la classe source (mercredi 14/10).                     │
│ P2 · Démarrage * [11/03/2027]                                                │
│      Même semaine que la classe source (mercredi 10/03).                     │
```

Colonne de droite (aperçu) :
```
┌ Période 1 · Scratch ─────────────────────────────────────────────────────────┐
│ Copie : 14 séances du 15/10 au 11/02 (4 dates sautées)                       │
│ Source : du 14/10 au 17/02 (5 dates sautées)                                 │
│ ℹ La copie finit 1 semaine plus tôt : l'Armistice (11/11) ne tombe pas       │
│   un jeudi.                                                                  │
│                                                                              │
│ Forcer │ N°      │ Source (mercredi)                │ Copie (jeudi) │ Écart  │
│ ───────┼─────────┼──────────────────────────────────┼───────────────┼─────── │
│        │ P1 · 1  │ 14/10                            │ 15/10         │ même sem.│
│   ☐    │ —       │                                  │ 22/10 ⏭ Vacances d'automne │
│   ☐    │ —       │                                  │ 29/10 ⏭ Vacances d'automne │
│        │ P1 · 2  │ 05/11 · déplacée (était 04/11)   │ 05/11         │ même jour│
│        │ P1 · 3  │ 18/11                            │ 12/11         │ 1 sem. plus tôt │
│        │ P1 · 4  │ 25/11                            │ 19/11         │ 1 sem. plus tôt │
│        │ P1 · 5  │ 02/12                            │ 26/11         │ 1 sem. plus tôt │
│        │ P1 · 6  │ 09/12 · annulée · bis 12/12      │ 03/12         │ 1 sem. plus tôt │
│        │         │   (non copiés)                   │               │        │
│        │ P1 · 7  │ 16/12                            │ 10/12         │ 1 sem. plus tôt │
│        │ P1 · 8  │ 06/01                            │ 17/12         │ 3 sem. plus tôt │
│   ☐    │ —       │                                  │ 24/12 ⏭ Vacances d'hiver │
│   ☐    │ —       │                                  │ 31/12 ⏭ Vacances d'hiver │
│        │ P1 · 9  │ 13/01                            │ 07/01         │ 1 sem. plus tôt │
│        │ …       │ …                                │ …             │        │
│        │ P1 · 14 │ 17/02                            │ 11/02         │ 1 sem. plus tôt │
└──────────────────────────────────────────────────────────────────────────────┘
┌ Période 2 · Python ── (replié, résumé visible) ──────────────────────── ▸ ──┐
│ Copie : 14 séances du 11/03 au 24/06 (2 dates sautées : 29/04, 06/05)         │
│ Source : du 10/03 au 23/06 (2 dates sautées) · même semaine pour les 14      │
└──────────────────────────────────────────────────────────────────────────────┘
                                         [Annuler]  [Créer la copie (28 séances)]
```
- Les dates sautées gardent la case **Forcer** de CLS-05 v0.2 (1re colonne). La colonne Source est vide sur ces lignes.
- Écart en texte : « même jour », « même sem. », « N sem. plus tôt / plus tard ».
- Signalements côté source : « déplacée (était …) », « annulée », « bis … (non copié) », « pendant un congé (libellé) ».
- Résumé des écarts en une phrase au-dessus du tableau ; P2 repliée par défaut si toutes ses séances sont dans la même semaine que la source.

### B4 · Forcer une date sautée de la copie

Clic sur ☐ en face de 22/10 :
```
│   ☑    │ P1 · 2  │ 05/11 · déplacée (était 04/11)   │ 22/10 ✓ Forcée · Vacances d'automne │ 2 sem. plus tôt │
│   ☐    │ —       │                                  │ 29/10 ⏭ Vacances d'automne │
│        │ P1 · 3  │ 18/11                            │ 05/11         │ 2 sem. plus tôt │
│        │ …                                                                   │
│        │ P1 · 14 │ 17/02                            │ 04/02         │ 2 sem. plus tôt │
```
Résumé : « Copie : 14 séances du 15/10 au 04/02 (3 dates sautées, 1 forcée) ».

### B5 · Séance tenue pendant un congé dans la source (source S3, copie le mercredi 16:00–17:30)

```
│ Forcer │ N°      │ Source (mercredi 14:00)               │ Copie (mercredi 16:00)          │
│   ☐    │ —       │                                       │ 11/11 ⏭ Armistice               │
│        │         │                                       │ ⓘ forcée dans la source (P1 · 3) │
│        │ P1 · 3  │ 11/11 · pendant un congé (Armistice)  │ 18/11                            │
```
- La date est **sautée par défaut** ; la case n'est **pas** cochée. La mention, à côté de la case, indique qu'un forçage a été fait sur la source ; un clic suffit pour le reproduire.

### B6 · Source déjà démarrée : démarrage dans le passé (S2 → jeudi)

Colonne de gauche :
```
│ P1 · Démarrage * [17/09/2026]                                                │
│      Même semaine que la classe source (mercredi 16/09).                     │
│      Date passée : voir l'avertissement de l'aperçu.                         │
```
Colonne de droite, au-dessus de l'aperçu :
```
┌──────────────────────────────────────────────────────────────────────────────┐
│ ⚠ Avertissement (non bloquant)                                               │
│ La copie démarre dans le passé : 3 séances seront créées à des dates déjà    │
│ passées (17/09, 24/09, 01/10). Vous pouvez choisir une date de démarrage     │
│ plus tardive.                                                                │
└──────────────────────────────────────────────────────────────────────────────┘
│ Forcer │ N°      │ Source (mercredi)   │ Copie (jeudi)        │ Écart     │
│        │ P1 · 1  │ 16/09               │ 17/09 · passée       │ même sem. │
│        │ P1 · 2  │ 23/09               │ 24/09 · passée       │ même sem. │
│        │ P1 · 3  │ 30/09               │ 01/10 · passée       │ même sem. │
│        │ P1 · 4  │ 07/10               │ 08/10                │ même sem. │
│        │ …                                                               │
                                         [Annuler]  [Créer la copie (28 séances)]
```
- Le bouton reste **actif** et garde son libellé : rien n'est bloqué.
- Si le directeur saisit le 08/10 : l'avertissement disparaît et la date n'est plus recalculée au changement de jour.

### B7 · Autres variantes de dates

Date transposée hors des bornes de la période :
```
│ P2 · Démarrage * [15/03/2027]                                                │
│      Le lundi de la même semaine (08/03) est avant le début de la période 2  │
│      (09/03) : premier lundi possible proposé.                               │
```
Source à une seule période (S4) :
```
│ ☑ Période 1   ☐ Ajouter la période 2 maintenant                              │
│ La classe source n'a que la période 1. Si vous ajoutez la période 2,         │
│ saisissez sa date : elle ne sera pas comparée à la source.                   │
```
P2 annulée dans la source (S5) :
```
│ ℹ La période 2 de la classe source est annulée : elle n'est pas reprise.     │
```
Changement d'année :
```
│ ⚠ Les dates de la classe source ne sont pas transposées sur une autre        │
│   année : saisissez les dates de démarrage. La comparaison est retirée.      │
```

---

## Les 4 états

| État | Rendu |
|---|---|
| **Chargement** | B1 (proposition) ; aperçu : squelette « Calcul des dates… », bouton désactivé (existant) |
| **Vide** | Source sans période active : `EmptyBlock` « Rien à dupliquer : toutes les périodes de cette classe sont annulées. » + [Créer une classe vide] [Revenir à la classe] |
| **Erreur** | 404 : `ErrorBlock` « Cette classe n'existe plus. » + [Créer une classe vide] ; réseau : « Impossible de préparer la copie. Vérifiez votre connexion puis réessayez. » + [Réessayer] ; 422 à la création : bandeau existant « La classe n'a pas pu être créée. Aucune séance n'a été générée… », saisie conservée |
| **Succès** | voir ci-dessous |

### Confirmation (succès)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ ✅ Classe « Scratch — jeudi 14:00–15:30 » créée à partir de                   │
│    « Scratch — mercredi 14:00–15:30 ».                                       │
│    P1 : 14 séances du 15/10 au 11/02 (4 dates sautées) ·                     │
│    P2 : 14 séances du 11/03 au 24/06 (2 dates sautées).                      │
│    Aucun professeur n'est encore assigné.                                    │
│                                                                              │
│    [Assigner des professeurs]  [Ouvrir la nouvelle classe]                   │
│    [Revenir à la classe source]                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```
- « Assigner des professeurs » (bouton primaire) ouvre la fiche de la nouvelle classe, positionnée sur la section Professeurs.
- Avec forçage : « … du 15/10 au 04/02 (3 dates sautées), dont 1 forcée sur un congé ».
- Avec démarrage passé : « P1 : 14 séances du 17/09 au 14/01, dont 3 à des dates déjà passées ».
- Toast identique (première phrase). Fiche de la copie : sous-titre « Dupliquée de Scratch — mercredi 14:00–15:30 ».

---

## Accessibilité et mobile

- Colonnes « Source » et « Écart » : `<th scope="col">` ; écarts, « passée » et signalements en texte, jamais seulement en couleur.
- Avertissements doublon et démarrage passé : `role="status"` ; résumé de l'aperçu en `aria-live="polite"` (existant).
- < 600 px : colonne Écart masquée, la date source s'affiche sous la date de la copie (« source : 18/11 »).

---

## Points à valider avec ces maquettes

- [ ] Point d'entrée (bouton sur la fiche) et réutilisation de l'écran Nouvelle classe
- [ ] Comparaison source → copie (colonnes, écarts, signalements)
- [ ] Avertissement « démarrage dans le passé » (B6)
- [ ] **Doublon = avertissement non bloquant + « Créer quand même » (Q3, non arbitré)**
- [ ] Séances passées de la copie et assignation ultérieure des professeurs (N-1 du Canvas)
- [ ] Textes validés

**Validé par :** *à remplir* · **Date :** *à remplir*
