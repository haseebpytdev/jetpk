# Homepage interactive — phase 1 status

## Code changes (working tree; not yet committed/deployed)

| Area | Change |
|------|--------|
| Hero backdrop | Fixed responsive canvas `clamp(22rem,48vh,34rem)`; search overlaps via normal-flow `-mt-*`; mode height independent of form |
| Travelers label | Removed visible `Travelers & Cabin`; kept `aria-label="Travelers and cabin"` |
| Travelers popover | Portaled to `document.body` with viewport flip/shift; no document-flow participation |
| Mobile trip tabs | Compact visible labels One / Return / Multi; full `aria-label`s |
| Service switch | Mobile card-width labeled; tablet compact centered labeled; desktop vertical icon rail |
| Tablet One Way | Field row + options/Search action row (`md`–`xl`); desktop xl keeps Search inline |
| Tablet Return | Airports row + balanced date pair + options/Search; xl full field row |

## Files

- `frontend/features/public-visual/hero/PublicHero.tsx`
- `frontend/features/search/components/TravelersCabinSelector.tsx`
- `frontend/features/search/components/SearchTabs.tsx`
- `frontend/features/search/hooks/use-search-tabs.ts`
- `frontend/features/search/components/SearchServiceSwitcher.tsx`
- `frontend/features/search/components/SearchModule.tsx`
- `frontend/features/search/components/OneWayForm.tsx`
- `frontend/features/search/components/ReturnForm.tsx`
- `frontend/tests/homepage.spec.ts`

## Gates

```text
HOMEPAGE_INTERACTIVE_VISUAL=PENDING_DEPLOY_AND_BROWSER_UAT
```

Live production still serves `22a4e759` / `mbGGmJZh1GgGZCIWsBdEV` until a commit + protected public build.
