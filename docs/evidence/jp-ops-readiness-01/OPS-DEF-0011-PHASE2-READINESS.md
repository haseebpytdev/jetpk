# OPS-DEF-0011 Phase-2 — Readiness (compatibility sunset)

**Date (UTC):** 2026-10-17  
**Mode:** SECURITY AUDIT + COMPATIBILITY ANALYSIS (no deploy, no mutations)  
**Base:** `a792bd0bc5988143d515fd8be0a9e705dc4b6d3f`  
**Production patch marker (unchanged this loop):**  
`922f1577…+ops-def-0005-0008@79da5477…+ops-def-0011-0012@9a3f2ba2`

## Decision

```text
OPS_DEF_0011_PHASE2_DECISION=C
OPS_DEF_0011_PHASE2_DECISION_LABEL=LEGACY_USAGE_UNKNOWN_REQUIRES_CONTROLLED_SUNSET
OPS_DEF_0011_STATUS=MITIGATED_PHASE1_DEPLOYED_PHASE2_SUNSET_PENDING
```

Hard removal (**A**) is **not** authorized this loop:

- Current app no longer **generates** legacy token URLs for new journeys.
- Historical DB structural hits for `/guest/bookings/…/access/` in `communication_logs` = **0**.
- Vhost access logging remains **unusable** (OPS-RISK-0017) → live legacy hit-rate **cannot** be proven.
- Browser bookmarks / shared Phase-1 exchange hops / stale mutation tabs may still exist outside DB message bodies.
- Token TTL (~30m) bounds secret lifetime but does not prove zero in-flight compat dependency.

## Phase 1 — Legacy token inventory (runtime vs non-runtime)

### LEGACY_TOKEN_ROUTE_REFERENCES (runtime)

| Ref | Location |
|-----|----------|
| `GET …/access/{token}` → `guest.bookings.exchange` | `routes/web.php` |
| `POST …/access/{token}/payment-proof` → `.legacy` | `routes/web.php` |
| `POST …/access/{token}/promo/apply\|remove` → `.legacy` | `routes/web.php` |
| `POST …/access/{token}/abhipay/start` → `.legacy` | `routes/web.php` |
| `POST …/access/{token}/cancellations` → `.legacy` | `routes/web.php` |
| LiveRouteSmokeCatalog sample URI | `app/Support/Audits/LiveRouteSmokeCatalog.php` |

**Count (named guest legacy routes):** 6 (1 exchange + 5 mutation aliases)

### LEGACY_TOKEN_URL_GENERATORS (runtime outbound)

| Surface | Result |
|---------|--------|
| Lookup redirect / JSON `redirect_url` | CLEAN → `guest.bookings.show` |
| Guest presenters / mutation_urls / document URLs | CLEAN token-free `/laravel/guest/…` |
| Blade guest cards / confirmation | No `/access/{token}` generators found |
| AbhiPay guest redirects | CLEAN → `guest.bookings.show` |
| Emails / SMS | Token may be **created** (`token_not_emailed=true`); **not** embedded in CTA URL |

```text
CURRENT_APP_GENERATES_LEGACY_TOKEN_URL=NO
CURRENT_APP_GENERATION_PATHS=(none)
```

### LEGACY_QUERY_TOKEN_REFERENCES / BODY

| Kind | Location | Role |
|------|----------|------|
| `?token=` | `GuestBookingAccessService::authorizeGuestAccess` | Compat consumer |
| `guest_token` body | same | Compat consumer |
| `$routeToken` path arg | mutation controllers | Compat consumer |

### LEGACY_EMAIL_LINK_GENERATORS / SMS

```text
LEGACY_EMAIL_LINK_GENERATORS=0
LEGACY_SMS_LINK_GENERATORS=0
```

`BookingCommunicationService::sendBookingRequestReceived` creates a guest token for session readiness metadata only (`token_not_emailed`).

### LEGACY_FRONTEND_REFERENCES

| Ref | Role |
|-----|------|
| `frontend/app/(public)/guest/bookings/[booking]/access/[token]/page.tsx` | CONSUMER → proxies to Laravel exchange |
| `frontend/features/standard-booking/lookup/guest-redirect.ts` | CONSUMER allowlist for one-hop legacy |
| Visual-audit fixtures/scenarios with `/access/test-token-…` | TEST_ONLY (not runtime generators) |

## Phase 2 — Runtime generation audit

```text
CURRENT_APP_GENERATES_LEGACY_TOKEN_URL=NO
```

Lookup, presenters, AbhiPay guest handoff, and booking-received email paths do not emit `/access/{token}` into user-facing URLs.

## Phase 3 — Historical link dependency (READ ONLY)

Prod probe on `communication_logs` (768 rows; columns `subject,message,error_message,meta`) and bookings text columns:

