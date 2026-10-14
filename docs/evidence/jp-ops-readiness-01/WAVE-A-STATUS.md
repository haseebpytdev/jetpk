# JP-OPS-READINESS-01 — Wave A Status

```
WAVE_A_STARTED=YES
WAVE_A_COMPLETE=PARTIAL
STOPPED_REASON=No further safe unsupervised probes without QA auth sessions or activation/remediation decisions
PRODUCTION_OPERATIONAL_READY=NO
```

## Closed this program

| Defect | Status |
|--------|--------|
| OPS-DEF-0019 | CLOSED — queue routing + canary on `a138eb57` |

## Still open (priority)

| Defect | Severity | Wave A evidence |
|--------|----------|-----------------|
| OPS-DEF-0006 | P1 | Confirmed: deposit approve commits wallet then `safeRecord` ledger |
| OPS-DEF-0007 | P1 | Live duplicate agency wallet groups = 0; unique still on `agent_id` only |
| OPS-DEF-0013 | P2 | `booking_payments` no gateway unique; `payment_transactions.client_transaction_id` unique |
| OPS-DEF-0016 | P1 | Sabre booking live-call ON; ticketing OFF; other suppliers OFF |
| OPS-DEF-0017 | P1 | No live AbhiPay charge |
| OPS-DEF-0018 | P1 | Journeys reclassified; authenticated depth blocked |
| OPS-DEF-0020 | P2 | `public/storage` directory inventory only |
| OPS-DEF-0021 | P3 | Residual communication_logs `queued` without jobs |

## Recommended next (human / gated)

1. Restore QA Playwright auth states → finish A2 authenticated negatives + staff/agent journeys  
2. Remedia OPS-DEF-0006 (atomic wallet+ledger) before finance go-live  
3. Decide Sabre booking live-call posture vs ticketing OFF  
4. Separate OPS-DEF-0020 storage reconciliation design  
5. Duplicate-safe historical notification replay plan (15 critical) — not auto-send
