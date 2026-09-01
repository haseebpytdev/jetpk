# JP-UX-POLISH-02 — Authority

| Field | Value |
|---|---|
| Phase | JP-UX-POLISH-02 |
| Branch | `phase/jp-flight-perf-01` |
| Start local HEAD | `6de2358d3d4f1a8daa3b4c6d7584fb9eeb147167` |
| Local engineering SHA | `3c418a2f7b3280de18c50149bef42291bbbcfc39` |
| Deploy engineering SHA | `f593ddeb45890fdd7d985f4a5ef9705ac7d4ea03` |
| Production base | `896f1e8acf5083ac8292b5287e1fc5bcb051e260` |
| Expected remote | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` |
| Public build before | `K_cN0j1y-P5mNXJBw4EYt` |
| Dashboard build before | `fbzOL_dHxc_Iq0ScPoglD` |
| MOFA engineering tip | `67fe90bad27ded5f2bb12475817250b5f3c09846` (NOT deployed) |
| Isolation | worktree from production runtime + UX-only cherry-pick |
| Push | NO |

## UI_DEPLOYMENT_ISOLATION_STRATEGY

`worktree_from_896f1e8a_apply_only_jp_ux_polish_02_runtime_diff`

Primary branch retains MOFA ancestry. Isolated branch `phase/jp-ux-polish-02-deploy` is rooted at production `896f1e8a` and contains only the authorized UI/checkout/runtime polish commit `f593ddeb…`.
