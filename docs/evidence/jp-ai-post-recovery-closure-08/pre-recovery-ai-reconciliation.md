# JP-AI-POST-RECOVERY-CLOSURE-08 — Pre-Recovery AI Reconciliation

**Authoritative main:** `cf8fd79d383928115acb36168772f3587737e430`  
**Recovery checkpoint:** `78dadc7b330ca24a6183241458dd8d1f6aa0d454`  
**Evidence branch (frozen):** `fix/jp-ai-closure-ls12-leads`  
**Date:** 2026-09-21

## Commit classification

| Commit | Subject | Classification | Notes |
|--------|---------|----------------|-------|
| `b26e2bda` | fix(qa): pass options into lead capture helper during live search runs | **TREE_EQUIVALENT_IN_MAIN** (harness-only) | QA harness delta in `frontend/scripts/live-readonly-qa-helpers.mjs`; not application runtime. Restored to closure branch for matrix evidence only. |
| `d9f363eb` | fix(ai): isolate runtime bindings from SEO deploy collision | **SUPERSEDED_IN_MAIN** | Main `cf8fd79d` contains `AiServiceProvider`, `bootstrap/providers.php` registration, and deploy guard scripts. Main adds canary middleware `boot()` that this commit lacked. `scripts/verify-ai-runtime-after-seo-activate.sh` was missing from main git but referenced by guards — **restored on closure branch**. |
| `0bba0e9d` | fix(ai): resolve canary eligibility from session on public config | **SUPERSEDED_IN_MAIN** | `PublicContentApiPresenter` on main includes AI eligibility wiring; tree differs but capability present. |
| `03662506` | fix(ai): register canary fault middleware from AiServiceProvider | **TREE_EQUIVALENT_IN_MAIN** | Canary middleware registration present in main `AiServiceProvider::boot()`. |
| `ad0f857f` | fix(ai): register AiServiceProvider from AppServiceProvider | **SUPERSEDED_IN_MAIN** | Main registers via `bootstrap/providers.php` (preferred recovery-safe pattern). |
| `8c01ca4f` | fix(ai): exclude non-flight suppliers from read-only search fan-out | **MISSING_FROM_MAIN** (partial) | Supplier fan-out guard may differ; requires runtime matrix proof. Not wholesale cherry-picked pending live LS matrix. |

## Related frontend/deploy evidence (frozen branch)

- `frontend/scripts/run-ls-matrix-strict.mjs` — strict 12/12 matrix harness
- `frontend/scripts/live-readonly-qa-helpers.mjs` — shared LS helpers
- `frontend/scripts/canary-matrix-helpers.mjs` — browser/session helpers
- `app/Http/Controllers/Admin/CustomerQueryController.php` — admin Customer Queries UI (**missing from main, present on production**)
- `AskJetPakistanChat.tsx` lead-capture deltas — restored on closure branch for UAT-05 parity

## Policy

- No automatic cherry-pick of old branch.
- Recovery safety and current-main architecture take precedence.
- Production-only files (e.g. `CustomerQueryController` on live before main merge) classified as **MIXED_RUNTIME** until protected redeploy from merged main.
