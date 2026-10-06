# PROF-02 — Workflow UX : archiver ou supprimer un professeur (avec forçage)

> Statut : **proposition à valider** (aucun développement avant validation des maquettes).
> Demande (verbatim directeur) : *« Ajouter une fonctionnalité pour supprimer un professeur, avec possibilité de forcer si des heures ont déjà été encodées, et archiver. En cas de suppression et/ou archive d'un professeur toutes les assignations futures qui sont à son nom doivent être nettoyées, les séances restent valables mais plus assignées à ce professeur. »*
> Références : `docs/mockups/PROF-01/WORKFLOW_UX.md` (cycle de vie), `docs/requirements/CLS-08-forcer-suppression-classe.md` (pattern de forçage validé).

## 1. Constats vérifiés dans le code

- Fiche `/admin/professeurs/:id` = `components/AdminProfesseurDetail.jsx`. Bouton rouge **« 🗑️ Supprimer »** en pied de page (à côté de « ← Retour » / « Éditer ») avec un `window.confirm` natif ; le backend répond 409 dès qu'il existe une heure, une assignation ou une ligne de séance → en pratique inutilisable.
- Bloc « 🔐 Compte de connexion » (`CompteProfesseurSection.jsx`) : badge `Actif` / `Désactivé`, bouton **« Désactiver »** → `DesactiverModal` (impact : classes actives, séances à venir, heures non finalisées, « Dernier professeur de… », case **« Terminer ses assignations… » cochée par défaut**, décochable).
- Liste `/admin/professeurs` (`pages/AdminProfesseursPage.jsx`) : filtre Statut `Actifs` (défaut) / `Désactivés` / `Tous` (`utils/filtres.js`).
- `desactiver(terminer=true)` appelle `terminer()` par assignation, qui ne retire que les lignes `origine=classe`, `remplace=false`, sans heure encodée. **Restent donc à son nom** : les ajouts ponctuels (`ajout`), les remplacements qu'il assure (`remplacement`), les lignes où il est lui-même remplacé. → Le nettoyage actuel est incomplet par rapport à la demande.
- `impact().seances_a_venir` compte toutes ses lignes non remplacées futures (y compris annulées : à corriger, filtrer `statut != annulee`).
- FK : `session_professors.professeur_id` cascade, `remplace_par_professeur_id` **nullOnDelete** ; supprimer le prof supprime aussi user, timesheets, tarifs, `TimesheetPdf`, signatures (`TimesheetSignature`, `SignatureSpecimen`), audits.

## 2. Terminologie — décision tranchée

**Un seul concept : « Archiver » remplace « Désactiver ».** Deux concepts (désactiver = compte bloqué, archiver = départ) n'apportent rien au directeur : dans les deux cas le prof ne donne plus cours, ne se connecte plus et l'historique est gardé. Le mot « Archiver » est déjà celui des classes (CLS-08) et exprime « on garde tout, on range ».

