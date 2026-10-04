# Mock-ups CLS-06 — Déplacer une séance et décaler les séances suivantes

**Créé :** 2026-10-04 · **Auteur :** sub-agent UX Expert · **Statut :** Brouillon v0.3 — en attente de validation. Arbitrages du directeur intégrés : cascade vers P2 (date de démarrage P2 alignée), arrêt sur séance verrouillée, **chevauchement = avertissement (jamais de refus)**, fin d'année = avertissement, case cochée par défaut
**Canvas :** `docs/requirements/CLS-06-replanifier-seances-suivantes.md`
**Écran modifié :** modale existante « Ajuster la séance » (`SessionAdjustModal.jsx`), mode **Déplacer à une autre date**. Aucun nouvel écran, aucun nouveau bouton dans le tableau des sessions.

Données d'exemple (toutes les maquettes) : classe **Scratch — mercredi 14:00–15:30 — 2026-2027**. P1 du 01/09/2026 au 29/01/2027, séances P1 · 1 à 14 : 16/09, 23/09, 30/09, 07/10, 14/10, 04/11, 18/11, 25/11, 02/12, 09/12, 16/12, 06/01, 13/01, 20/01. P2 du 01/02/2027 au 25/06/2027, séances P2 · 1 à 14 : 03/02, 10/02, 03/03, 10/03, 17/03, 24/03, 31/03, 07/04, 14/04, 21/04, 12/05, 19/05, 26/05, 02/06. Congés : Toussaint 19/10–30/10, Armistice 11/11, Noël 21/12–01/01, Détente 15/02–26/02, Printemps 26/04–07/05. Aujourd'hui : 04/10/2026.

---

## 0. Workflow en un coup d'œil

```
Tableau des sessions ── [Ajuster] sur P1 · 4
   └─ Modale « Ajuster la P1 · Séance 4 — mercredi 7 octobre 2026 »
        ◉ Déplacer à une autre date            (présélectionné, existant)
        Nouvelle date [14/10/2026]  Début [14:00]  Fin [15:30]   (existant)
        ☑ Décaler aussi les séances suivantes (P1 · 5 à 14)   ← NOUVEAU, cochée par défaut
        ┌ Aperçu avant → après ┐                               ← NOUVEAU (chargé à la saisie)
        Conséquence (réécrite)
        [Fermer sans modifier]          [Déplacer 11 séances]   ← libellé dynamique
   └─ Toast succès + tableau rechargé
```

