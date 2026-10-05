# SIG-01 — Signature numérique visible sur la fiche de défraiement

| | |
|---|---|
| **Statut** | ☐ Brouillon ☐ En revue ☑ Validé (DoR) ☐ En dev ☑ En recette ☐ Livré |
| **Date / Version** | 2026-10-05 · v1.0 (maquette et décisions validées le 2026-10-05) |
| **Liens** | Maquette `docs/mockups/SIG-01/index.html` · Spec PDF `TS-01-T5-pdf-fiche-de-defraiement.md` · Reconfirmation `TS-01-T4` |

## 1. Contexte et problème
- **Situation actuelle :** « Accepter et signer » (`ReconfirmationMois.jsx` → `POST /timesheets/sign-month`) pose seulement un timestamp `signature_professeur` sur chaque saisie. Le PDF imprime « jj/mm/aaaa — signé électroniquement par le volontaire ».
- **Douleur :** cette mention n'a aucune signature visible ni aucune preuve rattachée (qui, d'où, quel contenu). Rien ne permet de démontrer que le contenu du PDF correspond à ce que le professeur a signé.
- **Objectif :** une signature visible (image) **et** une preuve vérifiable, liée au contenu signé.

## 2. Objectifs
| Objectif | Indicateur | Cible |
|---|---|---|
| Signature visible sur le PDF | Fiche générée avec bloc signature | 100 % des fiches signées |
| Signer vite sur mobile | Clics entre « Accepter et signer » et la confirmation, signature déjà créée | ≤ 2 |
| Preuve vérifiable | Toute modification du contenu après signature détectée | 100 % (test) |

**Hors périmètre :** signature électronique *qualifiée* ou avancée eIDAS (itsme®, eID, certificat), signature cryptographique PAdES intégrée au fichier PDF, signature du directeur, vérification par dépôt du PDF (itération 2).

## 3. Rôles × actions
| Action | Admin | Staff | Professeur | Public |
|---|---|---|---|---|
| Créer, modifier ou télécharger *sa* signature | — | — | C L M | — |
| Signer son mois | — | — | C (le sien) | — |
| Voir la preuve d'une signature | L (IP complète) | L (IP masquée) | L (la sienne) | — |
| Vérifier un identifiant `SIG-…` | L | L | L | L (données minimales, seulement si émise avec la vérification) |
| Activer la vérification publique | M | M | — | — |

## 4. Glossaire
| Terme | Définition | Technique |
|---|---|---|
| Signature (spécimen) | Image de signature réutilisable du professeur : nom manuscrit, dessin ou initiales | `signature_specimens` |
| Signature du mois | Événement de signature immuable : contenu signé, preuve et copie figée de l'image | `timesheet_signatures` |
| Empreinte | SHA-256 du contenu canonique du mois (lignes, montants, IBAN, identité, spécimen) | `content_hash` |
| Sceau serveur | Signature Ed25519 de la preuve par la clé privée de l'application | `seal`, `key_id` |

## 5. Parcours (voir la maquette, écrans 1 à 7)
1. Le professeur, dans Timesheets, clique sur « Accepter et signer… ». Le dialogue « Signer mon mois » affiche le récapitulatif, sa signature et la certification, puis il clique sur « Signer ».
2. **Première fois :** le dialogue commence par le créateur (Nom manuscrit par défaut, Dessiner, Initiales ; police ; encre ; PNG ; consentement).
3. **Gestion :** le lien « Ma signature » dans le bloc compte du portail ouvre le même créateur.
4. **Directeur :** dans `DetailProfesseurMois`, une ligne « Signé le … · SIG-… · Preuve » s'affiche. Si le contenu a changé depuis la signature, un bloquant PDF s'affiche.
5. **PDF :** un bloc image, une mention et un QR code remplacent « Date et signature » sur chaque page.
6. `/verif/:id` (public) affiche l'état « valide », « remplacée » ou « introuvable », avec des données minimales.

