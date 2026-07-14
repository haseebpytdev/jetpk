# Production cron, queue, SMTP, and notifications

Use this checklist when preparing a client deployment preview or production cutover.

## Mail (`.env`)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-user
MAIL_PASSWORD=your-secret
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=bookings@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

- Per-agency SMTP can override defaults in **Admin → Communication settings** (password is stored encrypted; never log or echo it).
- Keep `MAIL_MAILER=log` only on local machines without SMTP.

## Queue (`.env`)

```env
QUEUE_CONNECTION=database
```

Requires migrated `jobs` and `failed_jobs` tables (`php artisan migrate --force`).

### VPS — Supervisor (recommended)

```ini
[program:ota-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ota/artisan queue:work --tries=3 --timeout=90 --sleep=3
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/ota/storage/logs/queue-worker.log
stopwaitsecs=3600
```

After each deploy: `php artisan queue:restart`

### Shared hosting — cron worker (no Supervisor)

Run a short worker every minute so mail and other queued jobs drain:

```cron
* * * * * cd /path/to/app && php artisan queue:work --stop-when-empty --tries=3 --timeout=90 >> storage/logs/queue-worker.log 2>&1
```

## Scheduler (required)

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled commands (see `routes/console.php`):

| Command | Schedule |
|---------|----------|
| `ota:cleanup-expired-access` | hourly |
| `ota:send-daily-report` | daily 08:00 |
| `ota:send-weekly-report` | Monday 08:00 |
| `ota:send-monthly-report` | 1st of month 08:00 |
| `ota:send-monthly-ledgers` | 1st of month 09:00 |
| `homepage:refresh-featured-fares` | daily 05:00 |

## Login security notifications (optional)

```env
NOTIFY_AGENT_LOGIN=false
NOTIFY_STAFF_LOGIN=true
NOTIFY_ADMIN_LOGIN=true
NOTIFY_FAILED_ADMIN_LOGIN=true
```

Privileged logins use `OtaNotificationService` (admin scope). Customers are not emailed on login.

## Verify after deploy

```bash
php artisan migrate --force
php artisan config:clear
php artisan optimize
php artisan ota:test-email --to=you@example.com
php artisan queue:work --stop-when-empty --tries=3
```

Check delivery in **Admin → Communication → Delivery log**.

### Failed jobs

```bash
php artisan queue:failed
php artisan queue:retry all
```

## Troubleshooting

| Symptom | Check |
|---------|--------|
| No email | `MAIL_*`, agency **email enabled**, per-event notification toggle, valid recipients |
| Jobs stuck | `QUEUE_CONNECTION`, cron/Supervisor worker running, `storage/logs/queue-worker.log` |
| Reports missing | `schedule:run` cron, report toggles in communication settings |
| SMTP auth errors | Host/port/TLS; password not exposed in logs (redacted automatically) |

## Rollback / safety

- Set `MAIL_MAILER=log` to stop outbound mail without code changes.
- Set `QUEUE_CONNECTION=sync` only for short debugging (blocks HTTP requests while sending).
- Disable noisy events in **Notification events** admin UI instead of removing cron.
- Do not commit `.env` or paste SMTP passwords into tickets.

See also: [deployment.md](deployment.md), [releases/phase-23c-notification-management.md](releases/phase-23c-notification-management.md).