| Avant | Après |
|---|---|
| Bouton « Désactiver » | **« Archiver… »** |
| Badge « Désactivé » | **« Archivé »** (gris, pas rouge : ce n'est pas une erreur) |
| Filtre « Désactivés » | **« Archivés »** |
| « Réactiver » | **« Réactiver »** (inchangé : verbe clair, cohérent avec l'invitation renvoyée) |
| Message de connexion bloquée | « Ce compte a été archivé. Contactez la direction de votre école. » |

Technique : `statut = 'inactif'` et la route `/desactiver` restent (pas de migration) ; seuls les libellés changent. Option : alias de route `/archiver`.

## 3. Points d'entrée et hiérarchie

- **Fiche professeur uniquement** (les deux actions demandent de voir l'impact ; pas d'action destructrice en ligne dans la liste, pas d'action groupée).
- Bloc « Compte de connexion » : **« Archiver… »** (bouton secondaire, contour, pas plein rouge) — action recommandée. Pour un archivé : « Réactiver » à la place.
- Pied de fiche : **retirer le gros bouton rouge « 🗑️ Supprimer »**. Le remplacer par une **zone « Zone sensible »** en bas de page (carte bordée rouge clair) : texte « Supprimer définitivement ce professeur et toutes ses données. Préférez l'archivage pour un départ. » + bouton lien rouge **« Supprimer le professeur… »**. Visible pour actif **et** archivé (cas fréquent : archivé il y a longtemps, à purger).
- Liste : badge « Archivé » dans la colonne statut ; filtre « Archivés ». Après suppression → retour à la liste.
- Permissions : admin et directeur (staff), comme CLS-08. Professeur : 403.

## 4. Parcours ARCHIVER

1. Fiche → « Archiver… » → modale **« Archiver Prénom Nom ? »** (`role=alertdialog`, focus sur « Annuler »).
2. Chargement : « Calcul de l'impact… » (squelette des 3 compteurs, bouton principal désactivé).
3. Contenu (impact recalculé par `GET /professeurs/{id}/impact-desactivation`, enrichi) :
   - Phrase d'intro : « Son historique (heures, fiches de défraiement, tarifs) est conservé. Il ne pourra plus se connecter. »
   - **« Ce qui sera nettoyé »** :
     - « N classe(s) : son assignation se termine aujourd'hui » (liste des classes, liens).
     - « N séance(s) à venir ne lui seront plus assignées » — détail repliable par type : *de ses classes* / *ajouts ponctuels* / *remplacements qu'il assurait* / *séances où il était déjà remplacé*.
   - **Avertissements (orange, non bloquants)** :
     - « Ces séances n'auront plus aucun professeur : N » + liste (date, classe) + lien « Voir dans le calendrier ».
     - « Dernier professeur de : Scratch (lun. 17h), … — pensez à en assigner un autre. »
     - « Il remplaçait un collègue sur N séance(s) : ces séances repassent “remplaçant à trouver”. »
   - **Info (bleu)** : « N séance(s) à venir avec des heures déjà encodées restent à son nom » (rare : séance du jour déjà encodée) ; « N encodage(s) d'heures non finalisé(s) : vous pourrez toujours les valider. »
   - **Pas de case à cocher** : le nettoyage est obligatoire (la demande l'impose ; une case décochable ouvre la porte à des séances fantômes assignées à un absent).
4. Boutons : « Annuler » · **« Archiver le professeur »** (primaire, pas rouge).
5. Succès : modale fermée, fiche rafraîchie (badge « Archivé », bouton « Réactiver »), toast : **« Prénom Nom archivé — N séance(s) à venir libérée(s). »** Si séances sans professeur > 0, toast avec action **« Voir les séances »**.
6. Erreur : bandeau `role=alert` dans la modale : message serveur ou « L'archivage a échoué, rien n'a été modifié. Réessayez. » ; bouton réactivé. 409 « déjà archivé » → fermer + rafraîchir.

## 5. Parcours SUPPRIMER

Seuil de forçage = **heures encodées** (comme demandé). Trois cas, décidés par le serveur (`DELETE` sans `force` → 204 ou 409 avec `resume`) ; le front appelle d'abord `GET /professeurs/{id}/impact-suppression` pour choisir la modale.

**A. Aucune heure encodée** (prof créé par erreur, ou assignations/séances sans heure) → modale simple **« Supprimer Prénom Nom ? »** :
« Son compte de connexion et sa fiche seront supprimés. » + si présent : « Il sera retiré de N classe(s) et N séance(s) à venir (les séances restent planifiées). » + avertissements « sans professeur / dernier professeur » identiques à l'archivage. Boutons « Annuler » · **« Supprimer »** (rouge). Remplace le `window.confirm`.

**B. Heures encodées, aucune fiche générée** → pattern CLS-08 :
- Étape 1 **« Ce professeur a un historique »** : compteurs
  - Heures : « 12 brouillon · 3 soumises · 40 confirmées · 1 contestée » (+ total en h).
  - « N tarif(s) », « N séance(s) passée(s) où il apparaît », « N classe(s) ».
  - Texte : « Pour un départ, archivez-le : tout est conservé et il ne peut plus se connecter. »
  - CTA principal focalisé **« Archiver le professeur »** (ouvre le parcours §4) · « Annuler » · lien discret **« Supprimer quand même… »**.
- Étape 2 **« Supprimer définitivement ? »** : bandeau rouge « Action irréversible : ses N heures encodées, ses tarifs, son compte et l'historique de ses séances passées seront effacés. Les séances restent planifiées, sans lui. » ; champ **Motif** (≥ 10 caractères) ; champ **« Recopiez “Prénom Nom” pour confirmer »** (casse/espaces/accents ignorés) ; case **« Je comprends que cette action est irréversible »**. Bouton rouge **« Supprimer définitivement »** actif seulement quand les trois sont valides (revalidé serveur, 422 sinon).

**C. Au moins une heure au statut `genere` (fiche de défraiement PDF générée, signée ou non)** → **suppression bloquée**, étape 1 avec bandeau rouge : « Impossible de supprimer : N fiche(s) de défraiement ont déjà été générées (ex. septembre 2026). Ces documents de paie doivent être conservés. Archivez ce professeur. » Pas de lien « Supprimer quand même… ». Recommandation : bloquer (cohérent avec CLS-08 Q1 ; une fiche générée = pièce comptable, souvent signée SIG-01, à conserver plusieurs années). Voir Q2.

Succès (A/B) : retour à `/admin/professeurs`, toast **« Prénom Nom supprimé. »** (+ « N séance(s) à venir sont désormais sans professeur. » si > 0). Traçabilité : `Log::info` (auteur, prof, compteurs, motif) — les audits du prof partant en cascade, le log applicatif est la seule trace (voir Q4).

Garde-fous serveur : refuser (422) si le user lié n'a pas le rôle `professeur` (compte double casquette staff : la cascade supprimerait un compte de direction) ou est l'utilisateur connecté.

## 6. Nettoyage des séances futures — règle unique

**Périmètre** : séances `date ≥ aujourd'hui` (Europe/Brussels), statut `planifiee` (exclure `en_cours`, `terminee`, `annulee` : ce qui a commencé appartient à l'historique). Même règle pour archiver et supprimer, une seule méthode serveur `libererSeancesFutures()`, dans la transaction.

| Sa ligne sur la séance | Comportement recommandé | Pourquoi |
|---|---|---|
| `classe` | Ligne supprimée ; assignation terminée à aujourd'hui. | Comme `terminer()` aujourd'hui. |
| `ajout` | Ligne supprimée. | Ajout ponctuel à son nom : plus valable. |
| `remplacement` (il remplace A) | Sa ligne B supprimée ; **A reste marqué remplacé, sans remplaçant** (`remplace_par_professeur_id = NULL`) → la séance affiche « A absent — remplaçant à trouver ». | A avait signalé une absence : le restaurer créerait une fausse présence. |
| Il est remplacé par C (`remplace=true`) | Sa ligne supprimée ; la ligne de C est convertie en `ajout` (C reste assigné, retirable normalement). | C assure déjà le cours ; aucune perte. |
| Heure encodée sur la séance future | **Archiver** : ligne conservée (cohérent avec `terminer`, l'heure prouve la prestation). **Supprimer** : tout part (l'heure est supprimée avec lui). | Simple et sans perte de paie à l'archivage. |

La séance reste toujours planifiée, quel que soit le nombre de professeurs restants (0 inclus). Le calendrier doit savoir afficher « remplaçant à trouver » pour `remplace=true` + `remplace_par=NULL` (à vérifier/ajouter côté UI).

**Séances passées** : archiver = intact. Supprimer = ses lignes passées disparaissent (cascade) ; la séance passée peut apparaître sans professeur. Annoncé en étape 2 (« l'historique de ses séances passées »).

## 7. États et textes exacts

| État | Archiver | Supprimer |
|---|---|---|
| Chargement | « Calcul de l'impact… » (compteurs en squelette, bouton désactivé) | « Vérification des données liées… » |
| Vide (rien à nettoyer) | « Aucune classe ni séance à venir à son nom. » | Cas A sans impact : « Aucune donnée liée. » |
| Erreur de chargement | « Impossible de calculer l'impact. » + « Réessayer » ; bouton principal désactivé | idem « Impossible de vérifier les données liées. » |
| Erreur d'action | « L'archivage a échoué, rien n'a été modifié. Réessayez. » | « La suppression a échoué, rien n'a été supprimé. Réessayez. » |
| Succès | Toast « Prénom Nom archivé — N séance(s) à venir libérée(s). » | Toast « Prénom Nom supprimé. » |

Titres et boutons : « Archiver Prénom Nom ? » / « Archiver le professeur » · « Supprimer Prénom Nom ? » / « Supprimer » · « Ce professeur a un historique » / « Archiver le professeur » + « Supprimer quand même… » · « Supprimer définitivement ? » / « Supprimer définitivement » · « Suppression impossible » (cas C) / « Archiver le professeur ». Erreurs de champ : « Le motif doit contenir au moins 10 caractères. » · « Le nom ne correspond pas. » Toujours « Annuler » en focus initial des modales destructrices, Échap ferme, focus rendu au déclencheur.

## 8. Questions ouvertes (avec recommandation)

1. **Fusionner Désactiver et Archiver en un seul concept « Archiver » ?** → Oui.
2. **Fiche de défraiement générée (statut `genere`) : bloquer la suppression ?** → Oui, bloquer ; seul l'archivage est possible (documents de paie à conserver). Alternative si refus : autoriser à l'admin seul, avec un 2e avertissement.
3. **Nettoyage obligatoire à l'archivage (suppression de la case à cocher) ?** → Oui, obligatoire, conforme à la demande.
4. **Traçabilité d'une suppression forcée** : les audits partent en cascade. Garder une trace durable (table `suppressions_professeurs` : nom, auteur, motif, compteurs) ou le log applicatif suffit ? → Log applicatif en v1 ; table si exigence comptable.
5. **Il remplaçait un collègue** : laisser le collègue « absent, remplaçant à trouver » (reco) ou restaurer le collègue ?
6. **Supprimer sans heure mais avec assignations/séances** : confirmation simple (reco) ou forçage complet ?
7. **Notifier quelqu'un** (autres profs de la classe, le prof archivé) ? → Non en v1 (cohérent CLS-08 Q3).
8. **Qui peut supprimer en forçant** : admin + directeur (reco, comme CLS-08) ou admin seul ?

## 9. Écrans à maquetter

1. Fiche professeur actif : bloc Compte avec « Archiver… » + nouvelle « Zone sensible » en pied (sans gros bouton rouge).
2. Modale « Archiver Prénom Nom ? » — cas riche (classes, séances sans professeur, dernier professeur, remplacements, heures en attente) + état chargement.
3. Modale archiver — cas vide (aucune séance à venir).
4. Fiche professeur archivé (badge « Archivé », « Réactiver », Zone sensible toujours présente) + toast succès avec « Voir les séances ».
5. Modale « Supprimer Prénom Nom ? » (cas A, avec et sans impact).
6. Modale « Ce professeur a un historique » (cas B, étape 1).
7. Modale « Supprimer définitivement ? » (étape 2 : motif, recopie du nom, case, bouton désactivé/activé, erreurs 422).
8. Modale « Suppression impossible » (cas C, fiche générée).
9. Liste des professeurs : filtre « Actifs / Archivés / Tous », badge « Archivé ».
10. Détail d'une séance future après nettoyage : « Aucun professeur » et « A absent — remplaçant à trouver ».
