# ADMIN-05 — Workflow UX : créer un compte professeur ou staff sans envoyer l'invitation

> Statut : **proposition à valider** (aucun développement avant validation des maquettes).
> Maquettes : `index.html`, `01` à `04` (bascule clair / nuit). Références : `docs/mockups/ADMIN-03/WORKFLOW_UX.md`, `docs/requirements/ADMIN-02-*.md`, `ADMIN-03-*.md`.
> Besoin : direction/admin configurent classes, tarifs, etc. **avant** que la personne ait accès à l'application.

## 1. Décisions client (non négociables)

1. Création (professeur **et** staff) : case « Envoyer l'invitation maintenant », **cochée par défaut** (comportement actuel). Décochée : compte créé, aucun email, statut d'accès `invitation_non_envoyee`.
2. Tant que `users.mot_de_passe_defini_le` est null : **aucun email de notification** (ex. `NotificationTimesheet`, canaux database+mail → seule la cloche in-app). Emails d'accès (invitation, réinitialisation, « mot de passe modifié ») autorisés.
3. Même comportement pour le staff.
4. (Confirmé ensuite) Les comptes **à mot de passe provisoire hérité** (`must_change_password=true`, `mot_de_passe_defini_le` null) sont traités comme « sans mot de passe défini » : emails de notification bloqués pour eux aussi.
5. (Confirmé ensuite) **L'envoi en lot est inclus** dans la tranche.

## 2. Constats (code actuel)

- `invitation_non_envoyee` existe déjà mais signifie **« échec d'envoi »** : pastille rouge « ✕ Invitation non envoyée », comprise dans `A_RELANCER_RAPIDE` / `acces.a_relancer`, bouton « Renvoyer » sur la carte. Le réutiliser tel quel pour le choix volontaire créerait un faux signal d'alarme et noierait le filtre « À relancer ».
- `CreerProfesseurModal` et `StaffCreateModal` ont déjà un écran de résultat (créé / échec + lien copiable, « Voir la fiche », « Créer un autre compte » côté staff) : on s'y greffe.
- `CompteProfesseurSection` : `libelleEnvoiLien` donne déjà « Renvoyer l'invitation » tant que le statut commence par `invitation_` ; confirmation en ligne, repli lien copiable déjà en place.
- Les listes (cartes professeurs, tableau staff) calculent `a_relancer` côté backend et filtrent côté front via `correspondAcces`.

## 3. Vocabulaire et statuts (D1)

