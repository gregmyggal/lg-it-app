# Mock-ups DEP-01 — Dépôt de fichiers et partage aux parents (stages)

**Créé :** 2026-11-11 · **Auteur :** sub-agent UX Expert · **Statut :** Brouillon v0.1, en attente de validation du directeur
**Canvas :** `docs/requirements/DEP-01-depot-fichiers-parents-stages.md`

Maquettes HTML autonomes (vue 375 px, ouvrir dans un navigateur ; chaque fichier montre les 4 états côte à côte) :
`01-fichiers.html` (section Fichiers + dépôt + sélection) · `02-partager.html` (dialogue de partage, envoi) · `03-partages.html` (liste, révoquer, régénérer) · `04-parent.html` (page parent + e-mail) · `05-expire.html` (lien expiré).

## 0. Où ça s'intègre (code existant)
- **Page stage du professeur** = `/mes-classes/:id` (`MesClasseSessionsPage`) : liste des sessions + liens du cours. La section « Fichiers » s'y insère entre les boutons « Gérer les liens du cours » et la liste des sessions ; chaque session reçoit une ligne « Fichiers (n) » qui filtre la section sur la séance.
- **Page stage de la direction** = `ClasseDetailPage` (admin) ; **page séance** = `/seances/:id` (`SeancePage`), **réservée admin/directeur** (route STAFF). Le professeur n'a pas de page séance : l'espace de séance est donc atteint chez lui par le filtre « Séance n » de la page stage. Même composant `FichiersStage` partout.
- Une seule liste agrège espace du stage et espaces de séances, avec un filtre (« Tout / Stage / Séance 1… »). Onglets de la section : **Fichiers | Partages**.
- Dépôt : feuille du bas (mobile) / `AdminModal` (desktop). Retours par toasts existants. Statuts via `utils/statuts.js` (nouvelles tables `STATUTS_PARTAGE` : actif/expire/revoque, `ETATS_ENVOI`).

## 1. Workflows par persona

### Professeur, mobile, fin de séance
1. Page stage → **Déposer des fichiers** (1) → sélecteur du téléphone (2, multi-sélection) → feuille : espace pré-rempli (séance en cours ou dernière), **libellé commun facultatif** appliqué au lot → **Déposer N fichiers** (3). **3 clics** (cible Canvas ≤ 3 respectée).
2. Plus tard (ou tout de suite) : **Partager** sur le groupe de l'enfant (1) → **Créer le lien** (2) → **Envoyer le lien** après saisie de l'e-mail (3, plus la saisie) ou **Copier le lien** (3). **3 clics + saisie** ; avec envoi, confirmation incluse : 4 (cible ≤ 4 respectée).
3. Sans libellé : sélection fichier par fichier (n clics) puis barre « Partager à un parent » ; c'est le point faible (voir §3).

### Direction, desktop
Même composant en pleine largeur, partages en **tableau** (libellé, fichiers, créé par, statut, envoi, ouvertures, actions), filtres par statut (Actifs / Expirés / Révoqués / Non envoyés), actions groupées « Révoquer la sélection ». Voit tous les stages ; journal (US-6) en bas de l'onglet Partages.

### Parent, mobile, sans compte
Ouvre le lien de l'e-mail → voit uniquement les fichiers du partage → **Télécharger** (1 clic par fichier). Lien invalide/expiré/révoqué : même page neutre.

## 2. Cas alternatifs
- Fichier refusé (type/taille/quota) : message **par fichier**, les autres sont déposés.
- Quota d'espace atteint : « Espace plein (200 Mo). Supprimez des fichiers pour en déposer d'autres. »
- Fichier supprimé alors qu'il est dans un partage actif : disparaît du lien ; si le partage devient vide, badge « Vide » et envoi désactivé.
- Prof non assigné : section absente (403 côté API, jamais de bouton inactif sans explication).
- E-mail en échec : le lien reste créé, message avec « Réessayer » ; l'envoi est toujours rejouable depuis la liste.
- Envoi à plusieurs parents : « Envoyer à un autre parent » (même lien, une trace par envoi).
- Hors ligne pendant un dépôt : reprise par fichier (« Réessayer ce fichier »).

