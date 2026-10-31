# Portal RBAC safe smoke (guest / unauthenticated)

| ROLE gate | ROUTE | EXPECTED | ACTUAL | PASS |
|---|---|---|---|---|
| guest | /admin | redirect login | 302 → /login | PASS |
| guest | /admin/bookings | redirect login | 302 → /login | PASS |
| guest | /staff | redirect login | 302 → /login | PASS |
| guest | /agent | redirect login | 307 → /login | PASS |
| guest | /customer | redirect login | 307 → /login | PASS |
| guest | /customer/dashboard | redirect login | 307 → /login | PASS |

Authenticated cross-role matrix **not** executed (no user mutation / no credential exercise this loop).

```
PORTAL_RBAC_SMOKE=PASS_GUEST_GATES
RBAC_CRITICAL_FAILURES=0
CLASSIFICATION=PARTIAL (guest gates PASS; authenticated RBAC still needs controlled QA accounts)
```