Le statut d'API reste `invitation_non_envoyee` (décision client). On ajoute au résumé `acces` un champ **`motif`** (`volontaire` | `echec`) : `echec` si une tentative d'envoi a échoué (déjà tracée par l'audit), sinon `volontaire`.

| Situation | Pastille (icône + texte) | Ton | `a_relancer` |
|---|---|---|---|
| Créé sans envoi (volontaire) | **⏸ Accès non envoyé** | neutre, bordure pointillée | **non** |
| Envoi tenté et échoué | **✕ Invitation en échec** | erreur (rouge) | oui |
| Envoyée, non utilisée | ✉ Invitation en attente | info | non |
| Expirée | ⚠ Invitation expirée | avertissement | oui |
| Provisoire hérité | ● Mot de passe provisoire | neutre | non |
| Défini | ✓ Mot de passe défini | succès | non |

Passage « Accès non envoyé » → « en attente » à l'envoi réussi ; → « en échec » si l'envoi échoue ; un échec suivi d'un renvoi réussi → « en attente ».

## 4. Parcours

### 4.1 Création professeur / staff (maquettes 01, 02)
1. Formulaire existant + **une case** en bas, avant les boutons. Libellé « Envoyer l'invitation maintenant ». Aide dynamique : cochée = « Un email … sera envoyé à X » ; décochée = « **Aucun email ne sera envoyé.** Le compte est créé « Accès non envoyé »… ».
2. Bouton principal dynamique : « Créer et envoyer l'invitation » ⇄ « **Créer le compte** ».
3. Case toujours recochée à l'ouverture (jamais mémorisée) ; conservée après une erreur 422.
4. **Résultat sans envoi** (professeur) : bandeau neutre « X est créé. Aucun email n'a été envoyé », étapes suivantes, boutons : **« Configurer ses classes → »** (principal, ouvre la fiche), « Envoyer l'invitation maintenant », « Fermer ». Staff : principal « Envoyer l'invitation maintenant », « Voir la fiche », « Créer un autre compte ».
5. Résultats « envoyé » et « échec + lien copiable » : inchangés (pastille « Invitation en échec »).

### 4.2 Fiche (maquette 04)
- Pastille « Accès non envoyé », phrase « Compte créé le … par …, aucun email d'invitation envoyé », bandeau « Cette personne n'a pas encore accès à l'application ».
- Bouton principal **« Envoyer l'invitation »** (confirmation en ligne existante, texte adapté), « Générer un lien à transmettre » conservé.
- **Encart permanent** pour tout compte sans mot de passe défini (non envoyé, en attente, expiré, échec, provisoire hérité) : « Aucun email ne sera envoyé à cette personne avant la définition de son mot de passe » + précision « les notifications restent visibles dans l'application ».
- Checklist « Avant d'envoyer » (profil, tarifs, classes assignées) : **indicative, non bloquante** (D8).

### 4.3 Liste et lot (maquette 03)
- Pastilles ci-dessus ; bouton carte « **Envoyer l'invitation** » (statut volontaire) vs « Renvoyer » (échec / expirée).
- Deux bandeaux distincts : gris « N accès non envoyés — Les afficher / Envoyer les N invitations… » ; bleu « N invitations à relancer (échec ou expirée) ».
- Filtre Accès : + option **« Accès non envoyé »** ; « À relancer » = échec + expirée seulement.
- **Lot** : cases à cocher sur les cartes/lignes « Accès non envoyé » ; « Tout sélectionner » = tous les résultats filtrés ; barre d'action collante « N sélectionnés — Envoyer les N invitations… » ; **modale de confirmation** listant nom + email de connexion ; **écran de résultat** par ligne (envoyée / échec + motif), « Réessayer les échecs ». Plafond 25 par lot, envoi séquentiel ; 1 envoi/min/compte (429) rapporté comme échec par ligne.

## 5. Règle « aucun email de notification avant mot de passe » (D3)
- Critère unique, **backend** : `mot_de_passe_defini_le` null → canal `mail` retiré de `via()` de toutes les notifications ; `database` conservé. Centraliser (trait/méthode `User::peutRecevoirEmailNotification()`), pas dans chaque notification.
- Inclus : provisoire hérité (décision client), invitation en attente / expirée / échec.
- Non rejoués : les emails « manqués » ne sont pas renvoyés à la définition du mot de passe (la cloche contient l'historique). Les emails d'accès et « mot de passe modifié » restent autorisés.
- Un test par notification existante (mail absent, database présent).

## 6. États et accessibilité
Bouton « Envoi… » en cours ; `role=status` (succès, résultat de lot), `role=alert` (échec) ; statut = icône + texte, jamais la couleur seule ; case = `<input type=checkbox>` + `<label>` cliquable, aide liée par `aria-describedby` et mise à jour annoncée (`aria-live=polite`) ; focus rendu au déclencheur à la fermeture ; barre de lot `role=region` ; mode nuit via jetons existants.

## 7. Permissions
Professeur : admin et directeur. Staff : admin seul (inchangé). Lot professeurs : admin + directeur ; lot staff : admin. Un directeur ne voit pas les comptes staff dans un lot.

## 8. Cas limites

| Cas | Comportement proposé |
|---|---|
| Provisoire hérité (`must_change_password`) | Pastille « Mot de passe provisoire » conservée, **emails de notification bloqués** (décision client), connexion avec le mot de passe provisoire inchangée ; encart d'information en fiche ; le lien de réinitialisation débloque les emails. Pas de pastille « Accès non envoyé » (mot de passe déjà communiqué). |
| Comptes existants | Aucune migration ; les « en attente / expirée / échec / provisoire » sont tous sans `mot_de_passe_defini_le` : **leurs emails de notification s'arrêtent au déploiement**. Prévoir une note de version et un compteur dans la liste. |
| Réactivation | Case « Envoyer l'invitation maintenant » identique (D7) ; l'ancien mot de passe est invalidé et `mot_de_passe_defini_le` remis à null (donc emails bloqués jusqu'à la nouvelle définition) ; décochée → « Accès non envoyé ». |
| Changement d'email de connexion avant envoi | Aucun lien à annuler ; le bouton « Envoyer l'invitation » vise la nouvelle adresse. |
| Désactivation d'un compte non envoyé | Pastille « — » comme aujourd'hui ; réactivation selon D7. |
| Doublon d'email de connexion | 422 sur le champ (inchangé), case conservée. |
| Lot : compte désactivé entre-temps / déjà invité | Ignoré avec mention « déjà invité » dans le résultat, aucun double envoi. |
| Échec de lot partiel | Compte-rendu par ligne ; échecs → « Invitation en échec », éligibles à « Réessayer ». |
| Compte jamais invité depuis longtemps | Aucun rappel automatique (hors périmètre) ; l'ancienneté s'affiche (« créé le … »). |

## 9. Décisions à valider

| # | Question | Recommandation |
|---|---|---|
| **D1** | Distinguer volontaire / échec : libellés « Accès non envoyé » vs « Invitation en échec », champ `acces.motif`, statut API inchangé | **Oui** |
| **D2** | « À relancer » = échec + expirée uniquement ; nouveau filtre « Accès non envoyé » et bandeau séparé | **Oui** |
| **D3** | Blocage mail centralisé sur `mot_de_passe_defini_le` null, emails manqués non rejoués | **Oui** (acté par le client, rejeu non retenu) |
| **D4** | Envoi en lot : sélection + « tout sélectionner (filtré) », confirmation nominative, résultat par ligne, plafond 25 | **Oui** (acté : lot inclus) |
| **D5** | Provisoires hérités : emails bloqués, sans pastille « Accès non envoyé », avec note de version (impact au déploiement) | **Oui** (acté) |
| **D6** | Résultat sans envoi : action principale « Configurer ses classes » (prof) / « Envoyer l'invitation » (staff) | **Oui** |
| **D7** | Case aussi à la **réactivation** (sinon un email part forcément) | **Oui** |
| **D8** | Checklist « Avant d'envoyer » indicative en fiche professeur | Optionnelle, peut être reportée |
| **D9** | Réactivation remet `mot_de_passe_defini_le` à null (emails bloqués jusqu'à nouvelle définition) | **Oui** |
