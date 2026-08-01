# JP-OPS-01 Background Operations Matrix

**Phase:** JP-OPS-01 | **SHA:** `cfd65a76b448ec7fb77fddfb4995f290b5d841b3`

**Note:** No production server inspection performed.

## Scheduler (`routes/console.php`)

| Command | Schedule | Classification |
|---------|----------|----------------|
| `ota:cleanup-expired-access` | hourly | CODE_READY_RUNTIME_UNVERIFIED |
| `abandoned-flight-search:process` | every 15m | CODE_READY_RUNTIME_UNVERIFIED |
| `abandoned-flight-search:send` | every 15m | CODE_READY_RUNTIME_UNVERIFIED |
| `group-ticketing:release-expired` | every minute | CODE_READY_RUNTIME_UNVERIFIED |
| Report generation (daily/weekly/monthly) | daily+ | CODE_READY_RUNTIME_UNVERIFIED |
| `homepage:sync-featured-fares` | daily | CODE_READY_RUNTIME_UNVERIFIED |
| Agency booking summary | daily | CODE_READY_RUNTIME_UNVERIFIED |
| Group inventory sync | daily | CODE_READY_RUNTIME_UNVERIFIED |

**Requirement:** `* * * * * php artisan schedule:run` on server — **REQUIRES_SERVER_CONFIGURATION**

## Queues

| Component | Config | Classification |
|-----------|--------|----------------|
| Queue driver | `config/queue.php` (.env) | REQUIRES_SERVER_CONFIGURATION |
| Failed jobs table | migrations present | CODE_READY_RUNTIME_UNVERIFIED |
| Supplier async jobs | booking/ticketing dispatch | CODE_READY_RUNTIME_UNVERIFIED |
| Payment callback processing | synchronous + queue optional | OPERATIONAL_LOCALLY |

## Email

| Type | Implementation | Classification |
|------|----------------|----------------|
| Transactional mailables | `app/Mail/*` | CODE_READY_RUNTIME_UNVERIFIED |
| OTP email | auth flow (demo patch) | OPERATIONAL_LOCALLY |
| Invoice email | post-payment jobs | CODE_READY_RUNTIME_UNVERIFIED |
| Mail driver | .env MAIL_* | REQUIRES_SERVER_CONFIGURATION |

## Notifications

| Channel | Customer | Agent | Classification |
|---------|----------|-------|----------------|
| In-app | `/customer/notifications` | `/agent/notifications` | OPERATIONAL_CONNECTED |
| Email | Laravel notifications | Laravel notifications | EXTERNAL_DEPENDENCY_BLOCKED (mail config) |

## Storage

| Disk | Purpose | Public/Private | Classification |
|------|---------|----------------|----------------|
| local | uploads, proofs | private | CODE_READY_RUNTIME_UNVERIFIED |
| public | airline logos, CMS media | public | OPERATIONAL_LOCALLY |
| s3 (optional) | production media | configurable | REQUIRES_SERVER_CONFIGURATION |

## Webhooks / callbacks

| Endpoint | Handler | Retry | Classification |
|----------|---------|-------|----------------|
| `payments/abhipay/callback` | AbhiPayPaymentController | throttle | OPERATIONAL_CONNECTED |
| OAuth callbacks | `auth/{provider}/callback` | — | OPERATIONAL_CONNECTED |

## Health checks

| Endpoint | Purpose | Classification |
|----------|---------|----------------|
| `GET /up` | Laravel health | OPERATIONAL_LOCALLY |

## Audit logging

| System | Location | Classification |
|--------|----------|----------------|
| Audit events | DB + `api/dashboard/audit` | OPERATIONAL_CONNECTED (read) |
| Wallet audit | `WalletAuditPolicy` | OPERATIONAL_CONNECTED |

## Log channels

- `config/logging.php` — stack, daily, production.ERROR monitoring required on deploy
- No production log inspection in this phase

## Cleanup

| Item | Mechanism | Classification |
|------|-----------|----------------|
| Expired access tokens | `ota:cleanup-expired-access` | CODE_READY_RUNTIME_UNVERIFIED |
| Temp uploads | application cleanup jobs | DEFERRED verification |
| Expired group holds | `group-ticketing:release-expired` | CODE_READY_RUNTIME_UNVERIFIED |
