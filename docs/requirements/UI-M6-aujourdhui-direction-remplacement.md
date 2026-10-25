# UI-M6 — « Aujourd'hui » direction, fiche séance et remplacement atomique avec mail — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. Sixième tranche de la refonte mobile-first (après UI-M1 à UI-M5). Tranche verticale complète : API → UI → tests. Maquettes : `docs/mockups/UX-MOBILE-02/index.html` (smartphone) et `desktop.html` (ordinateur) ; décisions : `docs/mockups/UX-MOBILE-02/WORKFLOW_UX.md`.

| | |
|---|---|
| **ID / Titre** | UI-M6 — Page « Aujourd'hui » de la direction, fiche séance (remplacer, ajouter, modifier, annuler), remplacement d'un professeur sur plusieurs séances en un bloc atomique, mail au remplaçant |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) — maquettes et décisions validées le 2026-10-25 ☑ En dev (livré, vérifié dans le navigateur, tests back verts) ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Architecte / Dev** | *à nommer* |
| **Date / Version** | 2026-10-25 · v1.0 |
| **Liens** | `CLS-04-ajout-professeur-session.md`, `CLS-05`, `CLS-06`, `PROF-02`, `SessionReplacementService`, `docs/API_UI_M6.md`, `UI-M2`, `UI-M3` |

---

## 1. 🔴 Contexte et problème

- **Situation actuelle :** pour changer le professeur d'une séance, la direction ouvre la classe, trouve la séance, ouvre une modale en lecture seule puis passe par des appels qu'aucun écran ne propose en un parcours : remplacement, ajout, déplacement, annulation sont dispersés ; le remplacement se fait **une séance à la fois**, sans voir les conflits d'horaire **avant** d'agir ; le remplaçant n'est **pas prévenu**. Il n'existe pas de page « qui enseigne où aujourd'hui ».
- **Douleur :** un professeur indisponible le matin même : la direction, au téléphone, doit remplacer à la main séance après séance, sans garantie que le remplaçant le sache, ni que l'opération soit cohérente (réussite partielle possible).
- **Décisions du directeur (2026-10-25) :** le remplaçant est **prévenu par mail** ; le remplacement multiple est **atomique (tout ou rien)** ; **seule la direction/l'admin décide** de changer un professeur, de déplacer ou créer une séance (un professeur ne se déclare jamais absent dans l'application) ; les tarifs sont visibles par la direction et l'admin ; le lieu n'est pas un filtre.

## 2. 🔴 Objectifs et indicateurs de succès

