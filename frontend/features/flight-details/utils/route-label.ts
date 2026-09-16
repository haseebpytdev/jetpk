import type { FlightOffer, FlightSegmentDisplay } from "@/features/flight-results/types";

function formatEndpoint(
  code: string | null | undefined,
  city: string | null | undefined,
): string | null {
  if (!code) return null;
  const cityTrim = city?.trim();
  return cityTrim ? `${cityTrim} (${code})` : code;
}

export function buildFareRouteLabel(
  offerOrSegments: FlightOffer | FlightSegmentDisplay[],
  fallbackRoute?: string | null,
): string | null {
  const offer = Array.isArray(offerOrSegments) ? null : offerOrSegments;
  const segments = Array.isArray(offerOrSegments) ? offerOrSegments : (offerOrSegments.segments ?? []);
  const routeFallback = Array.isArray(offerOrSegments)
    ? fallbackRoute
    : (fallbackRoute ?? offerOrSegments.route);

  if (segments.length > 0) {
    const first = segments[0];
    const last = segments[segments.length - 1];
    const originCode = first.origin_airport_code ?? first.origin;
    const lastDestCode = last.destination_airport_code ?? last.destination;
    // Round-trip segment chains end at origin (ISB→…→ISB). Prefer offer OD (ISB—DXB),
    // never first-segment dest (e.g. RUH layover) or last dest (= origin).
    const isRoundTripChain =
      segments.length >= 2 && Boolean(originCode) && originCode === lastDestCode;
    if (isRoundTripChain) {
      const dep = offer?.departure_airport_code;
      const arr = offer?.arrival_airport_code;
      // Prefer commercial OD when hydrate has not collapsed arrival back to origin.
      if (dep && arr && dep !== arr) {
        const origin = formatEndpoint(dep, first.origin_city);
        const dest = formatEndpoint(arr, null);
        if (origin && dest) return `${origin} — ${dest}`;
      }
      const route = routeFallback?.trim();
      if (route) {
        return route.replace(/\s*→\s*/g, " — ").replace(/\s*->\s*/g, " — ");
      }
    }
    const origin = formatEndpoint(originCode, first.origin_city);
    const dest = formatEndpoint(lastDestCode, last.destination_city);
    if (origin && dest && originCode !== lastDestCode) {
      return `${origin} — ${dest}`;
    }
  }

  if (offer?.departure_airport_code && offer?.arrival_airport_code) {
    const origin = formatEndpoint(offer.departure_airport_code, null);
    const dest = formatEndpoint(offer.arrival_airport_code, null);
    if (origin && dest) return `${origin} — ${dest}`;
  }

  const route = routeFallback?.trim();
  if (!route) return null;
  return route.replace(/\s*→\s*/g, " — ").replace(/\s*->\s*/g, " — ");
}
