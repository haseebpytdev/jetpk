const SEARCH_IDENTITY_KEYS = [
  "search_id",
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

const PRESENTATION_KEYS = new Set([
  "sort",
  "airline",
  "stops",
  "refundable",
  "cabin_filter",
  "baggage",
  "departure_window",
  "arrival_window",
  "min_price",
  "max_price",
  "max_duration",
  "duration_bucket",
  "layover_airport",
  "fare_family",
  "bookable_only",
  "operating_airline",
  "flight_number",
  "page",
  "view",
  "outbound_key",
  "combo_id",
]);

/**
 * Search identity for bootstrap. Presentation filters/sort must not re-init supplier search.
 * Direct-only at search time is encoded as `direct=1` only when no search_id exists.
 */
export function searchIdentityKey(params: URLSearchParams): string {
  const searchId = params.get("search_id")?.trim();
  if (searchId) {
    return `id:${searchId}`;
  }

  const parts = SEARCH_IDENTITY_KEYS.filter((key) => key !== "search_id").map(
    (key) => `${key}=${params.get(key) ?? ""}`,
  );
  parts.push(`multi_from=${params.getAll("multi_from[]").join(",")}`);
  parts.push(`multi_to=${params.getAll("multi_to[]").join(",")}`);
  parts.push(`multi_depart=${params.getAll("multi_depart[]").join(",")}`);
  if (params.get("stops") === "direct") {
    parts.push("direct=1");
  }
  return parts.join("&");
}

export function isPresentationOnlyParam(key: string): boolean {
  return PRESENTATION_KEYS.has(key);
}
