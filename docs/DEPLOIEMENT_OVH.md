# Déploiement sur OVH mutualisé (offre Pro)

Mise en ligne de lg-it-app (Laravel 11 + React) sur un hébergement OVH mutualisé
**Pro** ou **Performance** (l'offre Perso n'a pas d'accès SSH).

Principe :

- **Backend** : le serveur récupère le code par `git` depuis GitHub, puis
  `composer install`, migrations et caches sont lancés en SSH.
- **Frontend** : pas de Node sur le mutualisé. Le build Vite est fait sur ton
  poste, puis envoyé par `rsync` dans `backend/public/`.
- **Configuration** (`backend/.env`, migrations, compte admin) : assistant web
  `/install`, ouvert par un lien à usage unique.
- **Accès GitHub du serveur** : une clé SSH **protégée par passphrase**. Aucun
  accès automatique : la passphrase est demandée à chaque déploiement.

Dans la suite, remplace `<login>`, `<cluster>` et `<XXX>` par tes valeurs
(visibles dans le Manager OVH).

---

## A. Manager OVH (une seule fois)

1. **Activer SSH** : Hébergements → ton hébergement → onglet **FTP‑SSH** →
   activer **SSH** sur l'utilisateur principal. Noter :
   - le serveur `ssh.<cluster>.hosting.ovh.net` (port 22) ;
   - l'utilisateur (le login FTP) ;
   - le dossier personnel `/homez.<XXX>/<login>`.
2. **Créer la base MySQL** : onglet **Bases de données** → Créer → MySQL 8.
   Noter l'hôte (`<nom>.mysql.db`), le nom de la base, l'utilisateur et le mot de
   passe. La création prend quelques minutes.
   Une base déjà utilisée par un autre site peut être réutilisée : l'assistant
   préfixe les tables (`lgit_` par défaut) et signale les conflits.
3. **Adresse d'envoi des mails** (facultatif) : par exemple `noreply@lgit.be`
   dans l'onglet E‑mails. SMTP OVH : `ssl0.ovh.net`, port 465.
4. **Ne pas configurer le Multisite tout de suite** : le dossier du code n'existe
   pas encore (voir partie D).

---

## B. Préparer le serveur en SSH (une seule fois)

### 1. Installer ta clé SSH personnelle sur le serveur (depuis ton Mac)

```bash
ssh-copy-id <login>@ssh.<cluster>.hosting.ovh.net
```

```bash
ssh <login>@ssh.<cluster>.hosting.ovh.net
```

Les commandes suivantes s'exécutent **sur le serveur**.

### 2. Vérifier PHP et git

```bash
php -v && git --version
```

Si `php -v` affiche une version inférieure à 8.2, repère le binaire versionné
(`ls /usr/local/php8.*/bin/php` ou `which php8.3`) et mets ce chemin dans
`PHP_BIN` de `.env.deploy.prod`.

### 3. Installer Composer

```bash
cd ~ && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php && rm composer-setup.php && php ~/composer.phar --version
```

### 4. Créer la clé GitHub du serveur, avec passphrase

Ne pas passer `-N ""` : `ssh-keygen` demande alors une passphrase.

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github_lgit -C "ovh-lgit"
```

```bash
printf 'Host github.com\n  IdentityFile ~/.ssh/github_lgit\n  IdentitiesOnly yes\n' >> ~/.ssh/config && chmod 600 ~/.ssh/config
```

```bash
cat ~/.ssh/github_lgit.pub
```

Déclarer cette clé publique sur GitHub. Deux possibilités ; dans les deux cas
la passphrase reste demandée :

| Où la déclarer | Portée | Avis |
|---|---|---|
| Repo `lg-it-app` → Settings → **Deploy keys**, sans « Allow write access » | lecture seule, ce repo uniquement | **recommandé** |
| Ton compte → Settings → SSH and GPG keys | écriture sur **tous** tes repos | à éviter sur un mutualisé |

À éviter aussi : faire passer la clé de ton Mac jusqu'au serveur (redirection
d'agent, `ssh -A`). Pendant la connexion, n'importe quel processus disposant
d'un accès suffisant au serveur mutualisé peut l'utiliser.

Tester l'accès (la passphrase est demandée) :

```bash
ssh -T git@github.com
```

Réponse attendue : `Hi gregmyggal/lg-it-app! …` (deploy key) ou `Hi gregmyggal! …` (clé de compte).

Si le port 22 sortant est bloqué (timeout), passer par le port 443 :

```bash
printf 'Host github.com\n  HostName ssh.github.com\n  Port 443\n  IdentityFile ~/.ssh/github_lgit\n  IdentitiesOnly yes\n' > ~/.ssh/config
```

### 5. Cloner le repo en dehors de `www/`

```bash
cd ~ && git clone git@github.com:gregmyggal/lg-it-app.git && cd lg-it-app && git log --oneline -1
```

Le code reste ainsi hors du web : seul `backend/public` sera exposé (partie D).

### 6. Régler la version PHP pour ce site uniquement

L'hébergement sert aussi d'autres sites : ne pas modifier le `.ovhconfig` à la
racine, qui s'appliquerait à tous. Créer le fichier dans le dossier racine du
site (il est ignoré par git) :

```bash
printf 'app.engine=php\napp.engine.version=8.3\nhttp.firewall=none\nenvironment=production\ncontainer.image=stable64\n' > ~/lg-it-app/backend/public/.ovhconfig
```

La page `/install` affiche la version PHP réellement utilisée par le site web,
ce qui permet de vérifier que le réglage a pris.

---

## C. Premier déploiement (depuis ton Mac)

1. Créer la configuration du déploiement :

   ```bash
   cp .env.deploy.prod.example .env.deploy.prod
   ```

   Remplir `SSH_USER`, `SSH_HOST`, `REMOTE_PATH=/homez.<XXX>/<login>/lg-it-app`,
   `SITE_URL` et, si besoin, `PHP_BIN`. Ce fichier est ignoré par git.

2. Tout commiter et pousser (le script refuse de déployer en prod sinon) :

   ```bash
   git status --short && git push
   ```

3. Lancer le déploiement :

   ```bash
   ./scripts/deploy.sh prod
   ```

   Le script :
   - **1/5** — lance `git fetch` sur le serveur et **demande la passphrase** ;
   - **2/5** — construit le frontend React en local ;
   - **3/5** — `git merge`, puis `composer install`. Comme `backend/.env`
     n'existe pas encore, il **affiche un lien `https://…/install?token=…`
     valable 24 h** au lieu de lancer les migrations ;
   - **4/5** — envoie le build React dans `backend/public/`.

   Garder ce lien pour la partie E.

---

## D. Brancher le domaine (Manager OVH)

1. **Multisite** → ajouter ou modifier `lgit.be` (et `www.lgit.be`) →
   **Dossier racine : `lg-it-app/backend/public`**.
2. Cocher **SSL**, puis générer ou régénérer le certificat Let's Encrypt (onglet
   Informations générales). Compter 15 à 60 minutes, plus la propagation DNS si
   le domaine vient d'être ajouté.

