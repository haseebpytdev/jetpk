# Gateway URL Source Audit — JP-AI-PRODUCTION-CANARY-HTTP-GATEWAY-01

Generated: 2026-09-14

## Precedence

| Layer | Source | Effective value (production) |
|---|---|---|
| DEFAULT | `config/ai_lab.php` | `http://127.0.0.1:8765` |
| ENV | `OTA_AI_LAB_GATEWAY_URL` in production `.env` | `http://127.0.0.1:8765` |
| CONFIG | Laravel config cache (`bootstrap/cache/config.php`) | Must match ENV after deploy |
| RUNTIME_OVERRIDE (removed) | ~~`config(['ai_lab.gateway_url' => 'http://127.0.0.1:1'])` in certify command~~ | **Replaced** by request-scoped fault injection |
| TEST_OVERRIDE | `AiLabFaultInjectionContext` + `AiLabGatewayUrlResolver` | `http://127.0.0.1:1` only when mode=`SIMULATE_GATEWAY_DOWN` |

## Locations capable of setting/overriding gateway endpoint

| File | Mechanism | Scope | Notes |
|---|---|---|---|
| `config/ai_lab.php` | `env('OTA_AI_LAB_GATEWAY_URL', ...)` | Deploy-time config | Canonical default |
| `.env` / production env | `OTA_AI_LAB_GATEWAY_URL` | Persistent server env | Authoritative for production |
| `app/Services/Ai/Lab/AiLabGatewayUrlResolver.php` | Returns `127.0.0.1:1` when fault mode active | Request/process scoped | **Only** legitimate port-1 source |
| `app/Services/Ai/Lab/AiLabFaultInjectionContext.php` | Static mode flag | Request/process scoped | Cleared after each HTTP request |
| `app/Http/Middleware/ApplyAiLabCanaryFaultHeader.php` | Reads authorized headers | Single HTTP request | Requires canary eligibility + token |
| `app/Console/Commands/AiLabCanaryCertifyCommand.php` | `AiLabFaultInjectionContext::using()` | CLI process scoped | No longer mutates `config()` |
| `app/Services/Ai/Lab/HttpAiLabConsultantGateway.php` | Uses resolver, never sets URL | Consumer | Logs resolved URL on failure |
| `tests/Feature/Ai/AiLabControlledIntegrationPhase13Test.php` | `config(['ai_lab.gateway_url' => ...])` | PHPUnit only | Test environment |
| ~~`frontend/scripts/canary-gateway-control.mjs`~~ | ~~`systemctl stop` gateway~~ | **Global outage** | **Retired for case 28** |

## Port `127.0.0.1:1` root cause

**WHO:** `AiLabGatewayUrlResolver` when `AiLabFaultInjectionContext::mode()` is `SIMULATE_GATEWAY_DOWN`.

**WHEN (historical leak):** Prior certify command used `config(['ai_lab.gateway_url' => 'http://127.0.0.1:1'])` in-process; combined with case 28 harness stopping systemd globally, normal HTTP traffic saw `:8765 connection refused` or stale port `:1` in logs.

**SCOPE (after repair):** Request/process scoped only; middleware clears after each HTTP request; CLI uses `using()` with restore in `finally`.

**RESTORATION:** Automatic via middleware `finally` or `AiLabFaultInjectionContext::using()` `finally`.

## Failure injection modes

| Mode | Effect | Public trigger |
|---|---|---|
| `NORMAL` | Canonical gateway URL | N/A |
| `SIMULATE_GATEWAY_DOWN` | Resolver → `127.0.0.1:1` | Blocked without canary token |
| `SIMULATE_OLLAMA_DOWN` | Gateway client throws HTTP 503 | Blocked without canary token |
| `SIMULATE_MALFORMED_RESPONSE` | Gateway client throws malformed JSON | Blocked without canary token |

## CLI vs HTTP path equivalence

Both invoke `AiChatOrchestrator::handleChat()` → `AiLabAdapter` → `HttpAiLabConsultantGateway` → `http://127.0.0.1:8765/v1/consultant/turn`.

| Path | Uses gateway HTTP | URL |
|---|---|---|
| CLI `php artisan ai:lab-canary-certify` | YES | `config('ai_lab.gateway_url')` unless fault context active |
| HTTP `POST /api/public/ai/chat` | YES | Same resolver chain |

**CLI_AND_HTTP_PATH_EQUIVALENT=YES** (same network gateway class and URL resolver).

**Note:** CLI PASS alone is insufficient when HTTP runtime env diverges; both must be verified independently after repair.
