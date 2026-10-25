# UI-M4 — « Mon mois » en cartes, soumission en feuille de récapitulatif, signature par spécimen — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Quatrième tranche de la refonte mobile-first (après UI-M1 socle, UI-M2 navigation, UI-M3 « Aujourd'hui »). Tranche d'interface : l'API et les règles existantes sont inchangées (`GET /timesheets/mon-mois`, `POST/PUT/DELETE /timesheets`, `POST /timesheets/soumettre-mois`, `/ma-signature`, signature du mois SIG-01).

| | |
|---|---|
| **ID / Titre** | UI-M4 — « Mon mois » : cartes chronologiques par semaine, encodage en 1 tap, feuille « Autre durée », barre collante « Soumettre », récapitulatif de soumission, confirmation plein écran, rappel de la signature par spécimen |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — maquettes validées le 2026-10-25 ☑ En dev (livré, vérifié dans le navigateur, 535 tests back verts) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* (front uniquement) |
| **Date / Version** | 2026-10-25 · v1.0 |
| **Liens** | `docs/mockups/UX-MOBILE-01/index.html` (écrans « Mon mois » et « Soumettre »), `UI-M3-aujourdhui-professeur.md`, `TS-03-encodage-seances-a-venir.md`, `SIG-01`, `TimesheetsPage.jsx`, `SessionsDuMoisTable.jsx` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** « Encoder mon mois » empile trois sections numérotées (séances en tableau transformé en cartes, autres heures en second tableau, total) et une bannière d'employeur avant l'action. Chaque séance mélange stepper, ⓘ, bouton « Encoder », « Modifier », « Supprimer » dans une cellule ; sous 360 px ça déborde. La soumission passe par une modale de texte sans chiffres.
- **Douleur :** lecture longue, actions éparpillées, soumission « à l'aveugle » (le professeur ne voit pas ce qu'il envoie, ni les séances oubliées).
- **Déclencheur :** maquettes UX-MOBILE-01 validées ; décision du directeur : *« La signature est créée une fois par professeur et puis appliquée à chaque fois qu'un professeur fait une action de signature. »*

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif | Indicateur | Cible | Mesure |
|---|---|---|---|
| Lire son mois d'un coup d'œil | une seule liste chronologique par semaine (séances + autres heures) | 1 liste, 0 tableau | recette R1 |
| Encoder vite | taps pour encoder une séance sans heures | 1 (durée standard) | recette R2 |
| Soumettre en connaissance de cause | récapitulatif avant envoi : heures, montant, séances encodées/total, séances oubliées, plafond | 100 % des soumissions | recette R3 |
| Ne jamais redessiner sa signature | création une seule fois (Moi › Ma signature) ; le dialogue de signature réutilise le spécimen | 0 recréation demandée si un spécimen existe | recette R4 (comportement SIG-01 conservé) |
| Aucun libellé hors cadre | débordement horizontal à 360 px | 0 | recette R1 |

