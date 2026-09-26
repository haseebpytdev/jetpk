# CQ41-R2 — Open-domain latency telemetry

## Fix

- `AiConversationalAgent::tryOpenDomainRespond` captures `latency_ms` / `calls` and sets `OPEN_DOMAIN_LATENCY_MS` (including failed attempts after a provider call).
- `SemanticBrain::generalAnswer` sums `SEMANTIC_LATENCY_MS + OPEN_DOMAIN_LATENCY_MS` into `TOTAL_MODEL_LATENCY_MS` and counts planner + open-domain model calls.
- Deterministic `OpenDomainResponseService` fallback is not counted as a model call; attempted Qwen latency remains honest.

## Local PHPUnit

79 passed / 616 assertions (CQ41 + Order-39 + Brain28 + Hybrid + smoke + CQ26 AI-first)

## CI note

GITHUB_PHPUNIT=SKIPPED_NO_VENDOR
