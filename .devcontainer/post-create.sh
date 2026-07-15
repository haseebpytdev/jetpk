#!/usr/bin/env bash
# Runs after the dev container / cloud agent workspace is created.
# Installs Composer & npm deps, Python Playwright + Chromium, and bootstraps Laravel for SQLite.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

export PATH="${HOME}/.local/bin:${PATH}"
export COMPOSER_ALLOW_SUPERUSER=1

echo "==> PHP / Composer"
php -v
composer --version
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "==> Node / npm"
node -v
npm -v
if [[ -f package-lock.json ]]; then
  npm ci
else
  npm install
fi

echo "==> Python Playwright + Chromium (--with-deps installs OS libraries)"
python3 --version
python3 -m pip install --user --upgrade pip wheel
python3 -m pip install --user "playwright>=1.42"
python3 -m playwright install --with-deps chromium

echo "==> Verify Playwright"
python3 -c "from playwright.sync_api import sync_playwright; print('playwright:', sync_playwright)"

echo "==> Laravel bootstrap (SQLite demo)"
if [[ ! -f .env ]]; then
  cp .env.example .env
fi
php artisan key:generate --force

if [[ -f database/demo.sqlite ]] && [[ ! -f database/database.sqlite ]]; then
  cp database/demo.sqlite database/database.sqlite
  echo "    Copied database/demo.sqlite -> database/database.sqlite"
fi

php artisan route:list 2>/dev/null | head -8 || true

echo ""
echo "==> Ready."
echo "    HTTP:  php artisan serve --host=0.0.0.0 --port=8000"
echo "    Vite:  npm run dev (port 5173)"
echo "    Test:  python3 -m playwright codegen http://127.0.0.1:8000"
