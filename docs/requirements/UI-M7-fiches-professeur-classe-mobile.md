# UI-M7 — Fiche professeur et fiche classe sur smartphone (contact, archivage avec impact, assignations, créneau/lieu avec impact) — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Septième tranche de la refonte mobile-first (après UI-M1 à UI-M6). Tranche verticale : un aperçu d'impact côté API (créneau/lieu d'une classe) + les écrans. Maquettes : `docs/mockups/UX-MOBILE-02/index.html` et `desktop.html` ; décisions : `UX-MOBILE-02/WORKFLOW_UX.md`.

| | |
|---|---|
| **ID / Titre** | UI-M7 — Fiche professeur (contact en 1 tap, séances de la semaine, sections repliables, archivage avec impact) et fiche classe (modifier créneau et lieu avec impact, actions secondaires regroupées, séances à venir en tête) |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — maquettes et décisions validées le 2026-10-25 ☑ En dev (livré, vérifié dans le navigateur, tests back verts) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* |
| **Date / Version** | 2026-10-25 · v1.0 |
| **Liens** | `PROF-01`, `PROF-02` (archivage et impact), `CLS-01-T2` (assignations), `ClasseService::modifier`, `UI-M6`, `docs/API_UI_M7.md` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :**
  - *Fiche professeur* (`/admin/professeurs/:id`) : 9 cartes empilées (≈ 4 000 px de haut à 390 px), sans moyen d'appeler ou d'écrire en un tap, sans vue des séances de la semaine ; les boutons « Éditer » et « Remplacer » sont tout en bas.
  - *Fiche classe* (`/admin/classes/:id`) : ≈ 6 900 px de haut ; quatre boutons d'en-tête empilés (dont deux actions destructives), **aucun écran pour modifier le créneau ou le lieu** alors que l'API existe (`PUT /classes/{id}`, répercuté sur les séances à venir), et 14 séances détaillées sur 8 lignes chacune avant d'atteindre l'action utile.
