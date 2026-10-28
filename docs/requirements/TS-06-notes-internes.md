# Canvas de Requirements — TS-06 Notes internes (direction et admin)

| | |
|---|---|
| **ID / Titre** | TS-06 — Notes internes sur un mois et sur chaque ligne de timesheet, visibles uniquement par la direction et l'admin |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR, product owner, 2026-10-28) ☑ En dev (T1 codée) ☐ En recette ☐ Livré |
| **Analyste** | Analyste (Claude) + product owner | **UX/UI** | Sub-agent UX Expert ; maquette validée par le PO |
| **Architecte** | À désigner | **Dev back / front** | À désigner |
| **Date / Version** | 2026-10-28 — v0.1 | **Sprint cible** | À planifier |
| **Liens** | Maquette : `docs/mockups/TS-06/01-notes-internes.html` · TS-04 (versions) · TS-05 · `docs/AUDIT_RGPD.md` |

---

## 1. Contexte et problème
- **Situation actuelle :** la direction n'a aucun endroit pour consigner, lors de la validation d'un mois, des remarques de travail (vérification faite, point à lisser, contexte) sans que le professeur les voie. Le champ « commentaire » des saisies est rédigé par le professeur et lui est visible.
- **Besoin :** un commentaire global sur le mois et un commentaire par ligne, **jamais visibles du professeur**.

## 2. Objectifs et indicateurs
| Objectif | Indicateur | Cible | Mesure |
|---|---|---|---|
| Consigner une remarque en validation | Clics pour ajouter une note à la validation | ≤ 2 | Recette R1 |
| Confidentialité | Notes présentes dans une réponse accessible au professeur | 0 | Tests anti-fuite (toutes les routes professeur) |
| Traçabilité | Auteur, date, historique des modifications | 100 % | Tests + R3 |

**Hors périmètre (v1) :** fil de notes ; saisie de notes en lot dans la validation multi-professeurs ; repère de note dans la comparaison de versions ; notes visibles d'un autre rôle.

## 3. Acteurs, rôles et permissions
| Action | Admin | Direction | Professeur |
|---|---|---|---|
| Lire les notes (mois et lignes) | oui | oui | **jamais** (403, absentes des réponses) |
| Ajouter une note | oui | oui | — |
| Modifier / supprimer sa propre note | oui | oui | — |
| Modifier / supprimer la note d'un autre (modération) | oui | non (403) | — |

## 4. Glossaire
| Terme affiché | Définition | Nom technique |
|---|---|---|
| Note interne | Texte privé de la direction/admin | `timesheet_notes` |
| Note du mois | Une note par (professeur, mois) | `timesheet_id` nul |
| Note de ligne | Une note par saisie | `timesheet_id` renseigné |

## 5. Parcours et critères d'acceptation
**Parcours :** détail du mois → carte « Note interne du mois » et icône par ligne ; ou modale de validation (un professeur) → « Ajouter une note interne (optionnel) » → la note est enregistrée avec la validation.

| ID | Étant donné… | Quand… | Alors… |
|---|---|---|---|
| AC-1 | direction/admin | ajoute une note de mois ou de ligne (1 à 1000 caractères) | note créée avec auteur et date |
| AC-2 | une note existante | son auteur la modifie | texte mis à jour, ancienne version conservée dans l'historique, « modifiée N fois » |
| AC-3 | une note d'un autre auteur | un directeur la modifie ou la supprime | 403 ; l'admin le peut |
| AC-4 | une note | son auteur la supprime | note et historique supprimés |
| AC-5 | un professeur | appelle une route de notes | 403 |
| AC-6 | une note existe | le professeur ouvre Mon mois, la confirmation, les versions, la liste ou le détail de ses saisies, ses notifications | aucune note n'apparaît (tests par route) |
| AC-7 | une note existe | PDF, signature, empreinte et snapshot de version | aucune note incluse ; l'empreinte ne change pas |
| AC-8 | une validation d'un seul professeur | note saisie dans la modale | validation et note enregistrées ; une note vide ne crée rien ; la validation ne dépend jamais de la note |
| AC-9 | une validation de plusieurs professeurs | la modale s'ouvre | aucun champ de saisie ; rappel « N professeurs ont une note interne » |
| AC-10 | une note ajoutée | la frise du détail directeur | événement « Note interne ajoutée » sans texte, jamais visible du professeur |
| AC-11 | des notes de plus de 24 mois après la fin du mois | la commande de rétention s'exécute | notes et historique supprimés |
| AC-12 | une saisie supprimée | — | sa note de ligne est supprimée |

## 6. Règles métier
- **RG-1 :** une seule note par saisie et une seule note par (professeur, mois) ; modifiable.
- **RG-2 :** 1 à 1000 caractères ; facultative, jamais bloquante pour la validation.
- **RG-3 :** saisie à tout moment depuis le détail du mois ; la modale de validation d'un professeur est un raccourci.
- **RG-4 :** modification/suppression par l'auteur ; l'admin peut modérer. Historique des modifications en lecture seule.
- **RG-5 :** stockage **séparé** de `timesheets` (la ressource `TimesheetResource` expose tous les attributs d'une saisie) ; aucune note dans les snapshots de versions, le PDF, la signature, les notifications ou e-mails.
- **RG-6 :** conservation : 24 mois après la fin du mois concerné (suppression, historique compris).
- **RG-7 :** donnée personnelle : à inscrire à l'audit RGPD (droit d'accès possible).

