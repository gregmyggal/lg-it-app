# DEP-01 — Dépôt de fichiers d'enfants et partage aux parents par lien temporaire (stages) — Canvas de requirements

> Copie remplie de `docs/REQUIREMENTS_CANVAS.md`. **v1.0 — mock-ups validés par le directeur le 2026-11-11 ; tranches T1 (dépôt), T2 (partage par sélection, page parent, régénération, révocation) et T3 (envoi par e-mail, journal, purge à 60 jours) livrées** (backend + frontend, ADR `docs/adr/0003-depot-fichiers-et-partage-par-token.md`). Reste avant la production : Q6 (texte RGPD / consentement parental), confirmation de Q5, recette navigateur après `php artisan migrate`, limites PHP d'OVH (`upload_max_filesize`, `post_max_size`).

| | |
|---|---|
| **ID / Titre** | DEP-01 — Espaces de dépôt par séance et par stage, partage aux parents par lien de 7 jours |
| **Statut** | ☑ Brouillon ☐ En revue ☐ Validé (DoR) ☐ En dev ☐ En recette ☐ Livré |
| **Analyste** | Gregory Pierquin · **UX/UI** : sub-agent UX Expert |
| **Date / Version** | 2026-11-11 · v0.1 |
| **Liens** | `STG-01-stages-sur-une-semaine.md` (stage = `Classe` de type `stage`), `ADMIN-02` (modèle `AccesToken`, hash du token), `docs/AUDIT_RGPD.md` |

## 1. Contexte et problème
- **Situation actuelle :** les enfants créent des fichiers pendant les stages (projets Scratch, images, PDF…). Rien dans l'application ne permet de les remettre aux parents.
- **Douleur :** transmission ad hoc (clé USB, messageries personnelles du professeur), sans traçabilité ni durée de vie maîtrisée, avec des données de mineurs hors de contrôle.
- **Déclencheur :** demande du directeur (2026-11-11), sur la fonctionnalité Stages (STG-01) livrée le même jour.

## 2. Objectifs et indicateurs
| Objectif | Indicateur | Cible |
|---|---|---|
| Déposer sans friction depuis la page du stage | clics pour déposer un lot de fichiers | ≤ 3 |
| Envoyer en deux temps | dépôt et envoi possibles à des moments différents | 100 % |
| Aucune fuite de données d'enfants | fichiers servis sans token valide | 0 |

