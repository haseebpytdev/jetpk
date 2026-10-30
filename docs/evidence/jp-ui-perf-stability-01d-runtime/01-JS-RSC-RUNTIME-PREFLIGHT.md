# JP-UI-PERF-STABILITY-01D — JS / RSC / Runtime Preflight

## Authority

- `MAIN_BASE_SHA=1e11e2de94382541331a66275b8f04a12065e65e`
- `PRODUCTION_FRONTEND_BASE_SHA=4697b228e3e2c19c788cd0996a8d621a46b381d6`
- `PHASE_D_PRODUCTION_MAIN_PREIMAGE_PARITY=8_OF_8_PASS`
- Production remains unchanged this loop (`PRODUCTION_DEPLOYED=NO`)

## BASE production network (fresh 2026-10-30)

Source: `01-base-network-summary.json` against `https://jetpakistan.pk` (cache disabled).

| Viewport | Total | JS | Idle RSC | Idle routes |
|---|---:|---:|---:|---|
| 390 | 829328 | 402763 | 0 | — |
| 768 | 1073767 | 647202 | 0 | — |
| **1440** | **1578928** | **802443** | **1** | `/groups/search` (5490 B) |

Canonical desktop production control: `BASE_JS_TRANSFER_BYTES=802443`, `BASE_IDLE_RSC_COUNT=1`, `BASE_IDLE_RSC_ROUTES=/groups/search`.

Same-host local BASE (prod SHA build, networkidle, force-AI): `01-base-local-network-summary.json` → `BASE_LOCAL_JS=905103`.

Tesseract: `0` requests / `0` bytes → `OCR_RUNTIME_CHANGE_REQUIRED=NO`.

## Chunk ownership

Source: `01-base-chunk-ownership.json`.

- `INITIAL_JS_ACCOUNTED_PERCENT=97.4` (≥90 gate)
- `UNKNOWN_JS_BYTES=20149` (shared util medium-confidence OTHER_APP)
- Top initial: Next/React (~174KB + ~173KB), OpenTelemetry (~156KB), Search (~68KB + ~34KB), **Ask (~60KB / chunk 9583)**
- `C_REQUIRED=YES` (`ASK_INITIAL_CHUNK_BYTES=60662`)

## Hypothesis outcomes

### A — disable priorityPrefetch idle RSC — REJECTED

- Soft-nav material regression on `groups_home` (+180ms / +85.7% P95) in paired A+C sequential run
- `priorityPrefetch` preserved for `/groups/search` and `/login`
- Tree sanitized to MAIN for nav/header

### B — PublicHero server component — REJECTED

- No isolated measurable JS/TBT benefit
- `PublicHero` remains client + dynamic `SearchModule`
- Tree sanitized to MAIN

### C — Ask lazy panel — ACCEPTED

- Thin `AskJetPakistanChat` FAB + imperative dynamic `AskJetPakistanPanel`
- Same-parent selective overlay network (`01-selective-c-network-summary.json` / final):
  - `BASE_LOCAL_JS=905103` → `FINAL_JS=883355` (−21748 / −2.4%)
  - Ask panel chunk absent on idle homepage; transfers on first open (`01-ask-lazy-open-gate.json`)
- Interleaved soft-nav N=20: no material P95 regression (`01-soft-nav-interleaved.json`)
- AutoOpen one-shot fix prevents close→reopen loop

## Accepted set

```
ACCEPTED_A=NO
ACCEPTED_B=NO
ACCEPTED_C=YES
```

## Production safety

```
AIRBLUE_OR_SUPPLIER_PROVIDER_CALLS=0
BOOKING_MUTATIONS=0
PAYMENT_MUTATIONS=0
DATABASE_MUTATIONS=0
PRODUCTION_MUTATIONS=0
```
