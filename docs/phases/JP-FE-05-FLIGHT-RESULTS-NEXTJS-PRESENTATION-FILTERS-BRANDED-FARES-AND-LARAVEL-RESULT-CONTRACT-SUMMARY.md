# JP-FE-05 — Flight Results Next.js Presentation, Filters, Branded Fares, and Laravel Result Contract

## Phase metadata

| Field | Value |
| --- | --- |
| Phase | JP-FE-05-FLIGHT-RESULTS-NEXTJS-PRESENTATION-FILTERS-BRANDED-FARES-AND-LARAVEL-RESULT-CONTRACT |
| Branch | `phase/jetpk-fe-05-flight-results` |
| Baseline | `3de7440` — JP-FE-04A customer dashboard route closure |
| Objective | Replace Blade flight-results presentation with operational Next.js results while preserving Laravel search engine and booking handoff |
| Final status | **COMPLETE** (targeted tests pass) |
| Production | **Untouched** |

## Investigation findings

### Laravel results audit

- Public flight search uses synchronous Blade + JSON under `routes/web.php` (`flights.results`, `flights.results.search`, `flights.results.data`, `flights.results.revalidate-offer`, return-split routes).
- No dedicated polling/status API — search blocks at init.
- `FlightController::mapOfferForResultsApi` provides sanitized offer rows with authoritative pricing, branded fares, segments, `select_url`.
- Return-split flow (`OTA_RETURN_SPLIT_SELECT_ENABLED`) exposes `outbound_options` with safe `combo_id` pairing via `ReturnSplitComboService`.
- Multi-city search displays offers but `PublicMulticityInquiryPolicy` blocks automatic checkout.

### Blade behavior preserved/improved

- Card layout: airline identity, times, stops/layover, baggage, price CTA, branded fare carousel.
- Price button visible text is **price only** (`Rs. …` / `price_display`); accessible name includes select action.
- Layover tooltip: greyish popover, keyboard/tap accessible.
- Filters/sort/pagination via Laravel query params on `flights.results.data`.

### Result contract strategy

Additive consumption of existing Laravel JSON — no parallel TypeScript fare model. Next.js calls `/laravel/flights/results/data` with `search_id`, filters, sort, page.

## Included scope

- `frontend/features/flight-results/` feature module
- `/flights/results` operational route
- `/flights/return-options` return-split step 2
- Homepage handoff to Next.js results (`handoffToFlightResults`)
- Filters, sort, load-more, states (loading, empty, error, expired)
- Branded fare carousel (>3 fares scroll/carousel)
- Offer selection handoff to Laravel checkout
- Playwright: `frontend/tests/flight-results.spec.ts` (17 tests)
- Architecture doc: `frontend/docs/FLIGHT-RESULTS-ARCHITECTURE.md`

## Excluded scope

- Next.js checkout/passenger/details (transitional Laravel handoff)
- Laravel controller/route changes
- Supplier integration changes
- Multi-city automatic checkout
- Full return Pair View for non-split combined offers (rendered as standard `offers[]` when not in split flow)
- Production deployment

## Return results decision

- **Return split enabled:** outbound cards on `/flights/results`; return combos on `/flights/return-options`; selection via POST `select-return-combo` with authoritative `combo_id`.
- **No manual pairing** — only supplier-indexed combos.
- Combined RT offers (non-split) render as single `offers[]` entries.

## Multi-city decision

- Display supported when Laravel returns offers; inquiry-only offers show notice + Laravel inquiry URL.
- No segment stitching; no Next.js checkout for multi-city.

## Files changed

### Frontend (new)

- `frontend/features/flight-results/**`
- `frontend/app/flights/layout.tsx`
- `frontend/app/flights/results/page.tsx`
- `frontend/app/flights/return-options/page.tsx`
- `frontend/tests/flight-results.spec.ts`
- `frontend/docs/FLIGHT-RESULTS-ARCHITECTURE.md`

### Frontend (modified)

- `frontend/services/flight-search.ts`
- `frontend/features/search/components/SearchModule.tsx`

## Tests executed

| Command | Result |
| --- | --- |
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS — routes include `/flights/results`, `/flights/return-options` |
| `npx playwright test tests/flight-results.spec.ts` | **17/17 PASS** |
| Laravel targeted tests | Not run — no Laravel files changed |

## Known limitations

- Return combo select uses HTML form POST to Laravel (not JSON API)
- Offer details route remains Laravel stub
- Filter facets limited to Laravel-provided fields in first pass (stops, airlines, departure window, refundable)
- No async search polling (not required by current backend)

## Rollback

1. Revert merge commit on `main`
2. Restore `handoffToLaravelResults` in `SearchModule` if needed for Blade-only handoff

## Next phase

**JP-FE-06** — Return split/pair UX depth, flight details, fare rules, revalidation UI (if not duplicated here).

## Commit SHAs

| Commit | SHA |
| --- | --- |
| Feature | `c8dc5c8` |
| Docs | `34477b5` |
| Merge to main | `de5a951` |