**Hors périmètre :** signature **au moment** de la soumission (non : le professeur soumet ; la direction valide ; le professeur signe ensuite, flux SIG-01 inchangé), navigation par glissement entre les mois, transitions animées entre mois, bouton flottant « + » (remplacé par un bouton d'action en tête de liste), changement d'API ou de règles de soumission.

## 3. 🔴 Acteurs, rôles et permissions

**Persona :** Professeur (smartphone en priorité). Directeur/admin : inchangé (`/admin/timesheets`).

| Action | Admin | Staff | Professeur |
|---|---|---|---|
| Voir « Mon mois » | — (redirigé) | — (redirigé) | L (le sien) |
| Encoder / modifier / supprimer un brouillon, ajouter des heures | — | — | C M S (ses brouillons ; `can.update`/`can.delete` du serveur) |
| Soumettre le mois | — | — | V (le sien, une seule fois) |

Les permissions restent portées par le backend (`can.*`, `peut_encoder`, `mois_soumis`). Aucune permission nouvelle.

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Nom technique |
|---|---|---|
| Mon mois | Écran mensuel du professeur | `/timesheets`, `GET /timesheets/mon-mois` |
| Carte | Une séance ou des heures libres, avec son état | composant `CarteMois` |
| Encoder 2 h | Création d'un brouillon avec la durée défrayable par défaut | `POST /timesheets` |
| Autre durée | Feuille basse : heures (stepper), type d'activité, commentaire | `EncoderSessionModal` |
| Soumettre le mois | Passage de tous les brouillons du mois en « soumis », une seule fois | `POST /timesheets/soumettre-mois` |
| Spécimen de signature | Image de signature créée une fois (Moi › Ma signature) et réutilisée | `GET/PUT /ma-signature` |

## 5. 🔴 Parcours et critères d'acceptation

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | professeur | voir mes séances et mes autres heures dans une seule liste, par semaine | comprendre mon mois sans comparer deux tableaux | Must |
| US-2 | professeur | encoder une séance d'un tap, ou choisir une autre durée | aller vite dans le cas courant et rester précis dans les autres | Must |
| US-3 | professeur | modifier ou supprimer un brouillon depuis sa carte | corriger une erreur au même endroit | Must |
| US-4 | professeur | un récapitulatif avant de soumettre (heures, euros, séances oubliées, plafond) | ne pas envoyer un mois incomplet par erreur | Must |
| US-5 | professeur | une confirmation claire après la soumission | savoir que c'est fait et ce qui suit | Should |
| US-6 | professeur | être rappelé de créer ma signature une seule fois | pouvoir signer en un tap quand la direction aura validé | Should |

### 5.2 Parcours
1. « Mon mois » : sélecteur de mois, tuiles (heures · montant · état du mois), bouton « Ajouter des heures », liste par semaine (« Semaine du 20 oct. »), barre collante « 12 h · 142 € — Soumettre ».
2. Carte séance à encoder : « Encoder 2 h » (1 tap, toast de confirmation) et « Autre durée » (feuille basse). Carte encodée : heures, montant, badge d'état, « Modifier » / « Supprimer » si le serveur l'autorise. Séance annulée : barrée, « Annulée ». Séance à venir encodable : badge « À venir ».
3. « Soumettre » → feuille « Soumettre {mois} » : heures, montant, séances encodées sur total, alerte « N séances passées sans heures », alerte plafond journalier, rappel « une seule fois » ; si aucun spécimen : « Créez votre signature maintenant » (ouvre « Ma signature ») ; boutons « Soumettre le mois » / « Pas maintenant ».
4. Succès : écran plein écran « Mois soumis ! » (coche animée, mail à la direction, signature à venir) avec « Retour à Aujourd'hui ».
5. Alternatives : mois vide (état vide + « Ajouter des heures »), mois soumis (bandeau, cartes verrouillées, barre remplacée par l'état), erreur de chargement (Réessayer), refus serveur (422) en bandeau, mois contesté/ajusté (bloc de reconfirmation existant conservé au-dessus de la liste).

### 5.3 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | un mois avec séances et heures libres | affichage | une liste unique chronologique groupée par semaine ; aucun `<table>` | R1 |
| AC-2 | une séance `a_encoder` encodable, mois non soumis | tap « Encoder N h » | brouillon créé avec `duree_par_defaut`, carte à jour, toast | R2 |
| AC-3 | « Autre durée » | enregistrement | brouillon créé avec heures/type/commentaire saisis (feuille basse) | R2 |
| AC-4 | un brouillon (`can.update`/`can.delete`) | « Modifier » / « Supprimer » | feuille d'édition / confirmation ; la liste se met à jour | R2 |
| AC-5 | séance annulée | affichage | barrée, mention « Annulée », aucune action | R1 |
| AC-6 | mois soumis | affichage | bandeau, aucune action d'encodage, barre sans bouton | R3 |
| AC-7 | brouillons présents | « Soumettre » | feuille récapitulative avec heures, montant, séances encodées/total ; séances passées sans heures signalées | R3 |
| AC-8 | confirmation | « Soumettre le mois » | `POST /timesheets/soumettre-mois`, écran « Mois soumis ! », liste verrouillée | R3 |
| AC-9 | aucun spécimen enregistré | feuille de soumission | rappel avec « Créer ma signature » ; la soumission reste possible | R4 |
| AC-10 | un spécimen existe | dialogue « Signer mon mois » (mois validé) | l'image enregistrée est utilisée, sans redessiner | R4 |
| AC-11 | 360 px de large | toute la page | aucun débordement horizontal ; cibles ≥ 44 px | R1 |
| AC-12 | largeur ≥ 861 px | affichage | même liste de cartes, centrée (max 760 px) | R5 |

## 6. 🔴 Règles métier et cycle de vie
- **RG-1 :** règles d'écriture inchangées (mois courant, mois non soumis, doublon, séance annulée, soumission unique) ; le serveur répond, l'écran affiche son message.
- **RG-2 :** « Soumettre » reste désactivé s'il n'y a aucun brouillon ou si le mois est soumis.
- **RG-3 :** « séance passée sans heures » = séance non à venir, non annulée, `peut_encoder`, `encodage = a_encoder`.
- **RG-4 :** la signature n'est pas demandée à la soumission ; elle l'est après validation par la direction (flux SIG-01), avec le spécimen enregistré.
- Statuts : ceux de `utils/statuts.js` (`ETATS_ENCODAGE`, `STATUTS_TIMESHEET`), aucun nouveau.

## 7. 🔴 Données et migration
Aucune table, colonne, migration, backfill ni seeder. Aucun impact RGPD supplémentaire.

## 8. Exigences UX/UI
> Workflow : sub-agent UX Expert (UX-MOBILE-01, écrans C et D). Maquette : `docs/mockups/UX-MOBILE-01/index.html`. Validé par le directeur le 2026-10-25 ☑

| Écran | Contenu | Actions | Composants |
|---|---|---|---|
| `/timesheets` | Sélecteur de mois, tuiles, cartes par semaine, barre collante | Encoder, Autre durée, Modifier, Supprimer, Ajouter des heures, Soumettre | `AdminModal` (feuilles UI-M1), `HeuresStepper`, `StatutBadge`, `Banner`, `ReconfirmationMois` |

**4 états :**
| Chargement | Vide | Erreur | Succès |
|---|---|---|---|
| squelette | « Rien à encoder pour ce mois » + « Ajouter des heures » | « Impossible de charger votre mois… » + Réessayer | toasts d'encodage ; écran « Mois soumis ! » |

- **Textes exacts :** *Encoder {durée} · Autre durée · Modifier · Supprimer · Ajouter des heures · Semaine du {jj mmm} · Soumettre le mois · Pas maintenant · Mois soumis ! · {n} séances passées sans heures · Créez votre signature : vous signerez ensuite en un tap.*
- **Appareils :** ☑ Mobile (priorité) ☑ Tablette ☑ Desktop (liste centrée).
- **Accessibilité :** une `<ul>` par semaine avec titre, boutons nommés (ex. « Encoder 2 h — Scratch Junior séance 7 »), `aria-live` sur le total, écran de confirmation avec focus et `role="status"`, animation coupée si `prefers-reduced-motion`.
- **Charge cognitive :** 1 tap pour encoder ; 2 pour soumettre (Soumettre → confirmer).

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Performance | Aucun appel nouveau ; les mêmes requêtes qu'aujourd'hui |
| Compatibilité | iOS Safari ≥ 16, Chrome Android, desktop ; Europe/Brussels côté serveur ; français |
| Sécurité | Inchangée (isolation par professeur côté API) |

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB / Back / API | Aucun | — |
| Front | `components/timesheets/CarteMois.jsx`, `SoumettreMoisModal.jsx`, `MoisSoumisConfirmation.jsx` ; refonte de `pages/TimesheetsPage.jsx` ; `EncoderSessionModal` (stepper) ; suppression de `SessionsDuMoisTable.jsx` (remplacé) ; styles | M |
| Tests | `npm run lint`, `npm run build`, suite back (non impactée) ; vérification navigateur 390/1280 px ; AC listés | S |
| Docs | Ce Canvas | S |

- **Risques :** ne pas perdre une règle d'affichage de l'ancien tableau (séance remplacée, annulée, à venir) ; ReconfirmationMois reste intact. **Retour arrière :** revert du commit. **ADR :** non.

## 11. Plan de recette
| # | Rôle | Scénario | Résultat | AC | OK/KO |
|---|---|---|---|---|---|
| R1 | Professeur (360 px) | Ouvrir Mon mois (séances + heures libres + annulée) | Liste par semaine, aucun débordement | AC-1, 5, 11 | ☐ |
| R2 | Professeur | Encoder 1 tap, « Autre durée », modifier, supprimer | Brouillons corrects, liste à jour | AC-2 à 4 | ☐ |
| R3 | Professeur | Soumettre : récapitulatif, confirmation, mois verrouillé | Écran « Mois soumis ! », cartes verrouillées | AC-6 à 8 | ☐ |
| R4 | Professeur sans/avec spécimen | Récap de soumission ; signer un mois validé | Rappel si absent ; spécimen réutilisé | AC-9, 10 | ☐ |
| R5 | Professeur (1280 px) | Mon mois | Même liste centrée | AC-12 | ☐ |

**Jeu de données :** `lgit_demo` (alice : brouillons à recréer, felix : sans séance ; voir `scripts/demo-timesheets.sh`).

## 12. Questions ouvertes
Aucune bloquante. Le bouton flottant « + » de la maquette est remplacé par un bouton « Ajouter des heures » en tête de liste (évite de masquer la barre collante) ; à confirmer en recette.

## 13. Validation (DoR)
Maquettes et décisions du directeur du 2026-10-25. ☑ UX Expert ☑ Mock-ups ☑ §1–7 ☑ Permissions ☑ AC testables ☑ Impact données (aucun).
