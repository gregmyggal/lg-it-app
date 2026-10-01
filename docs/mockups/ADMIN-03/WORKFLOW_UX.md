# ADMIN-03 — Workflow UX : invitation par email et réinitialisation pour les professeurs

> Statut : **proposition à valider** (aucun développement avant validation des maquettes).
> Maquettes : `index.html`, `01` à `04`. Références : `docs/mockups/ADMIN-02/WORKFLOW_UX.md` (décisions D1-D6 **reconduites sans changement**), `docs/mockups/PROF-01/WORKFLOW_UX.md`, `docs/requirements/PROF-01-gestion-comptes-professeurs.md`.
> Demande : aligner les professeurs sur le mécanisme livré pour le staff (ADMIN-02) : plus de mot de passe provisoire en clair, un email avec lien à usage unique (tranche annoncée en ADMIN-02 §8).

## 1. Constats vérifiés (code actuel)

- **Création** (`CreerProfesseurModal`, `ProfesseurCompteService::creer`) : mot de passe aléatoire de 12 caractères, `must_change_password=true`, envoi de `InvitationProfesseurMail` **vers l'email de contact du profil** (`$professeur->email`) avec identifiant + mot de passe **en clair**, puis `MotDePasseProvisoireModal` réaffiche ce mot de passe (« J'ai noté le mot de passe »).
- **Réinitialisation** (`CompteProfesseurSection`) : `window.confirm` « Générer un nouveau mot de passe provisoire et déconnecter ce professeur ? » ; écrase le mot de passe, révoque les tokens, réaffiche le secret. Même défaut que P2 d'ADMIN-02 : le professeur est verrouillé dès le clic.
- **Réactivation** : idem (nouveau mot de passe provisoire en clair).
- **Deux emails** : `Professeur.email` (contact, profil) et `User.email` (identifiant de connexion, `unique:users`). Le formulaire pré-remplit la connexion avec le contact tant qu'ils sont égaux.
- **Déjà là et réutilisable** : `AccesCompteService` (envoi, repli lien manuel, throttling 1/min, statut d'accès, `resumeAcces`, `estActif()` qui gère déjà `isProfesseur()` via `professeur.statut`), `AccesController` (oubli, vérifier, définir : tous rôles), emails `InvitationCompteMail` / `ReinitialisationMotDePasseMail` / `MotDePasseModifieMail` (le libellé de rôle « professeur » est déjà prévu), colonnes `invitation_envoyee_le`, `mot_de_passe_defini_le` (rattrapage fait pour les comptes avec `must_change_password=false`), composants `LienCopiable`, pastille `STATUTS_ACCES`.
- **Pièges repérés** : (a) `statutAcces()` teste `$user->statut === 'inactif'` alors que le statut d'un professeur vit sur `Professeur.statut` : à unifier via `estActif()`. (b) `ProfesseurController` charge `user:id,email,must_change_password` : il faut y ajouter `acces` (résumé calculé backend, sans N+1 sur les tokens). (c) Désactivation et changement d'email de connexion doivent annuler les liens en cours (`annulerLiens`). (d) La liste professeurs est une **liste de cartes**, pas un tableau : « colonne Accès » devient « pastille dans la carte ».
- **Pages publiques** : « Définir mon mot de passe », « Lien expiré », « Mot de passe oublié » fonctionnent déjà pour tous les rôles ; un professeur peut les utiliser **aujourd'hui**. Rien à changer.

## 2. Parcours actuels vs cibles

| Besoin | Aujourd'hui (PROF-01) | Cible ADMIN-03 (= staff) |
|---|---|---|
| Créer un professeur | Mot de passe provisoire envoyé en clair + réaffiché | **Email d'invitation** (lien 72 h) ; aucun mot de passe affiché ni envoyé |
| Message de fin de création | `MotDePasseProvisoireModal` | « Invitation envoyée à … » ; échec : compte créé + « Réessayer » + **lien copiable** (une fois) |
| Relancer | Impossible sans reset | Fiche ou liste : **Renvoyer l'invitation** |
| Réinitialiser | `window.confirm`, écrasement + secret affiché | Confirmation en ligne -> **Envoyer un lien de réinitialisation** ; l'ancien mot de passe reste valable jusqu'à usage du lien |
| Réactiver | Nouveau mot de passe affiché | Réactivation + lien envoyé ; ancien mot de passe invalidé |
| Suivi | Rien | Pastille **Accès** (liste + fiche) ; filtre « À relancer » ; relance rapide |
| Oubli par le professeur | Demander à un directeur | « Mot de passe oublié ? » (déjà livré) |

## 3. Parcours détaillés

### 3.1 Création (maquette 01)
1. Directeur/admin : `/admin/professeurs` -> « ➕ Nouveau professeur » -> modale **inchangée** (mêmes 7 champs).
2. Seuls changements : bouton « **Créer et envoyer l'invitation** » (au lieu de « Créer et inviter ») ; aide du champ *Email de connexion* : « C'est l'identifiant du compte et l'adresse qui reçoit l'invitation… (lien valable 72 h) ».
3. Le serveur crée User + Professeur dans la même transaction (mot de passe aléatoire inutilisable, `must_change_password=false`), puis `AccesCompteService::envoyer` (synchrone). Rien n'est annulé si l'envoi échoue.
4. **Succès** : modale « Professeur créé » : « Invitation envoyée à … ». Boutons « Fermer » (défaut) et « Voir la fiche » (pour assigner des classes : prochaine étape naturelle). La carte apparaît avec « Invitation en attente ».
5. **Échec** : « Professeur créé, email non envoyé » : « Réessayer l'envoi » ou lien copiable (`LienCopiable`, affiché une fois, audité). Statut « Invitation non envoyée » jusqu'à un envoi réussi.
6. Email de connexion déjà pris : 422 sur le champ, comme aujourd'hui.

### 3.2 Fiche : section « Compte de connexion » (maquette 02)
- Ligne **Accès** ajoutée sous l'identifiant : `Mot de passe défini le …` · `Invitation envoyée le … (expire le …)` · `Invitation expirée` · `Invitation non envoyée` · `Mot de passe provisoire` (existant).
- Boutons (compte actif) : « **Renvoyer l'invitation** » (mot de passe jamais défini) **ou** « **Envoyer un lien de réinitialisation** » (défini, ou provisoire existant) : jamais les deux ; + « Modifier l'email de connexion », « Désactiver » (inchangés).
- Clic -> **confirmation en ligne** (remplace `window.confirm`) : « Envoyer un email à … ? Les liens précédents seront annulés. » [Envoyer] [Annuler]. Retour `role=status` « Email envoyé à … à HH:MM » ; échec `role=alert` + lien copiable. Lien secondaire « Générer un lien à transmettre ». Limitation 1 envoi/min/compte.
- **Changement d'email de connexion** : les liens en cours sont annulés (le lien embarque l'adresse) ; le bandeau de succès propose directement « Envoyer l'invitation à la nouvelle adresse ».
- **Désactivé** : aucun bouton d'envoi, texte « Réactivez le compte pour envoyer une invitation » ; liens annulés à la désactivation.
- **Réactivation** : confirmation « Un email de définition de mot de passe sera envoyé à … (l'ancien mot de passe ne sera plus valable). » ; échec -> repli lien copiable. Le bandeau de succès ne contient plus de mot de passe.

### 3.3 Liste (maquette 03)
- Carte professeur : **pastille Accès** (icône + texte) dans le bloc de droite de l'en-tête, avec la date en petit ; action rapide **Renvoyer** sur « expirée » / « non envoyée » (à côté de « Voir détails »). Compte désactivé : « — ».
- **Filtre « Accès »** (Tous, À relancer, Invitation en attente, Mot de passe provisoire, Mot de passe défini) ajouté aux boutons de statut existants (Actifs / Désactivés / Tous) ; bandeau « N invitations à relancer ». La liste étant paginée côté API, le filtre et le compteur doivent être **calculés côté backend** (paramètre `acces=`), pas sur la page courante.

### 3.4 Email (maquette 04)
Réutilisation des 3 modèles ADMIN-02 (objet, bouton, lien en clair, texte + HTML). Adaptations minimales : libellé « professeur » + une demi-phrase sur l'usage de la plateforme, « Ensuite, connectez-vous avec cette adresse email », « Une question ? Contactez la direction de votre centre » ; réinitialisation : mention « La direction a demandé… » seulement si envoi par un admin/directeur.

## 4. Cohabitation avec les professeurs existants

- Statut **« Mot de passe provisoire »** (pastille grise, déjà prévu dans ADMIN-02 §9) pour tout professeur avec `must_change_password=true` et sans `mot_de_passe_defini_le` ; son mot de passe provisoire **reste valable** (aucune coupure) et le changement forcé à la connexion (`/mot-de-passe`) reste actif tant qu'il n'a pas agi.
- Action « Envoyer un lien de réinitialisation » identique : à l'usage du lien, `must_change_password=false` et `mot_de_passe_defini_le` renseigné -> « Mot de passe défini ».
- **Pas de migration automatique** (envoi massif) : des emails non sollicités à des professeurs en activité sont perçus comme du phishing et surprennent ; la direction relance au cas par cas, avec un filtre dédié et un compteur. Aucune migration de schéma n'est nécessaire : le rattrapage `mot_de_passe_defini_le` d'ADMIN-02 couvre déjà les professeurs ayant changé leur mot de passe.
- Option (non recommandée de base) : commande artisan manuelle `acces:inviter-provisoires` si la direction veut tout envoyer d'un coup.

## 5. Quelle adresse reçoit le lien ? (décision D3)

**Recommandation : l'email de connexion (`User.email`).** Justification : (1) c'est l'adresse que le serveur utilise pour « Mot de passe oublié », le lien (`#email=`) et l'anti-fraude ; (2) un lien envoyé à une autre boîte que l'identifiant permettrait à qui contrôle l'email de contact de prendre le compte d'un identifiant qu'il ne contrôle pas ; (3) un seul canal = comportement identique au staff et prévisible pour le support ; (4) à la création, les deux champs sont égaux par défaut (pré-remplissage existant). Contrepartie : si l'identifiant n'est pas une vraie boîte, l'invitation n'arrive pas -> repli lien copiable + message d'aide du champ. **Alternative** : email de contact (continuité avec l'existant), au prix d'un cas particulier dans le service partagé et d'un « Mot de passe oublié » qui enverrait ailleurs.

