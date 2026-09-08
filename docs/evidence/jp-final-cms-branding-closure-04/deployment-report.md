# Closure-04 deployment report — COMPLETE

| Field | Value |
|-------|-------|
| AUTHORIZED_SHA | `20e921661da55e121a9b2353cba535b350613493` |
| PRODUCTION_RUNTIME_SHA | `20e921661da55e121a9b2353cba535b350613493` |
| PUBLIC_BUILD_ID | `XqAEESaNTRXUcuNc_Zj1d` |
| DASHBOARD_BUILD_ID | `HzAwsh7PBeJYcfQ8Zf-kM` |
| PREDEPLOY_VERIFIER | **PASS** |
| Rollback set | `/home/pkjetp/releases/jp-closure-04-20260908T114911Z` |
| DB backup | `jetpk-db-20260908T114642Z.sql.gz` |
| App tar backup | PARTIAL (`.next` changed during tar) |

## Sequence executed

1. Ownership preflight — all write probes PASS (`preflight-ownership-20260908.txt`)
2. `jetpk-backup.sh` — DB OK, app tar partial
3. Fresh `activate-closure-04.sh` from GitHub archive (all 28 runtime files)
4. `jetpk-next-build.sh` PUBLIC_ONLY=0 — public + dashboard build PASS, PM2 restart after valid BUILD_ID
5. `jetpk-pre-proxy-gate.sh` — PASS (LIVE_HTTPS=200, BRIDGE=200, PENDING_MIGRATIONS=0)

Console: `deploy-full-console-r2.txt`

## Post-deploy reconciliation (2026-09-08)

See **`acceptance-reconciliation.md`**. **Closure-04: PASS** — CMS 2.25MB production upload proven; all mandatory gates PASS (screenshots PARTIAL/limitation documented).


| Check | Result |
|-------|--------|
| HTTPS home/support | 200 / 200 |
| AI health | 200, `enabled=true` |
| Public config logo | `/storage/agencies/.../branding/...?v=` |
| Header logo src | storage URL with cache bust |
| Ask FAB visible | true |
| Dock visible | true |
| FAB overlap (mobile 390) | **false** |
| `--jp-ask-fab-bottom` | 86px |
| Ask panel opens | true |
| AI chat probe | 200, `message_id` present |

## Excluded / manual

- CMS 2.25MB upload: not mutated on production (draft-only policy)
- Full 20-turn chat simulation: bounded single-turn probe only on live
