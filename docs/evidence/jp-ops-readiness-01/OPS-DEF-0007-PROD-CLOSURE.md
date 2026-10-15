# OPS-DEF-0007 — Production Closure

```
PROTECTED_MAIN_SHA=7fe1cd8088ff15d4e27bbc512521dc1055e7a12c
PRE_DEPLOY_RUNTIME_SHA=a13170988c3f2f7916ae1fb0caf3db6719a8df4f
POST_DEPLOY_RUNTIME_SHA=7fe1cd8088ff15d4e27bbc512521dc1055e7a12c
MERGED_PR=#79
PR_HEAD_SHA=d09acf1051ff671141f3523705eb1f45f2d440d7
DEPLOY_MODE=SCOPED_RUNTIME_FILES + PATH_SCOPED_MIGRATION
OPS_DEF_0007_STATUS=CLOSED
CLOSED_AT_UTC=2026-10-15T15:45:00Z
```

## PRE_MIGRATION_COUNTS

| Metric | Value |
|---|---|
| TOTAL_WALLETS | 5 |
| AGENCIES_WITH_WALLETS | 5 |
| DUPLICATE_AGENCY_WALLET_GROUPS | 0 |
| ACTIVE_DUPLICATE_AGENCY_WALLET_GROUPS | 0 |
| NONZERO_DUPLICATE_AGENCY_WALLET_GROUPS | 0 |
| PENDING_DEPOSITS_ON_DUPLICATE_WALLETS | 0 |
| ORPHAN_WALLETS | 0 |
| WALLET_AGENT_AGENCY_MISMATCHES | 0 |
| HAS_CANONICAL_COL | false |

`FAILED_JOBS_PRE=1` = residual QA SMTP bounce on `notifications-transactional` to `jp-final-11-qa-legacy-agency-admin*` (unrelated to wallet/finance). HTTP + queues healthy.

## PRE_MIGRATION_BACKUP

```
BACKUP_DIR=/home/pkjetp/backups/ops-def-0007-pre-mig-20261015T153757Z
APP_ARCHIVE=jetpk_app-runtime-20261015T153757Z.tar.gz
DB_DUMP=jetpk-db-20261015T153757Z.sql.gz
ARCHIVE_RUNTIME_SHA=a13170988c3f2f7916ae1fb0caf3db6719a8df4f
APP_SHA256=e49b9fa61c997e19097be93c96644ce237a5b048c0e3ef9f4a85c6866ca7ab5a
DB_SHA256=c5fd680c156fc30e0e01ff3de75d3e40628707ae68bbf2fe200a4702c734a65a
PRE_MIGRATION_APP_BACKUP=PASS
PRE_MIGRATION_DB_BACKUP=PASS
PRE_MIGRATION_BACKUP_INTEGRITY=PASS
```

Pinned prior checkpoint `20261014T215304Z` untouched.

## DB

```
DB_ENGINE=mariadb.org binary distribution
DB_VERSION=10.11.18-MariaDB-ubu2404
MARIADB_NULL_UNIQUE_COMPAT=YES
```

## FILES_DEPLOYED

Exact merged SHA `7fe1cd80` bytes (git archive LF):

| Path | Notes |
|---|---|
| `app/Exceptions/Finance/NonCanonicalWalletDepositException.php` | new |
| `app/Models/AgentWallet.php` | |
| `app/Services/Agents/AgentWalletService.php` | |
| `app/Services/Finance/Wallets/DuplicateWalletArchiveService.php` | |
| `app/Support/Finance/OtaFinanceDemoScenario.php` | |
| migration file | installed under `storage/app/ops-def-0007-migrations/` because `database/migrations` is root-owned |

```
UNEXPECTED_RUNTIME_FILES=0
FRONTEND_FILES_CHANGED=0
RUNTIME_FILE_PARITY=PASS
MIGRATION_FILE_PARITY=PASS
PHP_SYNTAX=PASS
```

## MIGRATION

```
COMMAND=php artisan migrate --path=storage/app/ops-def-0007-migrations/2026_10_15_120000_add_canonical_for_agency_to_agent_wallets.php --force
PHP_BIN=/usr/local/lsws/lsphp83/bin/php
UNRELATED_PENDING_MIGRATIONS=0
IMMEDIATE_PREFLIGHT_DUPLICATES=0
MIGRATION_EXIT=0
MIGRATION_BATCH=24
MIGRATION_NAME=2026_10_15_120000_add_canonical_for_agency_to_agent_wallets
```

## SCHEMA_POST / DATA_POST

```
CANONICAL_COLUMN_PRESENT=YES (tinyint(3) unsigned NULL DEFAULT NULL)
CANONICAL_UNIQUE_INDEX_PRESENT=YES (agent_wallets_agency_canonical_unique on agency_id,canonical_for_agency)
AGENT_ID_UNIQUE_INDEX_PRESERVED=YES
TOTAL_WALLETS_POST=5
CANONICAL_WALLETS_POST=5
HISTORICAL_NULL_WALLETS_POST=0
AGENCIES_WITHOUT_CANONICAL_POST=0
AGENCIES_WITH_MULTIPLE_CANONICAL_POST=0
```

## CONSTRAINT_PROOF

Rolled-back production probe (no committed mutations):

- Moved wallet#2 onto agency#1 with `canonical_for_agency=NULL` → allowed
- Attempted `UPDATE ... SET canonical_for_agency=1` on that row → `1062 Duplicate entry '1-1' for key agent_wallets_agency_canonical_unique`
- `ROLLBACK` → counts unchanged (`wallets=5`, `canon=5`, `nulls=0`)

```
SECOND_CANONICAL_DB_BLOCKED=PASS
MULTIPLE_HISTORICAL_NULL_ROWS_ALLOWED=PASS
CONSTRAINT_PROBE_ROLLED_BACK=YES
FINANCE_ROWS_CHANGED_POST_PROBE=0
```

## OPS_DEF_0006_REGRESSION

Deployed `AgentWalletService` still contains `recordAgencyDepositApprovedOrFail` inside `approveDeposit` transaction.

```
OPS_DEF_0006_RUNTIME_INVARIANT=PASS
NONCANONICAL_DEPOSIT_GUARD_DEPLOYED=YES
```

No live deposit approvals performed.

## POST_HEALTH

| Gate | Value |
|---|---|
| RUNTIME_SHA | 7fe1cd80… |
| BUILD_ID | 7-iZf4RbaJmGpuDjO1tsh |
| FAILED_JOBS | 1 (pre-existing QA SMTP residual) |
| PENDING_DEFAULT | 0 |
| DEFAULT_WORKER | NO |
| /up / /login /admin /agent | 200/200/200/302/200 |
| FRONTEND_REGRESSION | NO |
| FINANCE_READ_SURFACES | PASS |

## MUTATION ACCOUNTING

```
PRODUCTION_WALLET_BALANCE_MUTATIONS=0
PRODUCTION_DEPOSIT_APPROVALS=0
PRODUCTION_LEDGER_MUTATIONS=0
SUPPLIER_MUTATIONS=0
PAYMENT_GATEWAY_MUTATIONS=0
HISTORICAL_NOTIFICATION_REPLAY=0
OPS_DEF_0020_TOUCHED=NO
```

Only intended migration DDL/DML (column + sole-wallet backfill to `1` + unique index) mutated schema/data.

## NEXT

`NEXT_PRIORITY=OPS-DEF-0013` (payment DB-level idempotency). Finance domain remains non-GREEN overall while 0013 is open.