Parcours nominal = **4 actions** : Ajuster → saisir la date → (case déjà cochée, lire l'aperçu) → Déplacer 11 séances.

Règle d'affichage de la case :
| Situation | Case |
|---|---|
| Séance de base (non bis) avec au moins une séance suivante dans la période | affichée, **cochée** |
| Dernière séance de P1 (P1 · 14), nouvelle date ≥ P2 · 1 | affichée, cochée : « Décaler aussi la période 2 (P2 · 1 à 14) » |
| Dernière séance de la période, sans débordement (ou P2 · 14) | absente + phrase « C'est la dernière séance de la période : aucune séance à décaler. » |
| Séance suivante immédiate verrouillée (heures encodées / terminée) | affichée, cochée ; l'aperçu dit « P1 · {n+1} verrouillée : aucune séance à décaler » |
| Bis | absente (un bis ne décale jamais la suite) |

---

## Parcours A — « Je décale d'une semaine » (nominal)

### A1 · Chargement de l'aperçu

Affiché ~300 ms après la saisie d'une date valide (et à chaque changement de date ou de case Forcer).

```
┌ Ajuster la P1 · Séance 4 — mercredi 7 octobre 2026 ──────────────────── ✕ ┐
│ QUE VOULEZ-VOUS FAIRE ?                                                    │
│ ◉ Déplacer à une autre date                                                │
│ ○ Annuler la séance (motif obligatoire)                                    │
│ ○ Ajouter un bis d'une séance                                              │
│                                                                            │
│ Nouvelle date *            Début           Fin                             │
│ [ 14/10/2026 ]             [ 14:00 ]       [ 15:30 ]                       │
│ La période 1 se termine le 29/01/2027. Une date plus tardive reste         │
│ possible (séance « hors période »).                                        │
│                                                                            │
│ ☑ Décaler aussi les séances suivantes (P1 · 5 à 14)                        │
│   Une séance par semaine, le mercredi, en sautant les congés.              │
│   Les numéros de séance ne changent pas.                                   │
│                                                                            │
│ ┌ Nouvelles dates ───────────────────────────────────────────────────────┐ │
│ │ ⏳ Calcul des nouvelles dates…                                          │ │
│ │ ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░                                      │ │
│ │ ░░░░░░░░░░░░░░░░░░░░░░░░░░                                              │ │
│ │ ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░                                         │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│               [Fermer sans modifier]   [ Déplacer la séance ] (désactivé)  │
└────────────────────────────────────────────────────────────────────────────┘
```
- Zone `aria-busy="true"` ; le bouton reste désactivé tant que l'aperçu n'est pas reçu.

### A2 · Aperçu prêt (avant confirmation)

```
│ ☑ Décaler aussi les séances suivantes (P1 · 5 à 14)                        │
│   Une séance par semaine, le mercredi, en sautant les congés.              │
│   Les numéros de séance ne changent pas.                                   │
│                                                                            │
│ ┌ Nouvelles dates ───────────────────────────────────────────────────────┐ │
│ │ 11 séances déplacées · dernière séance le 27/01 (était le 20/01)       │ │
│ │ · toujours dans la période 1.                                          │ │
│ │                                                                        │ │
│ │ Forcer │ Séance   │ Avant          │ Après                             │ │
│ │ ───────┼──────────┼────────────────┼────────────────────────────────── │ │
│ │        │ P1 · 4   │ mer. 07/10     │ → mer. 14/10   (déplacée)         │ │
│ │   ☐    │ —        │                │ ⏭ 21/10 sautée · Vacances Toussaint│ │
│ │   ☐    │ —        │                │ ⏭ 28/10 sautée · Vacances Toussaint│ │
│ │        │ P1 · 5   │ mer. 14/10     │ → mer. 04/11   (+3 sem.)          │ │
│ │   ☐    │ —        │                │ ⏭ 11/11 sautée · Armistice        │ │
│ │        │ P1 · 6   │ mer. 04/11     │ → mer. 18/11   (+2 sem.)          │ │
│ │        │ P1 · 7   │ mer. 18/11     │ → mer. 25/11   (+1 sem.)          │ │
│ │        │ P1 · 8   │ mer. 25/11     │ → mer. 02/12   (+1 sem.)          │ │
│ │        │ P1 · 9   │ mer. 02/12     │ → mer. 09/12   (+1 sem.)          │ │
│ │        │ P1 · 10  │ mer. 09/12     │ → mer. 16/12   (+1 sem.)          │ │
│ │   ☐    │ —        │                │ ⏭ 23/12 sautée · Vacances d'hiver │ │
│ │   ☐    │ —        │                │ ⏭ 30/12 sautée · Vacances d'hiver │ │
│ │        │ P1 · 11  │ mer. 16/12     │ → mer. 06/01   (+3 sem.)          │ │
│ │        │ P1 · 12  │ mer. 06/01     │ → mer. 13/01   (+1 sem.)          │ │
│ │        │ P1 · 13  │ mer. 13/01     │ → mer. 20/01   (+1 sem.)          │ │
│ │        │ P1 · 14  │ mer. 20/01     │ → mer. 27/01   (+1 sem.)          │ │
│ │                                                                        │ │
│ │ P1 · 1 à 3 ne changent pas. La période 2 ne change pas (la P1 finit    │ │
│ │ le 27/01, avant P2 · 1 le 03/02).                                      │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│ ┌ Conséquence ───────────────────────────────────────────────────────────┐ │
│ │ La P1 · Séance 4 passe au 14/10 et les 10 séances suivantes sont        │ │
│ │ décalées. Toutes gardent leur numéro de séance, leur horaire, leur lieu │ │
│ │ et leurs professeurs.                                                  │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│                  [Fermer sans modifier]   [ Déplacer 11 séances ]          │
```
Notes :
- Colonnes « Avant » / « Après » en texte ; la flèche « → » et l'écart (« +1 sem. ») évitent de dépendre de la couleur. Les lignes inchangées n'apparaissent pas (sauf figées, cf. A6).
- Lignes « sautée » : même rendu que l'aperçu de création (CLS-05 v0.2), case **Forcer** en 1re colonne.
- Au-delà de 6 lignes, le tableau est repliable : « Voir le détail (14 lignes) ▾ » ; le résumé reste visible.
- Mobile (< 600 px) : liste « P1 · 5 : 14/10 → 04/11 ».

### A3 · Forcer une date de congé dans l'aperçu

Clic sur ☐ de « 11/11 Armistice » → l'aperçu se recalcule.

```
│ │ 3 séances déplacées (P1 · 4 à 6) · P1 · 7 à 14 inchangées · dernière   │ │
│ │ séance le 20/01 (inchangée).                                           │ │
│ │   ☑    │ P1 · 6   │ mer. 04/11     │ → mer. 11/11   ✓ Forcée · Armistice│ │
│ │        │ P1 · 7   │ mer. 18/11     │   18/11        (inchangée)        │ │
│ │ …                                                                      │ │
```
Le bouton devient « Déplacer 3 séances ». Décocher → la date redevient « sautée ». Le forçage n'est pas mémorisé après l'enregistrement (comme CLS-05).

### A4 · Succès

Modale fermée, tableau des sessions rechargé, toasts :

```
✅ P1 · Séance 4 déplacée au 14/10. 10 séances suivantes décalées
   (dernière le 27/01). Les numéros de séance sont inchangés.
```
Avec avertissements éventuels (toasts jaunes séparés, non bloquants) :
```
⚠ P1 · Séance 14 (27/01) est après la fin de la période 1 (22/01) : marquée « Hors période · rattrapage ».
⚠ P1 · 9 bis (28/11) est désormais avant P1 · 8 (02/12) : déplacez-le si nécessaire.
⚠ Alice Martin a déjà une session le 18/11 à 14:00 (Python) : vérifiez sa disponibilité.
```

### A5 · Rien à décaler (état « vide »)

a) Report dans la même semaine (07/10 → jeudi 08/10) :
```
│ ☑ Décaler aussi les séances suivantes (P1 · 5 à 14)                        │
│ ┌ Nouvelles dates ───────────────────────────────────────────────────────┐ │
│ │ ℹ Aucune autre séance ne change de date : la P1 · 5 reste le 14/10     │ │
│ │   (semaine suivante, le mercredi).                                     │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                  [Fermer sans modifier]   [ Déplacer la séance ]           │
```
b) Dernière séance de la période (P1 · 14) :
```
│ Nouvelle date * [ 27/01/2027 ]                                             │
│ C'est la dernière séance de la période : aucune séance à décaler.          │
│                  [Fermer sans modifier]   [ Déplacer la séance ]           │
```
c) Case décochée par l'utilisateur : aperçu masqué, Conséquence actuelle (« … garde son numéro de séance 4 et sa période. »), bouton « Déplacer la séance » — **comportement actuel inchangé**.

