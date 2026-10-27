# Canvas de Requirements — TS-05 Soumission forcée du mois par la direction

| | |
|---|---|
| **ID / Titre** | TS-05 — L'admin et la direction soumettent le mois d'un professeur en l'état quand celui-ci a des heures en brouillon |
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR, product owner, 2026-10-27) ☑ En dev (T1) ☐ En recette ☐ Livré |
| **Analyste** | Analyste (Claude) + product owner | **UX/UI** | Sub-agent UX Expert ; maquette validée par le PO |
| **Architecte** | À désigner | **Dev back / front** | À désigner |
| **Date / Version** | 2026-10-27 — v0.1 | **Sprint cible** | À planifier |
| **Liens** | Maquette : `docs/mockups/TS-05/01-soumission-forcee.html` · TS-04 (versions) · TS-02 (remise en brouillon) · `docs/API_TS04.md` |

---

## 1. Contexte et problème
- **Situation actuelle :** seul le professeur soumet son mois (`TimesheetService::soumettreMois`, bouton « Soumettre mon mois »). Si un professeur a encodé des heures en brouillon mais ne soumet pas, le mois reste bloqué : la direction ne peut ni valider, ni faire signer, ni générer le PDF.
- **Douleur :** relances individuelles, retards de paie.
- **Déclencheur :** demande du PO, pour fluidifier le workflow.

## 2. Objectifs et indicateurs de succès
| Objectif | Indicateur | Cible | Mesure |
|---|---|---|---|
| Débloquer un mois non soumis | Clics pour soumettre à la place du professeur | ≤ 3 depuis le détail du mois | Recette R1 |
| Traçabilité | Soumissions forcées identifiées (auteur, message, version « par la direction ») | 100 % | Tests + R2 |
| Information du professeur | Professeur prévenu (notification et e-mail) | 100 % | Test e-mail + R3 |

**Hors périmètre :** soumission groupée depuis la synthèse (seconde tranche) ; soumission d'un mois sans heure encodée ; forcer la signature ou la validation à la place d'un autre acteur.

## 3. Acteurs, rôles et permissions
| Action | Admin | Direction (staff) | Professeur |
|---|---|---|---|
| Soumettre le mois d'un professeur en l'état (forcer) | oui | oui | — (il soumet son propre mois comme avant) |
| Voir qu'une soumission est forcée (version, frise, bandeau) | oui | oui | oui (ses mois) |

> Alimente : contrôle de rôle côté API (403 pour le professeur), test 403.

## 4. Glossaire métier
| Terme affiché | Définition | Nom technique |
|---|---|---|
| Soumission forcée | Soumission du mois par la direction ou l'admin à la place du professeur | `timesheet_versions.origine = direction` |
| Message au professeur | Motif obligatoire de la soumission forcée | `TimesheetAudit.motif` (action `soumission_forcee`) |
| Soumise par la direction | Version soumise créée par une soumission forcée | `origine = direction`, `auteur_id` |

## 5. Parcours et user stories
| ID | En tant que | Je veux | Afin de | MoSCoW |
|---|---|---|---|---|
| US-1 | direction/admin | soumettre le mois d'un professeur en l'état | lancer la validation sans attendre | Must |
| US-2 | direction/admin | repérer dans la synthèse les mois en brouillon non soumis | savoir qui relancer ou forcer | Should |
| US-3 | professeur | savoir que la direction a soumis mon mois, quand et pourquoi | comprendre l'état de mon mois | Must |
| US-4 | direction/admin | retrouver dans les versions et la frise que la soumission est forcée | auditer | Must |

**Parcours nominal :** détail du mois → bannière « 8,5 heures en brouillon, non soumises » → « Soumettre à la place du professeur… » → modale (récapitulatif, avertissements, conséquences, message obligatoire) → « Soumettre le mois de {Prénom} » → mois « À valider », professeur prévenu.

