import type { FlightOffer, FlightSegmentDisplay } from "@/features/flight-results/types";

export function buildFareRouteLabel(
  offerOrSegments: FlightOffer | FlightSegmentDisplay[],
  fallbackRoute?: string | null,
): string | null {
  const segments = Array.isArray(offerOrSegments) ? offerOrSegments : (offerOrSegments.segments ?? []);
  const routeFallback = Array.isArray(offerOrSegments)
    ? fallbackRoute
    : (fallbackRoute ?? offerOrSegments.route);

  if (segments.length > 0) {
    const first = segments[0];
    const last = segments[segments.length - 1];
    const originCode = first.origin_airport_code ?? first.origin;
    const destCode = last.destination_airport_code ?? last.destination;
    const originCity = first.origin_city?.trim();
    const destCity = last.destination_city?.trim();
    if (originCode && destCode) {
      const origin = originCity ? `${originCity} (${originCode})` : originCode;
      const dest = destCity ? `${destCity} (${destCode})` : destCode;
      return `${origin} — ${dest}`;
    }
  }

  const route = routeFallback?.trim();
  if (!route) return null;
  return route.replace(/\s*→\s*/g, " — ").replace(/\s*->\s*/g, " — ");
}
