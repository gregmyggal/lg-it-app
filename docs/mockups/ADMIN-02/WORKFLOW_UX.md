# ADMIN-02 — Workflow UX : invitation par email et réinitialisation du mot de passe

> Statut : **proposition à valider** (aucun développement avant validation des maquettes).
> Maquettes : `index.html`, `01` à `07` (ce dossier). Références : `docs/DEVELOPMENT_STANDARDS.md`, `docs/mockups/ADMIN-01/WORKFLOW_UX.md`, `docs/mockups/PROF-01/WORKFLOW_UX.md`.
> Demande : « envoyer par email une invitation à créer son mot de passe, ainsi que la réinitialisation ». Périmètre prioritaire : comptes staff (admin/directeur, `/admin/staff`). Alignement des professeurs : voir §8 et décision D2.

## 1. Constats vérifiés

- **Création staff (ADMIN-01, livré)** : `StaffCreateModal` -> le serveur génère 12 caractères, la modale les affiche une fois ; l'admin doit les transmettre « à la main » (SMS, oral, messagerie…). `must_change_password=true` -> page `/mot-de-passe` à la 1re connexion.
- **Réinitialisation et réactivation (`StaffController`)** : écrasent immédiatement le mot de passe, révoquent les tokens et affichent le nouveau mot de passe (dans la fiche, ou dans le bandeau de succès de `StaffAdminPage` pour la réactivation). Effet de bord : l'utilisateur est **déconnecté et verrouillé dès le clic**, même s'il n'a jamais reçu le nouveau mot de passe.
- **Fiche staff livrée** = **modale** `StaffDetailModal` (pas une page `/admin/staff/:id` comme dans les maquettes ADMIN-01). Les maquettes ADMIN-02 suivent l'UI réellement livrée.
- **Connexion** (`LoginPage`, routes `/` et `/connexion`) : aucun « Mot de passe oublié ». Un utilisateur qui a perdu son mot de passe dépend d'un admin (staff) ou d'un directeur (professeur).
- **PROF-01** : `InvitationProfesseurMail` envoie le mot de passe provisoire **en clair dans l'email** (texte), avec repli d'affichage à l'écran si l'envoi échoue (`ProfesseurCompteService::inviter`).
- **Infrastructure déjà présente** : table standard `password_reset_tokens` (migration `0001_01_01`), broker `users` (`config/auth.php`, expire 60 min, throttle 60 s). `MAIL_MAILER=log` en dev : les emails n'apparaissent que dans `laravel.log` (l'« envoi » ne peut donc pas échouer en dev ; le repli doit être testé par un mailer factice qui lève une exception).
- **Hors périmètre ADMIN-01** : l'envoi d'emails était explicitement exclu (`ADMIN-01…md` §2). ADMIN-02 lève cette exclusion.

## 2. Problèmes à résoudre

| # | Problème actuel | Conséquence |
|---|---|---|
| P1 | Mot de passe provisoire visible par l'admin et transmis par un canal non maîtrisé | Secret en clair qui circule ; l'admin connaît le mot de passe d'un autre. |
| P2 | Reset admin = écrasement immédiat + révocation | Utilisateur verrouillé tant que l'admin ne lui a pas communiqué le nouveau mot de passe. |
| P3 | Pas de self-service | Charge sur les admins pour chaque oubli ; délai pour l'utilisateur. |
| P4 | Aucun suivi « l'utilisateur a-t-il activé son compte ? » | L'admin ne sait pas qui relancer. |

## 3. Parcours actuels vs cibles

