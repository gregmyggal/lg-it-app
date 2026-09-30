#!/usr/bin/env bash
# Lance les tests back-end sur la base MySQL dédiée (docker `db_test`).
# Usage : scripts/test-backend.sh [arguments de php artisan test]
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

cd backend
exec php artisan test "$@"
