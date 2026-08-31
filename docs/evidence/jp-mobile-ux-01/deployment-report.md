# JP-MOBILE-UX-01 deployment report

## Engineering commits (R5)

| SHA | Subject |
|---|---|
| `08b941c6ed8f0299c5d3cc2e25227877eddef791` | reflow flight cards, header, FAB for narrow viewports |
| `b217a56c0d0d618a47c43c75eb6c92b5555938f1` | contain homepage card rails |
| `85dbd1ce03e7f10e82a7e28d72ef5db9e69ca63d` | stack traveler/contact fields below md |
| `df8ec3c399070d5ce0217acc12a1669275d48c14` | move FAB off Book Now corner (superseded) |
| `c89536afe762a567f1df0dc6e193ee0ba2a8af9f` | lift FAB above sticky fare CTAs + inset Book Now |

## Final runtime

- MOBILE_FINAL_ENGINEERING_SHA / RUNTIME: `c89536afe762a567f1df0dc6e193ee0ba2a8af9f`
- PUBLIC_BUILD_ID: `ZZfidOFs_ir31JteO5OU0`
- DASHBOARD_BUILD_ID: `vusuf0T5POTkwsLY8oUyO` (unchanged)
- FULL_RUNTIME_SOURCE_DRIFT: 0
- OLS: PASS
- FINAL_VERIFIED_ROLLBACK_COUNT: 2
- ROOT_DISK: ~20% used / ~78G free (post-deploy checks)

## Runtime changed

- PUBLIC_RUNTIME_CHANGED=YES
- DASHBOARD_RUNTIME_CHANGED=NO
- LARAVEL_RUNTIME_CHANGED=NO
- RESTARTED_SERVICES=jetpk-public-frontend

## Commercial

No supplier mutation / payment / ticket / void / refund during responsive QA.
