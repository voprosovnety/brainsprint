#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(dirname "$0")/.."
mkdir -p .tools
if [ ! -f .tools/composer ]; then
  curl -fsSL https://getcomposer.org/installer -o .tools/composer-setup.php
  expected="$(curl -fsSL https://composer.github.io/installer.sig)"
  actual="$(php -r "echo hash_file('sha384', '.tools/composer-setup.php');")"
  [ "$actual" = "$expected" ] || { echo 'Composer installer checksum mismatch'; exit 1; }
  php .tools/composer-setup.php --install-dir=.tools --filename=composer --quiet
  rm .tools/composer-setup.php
fi
php .tools/composer install --working-dir=apps/api --no-interaction --prefer-dist --quiet
npm ci --prefix apps/web --no-fund --no-audit
if [ ! -f apps/api/.env ]; then
  cp apps/api/.env.example apps/api/.env
  php -r '$p="apps/api/.env"; $s=file_get_contents($p); file_put_contents($p, str_replace(["replace_with_local_username", "replace_with_generated_password"], ["brainsprint", bin2hex(random_bytes(24))], $s));'
  (cd apps/api && php artisan key:generate --quiet)
fi
[ -f apps/web/.env.local ] || cp apps/web/.env.example apps/web/.env.local
chmod 600 apps/api/.env apps/web/.env.local
docker compose --env-file apps/api/.env -f infra/compose.yaml up -d --wait
(cd apps/api && php artisan migrate --force && php artisan db:seed --force)