| Metric | Value |
|--------|-------|
| `HISTORICAL_LEGACY_LINK_RECORDS` | **0** |
| `OLDEST_LEGACY_LINK_DATE` | NONE |
| `NEWEST_LEGACY_LINK_DATE` | NONE |
| `LEGACY_LINKS_CREATED_AFTER_PHASE1_DEPLOY` | **NO** |

No raw tokens / emails / phones / booking references were printed.

**Caveat:** absence in stored message bodies does **not** prove absence of browser history, chat shares, or support-copied links.

## Phase 4 — Access log / traffic (READ ONLY)

| Field | Value |
|-------|-------|
| `VHOST_ACCESS_LOG_ENABLED` | **NO** (effective) |
| `VHOST_ACCESS_LOG_PATH` | `/home/pkjetp/logs/access.log` |
| `LOG_WRITABLE` | NO (for `pkjetp`) |
| `RECENT_REQUESTS_VISIBLE` | **NO** (file size 153 bytes; single line dated 2026-07-29) |
| `LEGACY_TOKEN_ROUTE_USAGE_MEASURABLE` | **NO** |
| `LEGACY_USAGE_TELEMETRY` | **UNAVAILABLE** |

No accessLog directives found under `/usr/local/lsws/conf/vhosts` for the probed account. Server logging **not** repaired this loop.

## Phase 5–6 — Controlled sunset design (C)

While telemetry is unavailable and generators are already clean:

1. **Keep** temporarily: GET exchange, `*.legacy` mutation aliases, `authorizeGuestAccess` query/body/routeToken fallbacks, Next `[token]` page.
2. **Keep permanently (until separate work):** session bind, clean routes, atomic rotation (OPS-DEF-0012), guest security headers.
3. **Do not** mint legacy URLs in new product surfaces (already true).
4. **Sunset gates (future cutover loop):**
   - Restore measurable access logging **or** ship sanitized app counter (route type + outcome + timestamp only; never token/path-with-token).
   - Timebox ≥ 2× guest token TTL after telemetry is live, **or** owner waiver with documented residual.
   - Then remove legacy per Phase-8 list; recovery UX: HTML → `/lookup-booking` (generic); JSON → 403.
5. Optional prep (not implemented this loop): deprecation response header on exchange; Cache/Log counter without token.

## Phase 7 — Access log redaction design (ops follow-up only)

| Field | Value |
|-------|-------|
| `ACCESS_LOG_TOKEN_REDACTION_FEASIBLE` | **YES** (once logging works) |
| `ACCESS_LOG_REDACTION_LAYER` | OpenLiteSpeed/vhost CustomLog / rewrite of request URI before log, **or** reverse-proxy log format |
| `REQUIRES_ROOT_CONFIG_CHANGE` | **YES** |
| `REQUIRES_RESTART` | **YES** (OLS graceful restart expected) |

**Ops follow-up ticket (do not execute here):**

1. Fix OPS-RISK-0017 so domain access.log receives current requests.
2. Redact path segment after `/guest/bookings/{id}/access/` and query `token=` / body fields in any access/error log that stores URIs.
3. Verify with synthetic non-production path that tokens never land in retained logs.
4. Only then use logs as Phase-2 cutover evidence.

```text
OPS_RISK_0017_STATUS=OPEN_ACCESS_LOGGING_UNAVAILABLE
```

(Token redaction is a dependent follow-up once logging is restored → then `OPEN_TOKEN_REDACTION_REQUIRED` until redaction ships.)

## Phase 8–10 — Code removal this loop

```text
LEGACY_EXCHANGE_REMOVED=NO
LEGACY_MUTATION_ROUTES_REMOVED=NO
QUERY_TOKEN_FALLBACK_REMOVED=NO
BODY_TOKEN_FALLBACK_REMOVED=NO
NEXT_LEGACY_ROUTE_REMOVED=NO
```

No generator cleanup required (none found). Sanitized telemetry **designed** but **not** implemented this loop (evidence-only; avoids unverified runtime change without deploy authority).

## Phase 11–14 — Tests

Evidence-only loop: re-run focused guest hardening suite on branch tip as regression baseline (no behavior change expected).

Supplier/payment mutation counters remain 0 by policy; no live API tests executed.

## Residual

```text
LEGACY_PATH_TOKEN_EXPOSURE=POSSIBLE (first hop only)
LEGACY_QUERY_TOKEN_COMPAT=PRESENT
LEGACY_MUTATION_TOKEN_ROUTES=PRESENT
ACCESS_LOG_REDACTION_PENDING=YES
OPS_DEF_0011_PHASE2_REQUIRED=YES (sunset cutover)
```

## Next required action

1. ChatGPT review this evidence PR (no auto-merge required beyond evidence merge).
2. Ops: restore vhost access logging (OPS-RISK-0017) + plan URI redaction.
3. Optional next code loop: sanitized legacy-hit counter (no token).
4. After telemetry/timebox: Phase-2 **cutover** PR removing legacy routes + Next `[token]` page; then production deploy separately.
