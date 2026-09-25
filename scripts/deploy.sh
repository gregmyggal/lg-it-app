#!/usr/bin/env bash
# =============================================================
# LG-IT App — Déploiement (Laravel + React) sur OVH mutualisé
#
# Usage :
#   ./scripts/deploy.sh staging     # déploie sur staging
#   ./scripts/deploy.sh prod        # déploie en production
#
# Principe :
#   - Backend : le serveur fait un `git pull` de la branche déployée
#     (le commit local doit donc être poussé sur origin au préalable),
#     puis composer install / migrate / caches via SSH.
#   - Frontend : pas de Node sur OVH mutualisé → build Vite en local,
#     puis rsync de frontend/dist/ dans backend/public/.
#
# Pré-requis (une seule fois, voir .env.deploy.prod.example) :
#   - Offre OVH Pro/Performance (accès SSH), clé SSH configurée
#   - Repo cloné sur le serveur dans REMOTE_PATH, avec une deploy key
#     permettant au serveur de faire `git fetch` sur origin
#   - backend/.env : créé par l'assistant web /install au 1er déploiement
#     (le script affiche le lien), ou à la main
#   - composer.phar uploadé sur le serveur (COMPOSER_CMD)
#   - .ovhconfig à la racine de l'hébergement (PHP >= 8.2)
#   - Multisite OVH : dossier racine = <REMOTE_PATH>/backend/public
# =============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

# --- Argument : environnement cible ---
TARGET="${1:-}"

if [ -z "${TARGET}" ] || { [ "${TARGET}" != "staging" ] && [ "${TARGET}" != "prod" ]; }; then
  echo "Usage : ./scripts/deploy.sh <staging|prod>"
  echo ""
  echo "  staging  — déploie sur staging (test avant prod)"
  echo "  prod     — déploie en production"
  exit 1
fi

# --- Charger la configuration de l'environnement ---
ENV_FILE="${ROOT_DIR}/.env.deploy.${TARGET}"

if [ ! -f "${ENV_FILE}" ]; then
  echo "❌ Fichier ${ENV_FILE} introuvable."
  echo "   Copiez .env.deploy.${TARGET}.example → .env.deploy.${TARGET} et remplissez les valeurs."
  exit 1
fi

# shellcheck source=/dev/null
source "${ENV_FILE}"

for var in SSH_USER SSH_HOST REMOTE_PATH SITE_URL GIT_BRANCH; do
  if [ -z "${!var:-}" ]; then
    echo "❌ Variable ${var} manquante dans ${ENV_FILE}"
    exit 1
  fi
done

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_CMD="${COMPOSER_CMD:-${PHP_BIN} ~/composer.phar}"
VITE_API_URL="${VITE_API_URL:-${SITE_URL%/}/api}"
SSH_TARGET="${SSH_USER}@${SSH_HOST}"

# --- Vérifications git locales ---
CURRENT_BRANCH="$(git -C "${ROOT_DIR}" rev-parse --abbrev-ref HEAD)"
if [ "${CURRENT_BRANCH}" != "${GIT_BRANCH}" ]; then
  echo "❌ Branche locale '${CURRENT_BRANCH}' ≠ branche déployée '${GIT_BRANCH}'."
  echo "   Faites : git checkout ${GIT_BRANCH}"
  exit 1
fi

# Le frontend est buildé depuis le working tree local : des modifs non
# committées partiraient en ligne sans correspondre au commit backend.
if [ -n "$(git -C "${ROOT_DIR}" status --porcelain -- backend frontend)" ]; then
  echo "⚠️  Modifications non committées dans backend/ ou frontend/ :"
  echo ""
  git -C "${ROOT_DIR}" status --short -- backend frontend
  echo ""
  if [ "${TARGET}" = "prod" ]; then
    echo "❌ Refusé en production : committez (et poussez) avant de déployer."
    exit 1
  fi
  read -rp "Le build frontend les inclura mais pas le backend. Continuer ? (y/N) " confirm
  if [ "${confirm}" != "y" ] && [ "${confirm}" != "Y" ]; then
    echo "Déploiement annulé."
    exit 0
  fi
