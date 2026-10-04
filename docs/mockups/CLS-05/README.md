# Mock-ups CLS-05 — Forcer les séances sur congés/fériés

> **⚠ Remplacé (v0.2, 2026-10-04)** : le panneau « Congés affectant vos séances » ci-dessous n'est pas implémenté (il ne pouvait rien afficher avant un premier forçage). Implémentation retenue : une case « Forcer » sur chaque date sautée directement dans le tableau d'aperçu, forçage **date par date** (`dates_forcees[]`). Voir le Canvas CLS-05 v0.2.

**Créé :** 2026-10-04 · **Status :** Brouillon (à valider)

---

## État 1 : Aucun congé n'impacte les séances

**Contexte :** L'utilisateur a rempli le formulaire (Année, Jour, Horaire, Lieu, Période 1 : Cours + Date). L'aperçu se charge.

```
┌──────────────────────────────────────────────────────┐
│ 2 · Périodes à ouvrir maintenant                     │
│                                                       │
│ Période 1 · React · 14 séances                      │
│ 14 séances du 15/01 au 02/04                        │
│                                                       │
│ ┌──────────────────────────────────────────────────┐ │
│ │ Séance   │ Date                                   │ │
│ ├──────────┼──────────────────────────────────────┤ │
│ │ P1 · 1   │ 15/01 ✓                               │ │
│ │ P1 · 2   │ 22/01 ✓                               │ │
│ │ P1 · 3   │ 29/01 ✓                               │ │
│ │ ...      │ ...                                   │ │
│ │ P1 · 14  │ 02/04 ✓                               │ │
│ └──────────────────────────────────────────────────┘ │
│                                                       │
│ ❌ Panneau synthèse ABSENT (car aucun congé         │
│    n'impacte les séances)                           │
│                                                       │
│                                                       │
│                      [Annuler]  [Créer la classe]   │
└──────────────────────────────────────────────────────┘
```

---

## État 2 : Congés affichés, aucun coché (par défaut)

**Contexte :** Les dates sautées existent (calendrier rempli). L'utilisateur voit le panneau synthèse avec tous les checkboxes décochés.

```
┌──────────────────────────────────────────────────────┐
│ 2 · Périodes à ouvrir maintenant                     │
│                                                       │
│ Période 1 · React · 14 séances                      │
│ 14 séances du 15/01 au 02/04                        │
│                                                       │
│ ┌──────────────────────────────────────────────────┐ │
│ │ Séance   │ Date                                   │ │
│ ├──────────┼──────────────────────────────────────┤ │
│ │ P1 · 1   │ 15/01 ✓                               │ │
│ │ —        │ 22/01 ⏭ Sautée                       │ │
│ │          │ (Vacances d'hiver)                   │ │
│ │ P1 · 2   │ 29/01 ✓                               │ │
│ │ —        │ 14/04 ⏭ Sautée                       │ │
│ │          │ (Lundi de Pâques)                    │ │
│ │ P1 · 3   │ 21/01 ✓                               │ │
│ │ ...      │ ...                                   │ │
│ │ P1 · 14  │ 02/04 ✓                               │ │
│ └──────────────────────────────────────────────────┘ │
│                                                       │
│ ⚠️ NOUVEAU — Congés affectant vos séances           │ │
│ ┌──────────────────────────────────────────────────┐ │
│ │ 2 entrées du calendrier décalent au moins        │ │
│ │ 1 séance :                                       │ │
│ │                                                  │ │
│ │ 1. 🎄 Vacances d'hiver                          │ │
│ │    22/01 – 26/01                                │ │
│ │    • Décale : 1 séance                          │ │
│ │    ☐ Forcer malgré tout                         │ │
│ │                                                  │ │
│ │ 2. 🎉 Lundi de Pâques                           │ │
│ │    14/04                                         │ │
│ │    • Décale : 1 séance                          │ │
│ │    ☐ Forcer malgré tout                         │ │
│ │                                                  │ │
│ │ Ces congés seront ignorés seulement si vous     │ │
│ │ cochez la case correspondante. Les séances      │ │
│ │ forcées apparaîtront dans l'aperçu ci-dessus.   │ │
│ └──────────────────────────────────────────────────┘ │
│                                                       │
│                      [Annuler]  [Créer la classe]   │
└──────────────────────────────────────────────────────┘
```

---

## État 3 : Forçage partiel cochée (Vacances d'hiver seulement)

**Contexte :** L'utilisateur coche « Forcer » pour les vacances d'hiver. L'aperçu se met à jour en direct.

