# WALLET MUTATION INVENTORY — OPS-DEF-0007

**Probed against:** `ce2c7cdedf1c338f0786b0c5cd3e564c349a324e`  
**Mode:** read-only inventory (no production mutation)

| PATH | METHOD | MUTATION_TYPE | CANONICAL_RESOLUTION | LOCKING | IDEMPOTENCY | RISK |
|---|---|---|---|---|---|---|
| `AgentWalletService::getOrCreateCanonicalWalletForAgency` | create/update status+marker | CREATE / REACTIVATE | agency lock + marker=1 | `Agency::lockForUpdate` + txn | unique(agency,canonical) + refetch | P1 race without lock (hardened) |
| `AgentWalletService::walletFor` | delegates getOrCreate | CREATE (indirect) | canonical agency wallet | via getOrCreate | same | Low after harden |
| `AgentWalletService::submitDepositRequest` | create deposit+pending tx | ASSIGN `agent_wallet_id` | `walletFor()` + marker assert | deposit txn | none (new row) | Must stay canonical-only |
| `AgentWalletService::approveDeposit` | balance credit + posted tx + ledger | BALANCE+ | require deposit wallet == current canonical | deposit+wallet `lockForUpdate` | status re-check | P1 historical credit (fail-closed) |
| `AgentWalletService::rejectDeposit` | status/tx only | NO BALANCE | uses deposit wallet id | deposit+wallet lock | status re-check | Low (no credit) |
| `ManualWalletAdjustmentService::credit/debit` | balance ± + ledger | BALANCE± | request resolves canonical only | wallet `lockForUpdate` | idempotency_key | Medium if wallet_id spoofed (validated) |
| `ManualWalletAdjustmentService::reverse` | balance reverse | BALANCE± | original `agent_wallet_id` | wallet lock | reversal uniqueness | Medium if original pointed historical |
| `DuplicateWalletArchiveService::archiveWallet` | status→archived | STATUS | blocks marker=1 / resolver canonical | wallet lock | audit log | Must never archive canonical |
| `WalletAuditService::build` | none | READ | reports canonical id | n/a | n/a | None |
| `OtaFinanceDemoScenario` / QA commands | create/forceFill balance | SEED/QA | demo sets marker; QA uses walletFor | n/a | n/a | Out of prod path |
| `LedgerBalanceService` / reconciliation | none | READ (sum all wallets) | n/a | n/a | n/a | Must keep summing all rows |
| Booking/payment services | — | NO direct AgentWallet balance writes found | n/a | n/a | n/a | Separate domain |

## Notes

- No booking-path direct `AgentWallet` balance mutator found under `app/Services/Booking`.
- Historical multi-wallet rows remain for audit/reconciliation; operational writes must target marked canonical only.
- OPS-DEF-0006 ledger `OrFail` inside approve transaction remains mandatory.