fi

if ! git -C "${ROOT_DIR}" remote get-url origin >/dev/null 2>&1; then
  echo "❌ Aucun remote 'origin' configuré : le serveur ne pourra pas faire de git pull."
  echo "   Faites : git remote add origin <url> && git push -u origin ${GIT_BRANCH}"
  exit 1
fi

echo "▶ Vérification de la synchronisation avec origin/${GIT_BRANCH}..."
git -C "${ROOT_DIR}" fetch --quiet origin "${GIT_BRANCH}"
LOCAL_SHA="$(git -C "${ROOT_DIR}" rev-parse HEAD)"
REMOTE_SHA="$(git -C "${ROOT_DIR}" rev-parse "origin/${GIT_BRANCH}")"

if [ "${LOCAL_SHA}" != "${REMOTE_SHA}" ]; then
  echo "❌ HEAD local (${LOCAL_SHA:0:7}) ≠ origin/${GIT_BRANCH} (${REMOTE_SHA:0:7})."
  echo "   Poussez (git push) ou mettez à jour (git pull) votre branche avant de déployer."
  exit 1
fi

# --- Confirmation pour la production ---
if [ "${TARGET}" = "prod" ]; then
  echo ""
  echo "🚨 Déploiement en PRODUCTION sur ${SITE_URL} (commit ${LOCAL_SHA:0:7})"
  read -rp "Confirmer ? (y/N) " confirm
  if [ "${confirm}" != "y" ] && [ "${confirm}" != "Y" ]; then
    echo "Déploiement annulé."
    exit 0
  fi
fi

echo ""
echo "▶ Déploiement LG-IT App → ${TARGET}"
echo "  Host   : ${SSH_HOST}"
echo "  Path   : ${REMOTE_PATH}"
echo "  URL    : ${SITE_URL}"
echo "  Commit : ${LOCAL_SHA:0:7} ($(git -C "${ROOT_DIR}" log -1 --format=%s))"
echo "  API    : ${VITE_API_URL}"
echo ""

# --- 1. Build frontend (local) ---
echo "  1/4 — Build du frontend React (local)..."
(
  cd "${ROOT_DIR}/frontend"
  npm ci --no-audit --no-fund --loglevel=error
  # Une variable d'environnement déjà définie prime sur frontend/.env
  VITE_API_URL="${VITE_API_URL}" npm run build
)

if [ ! -f "${ROOT_DIR}/frontend/dist/index.html" ]; then
  echo "❌ Build frontend incomplet : frontend/dist/index.html absent."
  exit 1
fi

