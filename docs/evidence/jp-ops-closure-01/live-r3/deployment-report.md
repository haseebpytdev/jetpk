# JP-OPS-CLOSURE-01-R3 deployment report

| Field | Value |
|---|---|
| DEPLOY_1_SHA | `07a9c38fedf5568316d890ed4bf31758143002fc` |
| DEPLOY_1_PUBLIC_BUILD | `7CpMnF0Nvu9UgB60QIrCo` |
| DEPLOY_1_DASHBOARD | rebuilt (pkjetp) |
| DEPLOY_2_SHA | `d95680d06c1cb7f0c25d541df53c960c2a16318d` |
| DEPLOY_2_PUBLIC_BUILD | `54EJE07vqRlgexjmoCRzE` |
| DEPLOY_2_DASHBOARD | SKIPPED (`PUBLIC_ONLY=1`) |
| ACTIVATE | PASS (both) |
| FULL_RUNTIME_SOURCE_DRIFT | 0 |
| OLS | PASS (`612aa838…`) |
| PM2_PUBLIC | online |
| PM2_DASHBOARD | online |
| FINAL_VERIFIED_ROLLBACK_COUNT | 2 |
| ROOT_DISK | ~20% used / ~77GB free |
| GOOGLE_SETTINGS_AFFECTED_RUNTIME | Laravel + Dashboard (deploy 1) |
| WIZARD_AFFECTED_RUNTIME | Laravel + Public/Dashboard shells (deploy 1) |
| RESTARTED_SERVICES | jetpk-public-frontend (both); jetpk-dashboard (deploy 1 only) |
| LOCK | `/usr/local/sbin/jetpk-production-run` |