- **Douleur :** pour un changement « en direct » (téléphoner à un professeur, déplacer le lieu d'une classe, archiver un départ), la direction scrolle longuement et n'a pas d'aperçu de ce que le changement va toucher.
- **Décisions du directeur (2026-10-25) :** le directeur/admin paramètre un maximum d'informations des professeurs et d'une classe depuis un smartphone ; les tarifs sont visibles par la direction et l'admin ; le lieu n'est pas un filtre ; la création de classe et les écrans denses restent consultables mais plus confortables sur ordinateur.

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif | Indicateur | Cible | Mesure |
|---|---|---|---|
| Joindre un professeur | taps depuis la fiche | 1 (Appeler / SMS / Email) | recette R1 |
| Fiche lisible | hauteur de la fiche professeur à 390 px, sections repliées | ≤ 1 800 px (contre ≈ 4 000) | mesure navigateur |
| Voir l'impact avant d'agir | modification de créneau/lieu : nombre de séances qui seront mises à jour affiché avant d'enregistrer | 100 % | tests AC-1 à AC-4, recette R3 |
| Archiver en connaissance de cause | l'écran d'impact existant (PROF-02) est accessible d'un tap depuis la fiche | 1 tap | recette R2 |
| Fiche classe courte | séances affichées par défaut sur mobile | prochaines 5 + « Voir toutes » | recette R3 |

**Hors périmètre :** création de classe, périodes, liens de cours, duplication (restent accessibles, plus confortables sur ordinateur), modification du jour de la classe (CLS : « changer le jour = créer une autre classe »), décalage des séances suivantes (CLS-06), ajout/modification de tarif en mobile complet (la section tarif reste celle de l'écran existant), recherche globale, swipe.

## 3. 🔴 Acteurs, rôles et permissions

**Personas :** Directeur/Staff et Admin (smartphone et ordinateur).

| Action | Admin | Staff | Professeur |
|---|---|---|---|
| Voir les fiches professeur et classe | L | L | — (403) |
| `POST /classes/{id}/apercu-modification` | L | L | — (403) |
| Modifier créneau/lieu (`PUT /classes/{id}`, existant) | M | M | — |
| Archiver/désactiver un professeur (existant, PROF-02) | M | M | — |

Autorisation de l'aperçu = celle de la modification (`ClassePolicy::update`). Aucune permission nouvelle.

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Nom technique |
|---|---|---|
| Fiche rapide | En-tête de la fiche professeur : contact, actions, séances de la semaine | `FicheRapideProfesseur` |
| Modifier créneau et lieu | Changement de l'horaire ou du lieu de la classe, répercuté sur les séances à venir « planifiées » qui suivaient la classe | `PUT /classes/{id}` |
| Séances mises à jour | Séances à venir, `planifiee`, dont la valeur égalait celle de la classe | `seances_a_mettre_a_jour` |
| Séances inchangées | Séances passées, annulées ou déjà ajustées à la main | `seances_inchangees` |

## 5. 🔴 Parcours et critères d'acceptation

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | directeur | appeler, écrire un SMS ou un mail à un professeur d'un tap | le joindre tout de suite | Must |
| US-2 | directeur | voir les séances de la semaine d'un professeur depuis sa fiche | savoir où il enseigne et ouvrir la séance | Must |
| US-3 | directeur | une fiche professeur en sections repliables | ne pas scroller 4 000 px | Must |
| US-4 | directeur | archiver un professeur avec l'impact énoncé | acter un départ sans surprise | Must |
| US-5 | directeur | modifier le créneau et le lieu d'une classe en voyant combien de séances changent | agir en direct sans risque | Must |
| US-6 | directeur | une fiche classe courte, avec les prochaines séances d'abord | aller à l'essentiel | Should |

### 5.2 Parcours
1. **Fiche professeur** : en-tête (nom, statut) ; boutons Appeler · SMS · Email (si téléphone/mail), Remplacer sur plusieurs séances, Éditer, Archiver… ; liste « Cette semaine » (jusqu'à 5 séances, chacune ouvre la fiche séance UI-M6) ; sections repliables (Classes ouvertes ; tarif, compte, coordonnées, activité, employeur…). « Archiver… » ouvre l'écran d'impact existant (séances sans professeur, dernier professeur d'une classe, heures non finalisées) puis confirme.
2. **Fiche classe** : actions principales « Modifier créneau et lieu » et « Voir dans le calendrier » ; « Plus d'actions » (Dupliquer, Archiver, Supprimer) en feuille ; professeurs (Ajouter, Terminer : écrans existants) ; séances : les 5 prochaines, « Voir toutes les séances (N) », lien « Fiche » vers la fiche séance.
3. **Modifier créneau et lieu** : début, fin, lieu ; aperçu dynamique « 12 séances à venir seront mises à jour · 2 inchangées (passées ou déjà ajustées) » ; « Enregistrer » → `PUT /classes/{id}` → toast « 12 séances mises à jour ».
4. Alternatives : fin ≤ début (message de champ, 422) ; aucune modification (bouton désactivé) ; classe archivée (action masquée) ; erreur serveur (bandeau) ; aucun téléphone (bouton Appeler absent).

### 5.3 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | une classe avec 5 séances à venir `planifiee` et 1 passée | `POST /classes/{id}/apercu-modification {heure_debut}` | `seances_a_mettre_a_jour = 5`, `seances_inchangees` = autres séances actives | Feature |
| AC-2 | une séance à venir déjà ajustée (horaire différent) | même appel | elle compte parmi les inchangées | Feature |
| AC-3 | un aperçu | appel | **aucune écriture** (classe et séances inchangées) | Feature |
| AC-4 | aucune valeur changée | appel | `seances_a_mettre_a_jour = 0` | Feature |
| AC-5 | fin ≤ début | appel | 422 | Feature |
| AC-6 | professeur / sans jeton | appel | 403 / 401 | Feature |
| AC-7 | fiche professeur à 390 px | affichage | boutons Appeler/SMS/Email (liens `tel:`, `sms:`, `mailto:`), « Cette semaine », sections repliables | Recette R1 |
| AC-8 | fiche professeur | « Archiver… » | écran d'impact existant | Recette R2 |
| AC-9 | fiche classe | « Modifier créneau et lieu » | modale avec aperçu dynamique ; enregistrement répercuté | Recette R3 |
| AC-10 | fiche classe à 390 px | affichage | actions secondaires dans « Plus d'actions » ; 5 prochaines séances ; « Voir toutes » | Recette R3 |
| AC-11 | écran ≥ 861 px | fiches | actions et sections visibles comme avant, plus les nouveautés (contact, modification) | Recette R4 |

## 6. 🔴 Règles métier et cycle de vie
- **RG-1 :** la répercussion sur les séances reste celle de `ClasseService::modifier` : séances de la classe, `planifiee`, `date >= aujourd'hui (Europe/Brussels)`, dont le champ modifié égale l'ancienne valeur de la classe.
- **RG-2 :** l'aperçu applique exactement ces critères, sans écrire (même requête en `count`).
- **RG-3 :** « inchangées » = séances actives de la classe non touchées.
- **RG-4 :** l'archivage d'un professeur et son impact restent ceux de PROF-01/02 (aucun changement de règle).
- Statuts : inchangés.

## 7. 🔴 Données et migration
Aucune migration ni donnée nouvelle. Le téléphone du professeur est déjà visible par la direction ; il n'est pas exposé aux professeurs.

## 8. Exigences UX/UI
> Workflow : sub-agent UX Expert (UX-MOBILE-02). Maquettes validées le 2026-10-25 ☑

| Écran | Contenu | Actions | Composants |
|---|---|---|---|
| Fiche professeur | Fiche rapide, sections repliables | Appeler, SMS, Email, Remplacer, Éditer, Archiver | `FicheRapideProfesseur`, `Pliable`, `RemplacerPlusieursModal`, `ArchiverProfesseurModal` |
| Fiche classe | En-tête, professeurs, séances | Modifier créneau et lieu, Plus d'actions | `ModifierClasseModal`, `AdminModal`, `ClasseSessionsTable` |

**4 états :** chargement (squelette existant), vide (« Aucune séance cette semaine »), erreur (message + Réessayer), succès (toast).

- **Textes exacts :** *Appeler · SMS · Email · Cette semaine · Aucune séance cette semaine · Remplacer sur plusieurs séances · Archiver… · Modifier créneau et lieu · {N} séance(s) à venir seront mises à jour · {M} inchangée(s) (passées ou déjà ajustées) · Plus d'actions · Voir toutes les séances ({N}) · Fiche.*
- **Appareils :** ☑ Mobile (priorité) ☑ Tablette ☑ Desktop (mêmes actions ; sections toujours ouvertes).
- **Accessibilité :** liens `tel:`/`sms:`/`mailto:` nommés, `<details>/<summary>` natifs (clavier), modales avec focus piégé.
- **Charge cognitive :** 1 tap pour joindre ; l'impact est énoncé avant l'enregistrement.

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Sécurité | Aperçu soumis à `ClassePolicy::update` ; 401/403 testés |
| Performance | Aperçu = 3 requêtes `count` maximum |
| Compatibilité | Europe/Brussels ; français ; iOS Safari ≥ 16 |

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB | Aucun | — |
| Back | `ClasseService::apercuModification`, `ClasseController::apercuModification`, route | S |
| API | `POST /api/classes/{id}/apercu-modification` ; `PUT /api/classes/{id}` (existant) | S |
| Front | `FicheRapideProfesseur`, `Pliable`, `ModifierClasseModal`, adaptations `AdminProfesseurDetail`, `ClasseDetailPage`, `ClasseSessionsTable`, styles | M |
| Tests | Feature `ClasseApercuModificationTest` ; lint/build ; navigateur | M |
| Docs | `docs/API_UI_M7.md`, ce Canvas | S |

- **Risques :** doublon de titres dans les sections repliables (masqué par style) ; ne pas casser l'écran ordinateur. **Retour arrière :** revert du commit. **ADR :** non.

## 11. Plan de recette
| # | Rôle | Scénario | Résultat | AC | OK/KO |
|---|---|---|---|---|---|
| R1 | Directeur (390 px) | Ouvrir une fiche professeur : contacts, semaine, sections | Fiche courte et actionnable | AC-7 | ☐ |
| R2 | Directeur | « Archiver… » puis annuler | Écran d'impact | AC-8 | ☐ |
| R3 | Directeur | Fiche classe : modifier le lieu avec aperçu, enregistrer ; « Plus d'actions », « Voir toutes » | Séances mises à jour, toast | AC-9, 10 | ☐ |
| R4 | Directeur (1280 px) | Mêmes fiches | Comportement conservé + nouveautés | AC-11 | ☐ |

**Jeu de données :** `lgit_demo` (classe React du mercredi, alice, bob).

## 12. Questions ouvertes
Aucune bloquante.

## 13. Validation (DoR)
Maquettes et décisions du directeur du 2026-10-25. ☑ UX Expert ☑ Mock-ups ☑ §1–7 ☑ Permissions ☑ AC testables ☑ Impact données (aucune migration).
