# ADR-0003 — Dépôt de fichiers d'enfants et partage aux parents par token

**Date :** 2026-11-11 · **Statut :** Accepté (directeur, 2026-11-11) · **Canvas :** `docs/requirements/DEP-01-depot-fichiers-parents-stages.md` · **Maquettes :** `docs/mockups/DEP-01/`

## Contexte
Les stages (STG-01) produisent des réalisations d'enfants (projets, créations) que les parents doivent pouvoir récupérer. Aucune entité « enfant » n'existe (audit RGPD) et le directeur ne veut pas en créer. Hébergement OVH mutualisé : pas de worker, pas d'antivirus, quotas disque, SMTP OVH.

## Décision
1. **Pas de fiche enfant** : un fichier porte un libellé libre facultatif (prénom conseillé). Les photos d'enfants sont interdites par règle (consigne affichée, non contrôlable techniquement).
2. **Espaces** : `depot_espaces` rattachés à une classe de type `stage`, soit au stage entier (`course_session_id` NULL), soit à une séance. Créés à la demande. Hors stages : 404.
3. **Stockage privé** : disque `local` (`storage/app/private/depots/{classe_id}/{uuid}.{ext}`), jamais le disque public. Téléchargement par un contrôleur qui contrôle l'autorisation (T1) puis le token (T2). Liste blanche d'extensions + contrôle du type MIME réel, nom serveur aléatoire, réponse `attachment` + `nosniff`. Limites configurables (`config/depot.php`) : 10 Mo par fichier, 200 Mo par espace.
4. **Droits** : staff sur tous les stages ; professeur sur les stages qui lui sont visibles (`Classe::visiblePour`). Un `ClassePolicy::deposer` unique.
5. **Partage (T2)** : un lien = une **sélection de fichiers** (jamais l'espace entier), token de 32 octets, hash pour la recherche et copie **chiffrée** (`Crypt`) jusqu'à l'expiration pour pouvoir envoyer plus tard le même lien. Validité 7 jours, régénérable, révocable.
6. **Adresse du parent** : jamais conservée en clair, trace masquée seule.
7. **Rétention** : suppression physique 60 jours après l'expiration du dernier partage (ou après le dépôt si jamais partagé), commande planifiée `depots:purger` (T3). La suppression d'un stage supprime ses fichiers du disque.
8. **Envoi d'e-mail** synchrone (pas de worker).

## Conséquences
- Écart assumé avec `AccesToken` (hash seul) pour permettre l'envoi différé : risque limité à une fuite simultanée de la base et de `APP_KEY`.
- Les fichiers survivent aux sauvegardes OVH au-delà de la purge : à documenter dans `docs/AUDIT_RGPD.md`.
- Un ZIP peut contenir n'importe quoi : il n'est jamais ouvert ni servi inline.
- Limites PHP (`upload_max_filesize`, `post_max_size`) à vérifier sur l'hébergement.
