# Playwright E2E layout

- `specs/` — canonical e2e specs (moved from `test/e2e/`)
- `configs/` — specialized Playwright configs (moved from repository root)
- Root keeps a single entrypoint: `/playwright.config.ts`

Run specialized suites with:
`npx playwright test -c tests/e2e/playwright/configs/<name>.config.ts`
