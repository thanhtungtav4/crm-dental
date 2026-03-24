#!/usr/bin/env bash

set -euo pipefail

DEPLOY_HOST="${DEPLOY_HOST:-nttung.tail83a115.ts.net}"
DEPLOY_PORT="${DEPLOY_PORT:-22}"
DEPLOY_USER="${DEPLOY_USER:-root}"
DEPLOY_PATH="${DEPLOY_PATH:-/srv/www/laravel/current}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"

REMOTE_TARGET="${DEPLOY_USER}@${DEPLOY_HOST}"
SSH_OPTS=(
  -p "${DEPLOY_PORT}"
  -o StrictHostKeyChecking=accept-new
)

RSYNC_EXCLUDES=(
  --exclude ".git/"
  --exclude ".github/"
  --exclude ".agents/"
  --exclude ".cursor/"
  --exclude ".env"
  --exclude "bootstrap/cache/"
  --exclude "node_modules/"
  --exclude "output/"
  --exclude "storage/"
  --exclude "tests/"
  --exclude "vendor/"
)

echo "Deploying to ${REMOTE_TARGET}:${DEPLOY_PATH}"

rsync -az --delete \
  -e "ssh ${SSH_OPTS[*]}" \
  "${RSYNC_EXCLUDES[@]}" \
  ./ "${REMOTE_TARGET}:${DEPLOY_PATH}/"

ssh "${SSH_OPTS[@]}" "${REMOTE_TARGET}" "DEPLOY_PATH='${DEPLOY_PATH}' PHP_FPM_SERVICE='${PHP_FPM_SERVICE}' bash -s" <<'SH'
set -euo pipefail

cd "${DEPLOY_PATH}"

mkdir -p bootstrap/cache \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs

chgrp -R www-data bootstrap/cache storage || true
chmod -R ug+rwX bootstrap/cache storage || true

export HOME="${HOME:-/root}"
export COMPOSER_HOME="${COMPOSER_HOME:-${HOME}/.composer}"
export COMPOSER_ALLOW_SUPERUSER=1

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force --no-interaction
php artisan db:seed --class=RolesAndPermissionsSeeder --force --no-interaction
php artisan permission:cache-reset --no-interaction || true
php artisan optimize:clear --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan queue:restart --no-interaction || true

systemctl reload "${PHP_FPM_SERVICE}" || systemctl reload php-fpm || true
SH
