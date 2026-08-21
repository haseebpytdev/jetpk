import type { FlightOffer } from "../types";
import { formatWholePkr } from "./price";

/** Criteria keys that may appear on a reproducible public results URL (no secrets). */
const SAFE_SHARE_PARAM_KEYS = [
  "trip_type",
  "from",
  "to",
  "depart",
  "return_date",
  "adults",
  "children",
  "infants",
  "cabin",
  "include_nearby",
  "flexible_dates",
] as const;

const FORBIDDEN_SHARE_PARAM_KEYS = new Set([
  "search_id",
  "offer_id",
  "flight_id",
  "fare_option_key",
  "outbound_key",
  "combo_id",
  "token",
  "session",
  "signature",
]);

/**
 * Build a reproducible public `/flights/results` URL from search criteria only.
 * Strips search_id, offer ids, fare keys, and other internal identifiers.
 */
export function buildSafePublicResultsShareUrl(
  params: URLSearchParams,
  origin?: string,
): string {
  const next = new URLSearchParams();

  for (const key of SAFE_SHARE_PARAM_KEYS) {
    const value = params.get(key);
    if (value) next.set(key, value);
  }

  // Multi-city arrays — copy only when present; never copy forbidden keys.
  for (const key of ["multi_from[]", "multi_to[]", "multi_depart[]"] as const) {
    params.getAll(key).forEach((value) => {
      if (value) next.append(key, value);
    });
  }

  // Direct-only is a search-time option encoded as stops=direct (not a presentation filter).
  if (params.get("stops") === "direct") {
    next.set("stops", "direct");
  }

  for (const key of [...next.keys()]) {
    if (FORBIDDEN_SHARE_PARAM_KEYS.has(key) || key.includes("token") || key.includes("secret")) {
      next.delete(key);
    }
  }

  const base =
    origin ??
    (typeof window !== "undefined" && window.location?.origin
      ? window.location.origin
      : "https://jetpakistan.pk");

  return `${base.replace(/\/$/, "")}/flights/results?${next.toString()}`;
}

function resolveCityCode(
  city: string | undefined,
  code: string | undefined,
): { city: string; code: string } {
  const cleanCode = (code ?? "").trim().toUpperCase();
  const cleanCity = (city ?? "").trim();
  return { city: cleanCity || cleanCode, code: cleanCode };
}

export function buildFlightShareText(
  offer: FlightOffer,
  priceAmount: number | null | undefined,
  shareUrl: string,
): string {
  const first = offer.segments?.[0];
  const last = offer.segments?.[offer.segments.length - 1];
  const origin = resolveCityCode(
    first?.origin_city ?? offer.departure_city,
    first?.origin_airport_code ?? first?.origin ?? offer.departure_airport_code,
  );
  const destination = resolveCityCode(
    last?.destination_city ?? offer.arrival_city,
    last?.destination_airport_code ?? last?.destination ?? offer.arrival_airport_code,
  );
  const airline = (offer.airline_name ?? "Airline").trim();
  const iata = (offer.airline_code ?? "").trim().toUpperCase();
  const price = formatWholePkr(priceAmount) ?? "Price unavailable";
  const airlineLabel = iata ? `${airline} (${iata})` : airline;
  const routeLabel = `${origin.city}${origin.code ? ` (${origin.code})` : ""} to ${destination.city}${destination.code ? ` (${destination.code})` : ""}`;

  return [
    "✈️ *Your Flight Details*",
    "",
    `- ${airlineLabel}: ${routeLabel} - ${price}`,
    "",
    `💰 *Total:* ${price}`,
    "",
    "🔗 View & Book:",
    shareUrl,
  ].join("\n");
}

export function buildWhatsAppShareUrl(text: string): string {
  return `https://wa.me/?text=${encodeURIComponent(text)}`;
}

export async function copyTextToClipboard(text: string): Promise<boolean> {
  try {
    if (typeof navigator !== "undefined" && navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch {
    // fall through
  }

  try {
    const area = document.createElement("textarea");
    area.value = text;
    area.setAttribute("readonly", "");
    area.style.position = "fixed";
    area.style.left = "-9999px";
    document.body.appendChild(area);
    area.select();
    const ok = document.execCommand("copy");
    document.body.removeChild(area);
    return ok;
  } catch {
    return false;
  }
}
