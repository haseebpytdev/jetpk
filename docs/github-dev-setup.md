# GitHub / Cursor Agent demo setup

For **preinstalled PHP, Composer, Node, npm, and Python Playwright + Chromium** in cloud agents / Codespaces, see **`docs/cloud-agent-devcontainer.md`** and `.devcontainer/`.

## Database snapshot

This repo includes **`database/demo.sqlite`**: a fresh **`php artisan migrate:fresh --seed`** snapshot (demo users, agencies, and **reference airports/airlines** for autocomplete). No API secrets are stored in the database rows.

**Local use**

```bash
cp database/demo.sqlite database/database.sqlite
# or on Windows PowerShell:
Copy-Item database\demo.sqlite database\database.sqlite -Force
php artisan key:generate
php artisan serve
```

Default login accounts from `OtaFoundationSeeder` (password is typically set in seeder docs / same as local demo).

## Duffel test token — do not commit

**Never commit** `.env`, raw tokens, or `credentials` JSON containing keys.

For **private** GitHub repos and Cursor Cloud Agents:

1. Add **`DUFFEL_ACCESS_TOKEN`** (or your app’s env names from `.env.example`) as **GitHub Actions secrets** or **GitHub Codespaces secrets**.
2. Or paste the token only in **Admin → API settings / Supplier connections** after deploy (stored encrypted in DB).

Public repositories must not hold tokens in git history; rotate any token that was ever exposed.

## Optional: regenerate DB from scratch

```bash
php artisan migrate:fresh --seed
cp database/database.sqlite database/demo.sqlite   # refresh the committed snapshot
```
