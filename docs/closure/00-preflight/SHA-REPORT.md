# JetPakistan final delta — pre-flight SHA report

Captured: 2026-09-20 (local + live SSH probe)

## Report fields

```text
LOCAL_HEAD=22a4e7596b02580af28e442456f500b63df7b0c6
CURRENT_BRANCH=perf/PERF-CORRECTION-01
ORIGIN_MAIN=675c7e5efaa2656b2435c186cfe4665e086676bb
  (tracked as jetpk/main; local origin/main ref absent — use jetpk/main)
PRODUCTION_RUNTIME_SHA=22a4e7596b02580af28e442456f500b63df7b0c6
PUBLIC_BUILD_SOURCE_SHA=22a4e7596b02580af28e442456f500b63df7b0c6
PUBLIC_BUILD_ID=mbGGmJZh1GgGZCIWsBdEV
DASHBOARD_BUILD_SOURCE_SHA=675c7e5efaa2656b2435c186cfe4665e086676bb
DASHBOARD_BUILD_ID=dOefZBIOnEl7EbTETViNa
RUNTIME_MARKER=jp-c08-675c7e5e-1789758710
ROLLBACK_SHA=4f1836c16afc8c521c2d6c6adff26863fb1f53e1
WORKTREE_STATUS=DIRTY
  - modified: .github/workflows/jp-final-visual-uat-artifact.yml
  - modified: docs/evidence/jp-final-visual-uat/* (harness)
  - large untracked: docs/evidence/*, tmp/*, release mirrors
CHECKPOINT_SHA=22a4e7596b02580af28e442456f500b63df7b0c6
CHECKPOINT_NOTE=Documented tip before final-delta homepage edits; no history rewrite.
```

## Ancestry proof

```text
e5eead090ad530eaef74999e7e36e8ba756bbd58  ancestor_of_HEAD=YES (merge-base --is-ancestor exit 0)
8793cc9fdad6a198a76251862e88b83aa97c756f  ancestor_of_HEAD=YES
22a4e7596b02580af28e442456f500b63df7b0c6  == HEAD
675c7e5efaa2656b2435c186cfe4665e086676bb  ancestor_of_HEAD=YES (jetpk/main; tip is 31 commits ahead)
```

Commits after best soft-nav `8793cc9f` on this branch:

```text
4f1836c1 perf(soft-nav): home anonymous ISR + loading; login Suspense
22a4e759 perf(soft-nav): Suspense-wrap about-us CMS body
```

## Soft-nav evidence (do not destroy)

| Pack | SHA | BUILD | PASS |
|------|-----|-------|------|
| Best soft-nav | `8793cc9f` | `aEdZnf5JjqhJtZyrKe0dr` | 8/10 (support_home 1578, home_login 1522) |
| Tip live | `22a4e759` | `mbGGmJZh1GgGZCIWsBdEV` | 5/10 (regressed) |
| Intermediate | `4f1836c1` | `9uCDdSnCJsWGlCp9_mFEN` | FAIL (see SUMMARY) |

Traveler/Return historical PASS remains on `e5eead09` only — not final same-SHA cert.

## Stale facts

- `RUNTIME_MARKER` still references historical `675c7e5e` — must be regenerated at final canonical release.
- `DASHBOARD_BUILD_SOURCE_SHA` still on `675c7e5e` while public tip is `22a4e759`.
- `jetpk/main` / remote main must **not** be fast-forwarded until all critical gates pass.

## Hard stop

Phase 0 complete for proceeding to Phase 1 (homepage interactive only).
Do not start SEO/URL migration or retirement until soft-nav + same-SHA perf PASS.
