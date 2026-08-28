# ChatGPT visual review pack — JP-FINAL-CLOSURE-01

## Status

Partial. Mail robustness is a **runtime** change (no visual email pack complete).
Public screenshot capture may be partial if Playwright font timeouts persist.

Do **not** mark OWNER_VISUAL_PASS.

## Email

- Inventory: `email/email-inventory.json` (163 entries)
- Hardcode audit: `email/email-hardcode-audit.json` (32 hits)
- Infrastructure notes: `email/existing-infrastructure.md`
- Preview screenshots: **not generated this run** — ChatGPT should treat EMAIL_PREVIEW as incomplete

## Public / Groups

- Live smoke HTTP 200 on `/` and `/groups` after mail deploy
- Groups hero engineering (`7d4302c7`) **not** included in mail-only file deploy — inspect live `/groups` cautiously; may still be pre-hero baseline

## Flights / Customer / Agent / Admin

- Not freshly screenshot-certified in this loop
- R3 historical evidence remains under `docs/evidence/jp-ux-portal-perf-01/live-final-r3/`

## What ChatGPT should verify first

1. Git: live authority = `fa6dfdc4` (not branch tip `9200165a`)
2. Deployment report ACTIVATE=PASS + OLS + drift 0
3. Mail failure architecture (BestEffort + after-commit Registered)
4. Residual matrix honesty (NEW_QA_E2E, EMAIL_SYSTEM, GROUPS, portals)