## 6. Permissions

| Action | admin | directeur | professeur |
|---|---|---|---|
| Inviter / renvoyer / lien de réinitialisation / lien manuel (professeurs) | ✔ | ✔ | — |
| Envoyer-lien staff (ADMIN-02) | ✔ | — | — |
Le contrôle reste côté backend (routes professeurs déjà `admin|directeur`) ; un directeur ne peut pas viser un compte staff.

## 7. Suppression de l'ancien endpoint

`POST /professeurs/{id}/reinitialiser-mot-de-passe` (mot de passe affiché) est **supprimé** (le front est son seul client) et remplacé par `POST /professeurs/{id}/envoyer-lien` et `POST /professeurs/{id}/generer-lien` (même contrat que le staff). `creer` et `reactiver` ne renvoient plus `mot_de_passe` : réponse `{ data, mail_envoye, lien? }`. Disparaissent : `MotDePasseProvisoireModal`, `InvitationProfesseurMail` + sa vue, `genererMotDePasse` côté professeur. Les tests PROF-01 sont réécrits en conséquence.

## 8. États, erreurs, accessibilité

Identiques à ADMIN-02 §6-§7 : bouton « Envoi… » pendant l'appel, 422 de champ, `role=alert` pour échecs, `role=status` pour succès, 429 « Un email vient d'être envoyé. Réessayez dans 1 minute », statut = icône + texte (jamais la couleur seule), `<label>` + `aria-describedby`, lien copiable en lecture seule avec retour « Lien copié », focus rendu au déclencheur à la fermeture des modales, confirmation en ligne annoncée (`role=group` + libellé). Le `window.confirm` natif disparaît (non stylable, non annoncé de façon homogène).

