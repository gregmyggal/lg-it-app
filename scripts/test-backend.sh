#!/usr/bin/env bash
# Lance les tests back-end dans le conteneur `app` (PHP 8.5) sur la base MySQL dédiée (docker `db_test`).
# Usage : scripts/test-backend.sh [arguments de php artisan test]
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose up -d db_test app >/dev/null
printf "Attente de db_test"
for _ in $(seq 1 40); do
  status=$(docker inspect -f '{{.State.Health.Status}}' lgitapp-db-test 2>/dev/null || echo starting)
  [ "$status" = "healthy" ] && break
  printf "."; sleep 2
done
echo
[ "$status" = "healthy" ] || { echo "db_test n'est pas prête (statut: $status)" >&2; exit 1; }

# Dans le réseau docker, db_test s'atteint sur le port interne 3306 (phpunit.xml vise 127.0.0.1:3309 côté hôte).
# TestCase.php refuse toute base qui ne finit pas par _test.
exec docker compose exec -T \
  -e DB_HOST=db_test -e DB_PORT=3306 -e DB_DATABASE=lgit_test \
  -e DB_USERNAME=lgit_test -e DB_PASSWORD=lgit_test \
  app php artisan test "$@"
