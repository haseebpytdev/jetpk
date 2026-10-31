# JP-AUDIT-CLOSURE-02 — Phase A / Final Git Reconciliation

| Field | Value |
|---|---|
| START_MAIN_SHA | `d171fb29a01e1af3a9522d2869e31a99f0027ef3` |
| PR #151 | `7cc318122d4f2cf6c7b5571ce176970c3862180f` — flight-search retired-provider diagnostics |
| PR #152 | `1f4cdcbf2b18cb577677b8ddd1124017dd46394f` — FAB-02 |
| PR #153 | `d171fb29a01e1af3a9522d2869e31a99f0027ef3` — initial system audit |
| PR #154 | `8f33173710f233f6ec84c729eb96ab11a5c53d4c` — CMS trending-date freshness |
| PR #155 | `c56178a84f4bc3f2124b9d81ed9c890b5382d0f9` — component deployment marker split |
| MAIN_SHA before closure-doc PR | `c56178a84f4bc3f2124b9d81ed9c890b5382d0f9` |
| LIVE_FRONTEND_SHA | `d9e08e18907ebf2b3621d48bc0843588571caa96` (`.jetpk-frontend-sha`) |
| LIVE_DEPLOY_MARKER | `d9e08e18…` legacy marker from prior FE deploy; not authoritative Laravel provenance |
| LIVE_LARAVEL FlightSearchService hash | `1aa86ce4…` (incident fix present) |
| LIVE_LARAVEL_FILE_PARITY | selective hotfix present; canonical source now merged through PR #151 |
| MARKER_SPLIT_SOURCE | merged in PR #155; takes effect on next applicable deploy using updated scripts |

The closure-doc branch is rebuilt from current protected main and contains evidence/docs only.
