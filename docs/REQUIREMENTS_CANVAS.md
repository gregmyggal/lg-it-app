# Canvas de Requirements — Modèle

> **Mode d'emploi** — Copier ce fichier vers `docs/requirements/<ID>-<slug>.md` pour chaque évolution.
> Propriétaire : **Analyste fonctionnel**. Co-signataires : **UX/UI**, **Architecte**, **Dev back**, **Dev front**.
> Remplir dans l'ordre. Les blocs marqués 🔴 sont **bloquants** pour la Definition of Ready
> (voir `DEVELOPMENT_STANDARDS.md` §1). Supprimer les consignes en italique une fois rempli.

| | |
|---|---|
| **ID / Titre** | *ex. TS-12 — Synthèse mensuelle des heures* |
| **Statut** | ☐ Brouillon ☐ En revue ☐ Validé (DoR) ☐ En dev ☐ En recette ☐ Livré |
| **Analyste** | | **UX/UI** | |
| **Architecte** | | **Dev back / front** | | 
| **Date / Version** | | **Sprint cible** | |
| **Liens** | *Maquettes, ADR, issues, specs précédentes* |

---

## 1. 🔴 Contexte et problème
*Quel problème vécu, par qui, à quelle fréquence ? Décrire la situation actuelle (« comment ça se passe aujourd'hui ? »), avec un exemple concret. Pas de solution ici.*

- **Situation actuelle :**
- **Douleur / coût :** *(temps perdu, erreurs, retards de paiement…)*
- **Déclencheur :** *(pourquoi maintenant ?)*

## 2. 🔴 Objectifs et indicateurs de succès
| Objectif métier | Indicateur mesurable | Cible | Comment mesurer |
|---|---|---|---|
| *ex. Réduire le temps d'encodage* | *clics / minutes par saisie* | *≤ 3 clics* | *recette chronométrée* |

**Hors périmètre (explicite) :** *ce qu'on ne fait volontairement pas.*

## 3. 🔴 Acteurs, rôles et permissions

**Persona(s) concernés :** *(Professeur · Directeur/Staff · Admin · Élève via code)* — contexte d'usage (poste, mobile, fréquence).

**Matrice rôle × action** *(C = créer, L = lire, M = modifier, S = supprimer, V = valider, — = interdit ; préciser « ses propres » si isolé)*

| Ressource / Action | Admin | Staff (directeur) | Professeur | Élève (code) |
|---|---|---|---|---|
| *ex. Saisie d'heures* | C L M S V | L V | C L M (ses brouillons) | — |

> 👉 Alimente : Policies (back), `can.*` des Resources (API), visibilité des boutons et routes (front), tests 403.

## 4. 🔴 Glossaire métier
*Un terme = une définition = un nom de code. Sert de référence unique à l'UI, aux tables et à l'API.*

| Terme métier (affiché) | Définition | Nom technique (table/champ/enum) |
|---|---|---|
| *Co-enseignant* | *Prof assigné à un cours en plus du principal* | `professeur_cours.role = co_enseignant` |

## 5. 🔴 Parcours utilisateurs et user stories

Pour chaque rôle : **parcours nominal** puis **cas alternatifs et erreurs**.

### 5.1 User stories
| ID | En tant que… | Je veux… | Afin de… | Priorité (MoSCoW) |
|---|---|---|---|---|
| US-1 | *professeur* | *…* | *…* | Must |

### 5.2 Parcours (étapes numérotées, par rôle)
1. *Le professeur ouvre … → voit … → clique … → obtient …*
2. *Alternatives : liste vide, erreur réseau, donnée refusée, verrou, conflit…*

### 5.3 Critères d'acceptation (Given / When / Then — testables)
| ID | Étant donné… | Quand… | Alors… | Test (back / front / recette) |
|---|---|---|---|---|
| AC-1 | *un brouillon existant* | *le prof clique « Soumettre »* | *le statut passe à `soumis`, la saisie est verrouillée, un toast confirme* | *Feature + Recette R1* |

## 6. 🔴 Règles métier et cycle de vie

**Règles** *(numérotées, sans ambiguïté, avec exemples chiffrés si calcul)*
- **RG-1 :** …
- **RG-2 :** …

**Carte des statuts** *(contrat commun analyste / UX / dev — source de vérité pour `utils/statuts.js` et la table de transitions)*

| Statut (valeur stockée) | Libellé affiché | Couleur/badge | Modifiable par | Transitions autorisées → | Déclencheur | Notification |
|---|---|---|---|---|---|---|
| `brouillon` | Brouillon | ambre | Prof (propriétaire), Admin | `soumis` | « Soumettre » | — |

**Calculs / formules :** *(arrondis, unités, fuseaux, période de référence)*

## 7. 🔴 Données et migration

| Entité / champ | Type | Obligatoire | Contraintes (unique, FK, valeurs) | Exemple |
|---|---|---|---|---|
| | | | | |

- **Nouvelles tables / colonnes :**
- **Données existantes impactées :** *volume, valeurs legacy, mapping ancien → nouveau*
- **Backfill / migration de données :** *idempotent ? réversible ? sauvegarde ?*
- **Rétention / archivage / RGPD :** *durée, anonymisation, droit d'accès*
- **Seeders / factories à créer :**

## 8. Exigences UX/UI *(à remplir avec l'UX expert)*

> 🚦 **Barrière de validation** : le workflow doit être défini par le **sub-agent UX Expert** (intégré aux trajets actuels de l'application) et matérialisé par des **mock-ups validés** *avant* tout développement.
> Workflow UX : *lien / résumé* · Mock-ups : *`docs/mockups/<ID>/`* · Validé par : *nom, date* ☐

**Écrans concernés :** *nouveaux / modifiés / route / entrée de menu / rôles*

| Écran | Contenu clé | Actions primaires | Composants du design system réutilisés | Maquette |
|---|---|---|---|---|
| | | | `AdminPageLayout`, `AdminModal`, … | *lien* |

**Les 4 états** *(obligatoires — voir Standards §6.2)*
| Écran | Chargement | Vide (message + action) | Erreur (message + action) | Succès (confirmation) |
|---|---|---|---|---|
| | | | | |

- **Textes exacts** *(libellés de boutons, messages, aide contextuelle, confirmations avec conséquence énoncée)* :
- **Formulaires :** champs, valeurs par défaut, validations, messages d'erreur par champ, ordre de tabulation
- **Appareils :** ☐ Desktop ☐ Tablette ☐ Mobile *(priorité pour l'usage professeur)*
- **Accessibilité :** clavier, contraste, lecteurs d'écran, `aria-live`
- **Feedback :** toast / bandeau / e-mail / notification — quand et pour qui
- **Charge cognitive :** nb de clics du parcours nominal ≤ ___ ; informations pré-remplies :

## 9. Exigences non fonctionnelles
| Domaine | Exigence |
|---|---|
| **Sécurité / confidentialité** | *données sensibles (tarifs, heures), isolation par professeur, audit trail* |
| **Performance** | *volumes attendus, temps de réponse cible, pagination* |
| **Traçabilité** | *qui a modifié/validé quoi et quand (log, historique visible ?)* |
| **Notifications / export** | *e-mail, PDF, CSV — contenu et destinataires* |
| **Compatibilité** | *navigateurs, langue (FR), fuseau Europe/Brussels* |

## 10. Impact technique *(à remplir avec Architecte + Dev back + Dev front)*

| Couche | Changements prévus | Résp. | Taille (S/M/L) |
|---|---|---|---|
| **DB** | *migrations, index, contraintes* | | |
| **Back** | *modèles, services, policies, FormRequests, resources (`can`, `allowed_transitions`), jobs* | | |
| **API** | *endpoints (méthode + chemin + rôle), payloads, codes d'erreur, rétrocompat.* | | |
| **Front** | *pages, composants, hooks, routes/menu, table des statuts* | | |
| **Tests** | *feature, unit, composants, E2E, recette* | | |
| **Docs / ADR** | *API, ADR à rédiger ?* | | |

- **Risques et dépendances :** *(autres features, données, prestataires)*
- **Découpage en tranches verticales :** *T1 = …(usage réel minimal) ; T2 = … ; chaque tranche livre DB→API→UI→tests.*
- **Plan de déploiement et de retour arrière :**
- **Décision d'architecture (ADR) requise ?** ☐ non ☐ oui → `docs/adr/____`

## 11. Plan de recette *(par l'analyste et l'UX, données réalistes)*

| # | Rôle joué | Scénario (étapes) | Résultat attendu | AC liés | OK / KO | Date / Qui |
|---|---|---|---|---|---|---|
| R1 | *Professeur* | *…* | *…* | AC-1 | ☐ | |

**Jeu de données de recette :** *comptes de test par rôle, cas limites (mois vide, co-enseignement, tarif changé en cours de mois…)*

## 12. Questions ouvertes
| # | Question | Propriétaire | Décision requise avant | Réponse / date |
|---|---|---|---|---|
| Q1 | | | *DoR / dev / recette* | |

*Une question bloquante non résolue empêche le passage en « Validé (DoR) ».*

## 13. Validation (Definition of Ready)

| Rôle | Nom | Date | ☐ Validé | Réserves |
|---|---|---|---|---|
| Analyste fonctionnel | | | ☐ | |
| UX/UI | | | ☐ | |
| Architecte | | | ☐ | |
| Dev back | | | ☐ | |
| Dev front | | | ☐ | |
| Directeur / Sponsor métier | | | ☐ | |

**DoR :** ☐ Workflow UX (sub-agent) + mock-ups validés ☐ §1–7 complets ☐ Matrice des permissions ☐ Carte des statuts ☐ 4 états d'écran ☐ AC testables ☐ Impact données ☐ Questions bloquantes résolues

---

## Annexe

Exemple complet et à jour : `docs/requirements/CLS-01-modele-classes.md`.
