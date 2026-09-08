# Closure-04 gate evidence — SHA `20e92166`

Captured: 2026-09-08 (Closure-04 continuation tick)

## Engineering lineage

| Item | Value |
|------|-------|
| Branch | `phase/jp-master-unfinished-closure-10` |
| Base Closure-04 commit | `007e3bc1532326f88f0435d81c59d43001d34552` |
| FAB gate-fix commit | `20e92166` (mobile CSS preserves `--jp-ask-fab-bottom`) |
| Remote | `jetpk/phase/jp-master-unfinished-closure-10` pushed |

## PHPUnit

| Suite | Result |
|-------|--------|
| `PublicAiAssistantTest` | **11/11 PASS** (terminal 214273, exit 0) |
| `JetpkCompanyBrandingPropagationTest` | see terminal 214274 |
| `JetpkHomepageCmsAssetUploadTest` | see terminal 214274 |
| `JetpkHomepageTrendingRouteCtaTest` | see terminal 214274 |

## Frontend build

Single clean rebuild after FAB CSS fix (deleted `.next` first):

- Command: `cd frontend && Remove-Item -Recurse -Force .next; npm run build`
- Result: **exit 0**, Next.js 15.5.22, 41 static pages generated

## Playwright (sequential, smoke server on :3002)

| Spec | Result |
|------|--------|
| `jp-ask-fab-collision-03.spec.ts` | **2/2 PASS** |
| `jp-ask-fab-layout-03.spec.ts` | **3/3 PASS** |
| `jp-ask-ui-01.spec.ts` | **8/8 PASS** |

Screenshots: `docs/evidence/jp-ask-ui-01/*.png` (regenerated this tick)

## Closure-03 simulation (pre-deploy production baseline)

- Script: `docs/evidence/jp-ask-ai-capability-audit-01/run-capability-audit.mjs`
- Target: `https://jetpakistan.pk` (production SHA `544904ab` — baseline before deploy)
- Summary: 20 chat turns, duplicate_frontend_risk reflects **old** production UI (not `20e92166` code)

## FAB collision root cause (AI-UI-002)

Mobile `@media (max-width: 640px)` rule in `AskJetPakistanChat.module.css` set `bottom: max(14px, …)` without `var(--jp-ask-fab-bottom)`, ignoring shared layout authority while CSS variable on `:root` was correctly ≥86px.
