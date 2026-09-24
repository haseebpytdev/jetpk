# JetPakistan repository topology — Phase 3 (2026-09-24)

**Branch:** `maintenance/repository-topology-cleanup-2026-09-24`  
**Base:** `jetpk/main` @ `c14f7737083deb68adb26ffee2889e2bff557537`  
**Worktree:** `C:\Users\khadi\ota-jetpk-topology-cleanup` (primary dirty tree untouched)

## Intended product map

| Area | Location |
|---|---|
| Laravel backend | `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/` |
| Next public frontend | `frontend/` |
| Next dashboard/portal | `dashboard/` |
| AI chat/runtime | `app/Services/Ai/**`, `ai-lab-gateway/` (**KEEP_CURRENT_PENDING_SERVICE_REFACTOR**) |
| Tests/QA | `tests/` (PHPUnit + Playwright suites), `test/e2e/` (root package.json e2e scripts) |
| Deploy/ops | `scripts/jetpk/`, `deploy/`, `docs/deploy/`, `docs/deployment/` |
| Docs | `docs/` |
| Tools | `tools/` |

## Actions taken

### UNTRACK_AND_IGNORE
- `templates-source/` (~4851 files) — no app/build/CI runtime refs
- `tests/playwright/artifacts/` — generated Playwright output (configs still write here)
- `urdu-audio-transcriber/` — non-product scratch
- `.tmp-phase3/`, `.tmp-phase4/`, `.tmp-jetpk-card-ref/`, `_jetpk-package-temp/`
- `11k-s2-upload/` — historical sprint upload snapshots
- `tmp/` tracked phase-compare packages
- `test-output.txt`

### MOVE (tracked)
- `_production_baselines/` → `docs/maintenance/production-baselines/`
- `deploy_packages/jetpk_dedicated/` → `docs/deployment/jetpk-dedicated-package/jetpk_dedicated/`
- Updated `OtaJetpkDedicatedPackageAuditCommand` path check
- Extended leak-scan skip list for new baselines path

### KEEP (with reason)
- `ai-lab-gateway/` — deploy allowlist + systemd unit + hardening tests
- `test/` — referenced by root `package.json` e2e scripts (alongside `tests/`)
- Root `vite.config.js` / `tailwind` / `postcss` — Laravel Vite Blade assets
- Root `playwright*.config.ts` (~30) — **HOLD** consolidation to `tests/e2e/playwright/`
- `tools/` — reusable local setup helpers
- `deploy/` — OpenLiteSpeed route config

### HOLD
- Nested worktrees under primary `tmp/worktrees/*` (dirty/untracked/unique commits) — no retirement
- Playwright config consolidation / AI gateway rename
- External folder `C:\Users\khadi\ota-jetpk-audits` — copies now under ignored `docs/maintenance/local-audits/`; external folder safe for **manual** removal after owner confirms parity

## Local audits policy

- Path: `docs/maintenance/local-audits/` (tracked README only; contents gitignored)
- Permanent decisions stay in tracked `docs/maintenance/*.md`

## Validation expectations

Laravel boot, route list, ConfirmationPolicyGate tests, composer.lock unchanged, no production deploy.
