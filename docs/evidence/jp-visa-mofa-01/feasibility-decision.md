# Feasibility decision

## Decision matrix result: **CASE B**

| Dimension | Result |
|---|---|
| TECHNICAL_FEASIBILITY | **PASS** (protocol for session + human captcha relay + form POST established; success/PDF end-to-end unproven without authorized sample) |
| POLICY_FEASIBILITY | **PENDING** |
| PRODUCTION_POLICY_APPROVAL_REQUIRED | **YES** |
| RECOMMENDED_IMPLEMENTATION | `OPTIONAL_SERVER_SIDE_HUMAN_CAPTCHA_RELAY_AFTER_WRITTEN_POLICY_APPROVAL` with fallback `NATIVE_JETPAKISTAN_LANDING_PAGE_WITH_OFFICIAL_MOFA_REDIRECT` |
| NEXT_PHASE | **WAIT_FOR_POLICY_APPROVAL** |

## Why not CASE A

Terms/authorization do not clearly permit automated/server-mediated third-party commercial relay or PDF redistribution.

## Why not CASE C

Captcha image is legitimately retrievable; session cookies + antiforgery exist; human-solved captcha relay is architecturally viable without bypass. No anti-bot block observed under light legitimate inspection.

## Why not CASE D

No official visa-lookup partner API discovered.

## Rate / anti-automation observation

| Flag | Value |
|---|---|
| MOFA_RATE_LIMIT_OBSERVED | **NO** (under light legitimate probes only) |
| MOFA_ANTI_BOT_BLOCK_OBSERVED | **NO** |

## Certification

| Flag | Value |
|---|---|
| MOFA_01_CERTIFICATION | **PASS_FEASIBILITY_CASE_B** |
| FINAL_STATUS | **COMPLETE_NO_PRODUCTION_ACTIVATION** |

## Immediate product-safe path (if policy denied)

Ship JetPakistan Visa landing that explains the service and opens/links the official MOFA URL (`https://visa.mofa.gov.sa/visaservices/searchvisa`) — no iframe, no scraping.
