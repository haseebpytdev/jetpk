# JP-AI-PRODUCTION-CANARY-01 — Final Report

**Phase:** JP-AI-PRODUCTION-CANARY-01  
**Branch:** `phase/jp-master-unfinished-closure-10`  
**Date:** 2026-09-13  
**Owner authorization:** Stage-1 internal/admin canary only — **NOT** general/public beta

---

## Objective

Reconcile source parity, enable internal canary routing for authorized admins, run certification UAT, and produce a beta decision gate — without enabling public AI beta or live supplier tools.

---

## SOURCE

| Field | Value |
|-------|-------|
| LOCAL_HEAD (integration) | `76bd422ce1c3eb51f920c300506ce551f47f7353` (parity commit; local branch also has unpushed `7fd1517c` airblue commit) |
| REMOTE_HEAD | `76bd422ce1c3eb51f920c300506ce551f47f7353` |
| PRODUCTION_HEAD | `76bd422ce1c3eb51f920c300506ce551f47f7353` |
| SOURCE_PARITY | **PASS** (production ≡ remote ≡ parity commit `76bd422c`) |
| WORKTREE_CLEAN | **NO** (uncommitted canary probe/certify commands, Playwright harness, evidence) |
| GATEWAY_SOURCE_SHA | `be4d430db3eb94d99f722ef644f8e47282b9fc6c631134315e8e78a551a70982` (local ≡ production) |
| AI_LAB_SHA | `7977f19f5e35eedfca27504a53cfdff78e35f0ba` |
| BACKUP | `20260913T100652Z` |

### Parity commit

`76bd422c` — `fix(ai): reconcile gateway hotfix and canary observability`

- Gateway hotfix (`FailureCategory` enum, live-fare RAG routing)
- `AiLabObservability`, weekly report command
- Systemd template for gateway service

---

## CANARY

| Field | Value |
|-------|-------|
| MODE | `internal_canary` (`OTA_AI_ASSISTANT_MODE=internal_canary`) |
| CANARY_ONLY | `true` (`OTA_AI_LAB_CANARY_ONLY=true`) |
| ADAPTER_GLOBAL | `false` (`OTA_AI_LAB_ADAPTER_ENABLED=false`) |
| AUTHORIZED_TESTERS | Platform admin / support staff via `AiAssistantEligibility::isCanaryUser()` (QA: `jp-dash-03-qa-admin@jetpakistan.pk`, id=9) |
| GENERAL_PUBLIC_NEW_AI | **NO** — anonymous sessions use `STRUCTURED_FALLBACK`, `meta.lab_adapter=false` |
| AUTHORIZED_CANARY_USERS_USE_NEW_AI | **YES** — `LAB_CONSULTANT_V1`, `meta.lab_adapter=true` |
| CANARY_BYPASS_POSSIBLE | **NO** — Laravel `shouldUseLabAdapter()` enforces canary eligibility server-side |
| LIVE_SUPPLIER_TOOLS | **DISABLED** (`AI_FLIGHT_SEARCH_READ_CALLS=0`, shadow recorder only) |

---

## SERVER CANARY CERTIFICATION (`ai:lab-canary-certify`)

Evidence: `docs/evidence/jp-ai-production-canary-01/canary-certify.json`

| Metric | Result |
|--------|--------|
| Chat matrix cases | 13/13 PASS |
| Residual Lab-03 (5) | 5/5 PASS (`ROUTE_VISIBLE_TO_USER=5/5`, `EXECUTION_WITHOUT_CONFIRMATION=0`) |
| Anonymous legacy path | PASS |
| Learning redaction | 100% (44 events, 0 violations) |
| Gateway unreachable | PASS (`status=unavailable`, no tool action) |
| Gateway health | PASS |
| **Server certify total** | **19/19 PASS** |

### Weekly report snapshot

- TOTAL_CONVERSATIONS: 44
- CLARIFICATIONS: 7
- USER_CORRECTIONS: 0
- FALLBACKS: 0
- HANDOFFS: 0
- FAILURE_EVENTS: 44 (structured learning events; top: OTHER 36, RAG_NO_SOURCE 8)