**Hors périmètre v1 :** fiche « enfant » (option B retenue : aucun enfant n'est stocké) ; comptes parents ; galerie publique ; dépôt sur les classes ordinaires (stages seulement) ; dépôt par les parents ; statistiques détaillées.

## 3. Rôles et permissions
| Action | Admin | Staff | Professeur | Parent (lien) |
|---|---|---|---|---|
| Voir/créer l'espace d'un stage ou d'une séance | tous | tous | ses stages (assigné ou remplaçant) | — |
| Déposer / supprimer un fichier | oui | oui | ses stages | — |
| Créer / régénérer / révoquer un partage, envoyer par e-mail | oui | oui | ses stages | — |
| Consulter et télécharger | — | — | — | uniquement les fichiers du partage, jusqu'à expiration ou révocation |

L'accès parent n'exige aucune connexion : seul le token compte.

## 4. Glossaire
| Terme affiché | Définition | Technique (proposé) |
|---|---|---|
| Espace de dépôt | Dossier privé rattaché à un stage entier ou à une séance | `depot_espaces` (`classe_id`, `course_session_id` nullable) |
| Fichier | Fichier déposé ; libellé libre facultatif (ex. « Léa – jeu Scratch ») | `depot_fichiers` |
| Partage | Lien parent : sélection de fichiers + token + expiration 7 jours | `depot_partages` + pivot `depot_partage_fichiers` |
| Envoi | Trace de l'e-mail envoyé à un parent | `depot_envois` |

## 5. User stories
| ID | En tant que… | Je veux… | Priorité |
|---|---|---|---|
| US-1 | professeur/direction | déposer des fichiers depuis la page du stage, dans l'espace du stage ou d'une séance | Must |
| US-2 | professeur/direction | créer un partage en choisissant les fichiers destinés à un parent, sans envoyer tout de suite | Must |
| US-3 | professeur/direction | envoyer le lien à un parent en saisissant son adresse e-mail, plus tard et indépendamment du dépôt | Must |
| US-4 | professeur/direction | régénérer un lien expiré ou le révoquer | Must |
| US-5 | parent | ouvrir le lien sans compte, voir et télécharger les fichiers de mon enfant | Must |
| US-6 | direction | voir qui a déposé/partagé/envoyé quoi et quand | Should |

### Critères d'acceptation
| ID | Étant donné… | Quand… | Alors… |
|---|---|---|---|
| AC-1 | un stage | un prof non assigné appelle l'API de dépôt | 403 |
| AC-2 | un partage actif | le parent ouvre le lien | il voit uniquement les fichiers sélectionnés dans ce partage |
| AC-3 | un partage de plus de 7 jours ou révoqué | le parent ouvre le lien | message neutre « lien expiré », aucun fichier servi |
| AC-4 | un partage expiré | le prof clique « Régénérer » | l'ancien token cesse de fonctionner, un nouveau est valable 7 jours |
| AC-5 | un fichier `.html`, `.svg`, `.php` ou au MIME réel non autorisé | dépôt | refus avec message par fichier |
| AC-6 | un partage créé mais non envoyé | plus tard, saisie de l'e-mail | l'e-mail part avec le même lien, la date d'expiration inchangée |
| AC-7 | des fichiers périmés (cf. RG-6) | la commande planifiée s'exécute | fichiers supprimés du disque et de la base |

## 6. Règles métier
- **RG-1 :** aucun enfant n'est stocké. Le libellé est un texte libre facultatif ; **recommandation : ne pas y saisir le nom complet** (prénom seul).
- **RG-2 :** un espace par stage et un par séance (créés à la demande).
- **RG-3 (garde-fou vie privée) :** *un lien ne donne jamais accès à tout l'espace.* Il donne accès à la **sélection de fichiers** choisie lors de la création du partage. Sans cela, un parent verrait les fichiers des autres enfants.
- **RG-4 :** durée de validité du partage = 7 jours à compter de sa création ; régénérer = nouveau token, nouvelle échéance, ancien token invalidé ; révoquer = invalidation immédiate.
- **RG-5 (validé 2026-11-11) :** token aléatoire de 32 octets. Le **hash** (`token_hash`) sert à retrouver le partage à l'ouverture du lien ; le token est aussi conservé **chiffré** (`Crypt`, `APP_KEY`) jusqu'à l'expiration, pour pouvoir copier ou envoyer plus tard le même lien. Le token chiffré est effacé à l'expiration, à la révocation et à la régénération. Il n'est jamais exposé par l'API hors des actions « copier » et « envoyer » (rôles autorisés), ni écrit dans les logs. Écart assumé avec `AccesToken` (hash seul).
- **RG-6 (validé 2026-11-11) :** un fichier est supprimé du disque 60 jours après l'expiration de son dernier partage (ou 60 jours après dépôt s'il n'a jamais été partagé), puis l'espace est vidé.
- **RG-7 (validé 2026-11-11) :** l'adresse e-mail du parent est saisie à l'envoi et **n'est jamais conservée en clair** ; la trace d'envoi garde uniquement une version masquée (`m***@gmail.com`) et la date. Conséquence : renvoyer un lien exige de ressaisir l'adresse. Elle ne doit pas non plus apparaître dans les logs applicatifs.
- **RG-10 (validé 2026-11-11) :** seules des **réalisations** (projets, créations) sont déposées ; **pas de photos d'enfants**. Le dépôt affiche cette consigne. Aucun contrôle automatique du contenu n'est possible : la règle repose sur la consigne et sur la responsabilité du déposant (journalisé).
- **RG-8 :** liste blanche de types (images, PDF, zip, `.sb3` et équivalents à préciser), contrôle du MIME réel, renommage serveur, limite par fichier (10 Mo proposé) et quota par espace (200 Mo proposé). Aucun fichier n'est exécuté ni servi inline ; `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`.
- **RG-9 :** stockage privé uniquement (`storage/app/private`), téléchargement via contrôleur vérifiant le token ; page parent en `noindex`, sans cookie.

### Statuts d'un partage
| Statut | Libellé | Badge | Transitions → | Déclencheur |
|---|---|---|---|---|
| `actif` | Actif (expire le …) | vert | `expire`, `revoque`, nouveau partage par régénération | création / régénération |
| `expire` | Expiré | gris | régénération | `expire_le` dépassé (calculé, pas stocké) |
| `revoque` | Révoqué | rouge | régénération | « Révoquer » |

Envoi : « non envoyé » / « envoyé à m***@… le … » (un partage peut être envoyé à plusieurs parents).

## 7. Données
| Table | Champs clés |
|---|---|
| `depot_espaces` | id, classe_id (FK), course_session_id (FK nullable), unique (classe_id, course_session_id) |
| `depot_fichiers` | id, espace_id, nom_original, chemin, mime, taille, libelle (nullable), depose_par (FK users), timestamps, suppression physique en T1 (fichier + ligne) |
| `depot_partages` | id, espace_id, token_hash (unique), token_chiffre (nullable, effacé à l'expiration/révocation), expire_le, revoque_le (nullable), cree_par, nb_ouvertures, derniere_ouverture_le |
| `depot_partage_fichiers` | partage_id, fichier_id |
| `depot_envois` | id, partage_id, destinataire_masque, envoye_par, envoye_le |

- **Rétention / RGPD :** voir RG-6 et RG-7 ; commande `depots:purger` planifiée (même schéma que `timesheets:purger-*`) ; mise à jour de `docs/AUDIT_RGPD.md` (données de mineurs, sauvegardes OVH, base légale/consentement parental à valider par le juriste).
- **Jobs :** pas de worker sur le mutualisé → envoi d'e-mail en synchrone ; pas de zip volumineux (limite ou téléchargement fichier par fichier).

## 8. Exigences UX/UI
> 🚦 **Barrière de validation** : workflow par le sub-agent UX Expert + mock-ups validés avant tout développement. Workflow : `docs/mockups/DEP-01/` · Validé par : ☐

**Écrans pressentis :** (1) onglet/section « Fichiers » sur la page du stage et de chaque séance (professeur + direction, mobile prioritaire) ; (2) dialogue « Partager à un parent » (sélection des fichiers → créer le lien → copier ou envoyer plus tard) ; (3) liste des partages avec statut, régénérer, révoquer, envoyer ; (4) page publique parent (sans connexion, mobile) ; (5) page « lien expiré » ; (6) e-mail au parent.

**4 états à fournir pour chaque écran** (chargement, vide, erreur, succès). Charge cognitive : dépôt ≤ 3 clics ; partage + envoi ≤ 4 clics. Appareils : mobile/tablette prioritaires. Accessibilité : clavier, contraste, `aria-live` sur les téléversements.

## 9. Non fonctionnel
| Domaine | Exigence |
|---|---|
| Sécurité | token haché, comparaison à temps constant, limitation de débit sur la route publique, fichiers jamais sur disque public |
| Traçabilité | journal dépôt/partage/envoi/révocation + ouvertures (compteur, dernier accès) |
| Mail | sujet et corps en FR, expéditeur SMTP OVH existant, aucune donnée de l'enfant dans l'objet |
| Compatibilité | FR, Europe/Brussels, mobile |

## 10. Impact technique (à affiner)
| Couche | Changements | Taille |
|---|---|---|
| DB | 5 tables ci-dessus | M |
| Back | modèles, `DepotPolicy` (via `Classe`), services dépôt/partage, contrôleur public de téléchargement, mailable, commande de purge | L |
| API | `POST /classes/{id}/depots/fichiers`, `GET/DELETE …/fichiers`, `POST …/partages`, `POST …/partages/{id}/regenerer|revoquer|envoyer`, `GET /public/depots/{token}` (+ `/fichiers/{id}`) | L |
| Front | section « Fichiers » (stage + séance), dialogue de partage, page publique parent | L |
| Tests | feature (403, expiration, révocation, MIME), unit (token), recette navigateur | M |

**Tranches verticales :** T1 = dépôt + liste + suppression sur le stage/séance ; T2 = partage par sélection + lien copiable + page parent + expiration/régénération/révocation ; T3 = envoi par e-mail en second temps + journal + purge.
**ADR requis :** oui — `docs/adr/0003-depot-fichiers-et-partage-par-token.md` (stockage privé, token haché, sélection de fichiers).

## 11. Plan de recette (esquisse)
R1 professeur dépose 5 fichiers sur une séance · R2 crée un partage de 2 fichiers sans envoyer · R3 envoie plus tard à une adresse · R4 parent ouvre le lien (mobile) et télécharge · R5 lien à J+8 → message d'expiration · R6 régénération · R7 révocation immédiate · R8 prof non assigné → 403 · R9 fichier interdit refusé.

## 12. Questions ouvertes
| # | Question | Avant | Réponse |
|---|---|---|---|
| Q1 | **RG-3 : valider qu'un lien = une sélection de fichiers**, et non l'espace entier de la séance (sinon un parent voit les travaux des autres enfants). | DoR | **Tranché 2026-11-11** : sélection de fichiers, choisie par groupe de libellé |
| Q2 | Un stage de plusieurs séances : le partage peut-il mélanger des fichiers de l'espace du stage et de plusieurs séances ? | UX | |
| Q3 | Conservation de l'adresse e-mail du parent | DoR | **Tranché 2026-11-11** : jamais en clair, version masquée seulement (RG-7) |
| Q4 | Durée de conservation des fichiers après expiration | DoR | **Tranché 2026-11-11** : 60 jours (RG-6) |
| Q5 | Types de fichiers autorisés, limite par fichier et quota par espace ? | dev | **Valeurs par défaut en place (`config/depot.php`)** : png, jpg, gif, webp, pdf, txt, zip, sb3, sb2 ; 10 Mo par fichier ; 200 Mo par espace. À confirmer / ajuster. |
| Q6 | Texte d'information RGPD/consentement parental et validation juridique ? | recette | |
| Q7 | Photos d'enfants | DoR | **Tranché 2026-11-11** : interdites, uniquement des réalisations (RG-10) |
| Q8 | Les « cours » (classes ordinaires) sont-ils aussi concernés ? Le message initial parlait de « stages ou cours » ; le Canvas ne couvre que les stages (v1). | DoR | **Tranché 2026-11-11** : stages seulement |

## 13. Validation (DoR)
☑ Workflow UX + mock-ups validés (2026-11-11) ☑ Q1, Q3, Q4, Q7, Q8 tranchées (reste Q5 types/limites, Q6 texte RGPD) ☑ ADR rédigé ☑ AC testables ☐ Sponsor métier (directeur)