## 7. Données
- `timesheet_notes` : `id`, `professeur_id` (cascade), `annee`, `mois`, `timesheet_id` (nullable, cascade), `auteur_id` (set null), `texte`, `created_at`, `updated_at`. Unique `(professeur_id, annee, mois)` pour la note du mois (`timesheet_id` nul) et unique `timesheet_id` pour la note de ligne (deux index).
- `timesheet_note_historique` : `id`, `note_id` (cascade), `texte` (ancienne version), `auteur_id`, `created_at`.
- Rétention : commande `timesheets:purger-notes` (quotidienne, 04:15), idempotente, avec `--simulation`.

## 8. Exigences UX/UI
> 🚦 Workflow défini par le sub-agent UX Expert ; maquette 5 écrans (A à E) **validée par le PO le 2026-10-28** : ☑

- **Détail du mois :** carte repliable « Note interne du mois » ; une icône cadenas par ligne dans la colonne Actions (grisée si vide, accentuée si note) ouvrant la note sous la ligne ; colonne « Commentaire (professeur) » inchangée.
- **Confidentialité (partout) :** « Note interne — visible uniquement par la direction et l'administration » ; libellé « Note interne (privée) » ; « Non visible du professeur » ; placeholder « Note pour la direction : rien n'est envoyé au professeur » ; bouton « Enregistrer la note interne » ; couleur ambre dédiée ; jamais « Envoyer » ni « Répondre ».
- **Validation (un professeur) :** champ facultatif replié « Ajouter une note interne (optionnel) ». **Validation en lot :** aucun champ, rappel « N professeurs ont une note interne ».
- **Modifier/supprimer :** auteur, rôle, date, « modifiée N fois », historique en lecture seule ; suppression avec toast « Note supprimée · Annuler » (8 s, suppression réelle à l'issue).
- **Mobile :** feuille du bas, champ plein largeur, mention de confidentialité visible.
- **États :** vide, chargement (squelette), erreur « Note non enregistrée · Réessayer » (texte conservé), « Enregistré ».
- **Accessibilité :** nom accessible « Note interne de la ligne du 15/10 — présente/vide », focus piégé et Échap, `aria-describedby` vers la mention de confidentialité, cibles ≥ 44 px, compteur de caractères annoncé.

## 9. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB | `timesheet_notes`, `timesheet_note_historique` | S |
| Back | `TimesheetNote` (+ historique), `TimesheetNoteService` (upsert, droits, historique), contrôleur (staff), `notes_internes` dans le détail du mois, événement de frise, commande de rétention, tests anti-fuite | M |
| API | `PUT/DELETE /professeurs/{id}/timesheets-mois/notes`, `notes_internes` dans `GET /professeurs/{id}/timesheets-mois` | S |
| Front | carte du mois, icône et note de ligne, champ dans `ValiderLotModal` (un professeur), rappel en lot, toast d'annulation, frise | M |
| Tests | feature (AC-1 à AC-12), anti-fuite sur chaque route professeur | M |
| Docs | `docs/API_TS06.md`, mise à jour `docs/AUDIT_RGPD.md` | S |

- **Tranches :** T1 = back + détail du mois (carte, icônes, historique, suppression/annulation) + rétention ; T2 = champ dans la modale de validation et rappel en lot.
- **Risque majeur :** fuite par oubli (API professeur, e-mails, versions, frise, PDF) → tests anti-fuite obligatoires.

## 10. Recette
| # | Rôle | Scénario | Attendu |
|---|---|---|---|
| R1 | Directeur | Valider un mois avec une note interne | Note enregistrée, ≤ 2 clics |
| R2 | Professeur | Parcourir Mon mois, versions, notifications | Aucune note visible |
| R3 | Directeur | Modifier puis supprimer une note, lire l'historique | Historique, annulation 8 s |
| R4 | Directeur B | Modifier la note d'un autre | Refusé ; l'admin peut |

## 11. Questions ouvertes
| # | Question | Réponse |
|---|---|---|
| Résolue | Modifiable avec historique | Oui (2026-10-28) |
| Résolue | Saisie à tout moment | Oui, modale = raccourci (2026-10-28) |
| Résolue | Une note par ligne et par mois | Oui (2026-10-28) |
| Résolue | Conservation | 24 mois après la fin du mois (2026-10-28) |
| Q1 | Le droit d'accès du professeur (art. 15 RGPD) couvre-t-il ces notes ? Procédure à définir avec le juriste | Non bloquante pour le code |

## 12. Validation (Definition of Ready)
| Rôle | Nom | Date | ☐ Validé | Réserves |
|---|---|---|---|---|
| UX/UI | Sub-agent UX Expert | 2026-10-28 | ☑ | Workflow §8 |
| Directeur / Sponsor | PO | 2026-10-28 | ☑ | Maquette, règles et décisions validées |

**DoR :** ☑ Maquette validée ☑ Workflow UX ☑ AC testables ☑ Impact données ☑ Questions bloquantes résolues
