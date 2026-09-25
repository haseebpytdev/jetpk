# CQ28-36 evidence notes

Authoritative PASS run: **r2-*** only.

- `r1-run-console.txt` = FAIL (91.72%, CRITICAL=26)
- `r1-summary.txt` = restored to match r1 console (superseded)
- `r1-results.json` = may mirror post-fix scoring; **do not treat as independent r1 proof**
- `r2-summary.txt` / `r2-results.json` / `r2-run-console.txt` = cert PASS

Scoring: SEMANTIC_VALID_RATE is **hybrid-safe** (correct plans + safe CQ27 fallbacks after server policy blocks). Always cite `SAFE_FALLBACK_COUNT` + `SCORING_NOTE` with the 100% figure.
