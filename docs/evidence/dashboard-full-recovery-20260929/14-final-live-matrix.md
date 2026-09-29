# 14 — Final live matrix (Batch A status)

| Area | Local | Live unauth | Live auth UAT | Status |
|---|---|---|---|---|
| Admin Overview (Next) | PASS | 200 | PENDING credentials | PARTIAL |
| Booking Management Next list | present | 200 shell | PENDING | PARTIAL |
| Blade booking detail | present | 302→login | PENDING | PARTIAL |
| API Settings Blade | bridged | 302→login (route alive) | PENDING | PARTIAL |
| Company Profile / Branding | bridged | 302→login | PENDING | PARTIAL |
| Homepage CMS | bridged | 302→login | PENDING | PARTIAL |
| CMS Pages | bridged | 302→login | PENDING | PARTIAL |
| SEO | bridged | 302→login | PENDING | PARTIAL |
| Customer Queries | bridged | 302→login | PENDING | PARTIAL |
| OTP / AI settings | bridged | 302→login | PENDING | PARTIAL |
| Settings Hub | bridged | 302→login | PENDING | PARTIAL |
| Staff Blade | bridged | 302→login | PENDING | PARTIAL |
| Customer portal | source on main | 307→login | PENDING smoke | PARTIAL |
| Agent portal | source on main | 307→login | PENDING smoke | PARTIAL |
| Staff Next | present | 200 shell | PENDING | PARTIAL |
| RBAC cross-role | unit nav isolation | — | PENDING | PARTIAL |
| Public Homepage | PASS | PASS golden | N/A | PASS |
| Ask JetPakistan presence | PASS keyword | PASS | PENDING chat smoke | PARTIAL |
| Next owner-uat write hubs | NOT PORTED | — | — | INTENTIONALLY DEFERRED |

## Remaining blockers for VERIFIED_PASS
1. Authenticated Admin UAT proving each laravel nav item opens Blade and (for safe settings) save→reload→consumer.
2. Customer / Agent / Staff authenticated smoke matrices.
3. Optional Batch C: port Next API Connections write hub if Blade UX remains insufficient.
4. Git main SHA `0878e727` vs runtime marker `3dc81c07` — content-equivalent (squash parent); reconcile marker to merge SHA on next deploy touch if desired.
