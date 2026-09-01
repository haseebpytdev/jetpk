# Technical decision R2

## TECHNICAL_E2E_FEASIBILITY

**PASS** — authorized live lookup succeeded; structured HTML fields proven; session-bound official document page proven; byte-preserving relay proven for official **HTML** document.

### Important PDF caveat (truthful)

| Item | Result |
|---|---|
| MOFA returns `application/pdf` on success path | **NO** |
| Official document | HTML `/Home/PrintedUmrahVisa` |
| Native PDF relay | **NOT APPLICABLE / NO** |
| HTML document relay | **YES** (SHA256 matched in-memory) |

Product should not claim “MOFA PDF download API” unless a future path is observed. Offer structured summary + official document/print experience instead.

## SERVER_SIDE_ADAPTER_RECOMMENDED

**YES** — cookie jar, CSRF, human CAPTCHA relay, POST search, parse/display structured fields, optional short-lived HTML document stream. Fail closed on signature change. Never map provider errors to “visa not found”.

## Policy (unchanged gate)

| Flag | Value |
|---|---|
| MOFA_POLICY_CERTAINTY | LOW |
| PRODUCTION_POLICY_APPROVAL_REQUIRED | YES |
| WRITTEN_POLICY_APPROVAL_RECEIVED | NO |
| POLICY_FEASIBILITY | PENDING |

## NEXT_PHASE

`JP-VISA-MOFA-01B_OPTIONAL_MODULE_SHELL_WHILE_POLICY_PENDING`

01B may scaffold offline optional module only — **must not** activate live MOFA provider or public Visa page.

## Official integration recheck

| Flag | Value |
|---|---|
| OFFICIAL_MOFA_API_DISCOVERED | NO (visa lookup/print) |
| OFFICIAL_PARTNER_INTEGRATION_DISCOVERED | NO public OTA lookup API |

## Module boundary

Optional, uninstall-safe, not coupled to AI/Chatwoot/core booking.