| Besoin | Aujourd'hui | Cible ADMIN-02 |
|---|---|---|
| Créer un compte staff | Mot de passe affiché dans la modale, à communiquer | **Email d'invitation** avec lien à usage unique ; aucun mot de passe affiché |
| Utilisateur définit son mot de passe | Se connecte avec le provisoire puis `/mot-de-passe` | Clique le lien -> page publique « Définir mon mot de passe » -> se connecte |
| Admin relance (mail perdu/expiré) | Impossible sans reset | Fiche -> **Renvoyer l'invitation** |
| Admin réinitialise | Écrase et affiche un mot de passe | Fiche -> **Envoyer un lien de réinitialisation** (l'ancien mot de passe reste valide jusqu'à usage du lien) |
| Mot de passe oublié | Demander à un admin | Connexion -> **Mot de passe oublié ?** -> email -> lien |
| L'email n'arrive pas / échec d'envoi | n.a. (pas d'email) | Message clair + **lien copiable affiché une fois** + bouton Réessayer |
| Suivre les activations | Rien | Colonne/pastille **Accès** (liste) et ligne détaillée (fiche) |
| Réactiver un compte | Nouveau mot de passe affiché | Réactivation + **lien envoyé par email** |

## 4. Parcours détaillés

### 4.1 Créer un compte staff (maquette 01)
1. Admin : `/admin/staff` -> « ＋ Créer » -> modale (rôle, nom, email), **inchangée** sauf la note : « Un email d'invitation sera envoyé à cette adresse. »
2. Bouton « Créer et envoyer l'invitation » (libellé explicite de l'effet). Pendant l'appel : « Envoi… », champs désactivés.
3. Le serveur crée le compte (`statut=actif`, mot de passe aléatoire **inutilisable**, `must_change_password=false`), génère le token (broker « invitations », 72 h) et envoie l'email **de façon synchrone** (volume faible ; nécessaire pour connaître l'échec).
4. **Succès** : « Invitation envoyée à marie@… — le lien est valable 72 h. » Boutons : « Fermer » (défaut) et « Créer un autre compte ». La liste est rechargée, la ligne apparaît avec « Invitation envoyée ».
5. **Échec d'envoi** (SMTP KO, adresse refusée) : le compte **est créé** (pas de rollback, sinon l'admin perd sa saisie) ; l'écran indique en orange : « Le compte a été créé mais l'email n'a pas pu être envoyé. » Deux options : **Réessayer l'envoi** ou **copier le lien** (affiché une fois, champ sélectionnable + bouton « Copier » annoncé en `role=status`) à transmettre soi-même. Le statut reste « Invitation non envoyée » tant qu'un envoi n'a pas réussi.
6. **Email déjà pris** : inchangé (422, message de champ).

### 4.2 Fiche staff : statut + actions (maquette 02)
- Le bloc Compte gagne une ligne **Accès** : `Mot de passe défini le 12/09/2026` · `Invitation envoyée le 28/09 (expire le 01/10)` · `Invitation expirée le 01/10` · `Invitation non envoyée`.
- Actions du bloc Compte (compte actif) : **Renvoyer l'invitation** (visible si le mot de passe n'a jamais été défini) ou **Envoyer un lien de réinitialisation** (si déjà défini), + « Modifier email » et « Désactiver » existants. Une seule des deux, jamais les deux : l'intitulé suit la situation.
- Clic -> confirmation en ligne (pas de modale) : « Envoyer un email à marie@… ? Les liens précédents seront annulés. » [Envoyer] [Annuler].
- Retour : bandeau `role=status` « Email envoyé à marie@… à 14:32 » ; en cas d'échec, `role=alert` + « Copier un lien à transmettre » (génère un nouveau lien, annule les précédents).
- Lien secondaire permanent « Générer un lien à transmettre » : pour le cas où l'email est envoyé mais n'arrive jamais (spam, adresse erronée) — un échec de livraison asynchrone n'est pas détectable côté serveur.
- Compte **désactivé** : aucune action d'envoi (boutons absents, texte « Réactivez le compte pour envoyer une invitation »).
- Limitation de débit : 1 envoi / minute / compte -> « Un email vient d'être envoyé. Réessayez dans 1 minute. »

