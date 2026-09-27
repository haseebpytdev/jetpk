# CQ43 baseline

Date: 2026-09-27  
Production: https://jetpakistan.pk/  
Host: 185.215.166.176

## SHA parity

| Field | Value |
|---|---|
| START_MAIN_SHA | `b64588a3fb0d4cafa075898b0495133199412de2` |
| PRODUCTION_RUNTIME_SHA | `83f24c145407fe16008efcb96cdea1b2ac34bfcf` (expected) |
| Commits after runtime | PR #37 + PR #38 evidence/docs only under `docs/evidence/jp-ai-cq42-r3-production-closure/` |
| APPLICATION_CODE_PARITY | PASS (no app/routes/config delta vs runtime) |

## Policy

- Non-mutating production UAT
- `CONTROLLED_SEARCH_EXECUTION=SKIPPED_BY_UAT_POLICY`
- Rate limiter unchanged (30 sends / 60s / visitor)
- No application code patches during diagnostic soak
