# JP-FINAL-CLOSURE-01 — Deployment report

## Attempt 1 — mail robustness (failed gate)

- timestamp: 2026-08-28T21:21Z
- engineering SHA: `fa6dfdc403388232956a1a2062089a65d296b2b0`
- result: FAIL `STAGED_SHA_GATE_FAIL` (Windows-staged meta CRLF / archive meta mismatch)
- rollback: none (pre-mutation)

## Attempt 2 — mail robustness (PASS)

- timestamp: 2026-08-28T21:25Z–21:27Z
- engineering SHA: `fa6dfdc403388232956a1a2062089a65d296b2b0`
- backup ID: `jp-final-closure-01-mail-20260828T211049Z`
- build ID (unchanged, Laravel-only): `Q95djxDUc9lkeFU49cbLB`
- OLS: `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` PASS
- LIVE_SOURCE_DRIFT: 0
- PM2: public online, dashboard online
- migrations: 0
- commercial snapshot:
  - ALHAIDER_BOOKING_ENABLED=false
  - ALHAIDER_CANCEL_ENABLED=UNSET
  - SABRE_CANCEL_* flags observed true (pre-existing; not mutated this release)
- smoke: `/` `/login` `/verify-email` `/groups` → 200
- result: **ACTIVATE=PASS**
- rollback: not required

## Not deployed

- `9200165a` QA mailbox sink + branding seed tweak (local/remote branch only; sink off by default)
- `7d4302c7` Groups hero engineering (still undeployed relative to pre-mail live; present in fa6dfdc4 ancestry for code, but groups frontend assets from that commit were not in the 9-file mail manifest)