**Critères d'acceptation**
| ID | Étant donné… | Quand… | Alors… | Test |
|---|---|---|---|---|
| AC-1 | un mois avec des heures en brouillon et aucune saisie soumise | direction/admin forcent avec un message | toutes les lignes passent « soumis » en une transaction, version soumise créée (`origine = direction`, auteur) | Feature |
| AC-2 | idem | message vide ou de moins de 3 caractères | 422 : motif obligatoire, rien n'est modifié | Feature |
| AC-3 | un mois déjà soumis (ou plus avancé) | on force | 422 (409 en cas de concurrence), rien n'est modifié | Feature |
| AC-4 | un mois sans heure en brouillon | on force | 422 « aucune heure à soumettre » | Feature |
| AC-5 | un professeur | il appelle l'endpoint | 403 | Feature |
| AC-6 | une soumission forcée réussie | après la transaction | le professeur reçoit une notification et un e-mail (message, total, lignes) ; la direction n'est pas notifiée de sa propre action | Feature |
| AC-7 | une soumission forcée | lecture des versions et de l'historique | version « Soumise v1 — par {acteur} (direction) », trace d'audit par ligne, entrée de frise | Feature + Front |
| AC-8 | le mois n'est pas terminé | la modale s'ouvre | avertissement : les séances à venir ne seront pas incluses ; l'action reste possible | Front |
| AC-9 | `soumission_forcee.possible = false` | le détail s'affiche | ni bouton ni bannière | Front |
| AC-10 | une soumission forcée | le professeur ouvre Mon mois | bandeau « Votre mois a été soumis par la direction le … » avec le message | Front |

## 6. Règles métier
- **RG-1 :** la soumission forcée porte sur le mois entier du professeur, comme la soumission du professeur : toutes ses heures en brouillon du mois, en une transaction.
- **RG-2 :** possible seulement si le mois a au moins une heure en brouillon et aucune saisie déjà soumise ou plus avancée.
- **RG-3 :** message au professeur obligatoire (3 à 500 caractères) ; repris dans l'audit, la notification, l'e-mail et la frise.
- **RG-4 :** autorisée sur un mois en cours (non terminé), avec avertissement dans la modale. Les séances passées sans heures ne sont pas incluses et sont signalées.
- **RG-5 :** une version « soumise » est créée avec `origine = direction` et l'auteur ; le contrôle se fait sous verrou (aucune double soumission, concurrence avec le professeur : 409).
- **RG-6 :** après la soumission forcée, le circuit est inchangé (validation du mois entier, signature, PDF) ; la direction peut remettre le mois en brouillon.
- **RG-7 :** le plafond journalier dépassé n'est pas bloquant : information dans la modale, lissage à la validation.
- **RG-8 :** l'éligibilité est calculée par le serveur (`soumission_forcee: { possible, lignes, heures, total_eur, depassements, seances_sans_heures, mois_termine, raison_impossible }`) ; le front ne la déduit pas.

## 7. Données et migration
- `timesheet_versions.origine` (`professeur` par défaut, `direction`), ajoutée par migration ; non modifiable après création.
- Audit : une ligne `TimesheetAudit` par saisie, action `soumission_forcee` (avant `brouillon`, après `soumis`, motif).
- Notification et e-mail : nouveau code `SOUMISSION_FORCEE` dans `NotificationTimesheet`.
- Aucune reprise de données.

## 8. Exigences UX/UI
> 🚦 Workflow défini par le sub-agent UX Expert ; maquette 5 écrans (A à E) **validée par le PO le 2026-10-27** : ☑