---

## E. Assistant d'installation `/install` (navigateur)

Ouvrir le lien obtenu en C.3. L'assistant :

1. vérifie les prérequis : PHP ≥ 8.2, extensions, droits d'écriture, build React
   présent, HTTPS ;
2. demande la base de données (hôte `.mysql.db`, préfixe `lgit_`), avec un bouton
   pour tester la connexion ;
3. demande la configuration mail (SMTP OVH), elle aussi testable ;
4. écrit `backend/.env` (APP_KEY générée, `APP_DEBUG=false`), lance les
   migrations, crée le compte admin (12 caractères minimum, majuscules,
   minuscules et chiffres), puis génère les caches ;
5. désactive le lien et renvoie vers `/connexion`.

Si un dossier est signalé non inscriptible :

```bash
cd ~/lg-it-app/backend && chmod -R 755 storage bootstrap/cache
```

Si le lien a expiré, en générer un nouveau :

```bash
cd ~/lg-it-app/backend && php artisan app:installer --url=https://lgit.be
```

---

## F. Déploiements suivants

```bash
git push && ./scripts/deploy.sh prod
```

Le script demande la passphrase (étape 1/5), puis :
- met le site en maintenance ;
- `git merge`, `composer install`, migrations, reconstruction des caches ;
- envoie le build React ;
- remet le site en ligne et teste `/up` et `/`.

Si le `git fetch` échoue (mauvaise passphrase, accès GitHub), le script
s'arrête **avant** toute modification du site.

**Mise à jour manuelle** : se connecter en SSH et lancer `git pull` dans
`~/lg-it-app` fonctionne aussi. Le script relancé ensuite ne fera rien de plus
côté git (le contrôle du commit passera), mais il reste nécessaire pour le build
React, `composer`, les migrations et les caches.

**Reconfigurer** la base ou le mail : relancer `php artisan app:installer`.
L'assistant s'ouvre alors en mode « reconfigure » et conserve l'APP_KEY.

---

## Points d'attention

- **Files d'attente** : l'assistant écrit `QUEUE_CONNECTION=database`, mais
  aucun worker ne tourne en permanence sur un mutualisé. C'est sans effet
  aujourd'hui (aucun `ShouldQueue` ni `dispatch` dans le code). Si des mails
  passent un jour par la file, passer à `QUEUE_CONNECTION=sync`.
- **Tâches planifiées** : les tâches OVH tournent au mieux une fois par heure.
  Le `schedule:run` à la minute n'est pas disponible.
- **Staging** : même procédure avec `.env.deploy.staging`, un second clone (par
  ex. `~/lg-it-app-staging`), une base ou un préfixe distinct, et
  `./scripts/deploy.sh staging`.
