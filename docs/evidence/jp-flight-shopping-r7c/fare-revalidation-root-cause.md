# Fare revalidation root cause

## Symptom

Return Paired “Continue with this fare” → HTTP 422  
`status=failed`, message “We could not confirm this fare with the airline…”  
`offer_freshness_status=fresh`, `high_risk_cached_offer=true`

## Not the cause

- Not stale/expired offer (age 0, freshness fresh)
- Not missing offer in store (`findOfferForCheckoutTransition` FOUND)
- Not structural Sabre validation (`validateNormalizedSabreOffer` PASS)
- One Way same route continued successfully to Traveler

## Root cause

`SabreSelectedOfferRevalidationGate` live revalidation returned:

`selected_offer_revalidation_reason=sabre_revalidation_empty_or_unusable_response`

Controller only fell back to search rematch when status was `search_refresh_required`, so empty live responses hard-failed.

## Fix

1. Treat empty/unusable/timeout live failures as `search_refresh_required`
2. Controller also recovers on `failed` via bounded `refreshSelectedOfferViaSearch`
3. Frontend classifies fresh+failed as temporary timeout UX (not false “airline rejected”)
4. Pair drawer seeds `offerId` from `pair.offer_id ?? pair.combo_id`

## Live proof after fix

Return ISB–DXB pair Continue → 200 success → Traveler information  
URL retained `trip_type=round_trip` + `return_date` + `combo_id`  
Fare validate wall ~55s (includes search rematch; bounded, not infinite)
