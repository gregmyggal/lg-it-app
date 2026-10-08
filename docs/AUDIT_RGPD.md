# Audit RGPD & protection des données — Logiscool Pays Vert

Date : 2026-10-08 · Périmètre : backend Laravel 11, frontend React, Docker, déploiement OVH · **Audit statique du code, aucune modification effectuée.**
Autorité de contrôle compétente : Autorité de protection des données (APD/GBA, Belgique). Rôles des acteurs : voir §0.

## 0. Rôles RGPD des acteurs (qualification proposée, à valider juridiquement)

Faits établis : l'ASBL Code IT Bryan **emploie** les animateurs et **gère leur paie** ; L-IT Solutions (pour Logiscool Pays Vert) **met à disposition l'outil** de timesheet, **fait administrer la paie pour le compte de l'ASBL par son admin et son directeur (IBAN compris)**, **définit les durées de conservation** et reçoit les demandes de droits ; Myggal **met en place la plateforme, est titulaire du contrat OVH** et reçoit aussi les demandes de droits ; un même animateur peut, **mois par mois**, être sous contrat L-IT Solutions ou ASBL.

| Acteur | Traitements concernés | Qualification RGPD proposée |
|---|---|---|
| **L-IT Solutions** | Outil, planning, durées de conservation ; employeur de ses animateurs les mois où ils sont sous son contrat ; **gestion administrative de la paie de l'ASBL** (IBAN, fiches) via son admin/directeur | **Responsable de traitement** de la plateforme et employeur pour ses mois de contrat ; **sous-traitant de l'ASBL** (art. 28) pour la gestion de paie réalisée pour son compte → contrat écrit avec instructions de l'ASBL |
| **ASBL Code IT Bryan** | Contrat, paie, défraiement, IBAN de ses animateurs ; destinataire des fiches | **Responsable de traitement** (employeur/paie). Pour les timesheets saisis dans l'outil de L-IT : **coresponsables** (art. 26) ou responsables distincts avec transmission — l'ASBL fixe la finalité (paie), L-IT les moyens ; accord écrit à prévoir dans les deux cas |
| **Myggal** | Mise en place, exploitation, hébergement OVH, support | **Sous-traitant** (art. 28) de L-IT Solutions (et, selon l'accord, de l'ASBL) ; ses demandes de droits reçues doivent être **relayées** au responsable (art. 28.3.e) ; responsable de traitement uniquement de ses données propres (facturation, support) |
| OVH, prestataire SMTP | Hébergement, emails | Sous-traitants ultérieurs de Myggal (autorisation écrite de L-IT requise, art. 28.2) |

