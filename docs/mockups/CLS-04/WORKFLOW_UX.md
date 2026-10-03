# CLS-04 — Ajouter un professeur sur une session (même passée) : workflow UX (à valider avant développement)

| | |
|---|---|
| **Auteur** | UX Expert (sub-agent) |
| **Base** | `docs/requirements/CLS-04-ajout-professeur-session.md`, `ClasseSessionsTable.jsx`, `RemplacerProfesseurModal.jsx`, mock-ups CLS-01 03 et CLS-03 (même CSS) |
| **Mock-ups** | `index.html`, `01-tableau-sessions.html`, `02-modale-ajout.html` (statiques, sélecteur d'états en haut) |
| **Statut** | Révision 1 — aucune zone de validation remplie |

## 1. Constat
Dans le tableau des sessions d'une classe (page détail classe, staff), la colonne « Actions » porte déjà « Remplacer un professeur » + « Ajuster ». Un 3ᵉ bouton surchargerait une cellule déjà étroite, et deux boutons qui ouvrent deux modales proches (« Remplacer », « Ajouter ») obligent à choisir **avant** de savoir ce qu'il y a sur la séance.

## 2. Recommandation : un seul point d'entrée « Professeurs »
Remplacer « Remplacer un professeur » par **« Professeurs »** (même emplacement, même variante secondaire, actif sur session passée ; absent si annulée). Il ouvre la modale existante, élargie :

1. **Liste de la séance** en haut (ce qui est déjà là : nom, rôle, badges « remplaçant », « ajouté », « remplacé »), avec actions par ligne : « Annuler ce remplacement » (déjà existant) et **« Retirer »** (uniquement pour les lignes « ajouté »).
2. **Deux actions en onglets** sous la liste : **Ajouter un professeur** (onglet par défaut, le cas le plus courant ici) · **Remplacer un professeur** (formulaire actuel inchangé).

Pourquoi : un clic de moins que « choisir le bon bouton puis la bonne modale », contexte visible (qui est déjà sur la séance → pas de doublon possible), un point d'entrée unique pour tout ce qui touche les professeurs d'une séance, pas de cellule d'actions plus chargée. *Alternative acceptable (Q1) :* garder « Remplacer un professeur » et ajouter « Ajouter un professeur » à côté ; même modale, onglet présélectionné ; plus explicite mais plus bruyant.

## 3. Parcours nominal (4 clics)
1. Page de la classe › ligne de la séance › **Professeurs**.
2. Onglet **Ajouter un professeur** (déjà actif) › champ **Professeur** (liste = professeurs actifs absents de la séance ; saisie pour filtrer) ; **Rôle** prérempli « Co-enseignant » (secondaire, repliable).
3. **Ajouter à cette session**.
4. Modale fermée, toast « Carol Dubois a été ajoutée à la séance 5… », colonne mise à jour : « Carol Dubois **(ajoutée)** ».

Bouton primaire désactivé tant qu'aucun professeur n'est choisi. Fermeture : « Fermer sans modifier », Échap.

## 4. Cas particuliers
- **Session passée** : bandeau bleu permanent dans l'onglet : « Cette séance est passée. Les heures déjà encodées ne changent pas ; Carol pourra encoder les siennes. » Aucun blocage, aucune confirmation supplémentaire (action réversible via « Retirer »).
- **Conflit d'horaire** : après l'ajout, **avertissement non bloquant** (même traitement que le remplaçant) : la modale reste ouverte une fois, bandeau jaune « Carol est déjà assignée à une autre session ce jour-là (Python, 14:00–16:00). L'ajout a bien été effectué. » + bouton « Fermer ». (Le POST a déjà eu lieu : pas de pré-confirmation, comme pour le remplacement.)
- **Retrait** : bouton « Retirer » sur la ligne « ajouté » → confirmation en ligne (« Retirer Carol de la séance 5 ? Ses heures déjà encodées ne changent pas. » [Retirer] [Garder]) ; toast sans « annuler » (réajouter = 4 clics).
- **Ligne de classe / remplacement** : pas de « Retirer » (aide : « Assigné via la classe : gérez-le depuis la section Professeurs de la classe »).
- **Affichage colonne** : « Alice Martin », « Carol Dubois (ajoutée) » ; le rôle n'apparaît que s'il est « remplaçant ».

## 5. États vides et erreurs
| Situation | Comportement |
|---|---|
| Chargement de la liste | squelette (2 lignes) ; onglets désactivés |
| Séance sans professeur | « Aucun professeur sur cette séance. » + onglet Ajouter actif |
| Aucun professeur disponible | liste vide : « Tous les professeurs actifs sont déjà sur cette séance. » ; bouton désactivé |
| Session annulée | bouton « Professeurs » absent (info-bulle sur le statut) ; si état obsolète → 409 : bandeau rouge « Cette session vient d'être annulée. Fermez et actualisez. » |
| Doublon (course) | 422 : message sous le champ « Ce professeur est déjà assigné à cette séance. », liste rechargée |
| Professeur remplacé sur la séance | non proposé ; 422 explicite « …a été remplacé : annulez d'abord ce remplacement » |
| Erreur réseau/serveur | bandeau rouge `role="alert"`, saisie conservée, bouton réactivé |
| Succès | toast vert `aria-live`, tableau rechargé |

## 6. Accessibilité et contenus
Modale `role="dialog"` avec focus piégé ; onglets `role="tablist"` (flèches) ; avertissements dans une région `aria-live="polite"` ; badges « ajouté » en texte (pas seulement couleur) ; libellés exacts : « Professeurs », « Ajouter un professeur », « Ajouter à cette session », « Retirer », « Fermer sans modifier ».

## 7. Questions ouvertes (validation)
1. Un point d'entrée « Professeurs » (recommandé) ou deux boutons (Q1) ?
2. Retrait d'un ajout autorisé sans condition sur les heures (proposé) ou bloqué si une timesheet existe (Q2) ?
3. Rôle visible dans le formulaire (Co-enseignant/Principal) ou masqué par défaut (Q3) ?
4. Notifier le professeur ajouté (Q5, proposé : non) ?