## 9. Règles métier et hors périmètre

- Durées (72 h envoi par un admin/directeur, 60 min self-service), token à usage unique, un seul lien actif, sessions révoquées à la définition, pas de connexion automatique, un seul message pour lien invalide, notification après changement : **inchangés**.
- Un professeur désactivé ne reçoit rien ; la réactivation émet un lien et invalide l'ancien mot de passe.
- Audit : envoi, échec, lien manuel (qui, quand), comme ADMIN-02.
- **Hors périmètre** : fusion des deux emails en un seul champ, import CSV, relance automatique, suivi de livraison, envoi automatique aux comptes provisoires, changement des pages publiques.

## 10. Décisions à valider (par priorité)

| # | Question | Recommandation |
|---|---|---|
| **D1** | Supprimer complètement le mot de passe provisoire pour les professeurs (création, réactivation, réinitialisation) et l'endpoint associé ? | **Oui**, mêmes règles que le staff (D1-D6 ADMIN-02 reconduites). |
| **D2** | Comptes existants à mot de passe provisoire : migration/envoi automatique ? | **Non.** Statut « Mot de passe provisoire » + action manuelle + filtre dédié ; l'ancien mot de passe reste valable jusqu'à l'usage du lien. |
| **D3** | Adresse du lien : contact ou connexion ? | **Email de connexion** (§5). |
| **D4** | Qui envoie ? | **Admin et directeur** pour les professeurs ; staff réservé à l'admin. |
| **D5** | Textes email : adaptation légère (rôle, plateforme, contact direction) ? | **Oui**, mêmes 3 modèles, pas de nouveau gabarit. |
| **D6** | Remplacer `window.confirm` par une confirmation en ligne ? | **Oui** (cohérence staff + accessibilité). |
