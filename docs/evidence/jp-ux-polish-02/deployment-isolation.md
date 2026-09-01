# Deployment isolation

| Field | Value |
|---|---|
| UI_DEPLOYMENT_ISOLATION_STRATEGY | `worktree_from_896f1e8a_apply_only_jp_ux_polish_02_runtime_diff` |
| Production base | `896f1e8acf5083ac8292b5287e1fc5bcb051e260` |
| Primary local engineering | `3c418a2f7b3280de18c50149bef42291bbbcfc39` (on `phase/jp-flight-perf-01`, retains MOFA ancestry) |
| Isolated deploy engineering | `f593ddeb45890fdd7d985f4a5ef9705ac7d4ea03` |
| DEPLOY_TREE_CONTAINS_MOFA_01B_SOURCE | NO |
| MOFA_PRODUCTION_SOURCE_DEPLOYED_BY_THIS_PHASE | NO |
| CHATWOOT_PRODUCTION_CHANGES | 0 |
| Manifest | 14 frontend runtime files only |
| NEW_PUBLIC_BUILD_ID | `m-n0qXZkLHvCqrRPZ2lcx` |
| Dashboard rebuild | skipped |
| FULL_RUNTIME_SOURCE_DRIFT | 0 |
| FINAL_VERIFIED_ROLLBACK_COUNT | 2 |
| OLS | PASS (`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`) |
| Lock | `/usr/local/sbin/jetpk-production-run` label `jp-ux-polish-02-f593ddeb…` |
| REMOTE_DEPLOY_RC | 0 |

Server post-checks: `config/visa.php` absent, `app/Services/Visa` absent.