### A6 · Séances figées dans la plage (non bloquant)

P1 · 9 annulée + P1 · 9 bis le samedi 28/11 :
```
│ │        │ P1 · 8      │ mer. 25/11  │ → mer. 02/12   (+1 sem.)          │ │
│ │        │ P1 · 9      │ mer. 02/12  │   inchangée — annulée             │ │
│ │        │ P1 · 9 bis  │ sam. 28/11  │   inchangée — bis ⚠ avant P1 · 8  │ │
│ │        │ P1 · 10     │ mer. 09/12  │   09/12        (inchangée)        │ │
│ │ …                                                                      │ │
│ │ ⚠ Les séances annulées et les bis gardent leur date. P1 · 9 bis sera    │ │
│ │   avant P1 · 8 : déplacez-le ensuite si nécessaire.                    │ │
```

---

## Parcours B — Erreurs (bouton primaire désactivé)

Un chevauchement avec une séance verrouillée n'est **pas** une erreur : avertissement non bloquant, bouton actif (parcours D3, E2).

### B1 · Date avant la séance précédente (erreur de champ)

P1 · 7 (18/11) → 03/11 :
```
│ Nouvelle date *  [ 03/11/2026 ]                                            │
│ ✖ La nouvelle date doit être après la séance précédente P1 · 6 (04/11).    │
```
(Erreur 422 sous le champ, aperçu non affiché.)