Conséquences pour l'audit :
- **IBAN : l'accès admin/directeur L-IT est justifié**, à condition d'être cadré : ils agissent comme gestionnaires de paie pour l'ASBL (ou pour L-IT les mois où l'animateur est sous contrat L-IT). Cet accès doit être couvert par le contrat de sous-traitance L-IT ⇄ ASBL, limité aux seuls rôles `admin` et `directeur` (jamais l'animateur voyant celui d'un autre, ni Myggal hors support tracé), **journalisé**, et l'IBAN doit rester **masqué dans les listes** et chiffré en base. La recommandation d'un rôle « paie ASBL » distinct est donc **abandonnée**.
- **L'employeur varie par mois, pas par animateur.** Le champ « employeur » ne peut pas être porté par `professeurs` : il doit être rattaché à la **période de paie** (professeur + mois, ou chaque ligne/fiche de timesheet). Conséquences : l'entité émettrice et payeuse figurant sur la fiche PDF (aujourd'hui toujours l'ASBL dans `config/logiscool.php`), le responsable de traitement des heures du mois, les durées de conservation et le destinataire des demandes de droits dépendent du mois concerné. `type_contrat` (nature) reste un attribut distinct.
- **Données communes à deux employeurs successifs** : identité, téléphone, IBAN sont partagés entre L-IT et l'ASBL (même personne, deux responsables selon les mois). La communication de l'IBAN et de l'identité entre les deux entités doit être documentée (base légale : exécution du contrat, accord inter-entités) et mentionnée dans la notice.
- **Durées de conservation : L-IT décide, y compris pour les mois sous contrat ASBL, par délégation de l'ASBL.** Juridiquement, la délégation ne transfère pas la responsabilité : l'ASBL reste responsable de traitement de ses données de paie et L-IT agit sur **instructions documentées** (art. 28.3.a). Il faut donc (1) une **délégation écrite** (contrat L-IT ⇄ ASBL) qui fixe la politique de conservation ; (2) une politique **unique** appliquée par L-IT à tous les mois, quel que soit l'employeur, ce qui simplifie l'implémentation (pas de durées variables par employeur) ; (3) des durées **jamais inférieures aux obligations légales de l'ASBL** (pièces de paie/comptables) ; (4) à la fin du contrat ou sur demande de l'ASBL, **restitution ou effacement** des données qu'elle a confiées.
- **Accès administrateur de Myggal** : tous les professeurs, toutes les données ; contrat de sous-traitance avec instructions documentées, accès nominatif et journalisé, limité au support, et clause de confidentialité. Sa qualité de titulaire OVH lui donne aussi la maîtrise des sauvegardes : elles doivent être chiffrées avec une clé que le sous-traitant ne détient pas seul (ou au moins couvertes par le contrat).
- **Droits des personnes : deux guichets (L-IT et Myggal)** → risque de réponses incohérentes ou hors délai (1 mois). Désigner **un point de contact unique** (L-IT), obliger Myggal à relayer sous 48 h, tenir un registre commun des demandes. Les demandes sur la paie/contrat sont à transmettre à l'ASBL.
- **Notice d'information unique** à nommer : L-IT Solutions (outil), ASBL (paie, employeur), Myggal et OVH (destinataires/sous-traitants), contact unique, droit de plainte à l'APD.
- **Registres** : un par responsable (L-IT, ASBL) ; Myggal tient un registre de sous-traitant (art. 30.2).
- **Questions encore ouvertes** : Comment est enregistré, mois par mois, l'employeur d'un animateur (aujourd'hui rien dans le modèle) ? Hébergement OVH dans l'UE ?

## 1. Cartographie des données personnelles

| Donnée | Table / stockage | Sensibilité | Protection actuelle |
|---|---|---|---|
| Nom, email de connexion, mot de passe | `users` | Moyenne | Mot de passe haché (bcrypt, 12 tours). Nom et email en clair. |
| Prénom, nom, email, téléphone, dates d'entrée/sortie, type de contrat | `professeurs` | Moyenne | Clair |
| **IBAN** | `professeurs.compte_bancaire` | **Élevée** (financière) | **Clair**, validé par `Rules/Iban` |
| Photo | `professeurs.photo_path` | Moyenne | Chaîne libre non contrôlée |
| Heures, tarifs, montants, motifs d'audit | `timesheets`, `professeur_tarifs`, `timesheet_audits` (motif en texte libre) | Moyenne | Clair |
| **Fiches de défraiement PDF (IBAN, nom, montants)** | `storage/app/private/pdfs` + `timesheet_pdfs` | **Élevée** | Fichier non chiffré, disque privé |
| Spécimen de signature (image) + consentement | `signature_specimens`, `storage/app/private/signatures` | Élevée (quasi-biométrique) | Fichier non chiffré |
| **IP + user-agent de signature**, payload scellé | `timesheet_signatures` | Moyenne | Clair, conservé 7 ans puis anonymisé |
| IP + user-agent de session | `sessions` (driver `database`) | Faible | Clair, `SESSION_ENCRYPT=false` |
| Jetons d'invitation/réinitialisation | `acces_tokens` | Élevée (transitoire) | Stockés hachés (SHA-256) ✅ |
| Notifications | `notifications.data` (texte) | Faible à moyenne | Clair |
| Logs applicatifs | `storage/logs` | Variable | Voir §3.5 |

Les « élèves » ne sont pas stockés (le rôle `eleve` existe mais les codes de partage ne servent que du contenu pédagogique publié). Les `anniversaires` sont des offres commerciales, sans donnée de personne.

## 2. Points conformes (à conserver)

- Mots de passe hachés, jetons d'accès stockés hachés, lien à usage unique, throttle sur « mot de passe oublié ».
- Réponse de login neutre (ne révèle pas l'existence du compte) et verrou de compte désactivé (`EnsureCompteActif`, révocation des tokens à la désactivation/réinitialisation).
- Isolation par rôle via Policies ; vérification publique de signature à données minimales et identifiant aléatoire non énumérable (40 bits) ; IP masquée pour les non-admins.
- Consentement horodaté du spécimen de signature (`consenti_at`) et anonymisation planifiée à 7 ans (`signatures:anonymiser`).
- Masquage de l'IBAN dans l'écran de confirmation (`TimesheetConfirmationService`).
- Fichiers sur disque `local` (privé, hors `public/`), téléchargement via contrôleur autorisé ; `.env` ignoré par git ; installeur protégé par jeton.
- Suppression de fichiers associés lors de la suppression d'un professeur.

## 3. Constats et risques

Gravité : 🔴 critique · 🟠 élevée · 🟡 moyenne · 🔵 conformité/documentation.

### 3.1 Chiffrement des données au repos
- 🔴 **IBAN en clair** (`professeurs.compte_bancaire`). Aucun cast `encrypted`, aucun usage de `Crypt` dans le code. Une fuite de dump SQL, de sauvegarde OVH ou une injection SQL expose tous les IBAN.
- 🟠 **PDF de défraiement et images de signature non chiffrés** sur disque (le PDF contient l'IBAN complet : `TimesheetPdfService:193`).
- 🟠 Hébergement OVH mutualisé : le chiffrement disque/volume MySQL n'est pas maîtrisable → le chiffrement **applicatif** est le seul levier fiable.
- 🟡 `SignatureCle` dérive la clé de sceau d'`APP_KEY` si `signature.cle_privee` est absente : un seul secret protégerait à la fois le chiffrement et l'intégrité des preuves, et sa rotation invaliderait les sceaux.
- 🟡 Dump/sauvegarde : aucune procédure documentée (ni chiffrée, ni durée de rétention) dans `DEPLOIEMENT_OVH.md`.

### 3.2 Minimisation et exposition via l'API
- 🟠 `GET /professeurs` et `GET /professeurs/{id}` renvoient le modèle complet (IBAN, téléphone, etc.) à **tout le staff**, y compris pour une simple liste. `PUT /professeurs/{id}` renvoie aussi le modèle entier. L'IBAN ne devrait être lu qu'au moment de générer la fiche ou sur action explicite et journalisée.
- 🟡 `photo_path` accepte n'importe quelle chaîne : risque de référence à un chemin arbitraire et de donnée orpheline non purgée.
- 🟡 Doublons d'identité : nom/email dans `users` **et** `professeurs` → plus de copies à effacer et à rectifier.
- 🟡 `notifications.data` : vérifier qu'elle ne duplique pas de données personnelles non nécessaires.

### 3.3 Authentification et sessions
- 🟠 `POST /login` **sans throttle** (seuls les endpoints d'accès en ont) : force brute / bourrage d'identifiants possible.
- 🟠 Jetons Sanctum **sans expiration** (`expiration => null`) stockés dans `localStorage` (`AuthContext.jsx`) : tout XSS vole un accès permanent. Pas de purge planifiée.
- 🟡 Mot de passe : minimum 8 caractères, pas de contrôle de mots de passe compromis ; pas de MFA pour admin/directeur, qui voient tous les IBAN.
- 🟡 `changerMotDePasse` ne révoque pas les autres jetons de l'utilisateur.
- 🟡 Table `sessions` stocke IP et user-agent alors que l'API fonctionne par jetons : donnée inutile si le driver n'est pas utilisé.

### 3.4 Transport et configuration
- 🟠 `.env.example` : `APP_DEBUG=true`, `LOG_LEVEL=debug`, `MAIL_MAILER=log`. Si copié tel quel en production : traces d'erreur détaillées exposées, et **liens d'invitation/réinitialisation écrits dans les logs** (équivaut à des mots de passe temporaires). L'installeur force `APP_DEBUG=false`, à vérifier pour les deux autres.
- 🟡 Pas de en-têtes de sécurité vérifiés (HSTS, CSP, X-Frame-Options, `SESSION_SECURE_COOKIE`, `Referrer-Policy` hors installeur) ni de forçage HTTPS applicatif.
- 🟡 Pas de TLS imposé entre l'app et MySQL (acceptable en local Docker, à confirmer chez OVH).
- 🟡 `docker-compose.yml` publie MySQL (3308) et **phpMyAdmin (8091, `latest`, accès root)** sur l'hôte : à limiter à `127.0.0.1`, et à ne jamais déployer en production.

### 3.5 Journalisation
- 🟠 `Log::info('Professeur supprimé')` consigne **nom complet + motif libre** ; `LOG_STACK=single` → un fichier unique sans rotation ni durée de rétention : des données d'une personne « effacée » survivent indéfiniment dans les logs.
- 🟡 Aucune journalisation des **lectures** de données sensibles (consultation/téléchargement d'IBAN ou de PDF) : impossible de détecter un abus interne ou de documenter une violation (art. 33).

### 3.6 Conservation et effacement
- 🟠 **Aucune politique de conservation** pour les professeurs archivés (`statut=inactif`) : identité, téléphone et IBAN conservés indéfiniment.
- 🟠 **Conflit effacement / obligation légale** : `ProfesseurCompteService::supprimer` fait une suppression physique en cascade (heures, fiches, signatures, audits, PDF), y compris les pièces comptables que l'employeur/payeur (ASBL ou L-IT Solutions) doit conserver (7 ans, art. 3:15 CSA/ obligations fiscales belges — à valider avec le comptable). À l'inverse, l'archivage garde tout, sans limite.
- 🟡 Durée de 7 ans pour IP + user-agent des signatures : base légale et proportionnalité à justifier ; pour un défraiement associatif, 7 ans de conservation de l'IP se défend difficilement au-delà de la preuve de la fiche (envisager 12–24 mois pour l'IP/appareil, 7 ans pour le hash + sceau).
- 🟡 Jetons expirés (`acces_tokens`, `personal_access_tokens`, `password_reset_tokens`), notifications lues, sessions : aucune purge planifiée repérée (seules `liens:purge-historique` et `signatures:anonymiser` existent).
- 🔵 Sauvegardes : l'effacement ne s'y propage pas ; durée de vie des sauvegardes à définir et à documenter.

### 3.7 Droits des personnes (art. 12–22)
- 🟠 Aucun mécanisme d'**accès/portabilité** (export des données d'un professeur), de **rectification en self-service** (le professeur ne peut pas corriger lui-même téléphone/IBAN ?), ni de **limitation/opposition**.
- 🔵 Pas de procédure de réponse sous 1 mois, ni de journal des demandes.

### 3.8 Gouvernance (art. 5, 13, 24, 28, 30, 33, 35)
- 🔵 Pas de **registre des traitements** ni de **notice d'information** (finalités, base légale, durées, destinataires, droits, contact, droit de plainte à l'APD) affichée aux professeurs/staff. La mention RGPD n'existe que pour SIG-01.
- 🔵 Pas de **procédure de violation de données** (détection, notification APD sous 72 h, information des personnes).
- 🔵 **Sous-traitants** : Myggal (développeur/exploitant), OVH, prestataire SMTP : contrats de sous-traitance (art. 28), localisation UE, et accord écrit entre L-IT Solutions et l'ASBL (art. 26 si conjoints) à formaliser.
- 🟠 **Cloisonnement par entité absent** (voir §0) : pas de champ « employeur », donc pas de minimisation des accès entre L-IT Solutions et l'ASBL, ni de durées par responsable.
- 🔵 AIPD : probablement non obligatoire (petite structure, données non sensibles au sens de l'art. 9) mais une **analyse documentée** de ce constat est recommandée. Désignation d'un DPO non obligatoire a priori ; nommer au moins un référent.
- 🔵 Standards de dev (`DEVELOPMENT_STANDARDS.md`) ne comportent pas de volet protection des données (privacy by design, art. 25) dans la Definition of Done / Canvas.

## 4. Recommandations (priorisées)

### P0 — avant toute mise en production avec de vraies données
1. **Chiffrer l'IBAN au niveau applicatif** : cast Eloquent `encrypted` sur `compte_bancaire` (AES-256-GCM via `APP_KEY`), colonne passée de `string(34)` à `text` (le chiffré dépasse 34 caractères). Migration de données one-shot qui lit en clair et réécrit chiffré, avec sauvegarde préalable. Ajouter un `compte_bancaire_masque` (BE12 •••• 1234) pour l'affichage, calculé côté serveur, et **retirer l'IBAN de `$hidden`/des Resources** pour les listes. Comme la colonne est chiffrée, la recherche/filtre sur IBAN n'est plus possible (acceptable).
2. **Gestion de `APP_KEY`** : générer une `signature.cle_privee` dédiée (commande `signature:cle` existante) pour découpler sceau et chiffrement ; sauvegarder `APP_KEY` hors serveur (coffre) — perdre la clé = perdre les IBAN ; documenter la rotation via `APP_PREVIOUS_KEYS` (Laravel 11) puis ré-encryption.
3. **Throttle sur `POST /login`** (par IP **et** par email, ex. 5/min + verrouillage progressif) ; réponse identique en cas d'échec (déjà le cas).
4. **Durcir la configuration production** : `APP_DEBUG=false`, `LOG_LEVEL=warning`, `LOG_STACK=daily` avec `LOG_DAILY_DAYS=14`, `MAIL_MAILER=smtp` (jamais `log`), HTTPS forcé + HSTS, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`. Ajouter un contrôle de ces valeurs dans l'installeur/`deploy.sh`.
5. **Ne pas exposer l'IBAN dans les listes** : réponses API dédiées (Resource) — masqué par défaut ; IBAN complet seulement dans la fiche détail pour `admin`/`directeur` (gestionnaires de paie) et pour le professeur lui-même, avec journalisation des lectures.

### P1 — court terme (≈ 1 mois)
6. **Chiffrer les fichiers sensibles** : PDF de fiches et images de signature chiffrés à l'écriture (`Crypt::encryptString` / flux chiffré, ou disque dédié chiffré) et déchiffrés au téléchargement ; ou, a minima, ne jamais y mettre l'IBAN complet si le comptable peut s'en passer.
7. **Jetons Sanctum** : `expiration` de 8–12 h (ou 30 jours glissants avec renouvellement), planifier `sanctum:prune-expired --hours=24`, révoquer les autres jetons au changement de mot de passe. Évaluer le passage à un cookie `HttpOnly` + `SameSite` (SPA mode Sanctum) pour sortir le jeton de `localStorage`.
8. **MFA (TOTP)** obligatoire pour `admin` et `directeur`, contrôle de mot de passe compromis (règle `Password::min(12)->uncompromised()`).
9. **Journal d'accès** (qui a lu/exporté quel IBAN/PDF/profil, quand) dans une table d'audit en append-only, rétention 12 mois ; alerte sur exports en masse (le ZIP de fiches de `TimesheetPdfService` notamment).
10. **Logs sans PII** : remplacer nom/motif par `professeur_id` ; scrubber les emails dans les messages d'exception.
11. **Sauvegardes** : dumps MySQL chiffrés (GPG/age), stockés hors du serveur, rétention 30 jours, test de restauration trimestriel ; documenter l'effet sur le droit à l'effacement (purge naturelle au bout de 30 jours).
12. **Docker/dev** : lier MySQL/phpMyAdmin à `127.0.0.1`, épingler la version de phpMyAdmin, ne pas inclure phpMyAdmin dans tout artefact de déploiement.

### P2 — politique de conservation et droits (≈ 1–3 mois)
13. **Politique de durées** (décidée par L-IT par délégation de l'ASBL, une seule politique pour tous les mois ; plancher = obligations légales de l'ASBL, à valider avec le comptable) :
    - Professeur actif : durée du contrat.
    - Après archivage : IBAN et téléphone **supprimés/nullifiés** après 6–12 mois (hors PDF comptables déjà émis) ; identité conservée jusqu'à la fin de l'obligation comptable (7 ans) puis anonymisée.
    - Fiches PDF + hash/sceau : 7 ans.
    - IP/user-agent de signature : 12–24 mois (au lieu de 7 ans) — adapter `signatures:anonymiser`.
    - Jetons, notifications lues, sessions : purge planifiée (7–30 jours).
14. **Remplacer la suppression physique par une pseudonymisation** pour les professeurs avec fiches générées : remplacer nom/email/téléphone/IBAN/photo par des valeurs neutres tout en gardant les montants et hash pour la comptabilité ; la suppression physique reste possible quand aucune pièce comptable n'existe.
15. **Droits des personnes** : endpoint `GET /me/export` (JSON + PDF des fiches) ; formulaire de correction du téléphone/IBAN par le professeur lui-même ; procédure interne (1 mois, journal des demandes) pour effacement/limitation.
16. **Minimisation de la structure** : un seul propriétaire de l'identité (ex. `users.name/email` ; `professeurs` y renvoie), suppression de `photo_path` si inutilisé, ou validation stricte (chemin interne, extension, taille, stockage privé).

### P3 — gouvernance et documentation
17. Tenir le **registre des traitements** — un par responsable (L-IT Solutions, ASBL) ; Myggal tient un registre de sous-traitant (art. 30.2) (modèle : finalités = gestion des défraiements, planning, authentification ; bases légales = contrat/obligation légale/intérêt légitime ; destinataires ; durées ; mesures).
18. **Notice d'information** affichée à la première connexion et accessible dans l'app (finalités, durées, droits, contact, plainte à l'APD).
19. **Procédure de violation de données** : détecter → évaluer → notifier l'APD sous 72 h → informer les personnes ; registre des incidents ; coordonnées OVH.
20. **Contrats** : sous-traitance Myggal ↔ chaque responsable, sous-traitance OVH/SMTP, accord L-IT Solutions ↔ ASBL sur les données échangées ; hébergement UE. Encadrer l'accès `admin` de Myggal (accès nominatif, journalisé, limité en durée).
20bis. **Ajouter la notion d'employeur par mois** (ASBL / L-IT Solutions) sur la période de paie du professeur : détermine l'entité figurant sur la fiche PDF, le responsable de traitement des heures du mois et la durée de conservation applicable. L'accès à l'IBAN reste réservé à `admin`/`directeur` (rôles de gestion de paie), journalisé ; contrat de sous-traitance L-IT ⇄ ASBL à signer.
20ter. **Droits des personnes** : point de contact unique chez L-IT, relais obligatoire par Myggal sous 48 h, registre commun des demandes, transmission à l'ASBL pour la paie/le contrat.
21. Intégrer un volet **« Protection des données »** au Canvas `docs/REQUIREMENTS_CANVAS.md` et à la Definition of Done : nouvelles données ? finalité ? base légale ? durée ? chiffrement ? exposition API ? droits ?
22. **Tests** : tests automatisés vérifiant que (a) l'IBAN n'apparaît dans aucune réponse de liste, (b) la colonne est bien chiffrée en base, (c) un professeur ne lit que ses propres données, (d) les purges planifiées effacent bien ce qu'elles doivent.

## 5. Plan de migration du chiffrement de l'IBAN (esquisse, non implémentée)

1. Sauvegarde chiffrée de la base ; gel des écritures de profils.
2. Migration 1 : `compte_bancaire` → `text`.
3. Commande Artisan idempotente : pour chaque professeur, relire la valeur brute (`DB::table`), détecter si elle est déjà chiffrée, sinon `Crypt::encryptString`.
4. Activer le cast `encrypted` + Resource avec IBAN masqué ; adapter `TimesheetPdfService`, `FicheDefraiementLignes`, `TimesheetSyntheseMoisService` (qui lit `blank(compte_bancaire)` — OK avec le cast).
5. Tests sur `lgit_test`, vérification qu'un `SELECT` direct montre un chiffré illisible.
6. Purge des dumps antérieurs contenant l'IBAN en clair.

## 6. Limites de cet audit

Revue statique : non vérifiés l'infrastructure réelle OVH (TLS, chiffrement disque, sauvegardes), les en-têtes HTTP en production, le contenu exact des emails et des notifications, ni les contrats sous-traitants. Les durées de conservation comptable sont à confirmer avec le comptable de chaque responsable de traitement. Ce document ne constitue pas un avis juridique.
