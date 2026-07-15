# Cloud agent / Codespaces environment

This repo includes a **Dev Container** (`.devcontainer/`) so GitHub Codespaces, VS Code Dev Containers, and **Cursor Cloud Agents** that honor Dev Containers get:

| Tool | Purpose |
|------|---------|
| PHP 8.3 CLI | Laravel (`composer.json` requires ^8.3) |
| Composer | PHP dependencies |
| Node.js 22 + npm | Vite / frontend toolchain |
| Python 3.12 + Playwright + Chromium | Browser automation, `playwright codegen`, E2E |

## First-time container build

After the container is created, `.devcontainer/post-create.sh` runs automatically and:

1. Runs `composer install`
2. Runs `npm ci` (or `npm install`)
3. Installs Playwright for Python and downloads **Chromium** with system deps (`--with-deps`)
4. Copies `.env.example` → `.env`, runs `php artisan key:generate`
5. Copies `database/demo.sqlite` → `database/database.sqlite` when present

## Manual commands

```bash
php artisan serve --host=0.0.0.0 --port=8000
npm run dev
python3 -m playwright codegen http://127.0.0.1:8000
```

## Re-run setup only

```bash
bash .devcontainer/post-create.sh
```

## Secrets

Do not put API tokens in the devcontainer. Use repository **Secrets** or local `.env` (gitignored).
