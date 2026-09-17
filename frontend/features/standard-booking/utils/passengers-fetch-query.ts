/** Canonical passengers GET query — shared by inline boot, Book Now prime, and React. */
export const PASSENGERS_JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

export const PASSENGERS_FETCH_CREDENTIALS = "include" as const;

export const PASSENGERS_LARAVEL_PATH = "/laravel/booking/passengers";

export const PASSENGERS_CONTEXT_PRIME_STORAGE_KEY = "jp-passengers-context-prime";

export const PASSENGERS_CONTEXT_PRIME_TTL_MS = 120_000;

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

export type PassengersContextPrimeRecord = {
  key: string;
  at: number;
  data: unknown;
};

export function writePassengersContextPrime(key: string, data: unknown): void {
  if (typeof window === "undefined" || !key || data == null) return;
  try {
    const record: PassengersContextPrimeRecord = { key, at: Date.now(), data };
    sessionStorage.setItem(PASSENGERS_CONTEXT_PRIME_STORAGE_KEY, JSON.stringify(record));
  } catch {
    /* quota / private mode — ignore */
  }
}

export function readPassengersContextPrime(expectedKey: string): PassengersContextPrimeRecord | null {
  if (typeof window === "undefined" || !expectedKey) return null;
  try {
    const raw = sessionStorage.getItem(PASSENGERS_CONTEXT_PRIME_STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as PassengersContextPrimeRecord;
    if (!parsed?.key || parsed.key !== expectedKey) return null;
    if (typeof parsed.at !== "number" || Date.now() - parsed.at > PASSENGERS_CONTEXT_PRIME_TTL_MS) {
      sessionStorage.removeItem(PASSENGERS_CONTEXT_PRIME_STORAGE_KEY);
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}

export function clearPassengersContextPrime(expectedKey?: string): void {
  if (typeof window === "undefined") return;
  try {
    if (!expectedKey) {
      sessionStorage.removeItem(PASSENGERS_CONTEXT_PRIME_STORAGE_KEY);
      return;
    }
    const raw = sessionStorage.getItem(PASSENGERS_CONTEXT_PRIME_STORAGE_KEY);
    if (!raw) return;
    const parsed = JSON.parse(raw) as PassengersContextPrimeRecord;
    if (parsed?.key === expectedKey) {
      sessionStorage.removeItem(PASSENGERS_CONTEXT_PRIME_STORAGE_KEY);
    }
  } catch {
    /* ignore */
  }
}

/** Parse /booking/passengers?... into query params for Laravel JSON prime. */
export function passengersParamsFromHandoffUrl(pathOrAbsolute: string): Record<string, string | undefined> {
  try {
    const absolute = pathOrAbsolute.startsWith("http")
      ? pathOrAbsolute
      : `https://jetpakistan.pk${pathOrAbsolute.startsWith("/") ? pathOrAbsolute : `/${pathOrAbsolute}`}`;
    const url = new URL(absolute);
    const out: Record<string, string | undefined> = {};
    url.searchParams.forEach((value, key) => {
      out[key] = value;
    });
    return out;
  } catch {
    return {};
  }
}
