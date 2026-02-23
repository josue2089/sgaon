#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT_DIR}"

if [[ ! -f .env ]]; then
  echo "Missing .env. Copy .env.production.example first." >&2
  exit 1
fi

php artisan down || true

composer install --no-dev --optimize-autoloader

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan migrate --force
php artisan optimize

if command -v npm >/dev/null 2>&1; then
  npm ci
  npm run build
fi

php artisan up

echo "Predeploy completed."
echo "If using Supervisor, restart workers: supervisorctl restart sgaon-worker:*"
