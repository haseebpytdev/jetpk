# OPS-DEF-0006 — Production Closure

```
MERGED_SHA=a13170988c3f2f7916ae1fb0caf3db6719a8df4f
PR=#77
PR_HEAD=6eb74209856f09f0ed85d473d925fb765f2fc8a1
PRE_DEPLOY_RUNTIME_SHA=a138eb570b05910046df7fb251beeef839b25395
DEPLOYED_RUNTIME_SHA=a13170988c3f2f7916ae1fb0caf3db6719a8df4f
OPS_DEF_0006_STATUS=CLOSED
CLOSED_AT_UTC=2026-10-15T01:07:11Z
```

## FILES_DEPLOYED

| Path | SHA256 (deployed = main a1317098) |
|------|-----------------------------------|
| `app/Services/Agents/AgentWalletService.php` | `c7914912c74cb66066106bfe62a95a955a035652cd44b4fb451efafb25cf1b75` |
| `app/Services/Finance/Ledger/LedgerEventRecorder.php` | `e5645085dda281c8bdf5b8a23d4784ff11974fa4ebedfdcc07c716993380ef1e` |

```
AGENT_WALLET_SERVICE_SHA_MATCH=YES
LEDGER_EVENT_RECORDER_SHA_MATCH=YES
UNEXPECTED_RUNTIME_FILES=0
FRONTEND_FILES_CHANGED=0
MIGRATIONS_CHANGED=0
```

Runtime-diff `a138eb57…` → `a1317098…` under `app/`: only the two files above. Remaining delta is docs/tests/scripts/summary only.

## Deployment method

1. Pre-deploy health (SSH + mysql client + curl)
2. Scoped backup: `/home/pkjetp/backups/ops-def-0006-scoped-20261015T010711Z/`
3. SCP exact `git archive` bytes from `a1317098`
4. SHA256 parity gate (mismatch would restore backup)
5. `lsphp83 php -l` both files
6. `php artisan optimize:clear`
7. Bootstrap + ReflectionMethod presence
8. Markers: `.jetpk-runtime-sha` + `storage/app/deploy-sha.txt` → `a1317098…`

No migrations, npm, frontend/dashboard rebuild, `.env` changes, or finance mutations.

## Behavioral proof (source + pre-merge tests)

```
STRICT_METHOD_PRESENT=YES
STRICT_METHOD_INSIDE_APPROVAL_TX=YES
NOTIFICATION_AFTER_TX=YES
```

`recordAgencyDepositApprovedOrFail` inside `approveDeposit` `DB::transaction`; `notifyDepositEvent(AgentDepositApproved)` after commit.

Pre-merge automated evidence (PR #77):

- `AgentWalletDepositTest`: 13 tests / 57 assertions PASS
- LedgerLivePosting deposit filters: 3 tests / 6 assertions PASS  
  (failure-injection rollback + notification block + success atomicity + idempotency)

Production: **no** `approveDeposit`, wallet credit/debit, or ledger insert executed.

## Health

| Gate | Pre | Post |
|------|-----|------|
| RUNTIME_SHA | a138eb57… | a1317098… |
| BUILD_ID | 7-iZf4RbaJmGpuDjO1tsh | 7-iZf4RbaJmGpuDjO1tsh |
| FAILED_JOBS | 0 | 0 |
| PENDING_DEFAULT | 0 | 0 |
| WORKERS | NONE | NONE |
| DISK | 46% | 46% |
| /up / /login /admin /agent | 200/200/200/302/200 | 200/200/200/302/200 |

```
FRONTEND_REGRESSION=NO
ROLLBACK_CHECKPOINT_PRESENT=YES
ROLLBACK_APP=/home/pkjetp/backups/jetpk_app-20261014T215304Z.tar.gz
ROLLBACK_DB=/home/pkjetp/backups/jetpk-db-20261014T215304Z.sql.gz
SCOPED_FILE_BACKUP=/home/pkjetp/backups/ops-def-0006-scoped-20261015T010711Z/
```

Read-only counts post-deploy: `DEPOSIT_SUBMITTED=0`, `WALLETS=5` (no mutations).

## Closure note

Closure is based on **deployed code parity** + **automated transaction rollback tests**.  
No live customer-money mutation was performed.

```
PRODUCTION_FINANCE_MUTATIONS=0
SUPPLIER_MUTATIONS=0
PAYMENT_GATEWAY_MUTATIONS=0
HISTORICAL_NOTIFICATION_REPLAY=0
OPS_DEF_0020_TOUCHED=NO
```

## Remaining finance risk

`OPS-DEF-0007` (agency-wallet uniqueness architecture) remains **OPEN**.  
`OPS-RISK-0003` stays partially open pending 0007.
