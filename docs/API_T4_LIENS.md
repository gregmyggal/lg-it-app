# API CLS-01 · Tranche T4 — Liens du cours (généraux / par séance), historique, restauration

Préfixe `/api`, `Authorization: Bearer <token>` (Sanctum), erreurs `{message, errors}` en français. Contrat : `docs/requirements/CLS-01-T4-contrat.md`. Règles : `docs/mockups/CLS-01-T4/WORKFLOW_UX.md` (R-T4-1 à 12).

**Droits** — lecture : staff ou **tout professeur** ; écriture et restauration : staff ou professeur avec une **assignation active** sur une classe du cours ; historique : staff ou professeur qui **a (ou a eu)** une assignation sur le cours (la restauration exige l'assignation active). Sinon 403.

## Lien (`ClasseLienResource`, enveloppé dans `data`)
`{ id, titre, url, description, type, seance_numero, hors_programme, ordre, version, modifie_par:{id,nom}|null, modifie_le, can:{update, delete} }`
- `seance_numero` : `null` = lien **général**, `1..14` = séance (fixe : une session annulée le garde, un bis affiche les mêmes liens). `> 14` = `hors_programme: true` (donnée existante ; jamais affichée aux classes ni aux élèves).
- `type` : `document` | `video` | `outil` | `jeu` (ancien `theme`). `pinned` et `actif` ne sont pas exposés.

## Routes
| Méthode | Route | Notes |
|---|---|---|
| `GET` | `/cours/{cours}/liens` | `{ data:[lien…], peut_modifier, peut_voir_historique, anciennes_ressources }` ; généraux d'abord, puis séances, chacun par `ordre` ; archivés exclus |
| `POST` | `/cours/{cours}/liens` | `{ titre, url (http/https), description?, type?, seance_numero? (1..14 ou null) }` → **201** ; placé en **dernière position** de sa portée ; version `creation` |
| `PUT` | `/liens/{lien}` | `{ version (requise), titre?, url?, description?, type?, seance_numero? }` ; **409** `{message, lien:{…, version}}` si `version` dépassée (rien n'est écrasé) ; changer de portée = action `portee` (et dernière position) ; aucun changement = aucune version |
| `DELETE` | `/liens/{lien}` | **archive** (jamais de suppression physique) : `200 {historique_id}` ; l'id sert à « Annuler » (restauration) |
| `PUT` | `/cours/{cours}/liens/ordre` | `{ seance_numero: null\|n, ids:[…] }` = exactement les liens actifs de la portée (sinon 422) ; **un lot de déplacements** (même auteur, même portée, < 5 min) = **une** version |
| `GET` | `/cours/{cours}/liens/historique?portee=&auteur=&action=&page=&per_page=` | `portee` = `generaux` ou n° de séance ; versions des **6 derniers mois** : `{ id, lien_id, lien_titre, action, avant, apres, auteur:{id,nom}, created_at, restaure_depuis_id, peut_restaurer, expiree }` ; `{data, meta}` |
| `POST` | `/liens-versions/{version}/restaurer` | `{ confirmer?: bool }` → **200** `{data: lien\|[liens]}` ; **410** version de plus de 6 mois ; **422** une `creation` ne se restaure pas (archivez) ; **409** `{modifie_depuis}` si le lien a changé depuis (renvoyer avec `confirmer: true`) ; **409** si déjà restauré |
| `POST` | `/cours/{cours}/liens/reprendre-ressources` | reprend les anciennes ressources (`cours_ressources` non reprises) en **liens généraux** versionnés → `{creees}` ; idempotent |
| `GET` | `/share/{code}` | cours : `liens: { generaux:[…], par_seance:[{seance_numero, liens:[…]}] }` (forme publique : **sans auteur, version, droits ni archivés ni hors programme**) ; `ressources` = anciennes ressources **non reprises** (affichage transitoire) |

**Actions de version** : `creation`, `modification`, `portee`, `archivage`, `ordre`, `restauration`. Restaurer une version **défait** son effet : modification/portée → rétablit `avant` ; archivage → désarchive (**annulation de suppression**, remis en dernière position) ; ordre → rétablit la liste « avant » ; restauration → la défait. La restauration **crée une nouvelle version** `restauration` (`restaure_depuis_id`) ; l'historique n'est jamais réécrit.

**Purge** : commande `liens:purge-historique` (quotidienne, 03:30) supprime les versions de plus de 6 mois et les liens archivés depuis plus de 6 mois.

Les liens de **stages, formations et anniversaires** (`/{parent}/{id}/liens`, `/liens/{id}`) gardent leur comportement historique (hors T4), sauf la suppression physique qui reste physique pour eux.
