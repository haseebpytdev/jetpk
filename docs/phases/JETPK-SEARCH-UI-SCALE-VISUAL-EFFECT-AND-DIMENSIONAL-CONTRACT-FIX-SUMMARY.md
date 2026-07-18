# JETPK-SEARCH-UI-SCALE-VISUAL-EFFECT-AND-DIMENSIONAL-CONTRACT-FIX

- **Phase name:** JETPK-SEARCH-UI-SCALE-VISUAL-EFFECT-AND-DIMENSIONAL-CONTRACT-FIX
- **Branch:** main
- **Previous SHA:** d3bd25ec4b3fa76f292987d6a76cc745bdd697b7
- **Objective:** Make homepage Search UI CMS slider produce clear proportional dimensional changes across 80%–115%.
- **Final status:** READY_FOR_MANUAL_UPLOAD

## Root cause

1. Hidden `0.90` multiplier: `effective scale = 0.90 × CMS%` (100% → 0.90).
2. `max(44px, calc(base × scale))` on `--jp-search-control-height` flattened field height for 80%–100%.
3. Scaled derived tokens were defined on `:root`, so inherited computed values ignored CMS `--jp-search-ui-scale` on `.jp-home`.
4. Outer box used `max-width: 100%` without a scaled centered width contract.

## Corrected scale contract

| CMS % | Old CSS scale | New CSS scale |
|------|---------------|---------------|
| 80 | 0.72 | 0.80 |
| 84 | 0.756 | 0.84 |
| 90 | 0.81 | 0.90 |
| 100 | 0.90 | 1.00 |
| 115 | 1.035 | 1.15 |

**Formula:** `--jp-search-ui-scale = CMS% / 100` (no hidden multiplier).

## Files changed

- `app/Support/Client/Homepage/JetpkHomepageHeroSizing.php`
- `app/Support/Client/Homepage/HomepageCanonicalSchema.php`
- `public/themes/frontend/jetpakistan/css/tokens.css`
- `public/themes/frontend/jetpakistan/css/theme.css`
- `public/themes/frontend/jetpakistan/css/jp-search.css`
- `resources/views/themes/admin/jetpakistan/page-settings/partials/home-sections.blade.php`
- `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php` (asset v57)
- `playwright.jetpk-search-ui-scale.config.ts`
- `tests/Unit/Support/Client/Homepage/JetpkHomepageHeroSizingTest.php`
- `tests/Feature/Jetpk/JetpkHomepageSizingTest.php`
- `tests/Feature/Jetpk/JetpkSearchUiScaleVisualContractTest.php`
- `tests/playwright/jetpk/homepage-search-ui-scale.spec.ts`

## Tests executed

- PHPUnit: `JetpkHomepageHeroSizingTest`, `JetpkSearchUiScaleVisualContractTest`, `JetpkHomepageSizingTest` — 25 tests, all pass
- Playwright: `homepage-search-ui-scale.spec.ts` — 2 tests, desktop monotonic metrics + mobile touch-safe
- `jetpk:homepage-customization-coverage-audit` fail=0
- `jetpk:homepage-content-audit --profile=jetpk` fail_count=0
- `jetpk:homepage-media-audit --profile=jetpk` fail_count=0
- `ota:route-page-health-audit --all` fail=0 server_errors=0
- `git diff --check` clean
- `php artisan view:cache` / `view:clear` OK

## Browser metrics (1440×900 desktop, Playwright)

| Metric | 80% | 90% | 100% | 115% |
|--------|-----|-----|------|------|
| Outer width | 928px | 1044px | 1160px | 1334px |
| Outer height | 210px | 233px | 257px | 292px |
| Outer padding (sum) | 57.6px | 64.8px | 72px | 82.8px |
| Field height | 60.4px | 67.7px | 75.0px | 86.0px |
| Tab height | 28.8px | 32.4px | 36.0px | 41.4px |
| Search button height | 41.3px | 45.9px | 51.0px | 58.6px |
| Swap size | 30.4px | 34.2px | 38.0px | 43.7px |
| Row gap | 9.6px | 10.8px | 12.0px | 13.8px |
| Label size | 8.8px | 9.9px | 11.0px | 12.7px |
| Value size | 15.3px | 15.3px | 15.3px | 15.3px |
| Radius | 11.2px | 12.6px | 14.0px | 16.1px |

Screenshots: `tests/playwright/artifacts/homepage-search-ui-scale/desktop/` and `mobile/`.

## Corrected production backup block

```bash
set -euo pipefail

DEPLOY_ROOT="/path/to/jetpakistan"   # production app root
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
ARCHIVE="/tmp/jetpk-search-ui-scale-${STAMP}.tar.gz"
PARTIAL="${ARCHIVE}.partial"

FILES=(
  "app/Support/Client/Homepage/JetpkHomepageHeroSizing.php"
  "app/Support/Client/Homepage/HomepageCanonicalSchema.php"
  "public/themes/frontend/jetpakistan/css/tokens.css"
  "public/themes/frontend/jetpakistan/css/theme.css"
  "public/themes/frontend/jetpakistan/css/jp-search.css"
  "resources/views/themes/admin/jetpakistan/page-settings/partials/home-sections.blade.php"
  "resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php"
)

cd "${DEPLOY_ROOT}"
for rel in "${FILES[@]}"; do
  if [[ ! -f "${rel}" ]]; then
    echo "FATAL: missing backup source: ${rel}" >&2
    exit 1
  fi
done

rm -f "${PARTIAL}" "${ARCHIVE}"
if ! tar -czf "${PARTIAL}" "${FILES[@]}"; then
  rm -f "${PARTIAL}"
  echo "FATAL: tar failed; partial archive removed" >&2
  exit 1
fi

for rel in "${FILES[@]}"; do
  if ! tar -tzf "${PARTIAL}" | grep -Fq "${rel}"; then
    rm -f "${PARTIAL}"
    echo "FATAL: archive missing expected path: ${rel}" >&2
    exit 1
  fi
done

mv "${PARTIAL}" "${ARCHIVE}"
sha256sum "${ARCHIVE}"
echo "Backup OK: ${ARCHIVE}"
```

## Rollback

Restore the eight files above from the validated archive, then on server:

```bash
php artisan view:clear
php artisan config:clear
```

Revert CMS `hero.search_ui_scale` to prior published value if needed (optional; default 100 remains valid).
