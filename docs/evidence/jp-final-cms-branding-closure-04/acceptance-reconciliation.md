# Closure-04 acceptance reconciliation vs original contract

**Authorized production SHA:** `20e921661da55e121a9b2353cba535b350613493`  
**Branch:** `phase/jp-master-unfinished-closure-10`  
**Reconciled:** 2026-09-08T12:37:00Z  
**Overall Closure-04:** **PARTIAL** (one mandatory production gate blocked on credentials)

Production was **not** redeployed during this reconciliation tick. Engineering SHA remains `20e92166`.

---

## Original contract → evidence → status

| # | Original gate | Evidence artifact | Status | Notes |
|---|---------------|-------------------|--------|-------|
| 1 | Protected deploy to `20e92166`; `PUBLIC_BUILD_ID` valid | `deployment-report.md`, `deploy-full-console-r2.txt` | **PASS** | `RUNTIME_SHA=20e92166`, `PUBLIC_BUILD_ID=XqAEESaNTRXUcuNc_Zj1d` |
| 2 | Local PHPUnit (Ask AI, branding, CMS 2.25MB, trending CTA) | `gate-evidence-20e92166.md` | **PASS** | 11/11 + 10/10 |
| 3 | Local Playwright FAB + Ask UI | `playwright-gates-20e92166.txt`, `playwright-fab-collision-20e92166.txt` | **PASS** | 13/13 |
| 4 | Next.js build exit 0 | `next-build-20e92166.txt` | **PASS** | |
| 5 | **Production CMS 2.25MB JPEG upload (draft only), 2xx, asset record, media URL 200, draft preview, cleanup** | `closure-04-prod-gates.json` (`CMS_*`) | **FAIL — OWNER ACTION** | QA admin login `422` (credentials stale). Stale storage → `/access-denied`. **No publish.** Local proxy: `JetpkHomepageCmsAssetUploadTest` PASS at `20e92166`. |
| 6 | Favicon metadata + HTTP/browser request proof | `closure-04-prod-gates.json`, `prod-curl-probes.txt` | **PASS** | `config.favicon_url` set; `curl.exe -I` → **200** on storage favicon URL |
| 7 | Branding logo `/storage/` propagation | `prod-uat-postdeploy.json`, `closure-04-prod-gates.json` | **PASS** | Header logo src uses `/storage/agencies/...` |
| 8 | Trending route fare/date/CTA consistency (prod) | `prod-homepage-snapshot.json`, `closure-04-prod-gates.json` | **PASS** | 4/4 routes: origin/destination/depart match `fare_target_date`; DOM hrefs align |
| 9 | Bounded **20-turn** Ask production session | `closure-04-prod-gates.json` (`ask_20_turn`) | **PASS** | 20/20 HTTP 2xx |
| 10 | Duplicate assistant messages = 0 | `closure-04-prod-gates.json` | **PASS** | `duplicate_message_ids: 0` |
| 11 | Unexpected app/provider 429 = 0 | `closure-04-prod-gates.json` | **PASS** | `unexpected_429: 0` |
| 12 | Booking lookup / privacy (safe fake refs) | `closure-04-prod-gates.json` (`ASK_BOOKING_LOOKUP_PRIVACY`) | **PASS** | Turns 9–12: `booking_lookup` intent, no PNR/passport leakage in previews |
| 13 | Roman Urdu / context / grounding | `closure-04-prod-gates.json` (`ASK_ROMAN_URDU_GROUNDING`) | **PASS** | Turn 8: LHE→DXB flight search prepared |
| 14 | Error recovery | `closure-04-prod-gates.json` (`ASK_ERROR_RECOVERY`) | **PASS** | Turn 12 returns 200 with body after invalid ref |
| 15 | Production FAB collision (mobile 390) | `prod-uat-postdeploy.json` (rerun 12:36Z) | **PASS** | `ask_fab_visible=true`, `fab_overlap=false`, `--jp-ask-fab-bottom=86` |
| 16 | Production screenshots | `prod-gate-ask-open-mobile.png`, `prod-uat-postdeploy.json` | **PARTIAL** | Playwright font-load timeout on home screenshot (Windows). Ask-open capture succeeded in first gates run; rerun hit same font timeout. **Limitation documented — not silently PASS.** |
| 17 | PowerShell-safe `curl.exe` probes | `prod-curl-probes.txt`, `HOMEPAGE_API_CURL_PROBE` | **PASS** | Homepage API 200; favicon storage 200 |
| 18 | No engineering/code changes on prod | git status | **PASS** | Evidence-only reconciliation; SHA unchanged |

---

## Blocked gate — owner action required

**CMS production 2.25MB draft upload** cannot run until platform-admin QA credentials work on production:

1. Update `JetPakistan-JP-DASH-03-QA-Admin` in Windows Credential Manager **or** set `JP_DASH_03_QA_ADMIN_PASSWORD` for account `jp-dash-03-qa-admin@jetpakistan.pk`.
2. Re-run: `node docs/evidence/jp-final-cms-branding-closure-04/run-closure-04-prod-gates.mjs`
3. Expect: `CMS_225MB_DRAFT_UPLOAD_CLEANUP=PASS` with upload/preview/delete HTTP evidence; no publish.

Until then, Closure-04 **cannot** be declared full **PASS** per the original contract.

---

## Prior PASS claim correction

The earlier Closure-04 summary claimed **PASS** while explicitly omitting:

- production CMS 2.25MB upload proof
- full 20-turn Ask production transcript
- screenshot limitations

This reconciliation supersedes that claim: **PARTIAL** until CMS prod upload is proven or owner approves an exception.

---

## Evidence index

| File | Purpose |
|------|---------|
| `closure-04-prod-gates.json` | Primary production gate machine output |
| `closure-04-prod-gates-console.txt` | Console log |
| `run-closure-04-prod-gates.mjs` | Re-runnable verifier |
| `prod-homepage-snapshot.json` | Trending route API snapshot |
| `prod-uat-postdeploy.json` | FAB/branding/health post-deploy |
| `prod-curl-probes.txt` | curl.exe HEAD probes |
| `prod-gate-ask-open-mobile.png` | Ask panel screenshot (when font gate allows) |
| `deployment-report.md` | Deploy SHA / build IDs |
| `gate-evidence-20e92166.md` | Local test matrix |
