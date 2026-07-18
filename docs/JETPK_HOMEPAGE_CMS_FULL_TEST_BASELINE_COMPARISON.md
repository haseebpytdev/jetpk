# JetPK Homepage CMS — Full Test Baseline Comparison

**Integration HEAD:** post-hygiene closure
**Baseline:** `624f3dd`
**Date:** 2026-07-18

Per-directory JSON from merge gate is **local-only** (gitignored). Reproduce with:

```powershell
.\scripts\test\run-feature-dirs.ps1 -Label integration-HEAD
.\scripts\test\run-feature-dirs.ps1 -RepoRoot ..\ota-jetpk-baseline-624f3dd -Label baseline-624f3dd
```

## Feature directory matrix (23 directories)

| Directory | Integration pass/fail/skip | Baseline pass/fail/skip | Classification |
|-----------|---------------------------:|------------------------:|----------------|
| Admin | 63/2/0 | 53/2/0 | PRE_EXISTING_IDENTICAL |
| Agent | 144/20/3 | 144/20/3 | PRE_EXISTING_IDENTICAL |
| Auth | 48/24/0 | 48/24/0 | PRE_EXISTING_IDENTICAL |
| Booking | 14/1/0 | 14/1/0 | PRE_EXISTING_IDENTICAL |
| Client | 44/45/6 | 19/52/6 | PRE_EXISTING_IDENTICAL (+18 new CMS tests pass; +7 redirect repairs) |
| Communication | 23/5/0 | 23/5/0 | PRE_EXISTING_IDENTICAL |
| Console | 77/9/5 | 77/9/5 | PRE_EXISTING_IDENTICAL |
| Customer | 7/4/0 | 7/4/0 | PRE_EXISTING_IDENTICAL |
| Dashboard | 21/9/0 | 21/9/0 | PRE_EXISTING_IDENTICAL |
| Developer | 71/2/0 | 71/2/0 | PRE_EXISTING_IDENTICAL |
| Email | 17/0/0 | 17/0/0 | PASS |
| Finance | 232/7/0 | 232/7/0 | PRE_EXISTING_IDENTICAL |
| FlightSearch | 5/0/2 | 5/0/2 | PRE_EXISTING_IDENTICAL |
| GroupTicketing | 33/22/1 | 33/22/1 | PRE_EXISTING_IDENTICAL |
| Guest | 7/0/0 | 7/0/0 | PASS |
| Jetpk | 191/2/0 | 191/2/0 | PRE_EXISTING_IDENTICAL |
| Payments | 7/0/0 | 7/0/0 | PASS |
| Platform | 104/1/2 | 104/1/2 | PRE_EXISTING_IDENTICAL |
| Rbac | 53/9/0 | 53/9/0 | PRE_EXISTING_IDENTICAL |
| Reports | 2/0/0 | 2/0/0 | PASS |
| Sprint9E | 8/2/0 | 8/2/0 | PRE_EXISTING_IDENTICAL |
| Sprint9F | 8/3/0 | 8/3/0 | PRE_EXISTING_IDENTICAL |
| Support | 6/0/0 | 6/0/0 | PASS |
| Ui | 50/22/0 | 50/22/0 | PRE_EXISTING_IDENTICAL |

**`INTRODUCED_BY_INTEGRATION`:** 0

See `docs/JETPK_CLIENT_FEATURE_IMPROVEMENT.md` for Client pass/fail delta.