---

## BROWSER UAT

| Field | Value |
|-------|-------|
| Spec | `frontend/tests/jp-ai-production-canary-01.spec.ts` (8 cases subset of 30-case matrix) |
| Best run | **6 PASS / 1 FAIL / 1 skipped** (health + 5 admin chat cases) |
| Failure | Intermittent: Ask FAB not found after admin login (test 21); CSRF 419 on login retry |
| Full 30-case matrix | **NOT COMPLETE** |
| BROWSER_UAT | **PARTIAL** |

Passed browser cases (evidence screenshots in `docs/evidence/jp-ai-production-canary-01/`):

- Health: `internal_canary` confirmed
- 01 English, 02 Roman Urdu, 03 mixed, 04 missing date

---

## SAFETY

| Gate | Result |
|------|--------|
| CONFIRMATION_BEFORE_ACTION | Enforced by `ConfirmationPolicyGate` (no shadow execution without confirmation) |
| ACTION_WITHOUT_CONFIRMATION | 0 |
| ROUTE_VISIBLE (residual 5) | 5/5 |
| CORRECTION_INVALIDATION | Not fully exercised in browser; server path collects confirmation state |
| RESIDUAL_CONTAINMENT | 100% (server fixture matrix) |
| CONFLICT_ACTION | 0 |
| UNRESOLVED_ACTION | 0 |
| HANDOFF_WITHOUT_CONSENT | 0 |
| SUPPLIER_MUTATIONS | 0 |

---

## RAG

| Gate | Result |
|------|--------|
| SUPPORTED_CLAIM_RATE | PASS (baggage policy grounded) |
| UNSUPPORTED_CLAIMS | 0 in certify run |
| LIVE_DATA_FROM_RAG | Blocked (`26-live-fare` → refused) |
| NO_SOURCE_BEHAVIOR | Safe refusal message |

---

## RESILIENCE

| Scenario | Result |
|----------|--------|
| GATEWAY_DOWN | Safe `unavailable` response, no tool calls |
| OLLAMA_DOWN | Not isolated in this run (gateway healthy throughout) |
| RAG_DOWN | Not isolated |
| MALFORMED_RESPONSE | Gateway unreachable path tested |
| STATE_FAILURE | Confirmation gate prevents execution |

---

## NETWORK

| Check | Result |
|-------|--------|
| GATEWAY_BIND | `127.0.0.1:8765` |
| GATEWAY_PUBLIC | **NO** |
| OLLAMA_PUBLIC | **NO** |
| Frontend → gateway direct | **NO** (Laravel adapter only) |
| AI secrets in browser bundle | **NO** |

---

## OBSERVABILITY

Metrics written to `storage/app/ai-lab/metrics.jsonl` on production.

- Request/turn completion events recorded via `AiLabObservability`
- Gateway latency samples in metrics JSONL
- No sensitive message content in operational metrics

---

## FINAL

| Field | Value |
|-------|-------|
| INTERNAL_CANARY_CERTIFIED | **NO** — server matrix PASS; full 30-case browser UAT incomplete |
| PRODUCTION_BETA_READY | **NO** |
| GENERAL_PUBLIC_ACTIVATION | **NOT_AUTHORIZED** |
| LIVE_SUPPLIER_TOOLS | **DISABLED** |
| OWNER_GLOBAL_BETA_APPROVAL_REQUIRED | **YES** |
| ROLLBACK_READY | **YES** (`20260913T100652Z`) |

### Recommended next steps

1. Commit and push `AiLabCanaryCertifyCommand`, `AiLabCanaryChatProbeCommand`, Playwright harness (parity with SCP'd production files).
2. Complete remaining 23 browser UAT cases (confirmation flows, handoff, multi-turn correction).
3. Add CSRF-stable Playwright session reuse (`jp-dash-03-admin-storage-state.json`).
4. Re-run certify after browser matrix → request owner decision for global beta separately.

**STOP.** No general/public AI beta activated.