## 3. Point Q1 / RG-3 : un lien = une sélection de fichiers
**Verdict honnête :** le principe est indispensable (vie privée de mineurs) mais, tel quel (cocher N fichiers à chaque partage), le parcours est **trop lourd** : un stage de 10 enfants x 3 fichiers = 30 cases, 10 partages, risque d'erreur (mauvais fichier chez un mauvais parent, fuite).
**Parcours retenu, qui réduit la charge sans toucher à RG-3 :**
1. **Libellé commun au dépôt** : le libellé saisi une fois s'applique au lot (« Léa » pour 3 fichiers). Coût : 1 champ facultatif.
2. **Groupes par libellé** dans la liste : case de groupe = tout l'enfant (1 clic) ; bouton **Partager** sur chaque groupe = dialogue présélectionné.
3. Dialogue : filtre par libellé, compteur « 2/2 », avertissement « Vérifiez que tous les fichiers concernent le même enfant ».
Résultat : **3 clics par enfant** quand les libellés sont renseignés.
**Alternative (si les libellés sont jugés trop contraignants) :** « un lien par séance », donc tout l'espace d'une séance. Plus simple mais **inacceptable pour la vie privée** si plusieurs enfants y déposent. Je ne la recommande que si un espace = un enfant (non retenu). Autre option légère : bouton « Créer un lien par libellé » (1 clic génère N partages d'un coup, envoi ensuite) : à valider si les stages dépassent ~8 enfants.
**Garde-fou ajouté :** fichiers « Jamais partagé » signalés ; fichiers sans libellé groupés à part, avec avertissement avant partage.

## 4. Recommandations sur les questions du Canvas
- **Q1 :** valider « un lien = une sélection », avec libellé de lot + groupes (ci-dessus).
- **Q2 :** **oui**, un partage peut mélanger stage et séances (le parent veut tout le travail de son enfant en un lien). UX : liste unique avec filtre, le dialogue montre l'espace de chaque fichier en sous-titre.
- **Q3 :** **masquée seulement** (RG-7) ; « Renvoyer » = ressaisir l'adresse (1 champ). Rappeler dans le dialogue que l'adresse n'est pas conservée. En clair seulement si le directeur veut gagner du temps, avec mention RGPD.
- **Q7 :** **autoriser les images** (captures d'écran Scratch utiles) mais **interdire par règle les photos d'enfants**, avec avertissement permanent non bloquant dans la feuille de dépôt (pas de case à cocher : un clic de plus sans valeur réelle). Détection technique impossible.

## 5. Contradiction à trancher (Canvas RG-5 vs AC-6)
RG-5 : seul le hash du token est stocké, visible une seule fois. AC-6 : envoi **plus tard** avec le **même lien**. Incompatible tel quel. Options : (a) stocker le token **chiffré** (réversible) jusqu'à l'expiration, ce qui permet « Copier » et « Envoyer » à tout moment (recommandé, UX simple) ; (b) token jamais ré-affichable, « envoyer plus tard » régénère le lien (échéance repart à 7 j, anciens liens copiés invalidés).

## 6. Textes exacts (FR)
- Boutons : « Déposer des fichiers », « Déposer N fichiers », « Partager », « Partager à un parent », « Créer le lien (N fichiers) », « Copier le lien », « Envoyer le lien », « Envoyer l'e-mail », « Modifier l'adresse », « Envoyer à un autre parent », « Régénérer le lien », « Révoquer le lien », « Réessayer », « Plus tard — voir dans « Partages » ».
- Confirmations avec conséquence : Révoquer = « Le lien cessera de fonctionner immédiatement : le parent verra « lien expiré ». Les fichiers restent dans le stage. » ; Régénérer = « Un nouveau lien valable 7 jours sera créé avec les mêmes fichiers. L'ancien lien ne fonctionnera plus. Le nouveau lien ne sera pas envoyé automatiquement. » ; Envoyer = « Toute personne qui possède ce lien peut télécharger les N fichiers jusqu'au … » ; Supprimer = « Supprimer définitivement ? Les liens qui contiennent ce fichier ne le montreront plus. »
- Toasts : « N fichiers déposés pour Léa. Prochaine étape : « Partager ». » · « Lien créé. Rien n'a encore été envoyé. » · « E-mail envoyé à p***@…. Le lien reste valable jusqu'au … » · « Lien révoqué. »
- Page expirée : titre « Ce lien n'est plus valable » ; « Ce lien a expiré ou a été désactivé. Pour des raisons de confidentialité, les liens sont valables 7 jours. Contactez Logiscool Pays Vert pour recevoir un nouveau lien. »
- E-mail : objet « Les créations de votre enfant — Logiscool Pays Vert » (aucune donnée d'enfant) ; corps et bouton « Voir et télécharger les fichiers » dans `04-parent.html`.
- Badges : « Actif · expire le … » (vert), « Expiré » (gris), « Révoqué » (rouge) ; « Non envoyé » (orange), « Envoyé à m***@… le … » (vert).

## 7. Les 4 états par écran
Tous représentés dans chaque maquette (chargement : squelette + `role="status"` ; vide : pourquoi + action ; erreur : message métier + Réessayer, `role="alert"` ; succès : toast `role="status"` + résultat concret). Écrans : fichiers (01 a-g), dialogue (02 a-g), partages (03 a-g), parent (04), expiré (05).

## 8. Accessibilité
Cibles tactiles >= 44 px ; focus visible 3 px ; cases à cocher avec `aria-label` incluant le nom du fichier/groupe ; filtres en `aria-pressed` ; progression des dépôts dans une zone `aria-live="polite"` ; erreurs `role="alert"` ; statuts texte + couleur ; contrastes >= 4.5:1 (badges : fg foncé sur fond clair) ; dialogues avec piège du focus et fermeture Échap ; page parent `lang="fr"`, `noindex`, lisible à 200 % de zoom, une action par ligne.
