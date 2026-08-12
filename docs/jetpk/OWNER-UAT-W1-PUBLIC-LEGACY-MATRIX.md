# OWNER-UAT-W1 — Public Legacy Matrix

## Policy

- Browser/document requests → Next JetPakistan branded surface or canonical redirect to Next
- JSON/API requests → JSON only
- Parwaaz / master OTA / generic Blade card shells must not appear on public browser paths

## Matrix

| Boundary | Public path / trigger | Classification | Evidence / notes |
|---|---|---|---|
| Next not-found | unmatched Next App Router | NEXT_JETPAKISTAN_BRANDED | `frontend/app/not-found.tsx` |
| Next error | App Router error boundary | NEXT_JETPAKISTAN_BRANDED | `frontend/app/error.tsx` |
| Laravel HTML 404 | unknown public URL via Laravel | CANONICAL_REDIRECT_TO_NEXT | production → `/access-denied?reason=not-found` (OLS-safe existing Next route) |
| Laravel HTML 403 | authorization | CANONICAL_REDIRECT_TO_NEXT | → `/access-denied?reason=forbidden` |
| Laravel HTML 419 | CSRF/session | CANONICAL_REDIRECT_TO_NEXT | → `/login?reason=session-expired` |
| Laravel HTML 429 | throttle | CANONICAL_REDIRECT_TO_NEXT | → `/access-denied?reason=rate-limited` |
| Laravel HTML 500/503 | server | CANONICAL_REDIRECT_TO_NEXT | → `/access-denied?reason=service-error` / `unavailable` |
| Laravel JSON errors | `Accept: application/json` | INTERNAL_API_JSON | unchanged |
| Dashboard API errors | `/api/dashboard/*` | INTERNAL_API_JSON | DashboardReadOnlyEnvelope |
| Access denied | `/access-denied` | NEXT_JETPAKISTAN_BRANDED | Next page (also public error landing) |
| Login | `/login` | NEXT_JETPAKISTAN_BRANDED | Next page |
| Prefixed legacy login | `/jetpk/login` | CANONICAL_REDIRECT_TO_NEXT | 302 → `/login` |
| Lookup booking | `/lookup-booking` | NEXT_JETPAKISTAN_BRANDED | Next page |

## Counts

- `LEGACY_RENDER=0` for production browser document errors (redirect to Next; no Parwaaz/master Blade)
- `UNKNOWN=0`

## Note

New Next-only paths such as `/page-not-found` are not OLS-routed today. Without OLS changes, Laravel HTML errors must redirect to already-proxied Next routes (`/access-denied`, `/login`).