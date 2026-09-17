# JetPakistan Final Authority Ledger

**Mission:** `/JETPAKISTAN-FINAL-SYSTEM-RECONCILIATION-PRODUCTION-CERTIFICATION-01`  
**Generated:** 2026-09-17  
**Start point (reconciled):**

| Field | Value |
|---|---|
| START_BRANCH | `main` |
| START_LOCAL_HEAD | `4b111929fd8b25482332abc278e72dbbea46c563` |
| START_REMOTE_MAIN (`jetpk/main`) | `4b111929fd8b25482332abc278e72dbbea46c563` |
| AHEAD_BY | 0 |
| BEHIND_BY | 0 |
| TRACKED_MODIFIED | 0 |
| UNTRACKED | evidence/tmp/seo scripts + worktrees (classified later) |
| LOCAL_COMMITS | none beyond remote |
| Remote name | `jetpk` (not `origin`) |

Status vocabulary: `CLOSED` | `REGRESSED` | `PARTIAL` | `OPEN` | `BLOCKED_EXTERNAL` | `SUPERSEDED` | `ACCEPTED_DEBT` | `NEEDS_VERIFICATION`

---

## Historical authorities (located)

| ID | Subsystem | HISTORICAL_LAST_GOOD_SHA | Notes |
|---|---|---|---|
| A | Golden public / Authority-06 | `67510da49dfaeb5bef676cd4c11981c6b287dc02` | Branch lineage `phase/jp-homepage-cms-authority-06`; summary in worktree docs |
| B | Return Pair | `6057f8d9344dd54e1f52c95879ad178f7cdd6f0a` | Pair inventory usability |
| C | Ask JetPakistan UI | `544904ab951fb142de3f429296f51d259d76caa0` | Redesign Ask + support FAQ |
| D | Checkout Wave-7 / passport OCR | `f7796f89` + `968e322f` / `8cf657d7` | Wave-7 manifest + OCR harden |
| E | Email canonicalization | `0e8ba5f4` → `da660bf5` (`92fc5a6f` auth delivery) | Latest shell unify |
| F | Group ticketing | `e7ccb44a` / `800bb07f` / `9100d2ff` | UI + `/groups/search` soft-nav |
| G | CMS / SEO | `ad5f29c7` / `27be8ece` | SSR SEO + homepage SSR Laravel URL |
| H | Recovery / performance lineage | `20db691c` → `4b111929` | **HEAD includes biased traveler retry tests — integrity debt** |

---

## Subsystem matrix (initial)

| Subsystem | HISTORICAL_LAST_GOOD_SHA | CURRENT_CODE_PRESENT | CURRENT_TEST_PRESENT | CURRENT_RUNTIME_STATE | CURRENT_STATUS |
|---|---|---|---|---|---|
| Login OTP gate (configurable OFF) | Wave-0 OTP-off `b220b848` + env contract | Partial — `ClientLoginOtpGate` **hardcodes jetpk=true** | Yes (`JetPkLoginOtpTest`, auth suite) | Owner: OTP “off” still mails; login stuck | **REGRESSED** |
| Admin Security OTP control | Intended admin→runtime | UI is **local preview only**; MFA strings not wired to login OTP | Dashboard smoke only | Toggle does not persist / does not gate login | **REGRESSED** |
| OTP email branding/URLs | `0e8ba5f4` / `da660bf5` | Present; fallbacks still `www.jetpakistan.com` + `/jetpk/lookup-booking` | Partial | Stale domain/routes in OTP mail | **REGRESSED** |
| Destinations on the Rise | CMS Authority-06 + Blade section | CMS+API+`DestinationsSection` exist; **omitted from Next `HomepageContent`** | Fixture conflates title onto `routes` | Missing on Next homepage | **REGRESSED** |
| Trending Routes / Featured Deals | Authority-06 | Yes | Yes | NEEDS_VERIFICATION live | NEEDS_VERIFICATION |
| Return Pair card | `6057f8d9` + results perf | Yes | Perf harnesses | NEEDS_VERIFICATION | NEEDS_VERIFICATION |
| Segmented return | Wave commerce | Yes | Yes | NEEDS_VERIFICATION | NEEDS_VERIFICATION |
| Passport OCR | Wave-7 `968e322f` | Yes | Yes | NEEDS_VERIFICATION | NEEDS_VERIFICATION |
| Ask FAB / AI | `544904ab` | FAB present; AI settings service **missing on main** (in worktree only) | Partial | NEEDS_VERIFICATION | PARTIAL |
| Groups | recent groups fixes on HEAD | Yes | Yes | NEEDS_VERIFICATION | NEEDS_VERIFICATION |
| CMS publish/revalidate | SEO/CMS commits | Yes | Yes | NEEDS_VERIFICATION | NEEDS_VERIFICATION |
| SEO Phase 1/2 | `ad5f29c7` | Yes | Playwright scripts untracked | NEEDS_VERIFICATION | NEEDS_VERIFICATION |
| Performance evidence integrity | Pre-bias baselines | Biased retry in traveler/soft-nav harnesses on HEAD | Present but invalid methodology | Invalid for certification | **REGRESSED** |
| Exact SHA deploy parity | Prior golden closure | Process docs restored | Deploy scripts | NEEDS_VERIFICATION | NEEDS_VERIFICATION |

---

## P0 reopen rules applied

Reopened only where current evidence shows:

1. OTP OFF contract broken (hardcode + non-persisting admin MFA preview)
2. Destinations section renderer omission on Next
3. Stale email host/path fallbacks
4. Biased performance retry methodology on current main

Do not wholesale restore old commits.

---

## Changelog

| Date | Change |
|---|---|
| 2026-09-17 | Initial ledger from git + code/docs exploration at `4b111929` |
