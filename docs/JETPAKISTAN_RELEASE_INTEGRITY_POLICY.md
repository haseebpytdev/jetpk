# JetPakistan Release Integrity Policy

Authority: JetPakistan production on `https://jetpakistan.pk` (`185.215.166.176`).
Companion docs: `docs/jetpk/DEPLOYMENT-CONTEXT.md`, `docs/PRODUCTION_DEPLOYMENT_SAFETY.md`.

## Lifecycle states

| State | Meaning |
|---|---|
| **CODED** | Change exists on an authorized Git SHA on `main` (or an approved recovery branch that will land on `main`). |
| **DEPLOYED** | Exact SHA is live on Laravel runtime (`.jetpk-runtime-sha`) and any rebuilt Next apps; PM2 healthy. |
| **LIVE VERIFIED** | Production browser/API evidence proves the acceptance gates for that SHA. |

No release may be called complete before **LIVE VERIFIED** for the gates in scope.

## Exact SHA parity

Before closure, prove:

```text
REMOTE_MAIN_SHA
= RELEASE_SHA
= PRODUCTION_RUNTIME_SHA          # /home/pkjetp/jetpk_app/.jetpk-runtime-sha
= PUBLIC_BUILD_SOURCE_SHA         # frontend/.jetpk-next-build-source-sha
= DASHBOARD_BUILD_SOURCE_SHA      # dashboard/.jetpk-dashboard-source-sha
= RUNTIME_MARKER
```

Also record `PUBLIC_BUILD_ID`, `DASHBOARD_BUILD_ID`, and `ROLLBACK_SHA`.

**No parent-build exception.** If Laravel advances, Next source stamps must be rebuilt or otherwise updated from the same authorized SHA—not left on a parent commit.

## No production-only fixes

- Every production mutation must originate from Git at the authorized SHA.
- Protected backup → stage → deploy (and Next build when frontend/dashboard change) is required.
- SFTP/SCP is allowed only as an operational transport for staged artifacts or scripts—not as a substitute for an untracked hot patch.
- Do not force-push, rewrite history, or `git reset --hard` / `git clean` on production recovery paths.

## Build before restart

- Do not restart `jetpk-public-frontend` or `jetpk-dashboard` until a valid build for the authorized SHA exists.
- Preserve `frontend/.env.production.local` across builds and deploys.

## Environment preservation

- Never overwrite production `.env` or Next `.env.production.local` from a release archive.
- Never commit or log secrets, OTP codes, tokens, cookies, or private keys.

## Required regression / UAT assertions

At minimum for golden / final reconciliation closures:

1. **Golden UI** — public homepage and flight results use the approved Golden components (no stale card regressions).
2. **Pair card** — return Pair shows OUTBOUND | RETURN | TOTAL/ACTION on one paired card (no “outbound + N returns” regression).
3. **Segmented** — outbound → return option stages both use updated Golden cards.
4. **Destinations** — Destinations on the Rise (and Trending / Featured / Hero / Support CTA) render from live CMS/config.
5. **FAB** — Ask JetPakistan FAB opens without duplicate banners; respects Admin AI ON/OFF.
6. **Favicon / branding** — canonical JetPakistan assets; no broken logo/favicon.
7. **OTP OFF/ON** — Admin UI persist → refresh → runtime; restore owner-intended final state (`LOGIN_OTP=OFF` unless owner directs otherwise).
8. **Email canonical URLs** — received MIME (not source alone) uses `https://jetpakistan.pk`, correct Manage Booking `/lookup-booking`, current logo; no `www.jetpakistan.com`, `/jetpk/…`, or localhost.
9. **CMS revalidation** — draft does not leak; publish → public API/Next/browser; restore → publish restoration.
10. **Network clean gate** — unexplained asset failures = 0 on major routes (favicon, logo, CMS media, Tesseract, chunks, RSC).
11. **SEO regression** — public SEO endpoints and key pages remain healthy after CMS/publish.
12. **Safe commercial UAT boundary** — no supplier booking/ticket/cancel/refund/payment mutations for QA.

## Email delivery rule

`Mail::send` / “dispatched” is **not** delivery.

OTP/security email PASS requires:

- `SMTP_ACCEPTED=YES` (transport accept / Message-ID trail), and
- `GMAIL_OBSERVED=YES` (human/ChatGPT confirmation of the received message).

## Performance harness policy

- No retry because a sample is slow; no discard because a sample misses target; no hidden warming.
- Retries only for genuine harness failure, counted separately.
- Final metrics use one final `BUILD_ID`.

## Rollback

- Record `ROLLBACK_SHA` before each production mutation.
- Prefer restore from pre-deploy backup + redeploy of the last LIVE VERIFIED SHA.
- Keep backup timestamps and archive paths in evidence.

## Main branch protection (required)

GitHub `main` must enforce:

1. No direct force-push.
2. PR reviews for human-authored feature work when a second reviewer is available; agent push of authorized recovery SHAs must still be exact-SHA deployable and evidenced.
3. Status checks for the repository’s required PHPUnit / build gates when configured.
4. Linear history preferred; no history rewrite on `main`.

## Evidence

Store deploy/UAT evidence under `docs/evidence/…` with SHA, BUILD_IDs, backup TS, and gate outcomes. Do not claim VERIFIED PASS from an implementation summary alone.
