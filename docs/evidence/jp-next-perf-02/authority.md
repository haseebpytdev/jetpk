# JP-NEXT-PERF-02 — Authority

| Field | Value |
|---|---|
| Phase | JP-NEXT-PERF-02 |
| Branch | `phase/jp-flight-perf-01` |
| START_LOCAL_HEAD | `ed83b98299441480b4b5b17405151c34aae83c87` |
| REMOTE_HEAD (frozen) | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` |
| START_RUNTIME_SHA | `f593ddeb45890fdd7d985f4a5ef9705ac7d4ea03` |
| PUBLIC_BUILD_ID (start) | `m-n0qXZkLHvCqrRPZ2lcx` |
| ASK JetPakistan | PUBLIC |
| MOFA on production | NO (`config/visa.php` absent) |
| Chatwoot | NOT INSTALLED |
| OLS gate | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| SAFE_TO_PUSH | NO |

## 02B asset parity (pre-perf)

| Check | Result |
|---|---|
| PF live SHA256 | `206B5723231F5A9C…` = 02B audit |
| 9P live SHA256 | `ABE2F6547A595CEB…` = 02B audit |
| G9 control | `A366982A239FA08D…` = 02B audit |
| PF_ASSET_RUNTIME_PARITY | PASS |
| 9P_ASSET_RUNTIME_PARITY | PASS |
| FULL_RUNTIME_SOURCE_DRIFT | 0 (build id + runtime SHA match declared) |
| UNEXPLAINED_RUNTIME_ASSET_DRIFT | 0 |

Remote freeze verified via `git ls-remote jetpk refs/heads/phase/jp-flight-perf-01`.
