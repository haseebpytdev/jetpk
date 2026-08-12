# JP-UAT-01 — Final Report

## Result

**JP_UAT_01=AUTONOMOUS_BUSINESS_UAT_PASS_AWAITING_OWNER_SANITY_REVIEW**

This is autonomous business UAT acceptance only. It is **not** a declaration that JetPakistan is launched.

Evidence layers (do not blend):

| Layer | Result |
|-------|--------|
| SCRIPTED_DETERMINISTIC_UAT | PASS — `SCRIPTED_BUSINESS_UAT_SCORE=92` (retained) |
| AGENTIC_BLACK_BOX_UAT | PASS — Playwright CLI personas; `AGENTIC_BLACK_BOX_UAT_SCORE=90` |
| AUTHORITATIVE_VERIFIER | PASS — API/state, F004/F005, OLS, QA cleanup |

## 1. Tools used

- **Agentic:** Microsoft Playwright CLI + skills (`playwright-cli` sessions `uat-anonymous|customer|agent|staff|admin|explore`)
- **Scripted (retained):** `dashboard/scripts/jp-uat-01/*` deterministic explorers
- Deterministic verifiers: PHPUnit `JpOps08*` (15/85), Playwright `jp-ops-08-support-two-way.spec.ts`
- Production SSH (approved jetpk key) for QA lifecycle, builds, OLS hash, source parity

## 2–3. Tooling boundaries

```
BROWSER_USE=UNAVAILABLE_CREDENTIAL_BOUNDARY
PLAYWRIGHT_MCP=NOT_ACTIVE_SESSION_BOUNDARY
PLAYWRIGHT_CLI_SKILLS=ACTIVE
AGENTIC_PERSONA_EXECUTION=PASS
```

## 4. Deterministic verifier stack

- `tests/Feature/Ops/JpOps08*.php` — PASS 15/15, 85 assertions (prior scripted closure; not re-broken)
- Support loop ticket `SEWJRVS9` retained as scripted evidence
- Post-F005: agent `/laravel/agent?format=json` (+ bookings/wallet) return `application/json`

## 5–6. Personas

### Scripted (authoritative regression)

Unchanged PASS set from tip `8a8959f` closure (score 92).

### Agentic (this supplement)

See `docs/jetpk/JP-UAT-01-AGENTIC-PERSONA-RESULTS.md`.

| Persona | Result |
|---------|--------|
| Anonymous | PASS |
| Customer | PASS (F001/F004 blind revalidation) |
| Agent | PASS after F005 fix + blind rerun |
| Staff | PASS |
| Admin | PASS |
| Exploratory charter | PASS |

## 7–10. Defects

| ID | Severity | Status | Summary |
|----|----------|--------|---------|
| UAT-F001 | P1 | PASS | Portal/Support discoverability |
| UAT-F002 | P2 | PASS | Turnstile messaging |
| UAT-F003 | P3 | ACCEPTED_NONBLOCKER | Customer Dashboard→bookings |
| UAT-F004 | P1 | PASS | access-denied on unauthorized console |
| UAT-F005 | P1 | PASS | `/laravel` rewrite hit OLS Next SPA rules for `/agent/*` & `/customer/*`; fixed via `/index.php/:path*` rewrite + HTML `_html` API rejection |

**P0_COUNT=0**  
**P1_COUNT=0** (open)

## 11–20. Gate summary

Prior scripted gates remain PASS. Agentic supplement confirms:

- No mandatory persona required hidden route/source knowledge after F005
- Customer/Agent naturally find Dashboard/Support/Wallet
- Staff/Admin discover ops work and Support without coaching
- F004 still holds under agentic admin probe by customer/agent

## 21. Source parity

MATCH=yes for F005 deploy set:

- `frontend/next.config.ts`
- `frontend/lib/api/laravel-action-client.ts`
- `frontend/features/agent-dashboard/overview/AgentOverviewPage.tsx`
- `frontend/features/agent-dashboard/bookings/AgentBookingsPage.tsx`

Prior F001/F004 deploy set remains MATCH from earlier closure.

## 22. OLS

`sha256sum /usr/local/lsws/conf/httpd_config.conf` =

`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

MATCH. OLS unmodified (F005 fixed in Next rewrite, not OLS).

## 23. QA cleanup

Executed at supplement closure: identities suspended; sessions cleared; remember tokens null; login denial proven; normal OTP required; OTP_DEMO_* preserved.

## 24. Human-only residual

Owner sanity review only. No merge. Not launch-ready declaration.

## 25. Scores

| Score type | Value |
|------------|-------|
| SCRIPTED_BUSINESS_UAT_SCORE | 92 |
| AGENTIC_BLACK_BOX_UAT_SCORE | 90 |

## Final status

`JP_UAT_01=AUTONOMOUS_BUSINESS_UAT_PASS_AWAITING_OWNER_SANITY_REVIEW`
