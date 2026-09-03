# Playwright config inventory — JP-APP-PERF-CLOSURE-01

Counted on 2026-09-03 from repo root (excluding node_modules).

## Counts

| Location | Count |
| --- | --- |
| `frontend/playwright*.config.*` | 7 |
| Root `playwright*.config.*` (legacy Blade/OTA) | ~30 |
| `dashboard/playwright*.config.*` | 6 |
| **Total** | **~43** |

## Classification

### ACTIVE_CANONICAL (keep)

- `frontend/playwright.config.ts`
- `frontend/playwright.jp-full-next-frontend.config.ts`
- `frontend/playwright.jp-ui-05-dashboard.config.ts`
- `dashboard/playwright.config.ts`
- `dashboard/playwright.production-acceptance.config.mjs`

### PHASE_SPECIFIC_REQUIRED (keep until phase archived)

- `frontend/playwright.jp-frontend-ux-02*.config.ts`
- `frontend/playwright.jetpk-ui-03.config.ts`
- `frontend/playwright.theme-02.config.ts`
- `dashboard/playwright.jp-bo-04*.config.ts`
- Root `playwright.jetpk-9h*.config.ts` (release gates)

### DUPLICATE / OBSOLETE candidates (do not delete in this loop)

- Multiple root `playwright.jetpk-*` and `playwright.responsive*` configs overlapping Next public coverage now owned by `frontend/`
- `playwright.config.ts` duplicated at root and `frontend/` (different products historically)

## Decision this loop

`PLAYWRIGHT_CONFIGS_BEFORE≈43`  
`PLAYWRIGHT_CONFIGS_AFTER≈43` (no consolidation yet — avoid coverage loss mid-perf closure)

Next hygiene pass: merge proven root duplicates into `frontend/` canonical configs with a redirect note.
