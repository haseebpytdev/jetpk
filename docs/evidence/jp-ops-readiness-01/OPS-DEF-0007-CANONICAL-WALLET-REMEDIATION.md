# OPS-DEF-0007 — Canonical Agency Wallet Remediation

**Status:** `FIX_READY_NOT_DEPLOYED`  
**Base main SHA:** `ce2c7cdedf1c338f0786b0c5cd3e564c349a324e`  
**Production runtime SHA (read-only probe):** `a13170988c3f2f7916ae1fb0caf3db6719a8df4f`

## ROOT_CAUSE

Application intended one operational wallet per agency, but DB only enforced `UNIQUE(agent_id)`. Multiple active rows per agency were possible; deposit approval credited `deposit.agent_wallet_id` without verifying current canonical identity.

## SCHEMA_INVARIANT

`canonical_for_agency` nullable tinyint:

- `1` = canonical operational wallet
- `NULL` = historical / non-canonical

`UNIQUE(agency_id, canonical_for_agency)` named `agent_wallets_agency_canonical_unique`.

## WHY_NOT_UNIQUE_AGENCY_ID

`UNIQUE(agency_id)` would forbid historical duplicate rows required for audit, deposit references, transactions, and reconciliation visibility.

## HISTORICAL_WALLET_POLICY

Many historical rows per agency allowed (`NULL` marker). Exactly zero or one marked canonical. No balance merge/rewrite into canonical.

## PRODUCTION_PREFLIGHT (READ-ONLY)

| Metric | Value |
|---|---|
| TOTAL_WALLETS | 5 |
| AGENCIES_WITH_WALLETS | 5 |
| DUPLICATE_AGENCY_WALLET_GROUPS | 0 |
| ACTIVE_DUPLICATE_AGENCY_WALLET_GROUPS | 0 |
| ARCHIVED_DUPLICATE_GROUPS | 0 |
| SUSPENDED_DUPLICATE_GROUPS | 0 |
| NONZERO_DUPLICATE_GROUPS | 0 |
| PENDING_DEPOSITS_ON_NONCANONICAL_WALLETS | 0 |
| ORPHAN_WALLETS | 0 |
| WALLETS_WITH_MISMATCHED_AGENCY_AGENT | 0 |
| HAS_CANONICAL_COL | false |
| PRODUCTION_SCHEMA_MUTATIONS | 0 |
| PRODUCTION_FINANCE_MUTATIONS | 0 |

## CANONICAL_MARKER_DESIGN

Migration adds column → refuses if any `agency_id` has `COUNT(*) > 1` → backfills sole wallets to `1` → adds unique index. `down()` drops index then column. No wallet deletes.

## CREATE_RACE_PROTECTION

`getOrCreateCanonicalWalletForAgency`: DB transaction + `Agency::lockForUpdate` + recheck + create/reactivate with marker=1 + catch unique violation and refetch.

## NONCANONICAL_DEPOSIT_APPROVAL_GUARD

`approveDeposit` resolves current canonical for `deposit.agency_id` and requires `deposit.agent_wallet_id === canonical.id` and marker=1; otherwise `NonCanonicalWalletDepositException` (fail closed). No silent redirect/credit.

## ARCHIVE_COMPATIBILITY

`DuplicateWalletArchiveService` blocks `canonical_for_agency=1` and resolver-canonical wallet; archive clears marker to NULL defensively.

## REPORTING_COMPATIBILITY

`agencyWalletSummary` / ledger balance compare continue to **sum all** wallet rows per agency.

## TEST_RESULTS

Targeted suite (local SQLite): **121 passed / 0 failed / 358 assertions** including OPS-DEF-0007 invariant tests + OPS-DEF-0006 deposit atomicity suite (`AgentWalletDepositTest`).

## PRODUCTION_MUTATIONS

`PRODUCTION_MUTATIONS=0` in this loop. Deploy/migrate only after PR review.

## NEXT

OPS-DEF-0007 production schema/deploy closure (backup → migrate refuse=0 → deploy → verify markers + unique index).