### B2 · Le planning a changé depuis l'aperçu (409)

```
│ ⚠ Le planning de la classe a changé depuis l'affichage de l'aperçu         │
│   (une séance a été modifiée). L'aperçu a été recalculé : vérifiez-le      │
│   puis confirmez à nouveau.                                                │
```
Aperçu rechargé automatiquement, bouton réactivé.

### B3 · Erreur réseau / serveur

```
│ ✖ Impossible de calculer les nouvelles dates. Vérifiez votre connexion.    │
│   [Réessayer]                                                              │
```
Saisie conservée ; si l'erreur survient à l'enregistrement : bandeau rouge en tête de modale, rien n'est modifié (transaction), bouton réactivé.

---

## Parcours C — Avancer une séance

P1 · 7 (18/11) → jeudi 12/11 (semaine de l'Armistice, P1 · 6 le 04/11) :

```
│ │ 8 séances déplacées · dernière séance le 13/01 (était le 20/01).       │ │
│ │        │ P1 · 7   │ mer. 18/11     │ → jeu. 12/11   (déplacée)         │ │
│ │        │ P1 · 8   │ mer. 25/11     │ → mer. 18/11   (−1 sem.)          │ │
│ │        │ P1 · 9   │ mer. 02/12     │ → mer. 25/11   (−1 sem.)          │ │
│ │        │ P1 · 10  │ mer. 09/12     │ → mer. 02/12   (−1 sem.)          │ │
│ │        │ P1 · 11  │ mer. 16/12     │ → mer. 09/12   (−1 sem.)          │ │
│ │        │ P1 · 12  │ mer. 06/01     │ → mer. 16/12   (−3 sem.)          │ │
│ │   ☐    │ —        │                │ ⏭ 23/12 sautée · Vacances d'hiver │ │
│ │   ☐    │ —        │                │ ⏭ 30/12 sautée · Vacances d'hiver │ │
│ │        │ P1 · 13  │ mer. 13/01     │ → mer. 06/01   (−1 sem.)          │ │
│ │        │ P1 · 14  │ mer. 20/01     │ → mer. 13/01   (−1 sem.)          │ │
│                  [Fermer sans modifier]   [ Déplacer 8 séances ]           │
```
Note : la séance déplacée tombe un jeudi (choix explicite) ; les suivantes restent le mercredi.


---

## Parcours D — Cascade vers la période 2 (arbitrage 3)

Règle : si la **nouvelle dernière séance de P1** tombe le même jour ou après P2 · 1, toute la P2 est recalculée à partir de la semaine qui suit (une par semaine, mercredi, congés sautés, cases Forcer). Numéros conservés. Sinon la P2 ne bouge pas.

### D1 · Aperçu avec cascade (P1 · 4 07/10 → 04/11)

```
│ ☑ Décaler aussi les séances suivantes (P1 · 5 à 14)                        │
│   Une séance par semaine, le mercredi, en sautant les congés.              │
│   Si la période 1 déborde sur la période 2, celle-ci est décalée aussi.    │
│                                                                            │
│ ┌ Nouvelles dates — Période 1 ───────────────────────────────────────────┐ │
│ │ 11 séances déplacées · dernière séance le 03/02 (était le 20/01)       │ │
│ │ · ⚠ dépasse la fin de la période 1 (29/01).                            │ │
│ │                                                                        │ │
│ │ Forcer │ Séance   │ Avant          │ Après                             │ │
│ │ ───────┼──────────┼────────────────┼────────────────────────────────── │ │
│ │        │ P1 · 4   │ mer. 07/10     │ → mer. 04/11   (déplacée)         │ │
│ │   ☐    │ —        │                │ ⏭ 11/11 sautée · Armistice        │ │
│ │        │ P1 · 5   │ mer. 14/10     │ → mer. 18/11   (+5 sem.)          │ │
│ │        │ P1 · 6   │ mer. 04/11     │ → mer. 25/11   (+3 sem.)          │ │
│ │        │ P1 · 7   │ mer. 18/11     │ → mer. 02/12   (+2 sem.)          │ │
│ │        │ P1 · 8   │ mer. 25/11     │ → mer. 09/12   (+2 sem.)          │ │
│ │        │ P1 · 9   │ mer. 02/12     │ → mer. 16/12   (+2 sem.)          │ │
│ │   ☐    │ —        │                │ ⏭ 23/12 sautée · Vacances d'hiver │ │
│ │   ☐    │ —        │                │ ⏭ 30/12 sautée · Vacances d'hiver │ │
│ │        │ P1 · 10  │ mer. 09/12     │ → mer. 06/01   (+4 sem.)          │ │
│ │        │ P1 · 11  │ mer. 16/12     │ → mer. 13/01   (+4 sem.)          │ │
│ │        │ P1 · 12  │ mer. 06/01     │ → mer. 20/01   (+2 sem.)          │ │
│ │        │ P1 · 13  │ mer. 13/01     │ → mer. 27/01   (+2 sem.)          │ │
│ │        │ P1 · 14  │ mer. 20/01     │ → mer. 03/02   (+2 sem.) ⚠ Hors période │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│ ┌ Nouvelles dates — Période 2 (décalée en cascade) ──────────────────────┐ │
│ │ ℹ La P1 · 14 tombe le 03/02, le jour de la 1re séance de la période 2 :│ │
│ │   la période 2 est décalée à partir de la semaine suivante.            │ │
│ │ 14 séances déplacées · dernière séance le 09/06 (était le 02/06)       │ │
│ │ · toujours dans la période 2 (25/06).            [Voir le détail ▾]    │ │
│ │                                                                        │ │
│ │ Forcer │ Séance   │ Avant          │ Après                             │ │
│ │ ───────┼──────────┼────────────────┼────────────────────────────────── │ │
│ │        │ P2 · 1   │ mer. 03/02     │ → mer. 10/02   (+1 sem.)          │ │
│ │   ☐    │ —        │                │ ⏭ 17/02 sautée · Congé de détente │ │
│ │   ☐    │ —        │                │ ⏭ 24/02 sautée · Congé de détente │ │
│ │        │ P2 · 2   │ mer. 10/02     │ → mer. 03/03   (+3 sem.)          │ │
│ │        │ P2 · 3   │ mer. 03/03     │ → mer. 10/03   (+1 sem.)          │ │
│ │        │ P2 · 4 … 9 │              │ chacune +1 sem. (17/03 … 21/04)   │ │
│ │   ☐    │ —        │                │ ⏭ 28/04 sautée · Vacances printemps│ │
│ │   ☐    │ —        │                │ ⏭ 05/05 sautée · Vacances printemps│ │
│ │        │ P2 · 10  │ mer. 21/04     │ → mer. 12/05   (+3 sem.)          │ │
│ │        │ P2 · 11 … 14 │            │ chacune +1 sem. (19/05 … 09/06)   │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│ ┌ Conséquence ───────────────────────────────────────────────────────────┐ │
│ │ La P1 · Séance 4 passe au 04/11, les 10 séances suivantes de la        │ │
│ │ période 1 et les 14 séances de la période 2 sont décalées. Toutes      │ │
│ │ gardent leur numéro, leur horaire, leur lieu et leurs professeurs.     │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│        [Fermer sans modifier]   [ Déplacer 11 séances (P1) + 14 (P2) ]     │
```
Notes :
- Le bloc P2 est **séparé** et porte son propre résumé et ses propres cases Forcer. Replié par défaut au-delà de 6 lignes ; le résumé reste visible. (Les lignes « P2 · 4 … 9 » ci-dessus sont abrégées dans la maquette ; l'écran liste chaque séance.)
- Forcer une date de P1 peut **supprimer** la cascade (ex. forcer 11/11 : P1 · 14 → 27/01 < 03/02) : le bloc P2 disparaît et le bouton redevient « Déplacer 11 séances ».
- Forcer 17/02 en P2 : P2 · 2 → 17/02, P2 · 3 à 14 retrouvent leurs dates d'origine ; le bouton devient « Déplacer 11 séances (P1) + 2 (P2) ».

### D2 · Succès

```
✅ P1 · Séance 4 déplacée au 04/11. 10 séances suivantes de la période 1
   et 14 séances de la période 2 décalées (dernière le 09/06).
   Les numéros de séance sont inchangés.
⚠ P1 · Séance 14 (03/02) est après la fin de la période 1 (29/01) : marquée « Hors période · rattrapage ».
```

### D3 · Cascade arrêtée par une séance de P2 verrouillée (avertissement)

Même déplacement, mais P2 · 5 (17/03) a des heures encodées :
```
│ ┌ Nouvelles dates — Période 2 (décalée en cascade) ──────────────────────┐ │
│ │ 4 séances déplacées · arrêt à la P2 · 5 (heures encodées) :            │ │
│ │ P2 · 5 à 14 inchangées.                                                │ │
│ │                                                                        │ │
│ │ ⚠ La P2 · 4 tombera le 17/03, le même jour que la P2 · 5 (17/03,       │ │
│ │   heures encodées) : l'ordre des séances ne suivra plus leur numéro.   │ │
│ │                                                                        │ │
│ │        │ P2 · 1   │ mer. 03/02     │ → mer. 10/02   (+1 sem.)          │ │
│ │        │ P2 · 2   │ mer. 10/02     │ → mer. 03/03   (+3 sem.)          │ │
│ │        │ P2 · 3   │ mer. 03/03     │ → mer. 10/03   (+1 sem.)          │ │
│ │        │ P2 · 4   │ mer. 10/03     │ → mer. 17/03 ⚠ même jour que P2 · 5│ │
│ │   🔒   │ P2 · 5   │ mer. 17/03     │ heures encodées — non décalée,    │ │
│ │        │          │                │ les suivantes non plus            │ │
│ │        │ P2 · 6 … 14 │             │ inchangées                        │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│        [Fermer sans modifier]   [ Déplacer 11 séances (P1) + 4 (P2) ]      │
```
Bouton **actif**. Avertissement en `role="status"` (texte + ⚠), repris en toast après l'enregistrement. Forcer 17/02 (Détente) supprime l'avertissement (P2 · 2 → 17/02, P2 · 3 et 4 à leurs dates d'origine).

### D4 · Fin de l'année scolaire dépassée (avertissement)

Si l'année scolaire se termine le 04/06/2027 :
```
│ │        │ P2 · 14  │ mer. 02/06     │ → mer. 09/06 ⚠ Hors période       │ │
│ │ ⚠ La P2 · 14 (09/06) tombe après la fin de l'année scolaire (04/06).   │ │
```
Non bloquant, même badge « Hors période · rattrapage ».

### D5 · Date de démarrage de la période 2

Après une cascade, la date de démarrage de la P2 (fiche classe, section Périodes) devient celle de la nouvelle P2 · 1 (10/02). L'aperçu le mentionne sous le résumé du bloc P2 : « La date de démarrage de la période 2 passera au 10/02. »

---

## Parcours E — Arrêt sur une séance verrouillée (arbitrage 4)

Règle : on décale **jusqu'à** la première séance verrouillée (heures encodées ou terminée) ; elle et toutes les suivantes de la période restent inchangées ; pas de cascade vers la P2 (la dernière séance de P1 ne bouge pas). Si une date recalculée tombe le même jour ou après la séance verrouillée, le décalage est **autorisé** avec un avertissement non bloquant (l'ordre des dates ne suivra plus les numéros).

### E1 · Arrêt sans chevauchement (P1 · 7 18/11 → jeudi 12/11 ; P1 · 11 a des heures encodées)

```
│ ┌ Nouvelles dates — Période 1 ───────────────────────────────────────────┐ │
│ │ 4 séances déplacées · arrêt à la P1 · 11 (heures encodées) :           │ │
│ │ P1 · 11 à 14 inchangées · la période 2 ne change pas.                  │ │
│ │                                                                        │ │
│ │ Forcer │ Séance   │ Avant          │ Après                             │ │
│ │ ───────┼──────────┼────────────────┼────────────────────────────────── │ │
│ │        │ P1 · 7   │ mer. 18/11     │ → jeu. 12/11   (déplacée)         │ │
│ │        │ P1 · 8   │ mer. 25/11     │ → mer. 18/11   (−1 sem.)          │ │
│ │        │ P1 · 9   │ mer. 02/12     │ → mer. 25/11   (−1 sem.)          │ │
│ │        │ P1 · 10  │ mer. 09/12     │ → mer. 02/12   (−1 sem.)          │ │
│ │   🔒   │ P1 · 11  │ mer. 16/12     │ 16/12 — heures encodées :         │ │
│ │        │          │                │ non décalée, les suivantes non plus│ │
│ │        │ P1 · 12  │ mer. 06/01     │ 06/01   (inchangée)               │ │
│ │        │ P1 · 13  │ mer. 13/01     │ 13/01   (inchangée)               │ │
│ │        │ P1 · 14  │ mer. 20/01     │ 20/01   (inchangée)               │ │
│ │                                                                        │ │
│ │ ℹ 2 semaines sans séance entre P1 · 10 (02/12) et P1 · 11 (16/12).     │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│ ┌ Conséquence ───────────────────────────────────────────────────────────┐ │
│ │ La P1 · Séance 7 passe au 12/11 et les séances 8 à 10 sont décalées.   │ │
│ │ La P1 · 11 a des heures encodées : elle et les séances suivantes       │ │
│ │ gardent leur date. Tous les numéros sont conservés.                    │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                  [Fermer sans modifier]   [ Déplacer 4 séances ]           │
```
- Motif affiché : « heures encodées » ou « terminée ». Les lignes après la séance verrouillée sont affichées (grisées, « inchangée ») pour que l'arrêt soit visible.
- Toast : « P1 · Séance 7 déplacée au 12/11. 3 séances suivantes décalées ; P1 · 11 à 14 inchangées (P1 · 11 : heures encodées). Les numéros de séance sont inchangés. »

### E2 · Chevauchement : avertissement, bouton actif (P1 · 4 07/10 → 14/10 ; P1 · 8 25/11 a des heures encodées)

```
│ ┌ Nouvelles dates — Période 1 ───────────────────────────────────────────┐ │
│ │ 4 séances déplacées · arrêt à la P1 · 8 (heures encodées) :            │ │
│ │ P1 · 8 à 14 inchangées · la période 2 ne change pas.                   │ │
│ │                                                                        │ │
│ │ ⚠ La P1 · 7 tombera le 25/11, le même jour que la P1 · 8 (25/11,       │ │
│ │   heures encodées) : l'ordre des séances ne suivra plus leur numéro.   │ │
│ │   Pour l'éviter : forcez une date de congé ci-dessous ou choisissez    │ │
│ │   une date plus proche.                                                │ │
│ │                                                                        │ │
│ │ Forcer │ Séance   │ Avant          │ Après                             │ │
│ │ ───────┼──────────┼────────────────┼────────────────────────────────── │ │
│ │        │ P1 · 4   │ mer. 07/10     │ → mer. 14/10   (déplacée)         │ │
│ │   ☐    │ —        │                │ ⏭ 21/10 sautée · Vacances Toussaint│ │
│ │   ☐    │ —        │                │ ⏭ 28/10 sautée · Vacances Toussaint│ │
│ │        │ P1 · 5   │ mer. 14/10     │ → mer. 04/11   (+3 sem.)          │ │
│ │   ☐    │ —        │                │ ⏭ 11/11 sautée · Armistice        │ │
│ │        │ P1 · 6   │ mer. 04/11     │ → mer. 18/11   (+2 sem.)          │ │
│ │        │ P1 · 7   │ mer. 18/11     │ → mer. 25/11 ⚠ même jour que P1 · 8│ │
│ │   🔒   │ P1 · 8   │ mer. 25/11     │ heures encodées — non décalée,    │ │
│ │        │          │                │ les suivantes non plus            │ │
│ │        │ P1 · 9 … 14 │             │ inchangées                        │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│                  [Fermer sans modifier]   [ Déplacer 4 séances ]           │
```
- Bouton **actif** ; avertissement `role="status"`. Libellé « le même jour que » ou « après » selon le cas.
- Même avertissement si la **date saisie** elle-même tombe le jour ou après la séance verrouillée (« La P1 · 4 tombera le 02/12, après la P1 · 8 (25/11, heures encodées)… »), une ligne par séance concernée.
- Toast après succès : « P1 · Séance 4 déplacée au 14/10. 3 séances suivantes décalées ; P1 · 8 à 14 inchangées (P1 · 8 : heures encodées). » + toast jaune reprenant l'avertissement.

### E3 · Le forçage d'un congé supprime l'avertissement (facultatif)

Clic sur « Forcer » 11/11 dans E2 → recalcul, plus d'avertissement :
```
│ │ 3 séances déplacées · arrêt à la P1 · 8 (heures encodées) :            │ │
│ │ P1 · 8 à 14 inchangées · la période 2 ne change pas.                   │ │
│ │        │ P1 · 5   │ mer. 14/10     │ → mer. 04/11   (+3 sem.)          │ │
│ │   ☑    │ P1 · 6   │ mer. 04/11     │ → mer. 11/11   ✓ Forcée · Armistice│ │
│ │        │ P1 · 7   │ mer. 18/11     │   18/11        (inchangée)        │ │
│ │   🔒   │ P1 · 8   │ mer. 25/11     │ heures encodées — non décalée, …  │ │
│                  [Fermer sans modifier]   [ Déplacer 3 séances ]           │
```
(P1 · 7 retombe sur sa date d'origine : seules P1 · 4, 5 et 6 changent.)

---

## Textes exacts (récapitulatif)

| Élément | Texte |
|---|---|
| Case | « Décaler aussi les séances suivantes (P1 · {n+1} à {dernier}) » |
| Aide de la case | « Une séance par semaine, le {jour}, en sautant les congés. Les numéros de séance ne changent pas. » |
| Titre aperçu | « Nouvelles dates » |
| Résumé | « {N} séances déplacées · dernière séance le {date} (était le {date}) · {toujours dans / dépasse} la période {p}. » |
| Bouton | « Déplacer {N} séances » (N ≥ 2) · « Déplacer la séance » (N = 1) · « Déplacer {N} séances (P1) + {M} (P2) » (cascade) |
| Titre bloc P2 | « Nouvelles dates — Période 2 (décalée en cascade) » + « La P1 · {n} tombe le {date}, le jour de / après la 1re séance de la période 2 : la période 2 est décalée à partir de la semaine suivante. » |
| Ligne verrouillée | « 🔒 {date} — {heures encodées / terminée} : non décalée, les suivantes non plus » |
| Résumé avec arrêt | « {N} séances déplacées · arrêt à la P{p} · {m} ({motif}) : P{p} · {m} à {dernier} inchangées. » |
| Avertissement de chevauchement | « ⚠ La P{p} · {n} tombera le {date}, {le même jour que / après} la P{p} · {m} ({date}, {heures encodées / terminée}) : l'ordre des séances ne suivra plus leur numéro. » |
| Avertissement fin d'année | « ⚠ La P2 · {n} ({date}) tombe après la fin de l'année scolaire ({date}). » |
| Démarrage P2 | « La date de démarrage de la période 2 passera au {date}. » |
| Conséquence | « La {séance} passe au {date} et les {N−1} séances suivantes sont décalées. Toutes gardent leur numéro de séance, leur horaire, leur lieu et leurs professeurs. » |
| Action de repli | « Déplacer seulement cette séance » |
| Toast succès | « {Séance} déplacée au {date}. {N−1} séances suivantes décalées (dernière le {date}). Les numéros de séance sont inchangés. » ; cascade : « … {N−1} séances suivantes de la période 1 et {M} séances de la période 2 décalées (dernière le {date}). … » ; arrêt : « … ; P{p} · {m} à {dernier} inchangées (P{p} · {m} : {motif}). … » |

## Accessibilité
- Ordre de tabulation : date → début → fin → case → cases Forcer de l'aperçu → boutons.
- Aperçu : `<table>` avec `<caption>Nouvelles dates</caption>`, en-têtes de colonnes ; résumé dans `aria-live="polite"` ; blocages `role="alert"`.
- Aucune information portée par la couleur seule (flèche, « sautée », « inchangée », « Forcée » en texte).

## Validation requise
- [ ] Workflow intégré à « Ajuster » validé (pas de parcours parallèle)
- [x] Q1–Q5 tranchées par le directeur (2026-10-04)
- [x] N-1 à N-4 tranchés (chevauchement = avertissement, démarrage P2 aligné, déplacement simple inchangé, fin d'année = avertissement)
- [ ] Textes validés
- [ ] Cas limites (même semaine, dernière séance, cascade P2, arrêt et chevauchement sur séance verrouillée, fin d'année, avancer) relus

**Validé par :** *à remplir* · **Date :** *à remplir*
