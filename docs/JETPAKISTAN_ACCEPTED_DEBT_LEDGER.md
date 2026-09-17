# JetPakistan Accepted Debt Ledger

**Mission:** FINAL SYSTEM RECONCILIATION  
**Rule:** Do not convert regressions into accepted debt. Only historically deferred, non-blocking items.

| ITEM | ORIGIN | CURRENT STATUS | RISK | WHY ACCEPTED | BLOCKS RELEASE? | FOLLOW-UP |
|---|---|---|---|---|---|---|
| Dashboard Security MFA fields are preview/metadata | Dashboard settings module | Still preview-only except Login OTP now has real admin page | Low if Login OTP admin used | MFA TOTP not productized | NO | Productize MFA later |
| Soft-nav residual from Authority-06 | `67510da` | ACCEPTED historically | Perf residual | Explicitly accepted at Authority-06 closure | NO | Re-measure with unbiased harness |
| AI assistant settings missing on main (present in worktree) | AI closure worktree | PARTIAL — recover if Ask AI gating required for PASS | Medium | Must verify before AI gate PASS | YES for Ask AI enable path | Restore AI settings from worktree if needed |
| Biased traveler/soft-nav threshold retries on HEAD | `4b111929` / `392b9b49` | REGRESSED methodology — **must fix, not accept** | High for certification | Not accepted debt | YES for perf certification | Remove threshold retries |
| Legacy AirBlue CRANE_NDC labels | Supplier mapping docs | ACCEPTED_DEBT | Doc confusion | Explicit AGENTS.md debt | NO | Separate audited migration |
| Untracked tmp/worktrees evidence noise | Local agent artifacts | UNKNOWN → classify before delete | Low | Not runtime | NO | Section 60 cleanup |
| Multi-city if intentionally partial | Product state TBD | NEEDS_VERIFICATION | — | Only accept if latest product says deferred | TBD | Confirm product contract |