```
┌──────────────────────────────────────────────────────┐
│ 2 · Périodes à ouvrir maintenant                     │
│                                                       │
│ Période 1 · React · 14 séances                      │
│ 14 séances du 15/01 au 02/04                        │
│                                                       │
│ ┌──────────────────────────────────────────────────┐ │
│ │ Séance   │ Date                                   │ │
│ ├──────────┼──────────────────────────────────────┤ │
│ │ P1 · 1   │ 15/01 ✓                               │ │
│ │ P1 · 2   │ 22/01 ✓  ← forcée (était sautée)    │ │
│ │ P1 · 3   │ 29/01 ✓                               │ │
│ │ —        │ 14/04 ⏭ Sautée                       │ │
│ │          │ (Lundi de Pâques)                    │ │
│ │ P1 · 4   │ 21/04 ✓                               │ │
│ │ ...      │ ...                                   │ │
│ │ P1 · 14  │ 02/05 ✓                               │ │
│ └──────────────────────────────────────────────────┘ │
│                                                       │
│ ⚠️ Congés affectant vos séances                     │
│ ┌──────────────────────────────────────────────────┐ │
│ │ 1. 🎄 Vacances d'hiver                          │ │
│ │    22/01 – 26/01                                │ │
│ │    • Décale : 1 séance → FORCÉE ✓               │ │
│ │    ☑ Forcer malgré tout  ← COCHÉ                │ │
│ │                                                  │ │
│ │ 2. 🎉 Lundi de Pâques                           │ │
│ │    14/04                                         │ │
│ │    • Décale : 1 séance                          │ │
│ │    ☐ Forcer malgré tout                         │ │
│ │                                                  │ │
│ │ Les séances forcées s'affichent en vert dans    │ │
│ │ l'aperçu ci-dessus.                             │ │
│ └──────────────────────────────────────────────────┘ │
│                                                       │
│                      [Annuler]  [Créer la classe]   │
└──────────────────────────────────────────────────────┘
```

---

## État 4 : Classe créée avec forçages

**Contexte :** Après clic [Créer la classe], confirmation affichée.

```
┌──────────────────────────────────────────────────────┐
│                                                       │
│ ✅ Classe « React — mercredi — 2026-2027, P1 »      │
│    créée.                                           │
│                                                       │
│ P1 : 14 séances du 15/01 au 02/05                  │
│ (1 séance créée malgré congés : vacances d'hiver)  │
│                                                       │
│        [Ouvrir la classe]  [Créer une autre classe] │
│                                                       │
└──────────────────────────────────────────────────────┘
```

---

## État 5 : Deux périodes, forçages différents (P1 ≠ P2)

**Contexte :** Classe avec P1 et P2. P1 force vacances d'hiver, P2 ne force rien.

```
┌──────────────────────────────────────────────────────┐
│ Période 1 · React · 14 séances                      │
│ 14 séances du 15/01 au 02/04                        │
│                                                       │
│ ┌──────────────────────────────────────────────────┐ │
│ │ Séance   │ Date                                   │ │
│ │ P1 · 2   │ 22/01 ✓  ← forcée                     │ │
│ │ ...      │ ...                                   │ │
│ └──────────────────────────────────────────────────┘ │
│                                                       │
│ ⚠️ Congés affectant P1                              │ │
│ ┌──────────────────────────────────────────────────┐ │
│ │ ☑ 🎄 Vacances d'hiver (22/01–26/01)              │ │
│ │    → 1 séance forcée ✓                           │ │
│ └──────────────────────────────────────────────────┘ │
│                                                       │
│ ═════════════════════════════════════════════════════ │
│                                                       │
│ Période 2 · React · 14 séances                      │
│ 14 séances du 03/04 au 25/06                        │
│                                                       │
│ ┌──────────────────────────────────────────────────┐ │
│ │ Séance   │ Date                                   │ │
│ │ P2 · 1   │ 03/04 ✓                               │ │
│ │ —        │ 14/04 ⏭ Sautée                       │ │
│ │          │ (Lundi de Pâques)                    │ │
│ │ P2 · 2   │ 17/04 ✓                               │ │
│ │ ...      │ ...                                   │ │
│ └──────────────────────────────────────────────────┘ │
│                                                       │
│ ⚠️ Congés affectant P2                              │ │
│ ┌──────────────────────────────────────────────────┐ │
│ │ ☐ 🎉 Lundi de Pâques (14/04)                     │ │
│ │    • Décale : 1 séance                          │ │
│ │    ☐ Forcer malgré tout                         │ │
│ └──────────────────────────────────────────────────┘ │
│                                                       │
│                      [Annuler]  [Créer la classe]   │
└──────────────────────────────────────────────────────┘
```

---

## Interactions principales

### Interaction 1 : Cocher « Forcer »
- **Avant :** séance barré (⏭ Sautée) dans le tableau
- **Après (immédiat) :** 
  - Séance apparaît normal (P1 · N)
  - Case cochée ☑
  - Label du congé changes à « → FORCÉE ✓ »
  - Les autres séances peuvent être renumérotées si nécessaire (toujours 1-14)

### Interaction 2 : Décocher « Forcer »
- **Avant :** séance normal, case cochée ☑
- **Après :** séance re-sautée (⏭), case décochée ☐

### Interaction 3 : Forçer plusieurs congés
- Le panneau synthèse reste lisible (≤5 congés attendu)
- Chaque coché/décoché met à jour l'aperçu immédiatement
- Les séances restent numérotées 1-14

---

## Cas limites & notes

1. **Aucun congé impactant** → Panneau synthèse ABSENT (pas d'encombrement)
2. **Tous les congés cochés** → Aperçu affiche 14 séances normales (aucune barré)
3. **Calendrier vide** → Aucun congé, panneau absent, aperçu affiche 14 séances (existant)
4. **P1 et P2 indépendantes** → chacune a son propre panneau synthèse et checkboxes
5. **Toast post-création** → mentionne le nombre de séances forcées (optionnel mais recommandé)

---

## Validation requise

- [ ] Visuels validés par Product Owner / UX
- [ ] Textes français validés (terminologie métier)
- [ ] Comportement de mise à jour aperçu confirmé
- [ ] Cas limites testés (aucun congé, tous forcés, etc.)

**Validé par :** *à remplir* · **Date :** *à remplir*
