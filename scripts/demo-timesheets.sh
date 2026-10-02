#!/usr/bin/env bash
# Prépare une base de DÉMONSTRATION jetable (`lgit_demo`, sur le MySQL jetable db_test) avec un jeu de données de
# validation mensuelle des timesheets, pour vérifier les écrans dans le navigateur. Ne touche jamais la base de dev.
# Usage : scripts/demo-timesheets.sh   puis démarrer « demo-backend » et « demo-frontend » (.claude/launch.json).
# Comptes (mot de passe « password ») : admin@test.com, directeur@test.com, alice@test.com (professeur), etc.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose up -d db_test >/dev/null
printf "Attente de db_test"
for _ in $(seq 1 40); do
  status=$(docker inspect -f '{{.State.Health.Status}}' lgitapp-db-test 2>/dev/null || echo starting)
  [ "$status" = "healthy" ] && break
  printf "."; sleep 2
done
echo
[ "$status" = "healthy" ] || { echo "db_test n'est pas prête (statut: $status)" >&2; exit 1; }

docker exec lgitapp-db-test mysql -uroot -plgit_test_root -e \
  "DROP DATABASE IF EXISTS lgit_demo; CREATE DATABASE lgit_demo CHARACTER SET utf8mb4; GRANT ALL ON lgit_demo.* TO 'lgit_test'@'%';" 2>/dev/null

cd backend
export APP_ENV=local DB_HOST=127.0.0.1 DB_PORT=3309 DB_DATABASE=lgit_demo DB_USERNAME=lgit_test DB_PASSWORD=lgit_test \
  MAIL_MAILER=log CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan db:seed --class=TimesheetDemoSeeder --force --no-interaction
echo "Base lgit_demo prête."