### 4.3 Liste avec statut d'accès (maquette 03)
- Nouvelle colonne **Accès** entre Statut et Actions : pastille texte + icône (jamais la couleur seule) :
  « Mot de passe défini » (vert) · « Invitation en attente » (bleu, infobulle : date d'envoi) · « Invitation expirée » (orange) · « Invitation non envoyée » (rouge). Compte désactivé : « — ».
- Action rapide inline **Renvoyer** sur les lignes « expirée » / « non envoyée » (évite d'ouvrir la fiche : cas de relance en lot).
- Filtre « Accès » : Tous / À relancer (en attente depuis > 3 j, expirée, non envoyée). Compteur dans la barre : « 2 invitations à relancer ».

### 4.4 Connexion + « Mot de passe oublié ? » (maquette 04)
1. `LoginPage` : lien discret sous le champ mot de passe « Mot de passe oublié ? » -> `/mot-de-passe-oublie`.
2. Page : un champ email pré-rempli avec la saisie de la connexion. Bouton « Envoyer le lien ».
3. **Réponse toujours identique** (écran de confirmation), que l'email existe ou non, soit actif, désactivé, ou invalide côté serveur : « Si un compte correspond à cette adresse, un email vient d'être envoyé. Le lien est valable 60 minutes. Pensez à vérifier vos courriers indésirables. » + « Retour à la connexion ».
4. Aucun email envoyé pour un compte inexistant ou désactivé. Envoi **après** la réponse HTTP (file/`afterResponse`) pour que le temps de réponse ne révèle pas l'existence du compte.
5. Limitation de débit : 5 demandes / minute / IP (+ 1 / minute / compte via le broker) ; au-delà, 429 -> message générique « Trop de tentatives. Réessayez dans quelques minutes. » (n'indique rien sur le compte).
6. Valable pour **tous les rôles** (professeurs inclus) : voir D2.

### 4.5 Définir mon mot de passe (maquette 05)
1. L'utilisateur clique « Définir mon mot de passe » dans l'email -> `/definir-mot-de-passe` (page **publique**, layout carte étroite comme `LoginPage`).
2. Au chargement, appel **non consommant** `POST /mot-de-passe/verifier` : si le lien est valide, formulaire ; sinon écran 06. (Un clic automatique par un antivirus/prévisualiseur de liens ne consomme donc pas le lien : le token n'est consommé qu'à l'enregistrement.)
3. Formulaire : rappel de l'email du compte (lecture seule), « Nouveau mot de passe » (8 caractères minimum, aligné sur `ChangerMotDePassePage`), « Confirmer », bouton afficher/masquer, aide « Au moins 8 caractères » mise à jour en direct. `autocomplete="new-password"`, focus initial sur le 1er champ.
4. Erreurs de champ en 422 (trop court, confirmation différente) ; lien devenu invalide entre-temps -> écran 06 (pas de perte silencieuse).
5. **Succès** : le token est consommé, `mot_de_passe_defini_le` renseigné, **tous les tokens Sanctum du compte sont révoqués**, `must_change_password=false`. Redirection vers `/connexion` avec bandeau `role=status` : « Mot de passe enregistré. Connectez-vous avec votre nouveau mot de passe. » **Pas de connexion automatique** (un lien d'email ne doit pas suffire à ouvrir une session ; une étape de plus acceptable).
6. Un email de notification « Votre mot de passe a été modifié » est envoyé (détection d'abus ; contenu en 07).

### 4.6 Lien expiré / invalide (maquette 06)
- Cas confondus volontairement dans un seul message (expiré, déjà utilisé, remplacé par un lien plus récent, falsifié) : on ne révèle pas lequel, ni si le compte existe. Texte : « Ce lien n'est plus valable. Il a peut-être expiré, déjà été utilisé, ou remplacé par un lien plus récent. »
- Action principale : **« Recevoir un nouveau lien »** -> mène à `/mot-de-passe-oublie` avec l'email pré-rempli (jamais exposé dans le message). Secondaire : « Retour à la connexion ». Mention : « Si vous venez d'être invité(e), vous pouvez aussi demander une nouvelle invitation à votre administrateur. »
- Les invitations expirées sont donc récupérables en self-service (60 min) sans solliciter l'admin ; l'admin voit « Invitation expirée » et peut relancer.

### 4.7 Réactivation (compte désactivé -> actif)
1. Fiche d'un compte désactivé -> « Réactiver » (confirmation simple, comme aujourd'hui) : « Le compte sera réactivé et un email de définition de mot de passe sera envoyé à marie@… (l'ancien mot de passe ne sera plus valable). »
2. Compte réactivé, ancien mot de passe invalidé, lien d'invitation 72 h envoyé ; même gestion d'échec que 4.1 (lien copiable).
3. Le bandeau de succès de `StaffAdminPage` n'affiche plus de mot de passe : « X réactivé. Invitation envoyée à … ».

### 4.8 Email (maquette 07)
- **Invitation** : objet « Activez votre compte Logiscool Pays Vert » ; corps : nom, rôle, qui a créé le compte, bouton « Définir mon mot de passe », lien en clair en dessous (clients bloquant les boutons), validité 72 h, « Si vous n'attendiez pas cet email, ignorez-le : aucun compte n'est actif sans votre action. »
- **Réinitialisation** : objet « Réinitialisation de votre mot de passe Logiscool » ; validité 60 min (self-service) ; « Vous n'êtes pas à l'origine de cette demande ? Ignorez cet email : votre mot de passe actuel reste inchangé. »
- **Notification de changement** : objet « Votre mot de passe a été modifié » ; sans lien d'action.
- Texte et HTML (multipart), jamais de mot de passe dans l'email. Expéditeur nominal explicite (`MAIL_FROM_*` à configurer ; `hello@example.com` actuel inacceptable en production).

## 5. Décisions de conception justifiées (les 10 cas demandés)

| # | Cas | Décision | Pourquoi / alternatives écartées |
|---|---|---|---|
| 1 | Création | Invitation par email, plus de mot de passe affiché | Supprime P1. Écarté : conserver l'affichage « en plus » -> garde le secret en clair en circulation. |
| 2 | Actions admin | « Renvoyer l'invitation » / « Envoyer un lien de réinitialisation » remplacent « Réinitialiser mot de passe » | Supprime P2 : l'ancien mot de passe reste valable jusqu'à usage du lien (pas de verrouillage). Écarté : lien sans confirmation (clic accidentel = email envoyé). |
| 3 | Self-service | Page « Mot de passe oublié » + réponse neutre + throttling | Supprime P3 sans énumération de comptes. Écarté : message « email inconnu » (énumération) ; CAPTCHA (prohibé/complexe, throttling suffit à cette échelle). |
| 4 | Lien expiré/invalide | Message unique + rebond vers nouvelle demande | Évite de révéler l'état du compte. Écarté : distinguer « expiré » / « déjà utilisé » (utile mais fuit l'existence du compte). |
| 5 | Échec d'envoi | Compte créé + message clair + réessayer + **lien copiable une fois** | Conserve le repli qu'offre déjà PROF-01 sans exposer un mot de passe. Le lien est un secret de même sensibilité : affiché une fois, audité (« lien généré manuellement par X »), jamais stocké en clair (seul le hash est en base). Écarté : rollback de la création (perte de saisie) ; seulement « réessayer » (bloque l'admin si SMTP durablement KO). |
| 6 | Comptes désactivés | Aucune invitation/lien : actions absentes, self-service neutre sans envoi, tokens existants annulés à la désactivation | Un compte désactivé ne doit pas pouvoir se « réactiver » seul. À la réactivation, un nouveau lien est généré. |
| 7 | Statut visible | Colonne **Accès** (liste) + ligne détaillée (fiche) + relance rapide | Répond à P4 ; calculé par le backend (source de vérité). |
| 8 | Sessions | Révoquées à la définition du mot de passe (Sanctum `tokens()->delete()`) | Un mot de passe défini/réinitialisé met fin aux sessions précédentes (cohérent avec ADMIN-01 reset et changement d'email). Pas de connexion automatique. |
| 9 | `must_change_password` | **N'est plus posé** pour les nouveaux comptes (l'utilisateur choisit déjà son mot de passe). Conservé tel quel pour les comptes existants à mot de passe provisoire (jusqu'à leur premier changement) et pour PROF-01 tant qu'il n'est pas aligné. `/mot-de-passe` (changement connecté) inchangé. | Évite un double changement de mot de passe. Écarté : le supprimer maintenant (casse PROF-01 et l'existant). |
| 10 | Réactivation | Réactivation + **lien envoyé**, ancien mot de passe invalidé | Cohérent avec « plus de mot de passe en clair » et avec une sortie puis un retour (le mot de passe d'avant n'est plus de confiance). Écarté : conserver l'ancien mot de passe (plus simple, mais rouvre l'accès avec un secret possiblement ancien). |

**Choix techniques à noter (pour l'implémentation, pas pour la validation UX)** : deux « brokers » de tokens (`invitations` 72 h, `reinitialisation` 60 min) sur la même table ; un seul token actif par email (un nouvel envoi annule le précédent, ce qui est voulu) ; URL du lien construite depuis une variable `FRONTEND_URL` ; token + email transmis en **fragment** (`/definir-mot-de-passe#token=…&email=…`) plutôt qu'en paramètres de requête, pour qu'ils n'apparaissent ni dans les logs serveur ni dans l'en-tête `Referer` (alternative acceptable : query string + `Referrer-Policy: no-referrer` + effacement de l'URL au chargement) ; page publique sans ressource tierce.

## 6. États et erreurs (à implémenter pour chaque écran)

| Écran | Chargement | Erreur | Succès |
|---|---|---|---|
| Création | bouton « Envoi… », champs désactivés | 422 champ ; réseau `role=alert` ; **échec d'envoi** = écran dédié (4.1.5) | Confirmation « Invitation envoyée à … » |
| Fiche (envois) | bouton « Envoi… » | échec d'envoi + lien copiable ; 429 « réessayez dans 1 min » | `role=status` « Email envoyé à … à HH:MM » |
| Liste | Skeleton existant | `ErrorBlock` existant | Colonne Accès |
| Mot de passe oublié | bouton « Envoi… » | 429 générique ; réseau « Impossible d'envoyer la demande. Réessayez. » | Confirmation neutre |
| Définir mot de passe | « Vérification du lien… » (skeleton) | 422 champs ; lien invalide -> écran 06 ; réseau avec Réessayer | Redirection connexion + bandeau |

## 7. Accessibilité

- Tous les champs avec `<label>` visible, `aria-describedby` pour aide et erreur, `aria-invalid` ; erreurs de formulaire en `role=alert`, succès/envois en `role=status` (région live présente dans le DOM avant mise à jour).
- Statut d'accès : texte + icône, jamais la couleur seule ; contraste 4.5:1 minimum.
- Affichage/masquage du mot de passe : bouton `aria-pressed`, libellé « Afficher le mot de passe ».
- Lien copiable : champ en lecture seule sélectionnable, bouton « Copier » avec retour `role=status` « Lien copié ».
- Pages publiques : titre `<h1>` unique, focus sur le titre ou le 1er champ au chargement, navigation clavier complète, aucune dépendance à la souris.
- Modales : comportement ADMIN-01 (Échap, retour du focus au déclencheur, `aria-modal`).
- Email : version texte, contraste, bouton + lien texte, `lang="fr"`, largeur 600 px max.

## 8. Alignement des professeurs (PROF-01)

- Le « Mot de passe oublié » et la page « Définir mon mot de passe » sont **par nature communs à tous les rôles** (table `users`) : un professeur pourra donc s'en servir dès la livraison.
- Reste à aligner : l'invitation PROF-01 (`InvitationProfesseurMail` avec mot de passe en clair) et son repli d'affichage. **Recommandation** : extraire un service partagé `AccesCompteService` (envoi invitation/réinitialisation, token, repli, journal) utilisé par staff **et** professeurs, livré en deux tranches : ADMIN-02 (staff + self-service tous rôles), puis tranche immédiatement suivante pour PROF-01 (mêmes composants UI : pastille Accès, boutons de fiche). Sinon deux logiques d'onboarding cohabitent durablement.
- Directeurs : peuvent déjà gérer les professeurs ; ils garderont les actions d'envoi sur la fiche professeur (permission inchangée), pas sur le staff.

## 9. Règles métier proposées

- Un token = un usage ; un nouvel envoi annule le précédent ; suppression du token après usage.
- Durées : invitation / lien envoyé par un admin **72 h** ; auto-service **60 min**.
- Mot de passe : 8 caractères minimum (règle existante) ; jamais communiqué par email ni affiché.
- Statut d'accès calculé backend : `invitation_non_envoyee`, `invitation_en_attente`, `invitation_expiree`, `mot_de_passe_defini` (+ `mot_de_passe_provisoire` pour l'existant). Champs : `invitation_envoyee_le`, `mot_de_passe_defini_le` ; migration de rattrapage : `mot_de_passe_defini_le = created_at` pour les comptes déjà autonomes.
- Admin : peut envoyer à tout compte actif staff ; jamais à un compte désactivé. Directeur : uniquement professeurs (tranche suivante).
- Journal d'audit : envoi, échec, lien manuel généré, définition de mot de passe (qui, quand, sans le token). 6 mois comme ADMIN-01.
- Messages en français, sans jargon, pas de fuite d'information sur l'existence d'un compte.

## 10. Hors périmètre

- Changement d'email avec re-vérification par lien (ADMIN-01 inchangé).
- Authentification à deux facteurs, politique de complexité renforcée, historique des mots de passe.
- Invitation en masse (import CSV) ; relance automatique des invitations expirées.
- Suivi de livraison (bounces, ouverture des emails).
- Alignement PROF-01 (tranche suivante, voir §8).
- Personnalisation graphique avancée de l'email (logo/charte) : gabarit sobre en v1.

## 11. Décisions à valider (par priorité)

| # | Question | Recommandation |
|---|---|---|
| **D1** | Remplacer définitivement le mot de passe provisoire par un lien à usage unique pour le staff ? | **Oui.** Plus aucun mot de passe affiché ni envoyé. |
| **D2** | Périmètre : staff seul, ou aussi professeurs ? | **Staff + « Mot de passe oublié » pour tous les rôles dans ADMIN-02 ; PROF-01 aligné dans la tranche suivante** via un service partagé. |
| **D3** | En cas d'échec d'envoi, afficher un lien copiable à l'admin (secret affiché une fois, audité) ? | **Oui**, avec « Réessayer » ; sinon un SMTP en panne bloque l'onboarding. |
| **D4** | Durées : invitation / lien admin 72 h, self-service 60 min ? | **Oui** (72 h couvre un week-end ; 60 min = défaut Laravel pour l'oubli). |
| **D5** | Réactivation : ancien mot de passe invalidé + lien envoyé ? | **Oui.** |
| **D6** | Pas de connexion automatique après définition du mot de passe (redirection vers la connexion) ? | **Oui** (sécurité ; un clic de plus). |

Détails secondaires que je tranche sauf avis contraire : colonne « Accès » distincte du statut Actif/Désactivé ; email de notification après changement ; `must_change_password` conservé uniquement pour l'existant ; token en fragment d'URL.