| Objectif | Indicateur | Cible | Mesure |
|---|---|---|---|
| Remplacer vite | taps pour remplacer le professeur d'une séance du jour (carte → Remplacer → professeur → confirmer) | ≤ 4 | recette R1 |
| Voir les conflits avant d'agir | candidats avec leurs conflits d'horaire avant la confirmation | 100 % | tests AC-3, recette R1 |
| Tout ou rien | un remplacement multiple échoue en bloc ou réussit en bloc | 0 réussite partielle | tests AC-6, AC-7 |
| Remplaçant informé | mail + notification au remplaçant à chaque remplacement | 100 % (hors échec d'envoi, jamais bloquant) | tests AC-8 |
| Savoir qui enseigne où | page « Aujourd'hui » direction : séances du jour et du lendemain, séances sans professeur, timesheets à valider | 1 écran | recette R2 |

**Hors périmètre :** « décaler toutes les séances suivantes » depuis la fiche (reste l'écran classe/ordinateur, CLS-06), création de séance/classe, modification de classe (UI-M7), fiche professeur mobile complète (UI-M7), notification au remplaçant lors d'un **ajout** ponctuel (CLS-04 inchangé), notification du professeur remplacé, SMS, recherche globale.

## 3. 🔴 Acteurs, rôles et permissions

**Personas :** Directeur/Staff et Admin (smartphone et ordinateur) ; Professeur remplaçant (destinataire du mail, voit la séance dans « Aujourd'hui »).

| Action | Admin | Staff | Professeur |
|---|---|---|---|
| `GET /accueil-direction`, `GET /sessions/{id}`, `GET /sessions/{id}/candidats`, `GET /professeurs/{id}/seances` | L | L | — (403) |
| Remplacer / ajouter / retirer un professeur d'une séance (existant) | M | M | — |
| Remplacement multiple (aperçu et application) | M | M | — |
| Modifier / annuler une séance (existant) | M | M | — |

Policy : `CourseSessionPolicy::replace` (staff) ; routes sans jeton → 401, professeur → 403. Aucune permission nouvelle.

## 4. 🔴 Glossaire métier

| Terme affiché | Définition | Nom technique |
|---|---|---|
| Aujourd'hui (direction) | Page d'arrivée de la direction | `GET /api/accueil-direction`, route `/aujourdhui` |
| Fiche séance | Détail d'une séance avec ses professeurs et ses actions | `/seances/:id`, `GET /api/sessions/{id}` |
| Candidats | Professeurs actifs pouvant intervenir, avec leurs conflits d'horaire | `GET /api/sessions/{id}/candidats` |
| Remplacer sur plusieurs séances | Remplacement atomique d'un professeur par un autre sur un ensemble de séances | `POST /api/professeurs/{id}/remplacements` |
| Conflit | Le professeur a déjà une séance non annulée qui chevauche l'horaire | `conflits[]` |

## 5. 🔴 Parcours et critères d'acceptation

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité |
|---|---|---|---|---|
| US-1 | directeur | voir en arrivant les séances en cours, plus tard et demain, avec leurs professeurs, et les alertes | savoir qui enseigne où | Must |
| US-2 | directeur | ouvrir une séance et la remplacer / y ajouter un professeur / la modifier / l'annuler | réagir en direct | Must |
| US-3 | directeur | voir les conflits des candidats avant de choisir | éviter de doubler un professeur | Must |
| US-4 | directeur | remplacer un professeur sur toutes ses séances de la période en une fois, tout ou rien | ne pas laisser un état à moitié modifié | Must |
| US-5 | professeur remplaçant | être prévenu par mail et dans la cloche | savoir que je dois assurer la séance | Must |
| US-6 | directeur | appeler un professeur d'un tap | joindre quelqu'un tout de suite | Should |

### 5.2 Parcours
1. **Aujourd'hui (direction)** : alertes (séances sans professeur, timesheets à valider), groupes « En cours » / « Plus tard » / « Demain », carte séance (heure, cours, lieu, professeurs, « Appeler »). Tap → fiche séance.
2. **Fiche séance** : horaire, lieu, statut, professeurs (rôle, origine) ; barre d'actions Remplacer · Ajouter · Modifier · Plus (Annuler). « Remplacer » → feuille « Choisir le remplaçant » (disponibles, conflits, déjà sur la séance) → feuille d'impact (« Les heures déjà encodées ne bougent pas », « {nom} est prévenu(e) par mail ») → confirmation → toast avec « Annuler » (annule le remplacement).
3. **Remplacer sur plusieurs séances** (fiche professeur) : période (aujourd'hui / cette semaine), séances cochées par défaut (celles aux heures déjà encodées sont signalées), choix du remplaçant, aperçu des conflits, « Remplacer sur N séances » en un bloc.
4. Alternatives : séance annulée (409, message), remplaçant déjà présent (422), séance déjà remplacée (422) → **aucune** séance du lot n'est modifiée ; conflit d'horaire = avertissement non bloquant ; échec d'envoi du mail = jamais bloquant ; liste vide (rien aujourd'hui) ; erreur réseau (Réessayer).

### 5.3 Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | séances aujourd'hui/demain, une annulée | `GET /accueil-direction` | séances non annulées avec professeurs (non remplacés), `alertes.sans_professeur`, `alertes.timesheets_a_valider` | Feature |
| AC-2 | un professeur | `GET /accueil-direction` | 403 ; sans jeton 401 | Feature |
| AC-3 | une séance avec conflits | `GET /sessions/{id}/candidats` | professeurs actifs hors ceux déjà sur la séance, chacun avec `conflits[]` (classe, horaires) ; archivés exclus | Feature |
| AC-4 | un professeur et une période | `GET /professeurs/{id}/seances` | ses séances non annulées, non remplacées, dans la période, avec `heures_encodees` | Feature |
| AC-5 | remplacement d'une séance | `POST /sessions/{id}/remplacer` | le remplaçant reçoit une notification (base) et un mail | Feature |
| AC-6 | un lot de séances valides | `POST /professeurs/{id}/remplacements` | toutes remplacées, un seul mail récapitulatif au remplaçant | Feature |
| AC-7 | un lot dont une séance est annulée ou déjà remplacée | même appel | 4xx, **aucune** séance modifiée, aucun mail | Feature |
| AC-8 | aperçu | `POST /professeurs/{id}/remplacements/apercu` | mêmes règles, **aucune écriture**, avertissements de conflit renvoyés | Feature |
| AC-9 | échec d'envoi du mail | remplacement | le remplacement reste enregistré | Feature |
| AC-10 | direction sur smartphone | « Aujourd'hui » | page d'arrivée de la direction ; onglets Aujourd'hui · Timesheets · Classes · Professeurs · Plus | Recette R2 |
| AC-11 | fiche séance | « Remplacer » → candidat → confirmer | séance mise à jour, toast « Annuler » | Recette R1 |
| AC-12 | écran ≥ 861 px | fiche séance | mêmes actions en dialogues centrés | Recette R4 |

## 6. 🔴 Règles métier et cycle de vie
- **RG-1 :** les règles du remplacement unitaire (`SessionReplacementService::remplacer`) s'appliquent à chaque séance du lot : séance annulée (409), remplaçant ≠ remplacé, remplacé assigné et non déjà remplacé, remplaçant absent de la séance (422).
- **RG-2 :** lot atomique : une seule transaction ; au premier échec, tout est annulé.
- **RG-3 :** un conflit d'horaire est un avertissement, jamais un blocage (CLS-04).
- **RG-4 :** aucune timesheet n'est modifiée par un remplacement (RG-9 existante).
- **RG-5 :** après validation de la transaction, le remplaçant reçoit une notification (cloche + mail) listant les séances ; un échec d'envoi est journalisé et n'annule rien ; un lot = un seul mail.
- **RG-6 :** « sans professeur » = séance non annulée, des 7 prochains jours, sans ligne de professeur non remplacé.
- Statuts : `STATUTS_SESSION` inchangés ; remplacé = `remplace=true`.

## 7. 🔴 Données et migration
Aucune migration : lecture de `course_sessions`, `session_professors`, `professeurs`, `timesheets` ; la table `notifications` (Laravel) existe déjà. Pas de nouvelle donnée personnelle (nom, téléphone déjà visibles par la direction). Mail : contenu minimal (séances, dates, cours, nom du remplacé).

## 8. Exigences UX/UI
> Workflow : sub-agent UX Expert (UX-MOBILE-02). Maquettes validées le 2026-10-25 ☑

| Écran | Contenu | Actions | Composants |
|---|---|---|---|
| `/aujourdhui` (direction) | alertes, groupes de séances | Ouvrir la séance, Appeler | `Banner`, `StatutBadge`, `useApiQuery` |
| `/seances/:id` | séance, professeurs | Remplacer, Ajouter, Modifier, Plus › Annuler | `AdminModal` (feuille / dialogue), `StatutBadge` |
| Fiche professeur (ordinateur et mobile) | séances à venir | Remplacer sur plusieurs séances | `AdminModal` |

**4 états :** chargement (squelette), vide (« Aucune séance aujourd'hui »), erreur (Réessayer), succès (toast avec « Annuler »).

- **Textes exacts :** *Aujourd'hui · En cours · Plus tard · Demain · Remplacer · Ajouter · Modifier · Annuler la séance · Choisir le remplaçant · Disponibles · Conflit d'horaire · Déjà sur la séance · Les heures déjà encodées ne bougent pas · {nom} est prévenu(e) par mail · Remplacer sur {N} séances · Tout ou rien : si une séance échoue, aucune n'est modifiée.*
- **Appareils :** ☑ Mobile (feuilles basses) ☑ Tablette ☑ Desktop (dialogues centrés, mêmes libellés).
- **Accessibilité :** boutons nommés, listes de choix navigables au clavier, focus piégé dans les feuilles (AdminModal), toasts `aria-live`.
- **Charge cognitive :** 4 taps pour un remplacement ; conflits visibles avant le choix.

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| Sécurité | 401/403 testés ; staff uniquement ; contrôle serveur des règles ; mail sans donnée sensible |
| Performance | « Aujourd'hui » : 1 requête, eager loading (pas de N+1) ; candidats : conflits calculés en lot |
| Traçabilité | notifications en base (cloche) ; échec d'envoi journalisé |
| Compatibilité | Europe/Brussels ; français |

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB | Aucun | — |
| Back | `NotificationRemplacement`, `SessionReplacementService::remplacerPlusieurs`, `AccueilDirectionController`, candidats / séances d'un professeur / lot, `RemplacementLotRequest`, routes | L |
| API | `GET /accueil-direction`, `GET /sessions/{id}`, `GET /sessions/{id}/candidats`, `GET /professeurs/{id}/seances`, `POST /professeurs/{id}/remplacements/apercu`, `POST /professeurs/{id}/remplacements` ; mail ajouté à `POST /sessions/{id}/remplacer` | M |
| Front | `pages/AujourdhuiDirectionPage.jsx`, `pages/SeancePage.jsx`, composants de choix de professeur et de remplacement en lot, navigation direction (Aujourd'hui en premier), styles | L |
| Tests | Feature `RemplacementLotTest`, `AccueilDirectionTest` ; lint/build ; navigateur | M |
| Docs | `docs/API_UI_M6.md`, ce Canvas | S |

- **Risques :** mails en double si l'API est rappelée (une seule notification par appel) ; états partiels (couverts par la transaction). **Retour arrière :** revert du commit (aucune migration). **ADR :** non.

## 11. Plan de recette
| # | Rôle | Scénario | Résultat | AC | OK/KO |
|---|---|---|---|---|---|
| R1 | Directeur (390 px) | Aujourd'hui → séance → Remplacer → candidat avec conflit → confirmer → Annuler | Remplacement puis annulation du remplacement | AC-3, 5, 11 | ☐ |
| R2 | Directeur | Ouvrir « Aujourd'hui » | Groupes, alertes, onglets | AC-1, 10 | ☐ |
| R3 | Directeur | Fiche professeur → remplacer sur plusieurs séances, dont une annulée | Rien n'est modifié ; message ; sinon tout est remplacé + 1 mail | AC-6, 7 | ☐ |
| R4 | Directeur (1280 px) | Fiche séance | Dialogues centrés | AC-12 | ☐ |
| R5 | Professeur remplaçant | Cloche et « Aujourd'hui » | Notification, séance visible avec « Vous remplacez X » | AC-5 | ☐ |

**Jeu de données :** `lgit_demo` (alice, bob, chloé… classe React du mercredi).

## 12. Questions ouvertes
Aucune bloquante. Le « remplaçant à trouver » (état existant) n'est pas créé depuis cette tranche.

## 13. Validation (DoR)
Maquettes et décisions du directeur du 2026-10-25. ☑ UX Expert ☑ Mock-ups ☑ §1–7 ☑ Permissions ☑ AC testables ☑ Impact données (aucune migration).
