# JP-AI-ASSIST-02A-R2 Deployment

GITHUB_PUSH_REQUIRED_FOR_DEPLOY=NO — local Git object staging via protected wrappers + `jetpk-production-run` (no GitHub push).

| Field | Value |
|---|---|
| DEPLOY_ENGINEERING_SHA | `896f1e8acf5083ac8292b5287e1fc5bcb051e260` |
| AI_02A_FINAL_RUNTIME_SHA | `896f1e8acf5083ac8292b5287e1fc5bcb051e260` |
| PRIOR_RUNTIME | `0e747db23f9f75839ca73960dcc6fca47dab9ea1` |
| PUBLIC_BUILD_ID | `K_cN0j1y-P5mNXJBw4EYt` |
| DASHBOARD_BUILD_ID | `fbzOL_dHxc_Iq0ScPoglD` (unchanged) |
| Lock | `/usr/local/sbin/jetpk-production-run` label `jp-ai-assist-02a-r2-*` |
| PUBLIC_AI_DURING_INITIAL_DEPLOY | OFF |
| Final mode | INTERNAL_CANARY |
| Manifest | 63 AI runtime closure paths (+ hotfix `app/Contracts/Ai/InferenceProvider.php`) |
| OLS | PASS (`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`) |
| PREDEPLOY_ROLLBACK | PASS |
| FINAL_VERIFIED_ROLLBACK_COUNT | 2 (`jp-ai-assist-02a-r2-*`) |
| AUTHORIZED_RUNTIME_PARITY | PASS |
| FULL_RUNTIME_SOURCE_DRIFT | 0 |

AI_RUNTIME_DEPENDENCY_CLOSURE=PASS (base 01C + hybrid 01F/02A + migration + knowledge + FAB shell + staff queue + admin status).