## 6. Règles métier
- **RG-1 :** l'horodatage, l'IP et le user-agent sont relevés **côté serveur**, jamais fournis par le client. L'IP est réelle derrière le proxy OVH : configurer `trustProxies`.
- **RG-2 :** le contenu canonique est un JSON trié contenant `professeur_id, user_id, annee, mois, iban, lignes[{date, objet, unite, nombre, total}], total, specimen_sha256, signed_at, ip, ua`. On en calcule `content_hash = sha256(json)`, puis `seal = Ed25519_sign(json, clé privée)`. La clé est générée une fois (`php artisan signature:keygen`), stockée dans `.env` et identifiée par un `key_id` pour pouvoir changer de clé plus tard.
- **RG-3 :** l'identifiant public `SIG-XXXX-XXXX` est aléatoire (base32 Crockford, 40 bits), non séquentiel et unique.
- **RG-4 :** chaque signature du mois porte sur **tout** le mois (lignes déjà signées incluses). La dernière signature valide fait foi. Les précédentes passent à `remplacee`.
- **RG-5 :** avant la génération du PDF, le serveur recalcule l'empreinte. Si elle diffère, le bloquant « Données modifiées depuis la signature » s'affiche.
- **RG-6 :** une copie de l'image du spécimen est figée dans la signature du mois. Changer de spécimen ne modifie jamais une signature passée.
- **RG-7 :** le spécimen est un PNG validé côté serveur (type MIME réel, 300 Ko max., 2000×800 max.), ré-encodé avec GD pour retirer toute charge utile.

## 7. Données
- `signature_specimens` : id, user_id (unique), type (`nom|dessin|initiales`), texte?, police?, couleur, chemin_png, sha256, consenti_at, timestamps.
- `timesheet_signatures` : id, public_id (unique), professeur_id, user_id, annee, mois, statut (`valide|remplacee`), signed_at, ip (varbinary/string), user_agent, payload (json), content_hash, seal, key_id, specimen_png_chemin, specimen_type, timestamps. Index (professeur_id, annee, mois).
- `parametres_site` : cle (unique), valeur, updated_by. Clé `signature_verification_publique` (0/1, défaut 0).
- `timesheets.signature_professeur` est conservé pour la compatibilité (statuts existants). Un mois signé avant SIG-01 (sans `timesheet_signatures`) garde l'ancienne mention textuelle sur le PDF.
- **RGPD :** l'IP et le user-agent sont des données personnelles. Base légale : preuve d'un engagement (intérêt légitime). Conservation **7 ans** (pièces comptables) : `signatures:anonymiser` (planifiée chaque jour à 03:00) efface IP, appareil et contenu détaillé au-delà ; la signature reste mais devient « archivée ». Pas d'IP dans le PDF ni sur la page publique.

## 8. UX
Workflow défini par le sub-agent UX Expert (rapport du 2026-10-05) · Mock-ups : `docs/mockups/SIG-01/index.html` · Validé par : Gregory Pierquin, 2026-10-05 ☑

## 9. Décisions (2026-10-05)
1. **Signature à la place du professeur : interdite.** `POST /timesheets/sign-month` renvoie 403 à toute autre personne que le professeur concerné (directeur et admin compris). La route `POST /timesheets/{id}/sign` (signature ligne par ligne, sans preuve) est supprimée.
2. **Vérification publique : oui, selon un paramètre du site** (Paramètres timesheets → « Signature électronique des fiches », staff). Le réglage est figé dans chaque signature (`verification_publique`) : le QR code et la page `/verif/:id` n'existent que pour les signatures émises pendant que le paramètre était actif.
3. **Code par e-mail au moment de signer : non.**
4. **Conservation de l'IP et de l'appareil : 7 ans.**

## 10. API
`GET|PUT /api/ma-signature` (professeur) · `POST /api/timesheets/sign-month` (+ `certification`) · `GET|PUT /api/signature-parametres` (staff) · `GET /api/signatures/{id}/verification` (public, 30 requêtes/min). Ajouts : `signature`, `recapitulatif`, `a_signature` dans `GET /timesheets/ma-confirmation` ; `signature` (preuve) dans `GET /professeurs/{id}/timesheets-mois`.

## 11. Exploitation
`php artisan signature:cle` génère la clé Ed25519 (`SIGNATURE_CLE_ID`, `SIGNATURE_CLE_PRIVEE` ; anciennes clés publiques dans `SIGNATURE_CLES_PUBLIQUES`). `TRUSTED_PROXIES` sert à relever l'IP réelle derrière le proxy de l'hébergeur. Voir `docs/DEPLOIEMENT_OVH.md`.

## 12. Tests
`backend/tests/Feature/SignatureNumeriqueTest.php` (13 cas : spécimen ré-encodé et validations, preuve scellée et altération détectée, signature sans spécimen ou sans certification, interdiction pour la direction, péremption après ajustement et remplacement, changement d'IBAN bloquant le PDF, PDF de 15 lignes avec image et QR sur une page, vérification publique selon le paramètre, droits du paramètre, IP masquée ou complète, anonymisation après 7 ans, rotation de clé). `TimesheetConfirmationTest` adapté (spécimen et certification).
