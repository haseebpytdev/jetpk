# JetPK Homepage CMS — Client Feature test improvement

**Integration HEAD:** `d3c38fe` (post-hygiene commit pending)
**Baseline:** `624f3dd`

## Totals

| Worktree | Pass | Fail | Skip | Tests |
|----------|-----:|-----:|-----:|------:|
| Integration | 44 | 45 | 6 | 95 |
| Baseline | 19 | 52 | 6 | 77 |

**Net:** +25 passing tests, −7 failing tests, +18 tests in suite. **No tests weakened, skipped, or renamed** to manufacture the improvement.

## Why the suite grew (+18 tests)

Six new CMS integration test classes were added on the integration branch (not present at baseline):

| File | Tests | Role |
|------|------:|------|
| `HomepageDraftPublishPipelineTest.php` | 8 | Draft → publish pipeline, validation, ordering |
| `HomepagePublishRevisionIntegrationTest.php` | 4 | Revision snapshots on publish |
| `HomepageHostResolutionTest.php` | 3 | Host / profile resolution |
| `HomepageContentNormalizationIntegrationTest.php` | 2 | Stale groups migration on read |
| `HomepageCmsContentNeutralityTest.php` | 1 | Migrate boot hash preservation |
| `DefaultClientCanonicalRedirectTest.php` | (extended) | Existing file; 7 cases repaired (below) |

All **18 net-new test methods pass** on integration (45 remaining failures are the same pre-existing Client parity set as baseline, minus the 7 repairs).

## Tests repaired (fail @ baseline → pass @ integration)

All seven map to **`DefaultClientCanonicalRedirectTest` fixture theme fix** (`1ff4658` / `JetpkLocalAuditFixtureCommand` + test `makeProfile` themes `v1-classic` → `jetpakistan`). Baseline returned **500 on login** when canonical redirect targets hit legacy theme layout.

| Test | Integration change |
|------|-------------------|
| `test_default_slug_prefixed_paths_redirect_to_canonical_root` data set **root** | JetPK theme fixture → `/jetpk` → `/` returns 302 not 500 |
| same data set **home alias** | `/jetpk/home` → `/` |
| same data set **login** | `/jetpk/login` → `/login` renders JetPK auth |
| same data set **admin** | `/jetpk/admin` → `/admin` guest redirect works |
| same data set **staff** | `/jetpk/staff` → `/staff` |
| same data set **agent** | `/jetpk/agent` → `/agent` |
| `test_is_default_deployment_slug_helper` | Profile resolver with JetPK theme |

## Unchanged failure overlap

The remaining **45** integration failures are **the same test names** as **45** of baseline's **52** failures (7 repaired above). No new Client failure names were introduced by CMS work.

## Confirmation

- No `@skip` added to passing tests
- No assertions weakened on repaired tests
- No renames of failing tests to exclude them from the suite
- Improvement is **+18 new passing CMS tests** and **+7 repaired redirect tests**
