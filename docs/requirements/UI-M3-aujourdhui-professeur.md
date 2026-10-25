# UI-M3 — « Aujourd'hui » du professeur et encodage en 1 tap — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Troisième tranche de la refonte mobile-first (après `UI-M1-socle-mobile-first.md` et `UI-M2-navigation-onglets.md`). Tranche verticale complète : API → UI → tests (aucune table nouvelle).

| | |
|---|---|
| **ID / Titre** | UI-M3 — Page d'arrivée « Aujourd'hui » du professeur : prochaine séance, séances à encoder (1 tap, annulable 5 s), progression du mois ; endpoint `GET /api/mon-accueil` |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — maquettes validées le 2026-10-25 (encodage en 1 tap et page d'arrivée confirmés par le directeur) ☑ En dev (livré, vérifié dans le navigateur, 535 tests back verts) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* |
| **Date / Version** | 2026-10-25 · v1.0 |
| **Liens** | `docs/mockups/UX-MOBILE-01/index.html` (écrans « Aujourd'hui » et toast « Annuler »), `TimesheetMoisController::monMois`, `TimesheetService::creer`, `TS-03-encodage-seances-a-venir.md`, `UI-M2-navigation-onglets.md` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** après connexion, le professeur arrive sur « Mes classes » (liste de classes) ; rien ne répond à « que dois-je faire aujourd'hui ? ». Pour encoder une séance, il ouvre « Encoder mon mois », trouve la ligne dans un tableau et utilise un stepper + bouton, ou une modale complète.
- **Douleur :** plusieurs taps et du défilement pour une action que le professeur répète à chaque séance, en général juste après le cours, sur son téléphone.
- **Déclencheur (décisions du directeur, 2026-10-25) :** *« L'encodage en 1 tap pour les cours est acceptable. Ok pour une nouvelle page d'arrivée qui est “Aujourd'hui”. »*

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif | Indicateur | Cible | Mesure |
|---|---|---|---|
| Encoder en un geste | taps pour encoder une séance terminée avec la durée standard | 1 (contre ≥ 4) | recette R1 |
| Savoir quoi faire | informations visibles sans défiler sur un écran de 390×844 | prochaine séance + première séance à encoder + progression | recette R1 |
| Corriger une erreur | annulation d'un encodage | 1 tap pendant 5 s ; modifiable ensuite dans « Mon mois » | recette R2 |
| Backend source de vérité | règles d'encodage appliquées côté serveur | 100 % (mois courant, mois soumis, doublon, session annulée, droits) | tests AC-1 à AC-9 |

**Hors périmètre :** durée différente ou type d'activité (via « Mon mois » ; la feuille « Autre durée » arrivera avec UI-M4), « Mon mois » en cartes (UI-M4), remplacement et absence (UI-M6), séances à encoder des mois précédents, notifications push, anneau animé au-delà d'un simple SVG.

## 3. 🔴 Acteurs, rôles et permissions

**Persona :** Professeur, smartphone, plusieurs fois par semaine.

| Ressource / Action | Admin | Staff | Professeur | Élève |
|---|---|---|---|---|
| `GET /api/mon-accueil` | — (403) | — (403) | L (les siennes uniquement) | — |
| Encoder une séance (`POST /api/timesheets`, existant) | — | — | C (ses séances) | — |
| Annuler un encodage (`DELETE /api/timesheets/{id}`, existant) | — | — | S (ses brouillons) | — |

→ Policy : `Gate::authorize('create', Timesheet::class)` (comme `mon-mois`) ; aucune permission nouvelle. Sans jeton : 401. Isolation : aucune donnée d'un autre professeur.

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Nom technique |
|---|---|---|
| Aujourd'hui | Page d'arrivée du professeur | route `/aujourdhui`, `GET /api/mon-accueil` |
| Prochaine séance | Première séance non annulée de ce professeur (non remplacé) qui n'est pas terminée (date future, ou aujourd'hui avec heure de fin non atteinte) | `prochaine_seance` |
| À encoder | Séance du mois courant, commencée, non annulée, où le professeur intervient, sans saisie d'heures | `a_encoder[]` |
| Encoder 2 h | Création d'un brouillon avec les heures défrayables par défaut de la séance | `POST /timesheets {course_session_id, nombre_heures}` |
| Progression du mois | séances encodées / séances du mois (non annulées), heures et montant brut estimé | `progression` |

## 5. 🔴 Parcours et critères d'acceptation

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | professeur | arriver sur « Aujourd'hui » après connexion | voir immédiatement mon prochain cours et ce qu'il me reste à faire | Must |
| US-2 | professeur | encoder une séance terminée d'un seul tap | gagner du temps après chaque cours | Must |
| US-3 | professeur | annuler l'encodage pendant 5 secondes | corriger une erreur de tap sans quitter l'écran | Must |
| US-4 | professeur | voir la progression du mois (séances, heures, euros) | savoir où j'en suis avant de soumettre | Should |
| US-5 | professeur | être guidé vers « Mon mois » pour une autre durée ou pour soumettre | garder le contrôle sur les cas particuliers | Must |

### 5.2 Parcours
1. Connexion → `/aujourdhui` : salutation, anneau de progression (« 6/9 · 142 € »), carte « Prochaine séance » (classe, heure, lieu, co-professeur(s), « Vous remplacez X » le cas échéant, lien « Liens du cours »), pile « À encoder » (plus récente d'abord).
2. Tap « Encoder 2 h » → la carte sort de la pile, l'anneau avance, toast « Encodé · 2 h — Annuler » 5 s (vibration courte si disponible). « Annuler » supprime le brouillon et remet la carte.
3. « Autre durée » → ouvre « Mon mois ». « Voir mon mois et soumettre » → `/timesheets`.
4. Alternatives : rien à encoder → état vide « Tout est à jour » ; mois soumis → pas de pile, bandeau « Mois soumis à la direction » ; aucune séance à venir → « Aucune séance à venir » ; erreur réseau/serveur → message + « Réessayer », la carte reste ; échec d'encodage (422) → message du serveur en bandeau, la carte reste.

### 5.3 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | un professeur avec une séance commencée sans heures ce mois | `GET /mon-accueil` | elle est dans `a_encoder` avec `duree_par_defaut` ; les séances à venir, annulées ou d'un autre mois n'y sont pas | Feature |
| AC-2 | des séances aujourd'hui avant/après l'heure courante | `GET /mon-accueil` | `prochaine_seance` = première non terminée (en cours incluse) ; une séance annulée ou « remplacé » est ignorée | Feature |
| AC-3 | un professeur qui en remplace un autre | `GET /mon-accueil` | `prochaine_seance.remplace` = nom du professeur remplacé | Feature |
| AC-4 | une séance déjà encodée | `GET /mon-accueil` | elle n'est plus dans `a_encoder`, elle compte dans `progression.seances_encodees` | Feature |
| AC-5 | des heures encodées | `GET /mon-accueil` | `progression.heures` et `montant` = ceux de « Mon mois » | Feature |
| AC-6 | mois soumis | `GET /mon-accueil` | `a_encoder` vide, `mois_soumis` vrai | Feature |
| AC-7 | un autre professeur a des heures | `GET /mon-accueil` | aucune donnée de l'autre professeur | Feature (isolation) |
| AC-8 | un directeur ou admin | `GET /mon-accueil` | 403 ; sans jeton 401 | Feature |
| AC-9 | un professeur connecté | login | arrivée sur `/aujourdhui` ; l'onglet « Aujourd'hui » est le premier | Recette R1 |
| AC-10 | la pile « À encoder » | tap « Encoder N h » | un brouillon est créé (`POST /timesheets`), toast avec « Annuler » 5 s ; « Annuler » → `DELETE` du brouillon, la carte revient | Recette R1, R2 |
| AC-11 | liste vide / mois soumis / erreur | affichage | états décrits en 5.2 | Recette R3 |

## 6. 🔴 Règles métier et cycle de vie
- **RG-1 :** « à encoder » reprend `TimesheetService::peutEncoder` (non annulée, professeur non remplacé) et se limite au mois courant (Europe/Brussels), séance commencée (`aCommence`).
- **RG-2 :** les règles d'écriture restent celles de `TimesheetService::creer` (mois courant, mois non soumis, doublon, annulée) : l'écran n'en recopie aucune.
- **RG-3 :** durée proposée = `duree_par_defaut` (heures défrayables), jamais la durée du calendrier.
- **RG-4 :** l'encodage crée un brouillon ; rien n'est soumis ni signé ici.
- **RG-5 :** « Annuler » n'est possible que sur le brouillon créé (`can.delete`) ; passé les 5 s, la correction se fait dans « Mon mois ».
- Statuts : aucun nouveau (états d'encodage existants `a_encoder`/`brouillon`…).

## 7. 🔴 Données et migration
Aucune table, colonne, migration, backfill ni seeder. Lecture seule de `course_sessions`, `session_professors`, `timesheets`. Pas d'impact RGPD (données du professeur connecté uniquement).

## 8. Exigences UX/UI
> Workflow : sub-agent UX Expert (UX-MOBILE-01, écran A et B). Maquette : `docs/mockups/UX-MOBILE-01/index.html`. Validé par le directeur le 2026-10-25 ☑

| Écran | Contenu | Actions primaires | Composants |
|---|---|---|---|
| `/aujourdhui` | Salutation, anneau, prochaine séance, pile « À encoder » | « Encoder N h », « Autre durée », « Liens du cours », « Voir mon mois » | `useApiQuery`, `LoadingBlock`/`ErrorBlock`/`EmptyBlock`, `useToast`, `client.js` |

**Les 4 états :**
| Écran | Chargement | Vide | Erreur | Succès |
|---|---|---|---|---|
| Aujourd'hui | squelette | « Tout est à jour. Rien à encoder » ; « Aucune séance à venir » | « Impossible de charger votre journée. Réessayez » + bouton | toast « Encodé · 2 h » + « Annuler » |

- **Textes exacts :** *Aujourd'hui · Bonjour {prénom} · Prochaine séance · À encoder · Encoder 2 h · Autre durée · Liens du cours · Voir mon mois et soumettre · Tout est à jour · Encodé · 2 h · Annuler · Vous remplacez {nom}*.
- **Appareils :** ☑ Mobile (priorité) ☑ Tablette ☑ Desktop (colonne centrée, même contenu).
- **Accessibilité :** anneau avec texte alternatif (« 6 séances encodées sur 9 »), toast `aria-live`, boutons ≥ 44 px, animation coupée avec `prefers-reduced-motion`.
- **Charge cognitive :** 1 tap ; durée pré-remplie ; aucune confirmation (annulation à la place).

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Sécurité | Données du professeur connecté uniquement, 401/403 testés |
| Performance | 1 seul appel au chargement ; requêtes avec eager loading (pas de N+1) ; volume : une vingtaine de séances par mois |
| Compatibilité | Fuseau Europe/Brussels pour « aujourd'hui » et « commencée » ; français |

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB | Aucun | — |
| Back | `MonAccueilService`, `MonAccueilController`, route `GET /mon-accueil` | S |
| API | `GET /api/mon-accueil` (professeur) ; écriture : `POST/DELETE /timesheets` existants | S |
| Front | `pages/AujourdhuiPage.jsx`, `hooks/useMonAccueil`, route `/aujourdhui`, onglet « Aujourd'hui » (UI-M2), redirection après connexion, styles | M |
| Tests | Feature `MonAccueilTest` (AC-1 à AC-8) ; `npm run lint && npm run build` ; vérification navigateur | M |
| Docs | `docs/API_MON_ACCUEIL.md` | S |

- **Risques :** fuseau et séances « en cours » à minuit ; cohérence avec `monMois` (même service de lissage). **Retour arrière :** revert du commit (aucune donnée écrite par la nouvelle route). **ADR :** non.

## 11. Plan de recette
| # | Rôle | Scénario | Résultat | AC | OK/KO |
|---|---|---|---|---|---|
| R1 | Professeur (390 px) | Se connecter, encoder une séance d'un tap | Arrivée sur Aujourd'hui, carte retirée, anneau avancé, toast | AC-9, 10 | ☐ |
| R2 | Professeur | « Annuler » dans les 5 s | Carte et compteurs restaurés, brouillon supprimé | AC-10 | ☐ |
| R3 | Professeur | Mois soumis / rien à encoder / serveur coupé | États vide, bandeau, erreur + Réessayer | AC-11 | ☐ |

**Jeu de données :** `lgit_demo` (alice, séances d'octobre 2026 ; voir `scripts/demo-timesheets.sh`).

## 12. Questions ouvertes
Aucune bloquante. Les séances non encodées des mois précédents restent traitées dans « Mon mois » (pas dans la pile).

## 13. Validation (DoR)
Décisions du directeur du 2026-10-25. ☑ UX Expert ☑ Mock-ups ☑ §1–7 ☑ Permissions ☑ AC testables ☑ Impact données (aucun).
