# OPS-DEF-0006 — Wallet / Ledger Atomicity Remediation

```
STATUS=FIX_READY_NOT_DEPLOYED
BRANCH=fix/ops-def-0006-wallet-ledger-atomicity
BASE_MAIN_SHA=d39171ff7c23c7b49cd05e1db9616081cacd8112
PRODUCTION_MUTATIONS=0
```

## ROOT_CAUSE

`AgentWalletService::approveDeposit()` committed wallet credit + deposit approval inside `DB::transaction`, then called `LedgerEventRecorder::recordAgencyDepositApproved()` **after** commit. That method used `safeRecord()`, which catches `Throwable`, logs, and returns `null`. Wallet could credit with a missing/failed ledger post.

## FIX_DESIGN

1. Canonical posting body: `recordAgencyDepositApprovedStrict()`.
2. Soft API preserved: `recordAgencyDepositApproved()` → `safeRecord` → strict helper.
3. Strict API: `recordAgencyDepositApprovedOrFail()` → `ensureAccountsExist()` + strict helper (throws).
4. `approveDeposit()` calls OrFail **inside** the same outer transaction after deposit/wallet mutations.
5. `notifyDepositEvent(AgentDepositApproved)` remains **after** successful commit only.

## TRANSACTION_BOUNDARY_BEFORE

```
DB::transaction:
  lock deposit + wallet
  credit wallet
  approve DepositRequest tx
  create DepositApproved tx
  mark deposit approved
COMMIT
recordAgencyDepositApproved (safeRecord — may fail silently)
notify AgentDepositApproved
```

## TRANSACTION_BOUNDARY_AFTER

```
DB::transaction:
  lock deposit + wallet
  credit wallet
  approve DepositRequest tx
  create DepositApproved tx
  mark deposit approved
  recordAgencyDepositApprovedOrFail  ← same connection; nested post() uses savepoint
COMMIT (or full rollback on throw)
notify AgentDepositApproved  ← only after commit
```

## External dependencies during ledger post

- Same application DB connection only.
- `LedgerPostingService::post()` wraps in `DB::transaction` (Laravel savepoints under outer txn).
- No payment gateway / supplier / mail inside ledger posting.

## Call-site audit

| Call | Path |
|------|------|
| `recordAgencyDepositApprovedOrFail` | `AgentWalletService::approveDeposit` only |
| `recordAgencyDepositApproved` (soft) | `LedgerLivePostingTest` idempotency re-call; any other soft callers unchanged |
| `approveDeposit` | `AgentDepositController@approve` + finance feature tests |

## FAILURE_INJECTION_TEST

`AgentWalletDepositTest::test_ledger_failure_rolls_back_wallet_deposit_and_blocks_notification`  
Mock OrFail throws → deposit stays `submitted`, wallet unchanged, request tx `pending`, no DepositApproved wallet tx, no ledger row, `OtaNotificationService::send` not called.

## SUCCESS_TEST

`AgentWalletDepositTest::test_approve_deposit_is_fully_atomic_with_ledger`  
Approved deposit + balance + wallet txs + posted balanced ledger.

## IDEMPOTENCY_TEST

`test_admin_approve_increases_wallet_balance_once` strengthened: after second approve attempt, still 1 DepositApproved wallet tx and 1 AgencyDepositApproved ledger tx.  
Also covered by `LedgerLivePostingTest::test_approving_same_deposit_twice_does_not_duplicate_ledger_transaction`.

## NOTIFICATION_ROLLBACK_TEST

Paired with failure injection (`shouldNotReceive('send')`).

## CONCURRENCY_REVIEW

`lockForUpdate()` on deposit + wallet + re-check `submitted` status → PASS for double-credit under concurrent approve. OPS-DEF-0007 (agency-wallet uniqueness schema) out of scope.

## Test results (local)

```
php vendor/bin/phpunit tests/Feature/AgentWalletDepositTest.php
→ passed: 13 tests, 57 assertions (re-run captured _ops-def-0006-phpunit.txt)

php vendor/bin/phpunit --filter="test_approving_deposit|test_approving_same_deposit|test_deposit_ledger" tests/Feature/Finance/LedgerLivePostingTest.php
→ passed: 3 tests, 6 assertions (re-run captured _ops-def-0006-ledger-live.txt)
```

Adjacent smoke (not OPS-DEF-0006 closure proof): ManualWalletAdjustment credit sample 2/2 PASS after recorder refactor.

## PRODUCTION_MUTATIONS

```
PRODUCTION_FINANCE_MUTATIONS=0
SUPPLIER_MUTATIONS=0
PAYMENT_GATEWAY_MUTATIONS=0
OPS_DEF_0020_TOUCHED=NO
FRONTEND_CHANGED=NO
```
