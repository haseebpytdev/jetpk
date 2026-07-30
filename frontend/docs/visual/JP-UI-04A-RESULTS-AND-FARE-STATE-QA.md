# JP-UI-04A Results and Fare State QA

Phase: JP-UI-04A | Scenarios: Results 38, Fare 16 | Visual audit: **120/120 PASS**

## Results states verified

- Base layout: light/dark/system × 1440/1280/1024/768/390/375/320
- Zoom 125%/150% at 1280
- Loading, present, empty, partial supplier failure, expired session, invalid search
- Filter drawer, sort tabs, direct-only (`stops=direct`), nearby origin, flexible dates
- Layover popover (accessible name), branded fare carousel, return pair view, multi-city summary, group ticketing separation

## Fare states verified

- Theme/viewport/zoom matrix
- 1/3/4 fare families; carousel when >3
- Selected fare, revalidating (IATI + hung revalidate), price-changed dialog, unavailable, expired session

## Correctness assertions (targeted specs)

- Lowest Price sorts cheapest fixture first
- Direct-only chip visible with `stops=direct`
- Return pair shows `outbound-option-card` (no manual stitching)
- Group search separate from standard results
- Fare revalidation busy state, price-change acceptance, unavailable blocks continue

## Scores (post-04A evidence)

| Surface | Score |
|---------|:-----:|
| Results desktop light/dark | 4 |
| Results mobile light/dark | 4 |
| Results 150% zoom | 4 |
| Fare desktop light/dark | 4 |
| Fare mobile | 4 |
| Fare 150% zoom | 4 |
