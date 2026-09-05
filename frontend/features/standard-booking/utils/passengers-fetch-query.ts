/** Canonical passengers GET query — shared by inline boot and React. */
export const PASSENGERS_JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

export const PASSENGERS_FETCH_CREDENTIALS = "include" as const;

export const PASSENGERS_LARAVEL_PATH = "/laravel/booking/passengers";

export function hasPassengersHandoffQuery(
  params: URLSearchParams | Record<string, string | undefined>,
): boolean {
  const get = (key: string): string => {
    if (params instanceof URLSearchParams) {
      return (params.get(key) ?? "").trim();
    }
    return String(params[key] ?? "").trim();
  };
  const searchId = get("search_id");
  const offerId = get("offer_id") || get("flight_id");
  return searchId !== "" && offerId !== "";
}

export function buildPassengersFetchQuery(
  params: URLSearchParams | Record<string, string | undefined>,
): string {
  const search = new URLSearchParams();
  if (params instanceof URLSearchParams) {
    params.forEach((value, key) => {
      if (value !== undefined && value !== "") {
        search.set(key, value);
      }
    });
  } else {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== "") {
        search.set(key, value);
      }
    });
  }
  search.set("format", "json");
  return search.toString();
}

export function shouldReuseEarlyPrime(earlyKey: string | undefined, currentKey: string): boolean {
  return Boolean(earlyKey && earlyKey === currentKey);
}

/** Network/malformed/auth failures must not stick as the only attempt. */
export function shouldFallbackAfterEarlyResult(result: { ok?: boolean } | null | undefined): boolean {
  return !result || result.ok !== true;
}
