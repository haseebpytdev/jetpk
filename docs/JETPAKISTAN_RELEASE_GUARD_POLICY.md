# JetPakistan — Release Guard Policy

**Status:** FINAL (2026-09-21)  
**Canonical release lock:** `docs/closure/jetpakistan-release-lock.json`  
**Canonical release manifest:** `docs/closure/JETPAKISTAN_CANONICAL_RELEASE.md`

## Core inequality

```
CODED ≠ DEPLOYED ≠ LIVE VERIFIED
```

A green CI build or a GitHub `main` tip is never sufficient for production PASS. Every release requires:

1. Exact authorized Git SHA through backup → stage → deploy (or scoped Next rebuild with stamps)
2. Build **before** PM2 restart
3. Runtime SHA stamps + BUILD_ID provenance on host
4. Live verification on `https://jetpakistan.pk` only

## Application release vs closure metadata

Separate identities (never conflate):

```
application_release_sha   = immutable commit whose tree produced live binaries
closure_metadata_commit_sha = optional later docs/lock/CI commit on main
```

- Binaries and host stamps must track **`application_release_sha` only**.
- `closure_metadata_commit_sha` may advance `REMOTE_MAIN` without becoming build authority.
- Do **not** treat `HEAD~1` / parent heuristics as release authority.
- Do **not** claim a metadata commit as binary source unless `npm run build` was actually run from that tree.

## Exact production stamp parity

Required equality after deploy (application release):

```
PRODUCTION_RUNTIME_SHA
  = PUBLIC_BUILD_SOURCE_SHA
  = DASHBOARD_BUILD_SOURCE_SHA
  = application_release_sha
```

`REMOTE_MAIN_SHA` may equal `closure_metadata_commit_sha` when a metadata-only tip follows the tagged application release. Tag remains on `application_release_sha` unless an explicit new annotated tag is cut.

Mismatch of runtime/source stamps vs `application_release_sha` ⇒ `BUILD_PROVENANCE=FAIL`.

## Build provenance

- Public and dashboard Next must be built from **`application_release_sha`** (even if dashboard code unchanged).
- Marker files (`.jetpk-runtime-sha`, `.jetpk-runtime-marker`, `.jetpk-*-source-sha`) never substitute for a real `npm run build`.
- Record `PUBLIC_BUILD_ID` and `DASHBOARD_BUILD_ID` from `.next/BUILD_ID` after that build.

## No production-only authority

Forbidden as sole authority:

- Manual OLS edits without a tracked snippet/script
- Server-only app source edits
- Unttracked hotfix helpers

Allowed server-side only:

- Secrets / `.env*` / credentials
- Runtime caches / storage / logs

Infrastructure logic must live in-repo (`deploy/openlitespeed/`, `docs/jetpk/ols-snippets/`, assert scripts).

## Route ownership

| Surface | Owner |
|---|---|
| `/flights/s/{ref}` | Public Next (OLS GET/HEAD → `jetpk_public_next`) |
| `/flights/results`, `/flights/return-options`, `/flights/fare-selection` | Public Next |
| `/groups` (exact), `/groups/search` | Public Next |
| Laravel catch-all / CMS pages | Laravel (must not capture short URL) |
| Dashboard portals | Dashboard Next (:3001) behind auth |

Guard: `scripts/jp-ols-assert-flights-short-url.sh` + unit test `OlsFlightsShortUrlSnippetTest`.

## Short URL safety

- Opaque Class-B refs only in browser URL
- No `search_id` / PII / long serialized state in normal browser URLs
- Refresh / back-forward / expiry / legacy long URL compatibility required
- Mutations not proxied by OLS short-url GET/HEAD guard

## SEO canonical safety

- Public indexables have canonicals
- Transactional / short search / results: noindex as designed
- Sitemap + robots tracked
- Private pages remain noindex
- IndexNow may be `NOT_APPLICABLE` when freshness uses CMS revalidate + sitemap `lastmod` (documented)

## Performance methodology

Do not reopen CMS cache / soft-nav / Traveler / Return architecture without a proven regression.

Gates (same-SHA certification baseline `cbd7686f`):

- Soft-nav P95 ≤ 1500ms
- Traveler P95 ≤ 2000ms
- Return `BROWSER_RENDER_MS` (post-supplier → paired card) P95 ≤ 1000ms
- Duplicate supplier calls = 0

`RETURN_METRIC_EQUIVALENCE` requires the harness definition of `BROWSER_RENDER_MS`, not `poll_total`.

## CMS cache invalidation

Preserve content-driven invalidation; do not replace with ad-hoc TTL experiments during closure.

## Retired path denylist

Paths classified `DELETE` in `docs/closure/JETPAKISTAN_RETIREMENT_INVENTORY.md` must not reappear in:

- imports
- routes
- builds
- deploy scripts
- OLS rules

CI: retired-path / OLS snippet / short-URL ownership guards.

## Commercial mutation ban (UAT)

Default UAT/closure: no payment, PNR create, order create, ticketing, void, refund.

## Rollback

Documented rollback SHA + OLS bak + env preservation + forward restoration. Do not leave production rolled back after a verification drill.

## Post-recovery deploy safety (2026-09-21)

Production deployment accepts only an authorized `main` SHA or the explicit rollback SHA.
Guards (see `scripts/jetpk/guard-*.sh`):

| Gate | Fail token |
|---|---|
| Dirty worktree | `WORKTREE_DIRTY=FAIL` |
| Unauthorized / mismatched source SHA | `SOURCE_SHA_NOT_AUTHORIZED=FAIL` / `BUILD_SOURCE_SHA_MISMATCH=FAIL` |
| Missing replacement `.next` before PM2 restart | `BUILD_BEFORE_RESTART_GUARD=FAIL` |
| Disk below reserve | `DISK_SPACE_GUARD=FAIL` |
| Release retention | default **DRY-RUN** via `release-retention-dry-run.sh` |

Sequence: backup → stage → install → build → verify BUILD_ID → switch/restart → health → live smoke → stamp.
If build fails, leave current runtime untouched.
