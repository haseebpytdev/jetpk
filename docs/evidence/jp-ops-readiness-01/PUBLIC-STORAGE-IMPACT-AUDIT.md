# Wave 1B — public/storage Impact Audit

**Mode:** READ-ONLY (no `storage:link`)

## Facts

| Check | Result |
|-------|--------|
| Path exists | YES — directory |
| Is symlink | **NO** |
| Size | ~91M |
| Contains | agencies, airline-logos, client-assets, jetpk, travel-assets |
| `storage/app/public` | exists (~58M) |
| App expects `/storage/*` URLs | YES (Laravel convention / public assets) |
| Live breakage proven this wave | **NOT PROVEN** — site `/` and `/up` 200; assets may be served from this real directory |

## Classification

```
PUBLIC_STORAGE_CLASSIFICATION=LEGACY_DIRECTORY|BLOCKED_BY_EXISTING_FILES
REQUIRED_MISSING_SYMLINK=UNPROVEN
INTENTIONAL_ARCHITECTURE=POSSIBLE (deploy copied tree instead of link)
```

## Risk of naive `php artisan storage:link`

**HIGH** — artisan would fail or conflict while directory exists; forcing replace could shadow/lose 91M of files if not merged with `storage/app/public` first.

## Disposition

OPS-DEF-0020 remains **OPEN**. Wave 1C/ later: diff inventories of `public/storage` vs `storage/app/public`, then plan merge+link — **not** mutate in 1B.
