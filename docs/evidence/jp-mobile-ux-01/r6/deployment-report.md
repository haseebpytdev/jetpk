# JP-MOBILE-UX-01 R6 deployment report

## Engineering commits
| SHA | Subject |
|---|---|
| `0ec10a02b6951dd50aa1725c199af3ce82e1ccf3` | public group short-link create API + branded landing |
| `e6f022e7f37f0994aeee7027c58dfc513f2aa783` | keep checkout Continue clear of FAB on traveler |

## Final runtime
- R6_FINAL_ENGINEERING_SHA / RUNTIME: `e6f022e7f37f0994aeee7027c58dfc513f2aa783`
- PUBLIC_BUILD_ID: `qqh-GEl1jVdeJPSOUwDQ7`
- DASHBOARD_BUILD_ID: `vusuf0T5POTkwsLY8oUyO` (unchanged)
- FULL_RUNTIME_SOURCE_DRIFT: 0
- OLS: PASS
- FINAL_VERIFIED_ROLLBACK_COUNT: 2
- ROOT_DISK_USED_PERCENT: 20
- ROOT_DISK_FREE_GB: 77.1
- AUTHORIZED_RUNTIME_PARITY / FULL_GIT_OBJECT_PARITY: PASS
- PM2_PUBLIC: online
- PM2_DASHBOARD: online

## Runtime changed
- PUBLIC_RUNTIME_CHANGED=YES
- DASHBOARD_RUNTIME_CHANGED=NO
- LARAVEL_RUNTIME_CHANGED=YES (share + staged paths; no new migrations this activate)

## Commercial
SUPPLIER_MUTATION_CALLS=0 during responsive QA (Review reached; Confirm not invoked).
