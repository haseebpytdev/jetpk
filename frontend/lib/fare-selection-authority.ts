/**
 * Fare-family UX authority for JetPakistan results and checkout.
 *
 * Resolved in JETPK-UI-04:
 * - Inline `BrandedFareCarousel` on result cards — quick preview when ≤3 visible families or before navigation.
 * - Dedicated `/flights/fare-selection` — full mockup #11 comparison surface for branded fares (>1 family).
 * - `FlightDetailsDrawer` — segment timeline, baggage, and fare rules; not the primary fare-comparison surface.
 *
 * Branded fare cards use a contained horizontal carousel when more than three families are present.
 */

export const FARE_SELECTION_ROUTE = "/flights/fare-selection";

export const FARE_CAROUSEL_THRESHOLD = 3;

export type FareSelectionSurface = "inline_carousel" | "dedicated_route" | "details_drawer";

export function resolveBrandedFareSurface(familyCount: number): FareSelectionSurface {
  if (familyCount <= 0) return "details_drawer";
  if (familyCount > 1) return "dedicated_route";
  return "inline_carousel";
}

export function shouldUseFareCarousel(familyCount: number): boolean {
  return familyCount > FARE_CAROUSEL_THRESHOLD;
}
