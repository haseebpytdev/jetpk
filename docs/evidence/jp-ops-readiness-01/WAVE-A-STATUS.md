# JP-OPS-READINESS-01 — Wave A Status

```
WAVE_A_STARTED=YES
WAVE_A_COMPLETE=PARTIAL
AUTHENTICATED_CONTINUATION=YES
STOPPED_REASON=Evidence packed; browser Playwright auth still blocked (QA password env missing); write-path journeys intentionally not executed
PRODUCTION_OPERATIONAL_READY=NO
```

## Closed this program

| Defect | Status |
|--------|--------|
| OPS-DEF-0019 | CLOSED — queue routing + canary on `a138eb57` |
| OPS-DEF-0014 | CLOSED — disk retention |
| OPS-DEF-0015 | CLOSED — failed_jobs backlog |

## Authenticated continuation (this loop)

| Item | Result |
|------|--------|
| QA accounts | Reused JP-DASH-03 + FINAL-11 markers |
| Server-side auth | PASS for admin/staff/owner/manager/accountant/support/ticketing/viewer/sales/customer/agent-b |
| Browser auth | BLOCKED_NO_PASSWORD_ENV |
| A2 matrix | Capability-level complete |
| OJ-B2B-MGR/ACC/SUP/STAFF/OWN | PARTIAL (ACC blocked by OPS-DEF-0006) |
| OJ-B2C-001 | PASS_TO_SAFE_BOUNDARY |
| OPS-DEF-0004 | OPEN_VERIFIED (policy) |
| OPS-DEF-0024 | NEW — legacy support QA over-permissioned |

## Still open (priority)

| Defect | Severity | Wave A evidence |
|--------|----------|-----------------|
| OPS-DEF-0006 | P1 | Confirmed; blocks finance readiness |
| OPS-DEF-0007 | P1 | Live duplicates=0; architecture open |
| OPS-DEF-0013 | P2 | booking_payments uniqueness |
| OPS-DEF-0004 | P2 | Viewer cancel BookingsView-only |
| OPS-DEF-0016 | P1 | Sabre book live-call ON; ticketing OFF |
| OPS-DEF-0017 | P1 | AbhiPay activation incomplete |
| OPS-DEF-0018 | P1 | PARTIAL — server-side journeys done; browser depth pending |
| OPS-DEF-0020 | P2 | public/storage — do not touch |
| OPS-DEF-0021 | P3 | 178 queued logs; recommend historical mark later |
| OPS-DEF-0024 | P2 | Legacy support QA over-permissioned |

## Recommended next

1. Remediate OPS-DEF-0006 (atomic wallet+ledger)  
2. OPS-DEF-0007 uniqueness + OPS-DEF-0013 payment uniqueness  
3. OPS-DEF-0004 viewer cancel permission tighten  
4. Ops inject QA password env → browser Playwright depth  
5. Manual activation cert OPS-DEF-0016/0017  
