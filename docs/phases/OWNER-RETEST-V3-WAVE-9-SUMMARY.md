# OWNER-RETEST-V3 WAVE-9 — SUMMARY

## Phase name
OWNER_RETEST_V3 Wave-9 — Review integrity, selected-fare durability, AbhiPay handoff

## Branch name
`feat/jetpk-flight-results-booking-flow-20260819`

## Objective
Fix P0 selected branded fare loss on Review JSON, title/`null` integrity, premium Review IA,
traveler edit idempotency, order-summary/pricing parity, and complete existing AbhiPay review options.

## Included scope
- Durable selected fare from `booking.meta` into Review draft/viewData/JSON
- Fail-closed when `fare_option_key` lacks selected intent
- Title allow-list + defensive display (no literal `null`)
- Full Review itinerary / travelers / contact / payment IA
- Idempotent Draft booking reuse on traveler edit
- Whole-PKR price summary + flight-preview order summary
- Card + Manual payment options when AbhiPay gateway available
- Card payment page JetPakistan branding
- Focused PHPUnit + Playwright visual matrix

## Excluded scope
- Production deploy
- Live PNR / hold / AbhiPay order / ticket / payment charge
- Sabre auto-PNR/cancellation behavior changes
- Duplicate AbhiPay module

## Investigation findings
Next.js Review JSON rebuilt a synthetic draft **without** `selected_fare_family_option`,
so `presentItinerary` fell back to base `offer.fare_family` (often ECONOMY BASIC).
Blade Review already read meta correctly. Title validation lacked allow-list.
Passengers POST always `createDraftBooking` (edit created duplicates / lost draft).

## Root causes
1. `BookingController::review()` omitted durable fare intent from draft/viewData
2. Presenter preferred base offer when intent missing
3. `passengers.*.title` was free-form string (accepted `"null"`)
4. Session booking not reused / not hydrated for Review → Edit travelers
5. Review UI used collapsed OrderSummary stub instead of full itinerary

## Exact files changed
### Cluster A
- `app/Http/Controllers/Frontend/BookingController.php`
- `app/Support/Booking/StandardBookingJsonPresenter.php`
- `app/Http/Requests/Frontend/StoreBookingPassengersRequest.php`
- `frontend/features/standard-booking/components/BookingReviewPage.tsx`
- `frontend/features/standard-booking/components/ReviewPassengerList.tsx`
- `tests/Feature/Wave9PassengerTitleIntegrityTest.php`
- `tests/Feature/BookingBrandedFareSelectionIntentTest.php`
- `tests/Feature/StandardBookingReviewJsonTest.php`
- `tests/Support/PublicBookingPassengersPayload.php`

### Clusters B/C/D
- `app/Http/Controllers/Frontend/BookingController.php` (hydrate + idempotent reuse)
- `app/Support/Booking/StandardBookingJsonPresenter.php` (segments_display mapping)
- `frontend/features/standard-booking/components/BookingReviewPage.tsx`
- `frontend/features/standard-booking/components/ReviewPassengerList.tsx`
- `frontend/features/standard-booking/itinerary/ItineraryTimeline.tsx`
- `frontend/features/booking-layout/components/OrderSummary.tsx`
- `frontend/features/standard-booking/components/CardPaymentPage.tsx`
- `frontend/features/standard-booking/components/PaymentMethodSelector.tsx`
- `frontend/features/standard-booking/types/review-payment.ts`
- `tests/Feature/Wave9ReviewEditPassengersIdempotencyTest.php`
- `tests/Feature/Wave9ReviewPaymentMethodsTest.php`

### Cluster E
- `frontend/tests/owner-v3-flight-wave-9-visual-matrix.spec.ts`
- `tmp/owner-v3-flight-wave-9/*` (visual proof)
- this summary

## Routes changed
None (behavior on existing `/booking/review`, `/booking/passengers`, `/booking/payment/card`).

## Database changes
None.

## Backend changes
- Durable selected-fare resolution + fail-closed
- Title allow-list `Mr|Mrs|Ms|Miss|Dr|Mstr`
- Prefer `selected_fare_total` in authoritative pricing
- Attach fare breakdown total from selected branded fare when present
- Hydrate draft + prefill from session Draft booking
- Reuse Draft booking on passengers POST when safe

## Frontend changes
- Full Review IA (itinerary, travelers, contact, payment)
- Edit traveler/contact → `/booking/passengers`
- Flight-preview order summary + whole PKR price rows
- Manual CTA `Confirm booking` / Card CTA `Continue to payment`
- Secure card payment branding (Powered by AbhiPay subtle)

## Tests executed
- `Wave9PassengerTitleIntegrityTest` — PASS
- Branded fare Review JSON durability + fail-closed — PASS
- `StandardBookingReviewJsonTest` — PASS
- `Wave9ReviewEditPassengersIdempotencyTest` — PASS
- `Wave9ReviewPaymentMethodsTest` — PASS
- `npx tsc --noEmit` — PASS
- `npm run build` — PASS
- Playwright wave-9 visual matrix — see Cluster E evidence

## Assertion counts
Cluster A focused: 17 tests / 128 assertions  
BCD focused: 11 tests / 74 assertions  

## Screenshots
`tmp/owner-v3-flight-wave-9/` — required 01–16 states

## Responsive / accessibility
Desktop sticky sidebar + mobile stacking preserved; focus-visible edit actions retained.

## Known limitations
- Card option appears only when active AbhiPay gateway is configured (`isAvailableForCheckout`)
- Do not create real AbhiPay orders in engineering verification
- OWNER_RETEST_V3 remains FAILED_REMEDIATION_REQUIRED until production deploy + owner retest

## Risks
- Fail-closed Review may redirect legacy inconsistent meta bookings to results (intentional)
- Idempotent reuse only for Draft without commercial hold/PNR/paid state

## Rollback instructions
Revert commits on `feat/jetpk-flight-results-booking-flow-20260819` after Wave-9 SHA pins.
Do not roll production until a Wave-9 deploy is authorized.

## Commit SHAs
- Cluster A: `baa60350`
- Clusters B/C/D: `1c846fa8`
- Cluster E / FINAL_WAVE9_ENGINEERING_SHA: 8db2fe84593e92192e9b0f090f58b7894218bf47

## Final status
ENGINEERING_COMPLETE_PENDING_OWNER_RETEST  
OWNER_RETEST_V3=FAILED_REMEDIATION_REQUIRED  
DO_NOT_DEPLOY until owner authorizes.
