# Playwright E2E layout (Phase 3B)

## Status: HOLD — root configs not relocated

Phase 3B inventoried **30** root `playwright*.config.ts` files. They were **not**
moved into `tests/e2e/playwright/configs/` because Playwright resolves
`testDir` / `outputDir` / `globalSetup` / reporter paths **relative to the
config file**. A mechanical move would break release QA without rewriting
every path (and `scripts/run-jetpk-dashboard-qa.ps1`, `package.json`, and many
phase docs hard-code root `-c playwright.*.config.ts`).

### Families (keep at repo root until dedicated Playwright phase)

| Family | Example configs | testDir |
|---|---|---|
| Default e2e | `playwright.config.ts` | `./test/e2e` |
| Visual / responsive | `playwright.responsive*.config.ts`, `*.public-*.config.ts`, `*.agent-critical.config.ts`, … | `./tests/visual` |
| JetPK suite | `playwright.jetpk-*.config.ts` | `./tests/playwright/jetpk*` |
| Dashboard 9h | `playwright.jetpk-9h*.config.ts` | `tests/playwright/jetpk-9h*` |
| Proposed-safe | `playwright.mobile-integration.config.ts`, `playwright.jetpk-portal-parity.config.ts` | `./tests/proposed-safe-tests` |

### Callers that block a bulk move

- Root `package.json` scripts (`-c playwright.*.config.ts`)
- `scripts/run-jetpk-dashboard-qa.ps1`
- Multiple `docs/phases/**` and `docs/playwright-responsive-audit.md` command examples

### Target (future phase)

`tests/e2e/playwright/configs/*.config.ts` with root-relative path helpers,
updated callers, and `playwright test -c … --list` smoke per family.
