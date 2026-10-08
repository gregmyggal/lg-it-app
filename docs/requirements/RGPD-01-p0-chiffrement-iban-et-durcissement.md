# RGPD-01 — P0 : chiffrement de l'IBAN et durcissement (Canvas allégé)

| | |
|---|---|
| **Statut** | En dev (décision du product owner : démarrer l'implémentation de `docs/AUDIT_RGPD.md` §4, P0) |
| **Source** | `docs/AUDIT_RGPD.md` §3.1, 3.2, 3.3, 3.4, 3.5 et recommandations P0 (1 à 5) |
| **Hors périmètre** | P1/P2/P3 : expiration des jetons, MFA, chiffrement des fichiers, conservation, export des données, notice, registre. Pas d'écran UI de consultation du journal. |

## 1. Contexte
L'IBAN des professeurs est stocké en clair, renvoyé par `GET /professeurs` à chaque liste, jamais journalisé à la lecture ; `POST /login` n'a pas de limiteur ; `.env.example` et le Docker de dev exposent des réglages dangereux en production ; les logs contiennent nom et motif libre.

## 2. Objectifs
| Objectif | Indicateur | Cible |
|---|---|---|
| IBAN illisible au repos | `SELECT compte_bancaire` brut | valeur chiffrée uniquement |
| IBAN hors listes | réponses de liste | `compte_bancaire_masque` + `compte_bancaire_renseigne`, jamais l'IBAN |
| Lectures tracées | table `acces_donnees_sensibles` | 1 ligne par lecture/téléchargement (ids seulement), purge 12 mois |
| Force brute login | 6e tentative/min/email+IP | 429 au contrat d'erreur existant |
| Config sûre | `.env.example`, installeur, en-têtes, logs, Docker | voir règles |

## 3. Acteurs et permissions (IBAN complet)
| Ressource | Admin | Directeur | Professeur | Autre professeur |
|---|---|---|---|---|
| `GET /professeurs` (liste) | masqué | masqué | 403 | 403 |
| `GET /professeurs/{id}`, `PUT` | complet, journalisé | complet, journalisé | `show` : le sien, complet (non journalisé) | 403 |
| `GET /me` | — | — | le sien, complet (non journalisé) | — |
| PDF / ZIP | téléchargement journalisé | idem | ses fiches (non journalisé) | 403 |

Décision : l'admin et le directeur sont gestionnaires de paie (audit §0). La lecture par le titulaire de sa propre donnée n'est pas journalisée (bruit sans valeur de contrôle).

## 4. Règles
- RG-1 `professeurs.compte_bancaire` : colonne `text`, cast Eloquent `encrypted`, attribut `$hidden` sur le modèle (aucune sérialisation implicite ne peut le divulguer ; l'exposition est explicite via `ProfesseurResource`).
- RG-2 Masque : `BE12 •••• •••• 1234` (4 premiers + 4 derniers), calculé côté serveur. `compte_bancaire_renseigne` booléen.
- RG-3 Recherche/filtre sur IBAN impossible (accepté).
- RG-4 Journal append-only (`acces_donnees_sensibles` : user_id, professeur_id, action `lecture_iban|telechargement_pdf|export_zip`, created_at) ; aucun nom/email ; pas de clé étrangère (la trace survit à la suppression du compte, ids seuls). Purge planifiée à 12 mois (`acces:purger-journal`, 03:15).
- RG-5 `POST /login` : 5/min par email+IP et 20/min par IP ; 429 `{message}`.
- RG-6 Config : `.env.example` sûr par défaut (APP_DEBUG=false, LOG_LEVEL=warning, LOG_STACK=daily, LOG_DAILY_DAYS=14, SESSION_ENCRYPT=true, SESSION_SECURE_COOKIE=true, MAIL_MAILER=smtp), bloc « dev local » séparé ; l'installeur écrit ces valeurs et refuse `MAIL_MAILER=log` en production ; middleware d'en-têtes (nosniff, Referrer-Policy, X-Frame-Options, HSTS si HTTPS) ; logs sans nom ni motif libre ; Docker : MySQL/phpMyAdmin liés à 127.0.0.1, `phpmyadmin:5`.
- RG-7 Clé de sceau : documentation seule (`signature:cle`, sauvegarde d'APP_KEY, rotation `APP_PREVIOUS_KEYS`). `SignatureCle` inchangé.

## 5. Critères d'acceptation
- AC-1 Un SELECT brut sur `professeurs.compte_bancaire` ne contient pas l'IBAN ; `->compte_bancaire` le restitue.
- AC-2 La commande `professeurs:chiffrer-iban` chiffre les valeurs en clair, est idempotente, `--dry-run` ne modifie rien.
- AC-3 La migration chiffre l'existant au `up()`, déchiffre au `down()` (colonne `string(34)` restaurée).
- AC-4 L'IBAN est absent de `GET /professeurs` et de toute réponse de liste ; présent dans `show`/`update` pour admin/directeur et `show`/`/me` pour le professeur titulaire ; 403 pour un autre professeur.
- AC-5 Journal alimenté par show, update, aperçu/téléchargement PDF, ZIP ; purgé au-delà de 12 mois ; non modifiable par le modèle.
- AC-6 `login` : 429 à la 6e tentative/min (même email+IP), reprise après 1 minute.
- AC-7 Installeur : `MAIL_MAILER=log` refusé en production, `APP_DEBUG=false`, `SESSION_ENCRYPT=true` écrits ; en-têtes présents sur les réponses API ; logs sans PII.

## 6. Données / migration
1. `2026_10_12_100000_encrypt_professeurs_compte_bancaire` : colonne → `text`, puis chiffrement des valeurs en clair (même logique que la commande). `down()` : déchiffre puis `string(34)`.
2. `2026_10_12_100100_create_acces_donnees_sensibles_table`.
- Détection « déjà chiffré » : tentative de `Crypt::decryptString`. Lecture brute via `DB::table` (jamais le modèle).
- Avant la migration en production : sauvegarde de la base ET d'`APP_KEY` hors serveur.

## 7. API / UI
- `ProfesseurResource` (sans enveloppe `data`, forme plate inchangée) ; `compte_bancaire` n'apparaît que si le contexte l'autorise.
- Front : parcours d'édition inchangé (la fiche détail reçoit toujours l'IBAN) ; aide « compte enregistré (masqué) » sur la section ; aucune liste ne lit l'IBAN.

## 8. Tests
Colonne chiffrée en base ; commande idempotente ; migration up/down ; index/synthèse/relations sans IBAN ; IBAN visible admin/directeur/professeur titulaire, 403 autre professeur ; journal (show, update, PDF, ZIP, pas de journal pour le titulaire) ; purge 12 mois ; append-only ; login 429 puis reprise ; installeur (refus log en production) ; en-têtes de sécurité.