# --- 2. Backend (serveur) ---
echo "  2/4 — Mise à jour du backend sur le serveur..."
ssh "${SSH_TARGET}" bash -s <<EOF
  set -euo pipefail
  cd "${REMOTE_PATH}"

  if [ ! -d .git ]; then
    echo "        ❌ ${REMOTE_PATH} n'est pas un clone git."
    exit 1
  fi
  if ! ${PHP_BIN} -r 'exit(version_compare(PHP_VERSION, "8.2.0", "<") ? 1 : 0);'; then
    echo "        ❌ PHP CLI trop ancien (\$(${PHP_BIN} -r 'echo PHP_VERSION;')) — Laravel 11 exige 8.2+."
    echo "           Ajustez PHP_BIN (ex. php8.3) dans .env.deploy.${TARGET}."
    exit 1
  fi

  cd backend

  # Sans .env, l'app n'est pas encore installée : pas de migrations ni de
  # caches (ils figeraient une config vide), l'assistant /install s'en charge.
  FIRST_INSTALL=0
  [ -f .env ] || FIRST_INSTALL=1

  # Maintenance (échoue silencieusement au 1er déploiement, sans vendor/)
  if [ "\${FIRST_INSTALL}" = "0" ]; then
    ${PHP_BIN} artisan down --retry=60 2>/dev/null && echo "        Mode maintenance activé" || true
  fi

  echo "        git pull origin ${GIT_BRANCH}..."
  git fetch --quiet origin "${GIT_BRANCH}"
  git merge --ff-only --quiet "origin/${GIT_BRANCH}"
  DEPLOYED_SHA="\$(git rev-parse HEAD)"
  if [ "\${DEPLOYED_SHA}" != "${LOCAL_SHA}" ]; then
    echo "        ❌ Commit serveur (\${DEPLOYED_SHA:0:7}) ≠ commit attendu (${LOCAL_SHA:0:7})."
    exit 1
  fi

  echo "        composer install..."
  ${COMPOSER_CMD} install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet

  if [ "\${FIRST_INSTALL}" = "1" ]; then
    echo "        backend/.env absent : première installation, ouverture de l'assistant web..."
    ${PHP_BIN} artisan app:installer --url="${SITE_URL%/}" | sed 's/^/        /'
    exit 0
  fi

  echo "        Migrations..."
  ${PHP_BIN} artisan migrate --force

  echo "        Caches config/routes/vues..."
  ${PHP_BIN} artisan optimize:clear --quiet
  ${PHP_BIN} artisan config:cache --quiet
  ${PHP_BIN} artisan route:cache --quiet
  ${PHP_BIN} artisan view:cache --quiet
  ${PHP_BIN} artisan storage:link --quiet 2>/dev/null || true
EOF

# --- 3. Frontend (rsync dans backend/public) ---
echo "  3/4 — Envoi du build frontend dans backend/public/..."
# D'abord tout le build (nouveaux assets + index.html), sans rien supprimer :
# index.php, .htaccess, robots.txt… de Laravel restent en place.
rsync -az "${ROOT_DIR}/frontend/dist/" "${SSH_TARGET}:${REMOTE_PATH}/backend/public/"
# Puis purge des anciens assets hashés devenus inutiles.
rsync -az --delete "${ROOT_DIR}/frontend/dist/assets/" "${SSH_TARGET}:${REMOTE_PATH}/backend/public/assets/"

# --- 4. Remise en ligne + vérification ---
echo "  4/4 — Sortie du mode maintenance..."
ssh "${SSH_TARGET}" "cd '${REMOTE_PATH}/backend' && ${PHP_BIN} artisan up 2>/dev/null || true"

if ! ssh "${SSH_TARGET}" "test -f '${REMOTE_PATH}/backend/.env'"; then
  echo ""
  echo "🧩 Code déployé. Terminez l'installation dans le navigateur avec le lien affiché"
  echo "   à l'étape 2/4 (valable 24 h). Pour en générer un nouveau :"
  echo "   ssh ${SSH_TARGET} \"cd ${REMOTE_PATH}/backend && ${PHP_BIN} artisan app:installer --url=${SITE_URL%/}\""
  exit 0
fi

echo ""
HEALTH_CODE="$(curl -s -o /dev/null -w '%{http_code}' "${SITE_URL%/}/up" || echo '000')"
HOME_CODE="$(curl -s -o /dev/null -w '%{http_code}' "${SITE_URL%/}/" || echo '000')"
echo "  Laravel (/up) : HTTP ${HEALTH_CODE}"
echo "  Frontend (/)  : HTTP ${HOME_CODE}"

if [ "${HEALTH_CODE}" != "200" ] || [ "${HOME_CODE}" != "200" ]; then
  echo ""
  echo "⚠️  Déploiement terminé mais le site ne répond pas 200 — vérifiez :"
  echo "   ssh ${SSH_TARGET} tail -n 50 ${REMOTE_PATH}/backend/storage/logs/laravel.log"
  exit 1
fi

echo ""
echo "✅ Déploiement ${TARGET} terminé — ${SITE_URL} (commit ${LOCAL_SHA:0:7})"
