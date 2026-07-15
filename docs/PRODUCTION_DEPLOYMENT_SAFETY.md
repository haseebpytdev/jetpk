# Production Deployment Safety (OTA)

Stable project standard for live Laravel OTA changes. **Read and obey this document before modifying files.**

Stability is more important than speed. This codebase runs on a live Hostinger production server synced via SFTP.

---

## Mandatory pre-task checklist

Before making changes:

1. Search for every referenced class before adding or using it.
2. Confirm namespace imports are correct (see **No blind namespace** below).
3. Do not create duplicate classes if a real class already exists.
4. Keep the change scoped — smallest safe fix only.
5. Run or provide syntax checks on touched PHP files.
6. Run or provide route/view/cache verification after deploy.
7. Wrap non-critical email/API/notification code in `try-catch (\Throwable)` — log warning, do not throw.
8. After implementation, list touched files and exact verification commands with expected results.

Also obey `.cursor/rules/sftp-live-server-rules.mdc` for upload and SSH cache clears.

---

## Strongest rule: no blind namespace

**Cursor must never use an unqualified class name in a namespaced PHP file unless:**

1. The class exists in the **same namespace** as the file using it, **or**
2. The correct `use ...;` import is present at the top of the file, **or**
3. The class is referenced with a **fully-qualified** name (leading `\`).

### Before every class reference

1. **Search** the project for the real class file (`grep`, IDE search, or `Glob`).
2. **Open** that file and read the `namespace` declaration.
3. **Add** the exact `use Vendor\Package\ClassName;` import — or use the FQCN inline.
4. **Never** assume Laravel will resolve a short name from another namespace.
5. **Never** create a duplicate stub/fallback class if the real class exists elsewhere.

This single rule prevents production 500s from missing imports (e.g. login/auth controllers).

### Constructor dependency injection

Every type-hinted constructor parameter must be resolvable by Laravel's container. Verify touched controllers via `php artisan route:list` or targeted tests before calling deploy complete.

---

## Defensive coding rules

### Non-critical paths must not crash critical pages

Login, register, checkout, booking detail, dashboard, and admin pages must still render if email, notifications, Sabre, Duffel, payment, or file-generation logic fails.

```php
try {
    $this->notificationService->send(...);
} catch (\Throwable $e) {
    report($e); // or Log::warning with context — never expose secrets
}
```

### Blade templates

- Use `?->` for nullable relationships.
- Use `data_get()` for nested arrays/JSON.
- Use `@forelse($items ?? [] as $item)` for lists that may be empty.
- Never assume a relation, array key, or metadata path exists.

### Controllers and services

- Wrap external supplier/mail/payment calls in `try-catch` when the page must still render.
- Return safe fallback values to the view (empty collection, null coalescing defaults).

### Incident fixes

- No broad refactors during production incidents.
- Fix the smallest root cause first, verify, then optional cleanup in a separate pass.

---

## Best practical workflow (every change)

Use this order:

| Step | Action | Where |
|------|--------|-------|
| 1 | Cursor changes files locally | Local |
| 2 | Cursor lists touched files | Chat / report |
| 3 | Run local syntax/import checks if possible | Local |
| 4 | Sync files to live (single-file SFTP per server rules) | SFTP |
| 5 | `composer dump-autoload` | Server SSH |
| 6 | `php artisan optimize:clear` | Server SSH |
| 7 | `php artisan route:list` | Server SSH |
| 8 | `php artisan view:cache` — **only if Blade/views changed** | Server SSH |
| 9 | Open the affected URL in a browser | Browser |
| 10 | Immediately tail `storage/logs/laravel.log` | Server SSH |

**No live deployment is complete until all applicable steps pass.**

---

## Commands reference

### Local (step 3 — before upload)

```bash
php -l path/to/touched-file.php
```

Repeat `php -l` for each touched PHP file. Optionally:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan route:list
```

### Server SSH (steps 5–8 — after upload)

```bash
php -l path/to/touched-file.php
composer dump-autoload
php artisan optimize:clear
php artisan route:list
```

If Blade/views changed:

```bash
php artisan view:clear
php artisan view:cache
```

Run `view:clear` before `view:cache` when replacing compiled views on live.

### Post-deploy (steps 9–10)

- Browser: load the affected URL; confirm no 500 / white screen; exercise the fixed behavior.
- Log:

```bash
tail -n 80 storage/logs/laravel.log
```

Look for fresh `ERROR` / `CRITICAL` entries tied to the URL you just tested. None = pass.

---

## Expected results

| Check | Pass criteria |
|-------|----------------|
| `php -l` | No syntax errors |
| `composer dump-autoload` | Completes without fatal errors |
| `php artisan optimize:clear` | All caches cleared successfully |
| `php artisan route:list` | Full route table loads without exception |
| `php artisan view:cache` | Completes without exception (Blade changes only) |
| Browser test | Page renders; fix behaves as expected |
| `laravel.log` tail | No new errors after the browser test |

---

## Agent completion report (required)

Every implementation must end with:

1. **Files changed** (local)
2. **Files to upload** (exact paths for SFTP)
3. **Commands to run after upload** (from this doc, steps 5–10 as applicable)
4. **Test steps** (affected URL(s))
5. **Rollback instructions** (revert which files)

---

## Related docs

- `.cursor/rules/laravel-production-safety.mdc` — Cursor always-on rule (points here)
- `.cursor/rules/sftp-live-server-rules.mdc` — SFTP upload and cache-clear policy
- `docs/deployment.md` — first-time server setup and infrastructure