- **Détail du mois (`DetailProfesseurMois.jsx`)** : bannière d'alerte + bouton « Soumettre à la place du professeur… » (principal tant que le mois n'est pas soumis ; « Valider le mois » désactivé). Mot « Soumettre » jamais seul.
- **Modale** : « Soumettre le mois de {nom} » ; récapitulatif (heures, montant brut, lignes) ; avertissements (mois non terminé ; séances passées sans heures ; plafond dépassé en simple information) ; conséquences énoncées ; message obligatoire ; boutons « Annuler » et « Soumettre le mois de {Prénom} ».
- **Synthèse** : badge « Brouillon non soumis · N h » dans les alertes de ligne, sans bouton.
- **Professeur** : bandeau dans Mon mois « Votre mois a été soumis par la direction le … » + message ; notification et e-mail.
- **Versions et frise** : « Soumise v1 — par {acteur} (direction) » ; entrée « Soumis par la direction ».
- **États :** chargement (squelette), « Aucune heure encodée : rien à soumettre. », erreur 409 « Ce mois vient d'être soumis par le professeur. », succès « Mois soumis pour {nom} : à valider. »
- **Mobile :** même modale ; bouton dans la barre d'actions collante du détail.
- **Accessibilité :** modale avec focus piégé et fermeture clavier (`AdminModal`), alertes en `role="alert"`, cibles tactiles ≥ 44 px.

## 9. Exigences non fonctionnelles
Sécurité : rôle admin ou staff vérifié côté serveur. Traçabilité : audit, version, notification. Volume négligeable.

## 10. Impact technique
| Couche | Changements | Taille |
|---|---|---|
| DB | migration `origine` sur `timesheet_versions` | S |
| Back | `TimesheetService::forcerSoumission` (cœur partagé avec `soumettreMois`, verrou, version `origine = direction`, audit), `TimesheetNotifier::soumissionForcee`, détail du mois (`soumission_forcee`), vue des versions (`origine`, auteur) | M |
| API | `POST /professeurs/{id}/timesheets-mois/forcer-soumission` `{annee, mois, motif}` (admin/staff) ; `soumission_forcee` dans `GET /professeurs/{id}/timesheets-mois` ; `origine` dans les versions | S |
| Front | bannière, bouton et modale dans le détail, badge de synthèse, bandeau professeur, libellé des versions et de la frise | M |
| Tests | feature (AC-1 à AC-7, concurrence), notification/e-mail, recette | M |
| Docs | `docs/API_TS05.md` | S |

- **Tranches :** T1 = back + détail du mois + bandeau professeur ; T2 = soumission groupée depuis la synthèse.
- **Retour arrière :** migration additive ; masquer le bouton suffit.

## 11. Recette
| # | Rôle | Scénario | Attendu | AC |
|---|---|---|---|---|
| R1 | Directeur | Forcer la soumission d'un mois en brouillon | Mois « À valider », ≤ 3 clics | AC-1 |
| R2 | Directeur | Lire versions et frise | « par la direction » + message | AC-7 |
| R3 | Professeur | Ouvrir Mon mois, lire l'e-mail | Bandeau, notification, e-mail | AC-6, AC-10 |
| R4 | Directeur | Forcer un mois en cours | Avertissement affiché, action possible | AC-8 |

## 12. Questions ouvertes
| # | Question | Réponse |
|---|---|---|
| Résolue | Motif | Obligatoire (2026-10-27) |
| Résolue | Mois en cours | Autorisé avec avertissement (2026-10-27) |
| Résolue | Action groupée | Seconde tranche (2026-10-27) |
| Q1 | Droits du staff limités à certaines écoles ? (à confirmer côté back : aujourd'hui aucun périmètre par école) | Non bloquante |

## 13. Validation (Definition of Ready)
| Rôle | Nom | Date | ☐ Validé | Réserves |
|---|---|---|---|---|
| UX/UI | Sub-agent UX Expert | 2026-10-27 | ☑ | Workflow §8 |
| Directeur / Sponsor | PO | 2026-10-27 | ☑ | Maquette, règles et décisions validées |
| Architecte | | | ☐ | |

**DoR :** ☑ Maquette validée ☑ Workflow UX ☑ AC testables ☑ Impact données ☑ Questions bloquantes résolues
