/** Session flag: results left for checkout so Back/BFCache must refresh search. */
export const RESULTS_LEFT_FOR_CHECKOUT_KEY = "ota_results_left_for_checkout";

export function markResultsLeftForCheckout(searchId?: string | null): void {
  if (typeof window === "undefined") return;
  try {
    window.sessionStorage.setItem(RESULTS_LEFT_FOR_CHECKOUT_KEY, String(searchId?.trim() || "1"));
  } catch {
    // sessionStorage may be unavailable
  }
}

export function clearResultsLeftForCheckout(): void {
  if (typeof window === "undefined") return;
  try {
    window.sessionStorage.removeItem(RESULTS_LEFT_FOR_CHECKOUT_KEY);
  } catch {
    // ignore
  }
}

export function didLeaveResultsForCheckout(): boolean {
  if (typeof window === "undefined") return false;
  try {
    return Boolean(window.sessionStorage.getItem(RESULTS_LEFT_FOR_CHECKOUT_KEY));
  } catch {
    return false;
  }
}

export function navigationWasBackForward(): boolean {
  if (typeof window === "undefined" || typeof performance === "undefined") return false;
  try {
    const entries = performance.getEntriesByType("navigation");
    const nav = entries[0] as PerformanceNavigationTiming | undefined;
    if (nav?.type === "back_forward") return true;
  } catch {
    // ignore
  }
  const legacy = (performance as Performance & { navigation?: { type?: number } }).navigation;
  return legacy?.type === 2;
}

/** Criteria keys preserved when forcing a fresh supplier search. */
export const PRESERVED_SEARCH_CRITERIA_KEYS = [
  "trip_type",
  // Public results URL contract
  "from",
  "to",
  "depart",
  "return_date",
  // Alternate / internal aliases (keep if present)
  "origin",
  "destination",
  "departure_date",
  "adults",
  "children",
  "infants",
  "cabin",
  "view",
  "direct",
  "return_view",
] as const;

/** Selection / inventory authority keys stripped on fresh return search. */
export const STRIPPED_SELECTION_KEYS = [
  "search_id",
  "offer_id",
  "combo_id",
  "outbound_key",
  "fare_option_key",
  "outbound_fare_option_key",
  "return_fare_option_key",
  "selected_fare_option_id",
] as const;

export function buildFreshResultsSearchParams(source: URLSearchParams): URLSearchParams {
  const next = new URLSearchParams();
  for (const key of PRESERVED_SEARCH_CRITERIA_KEYS) {
    const value = source.get(key);
    if (value) next.set(key, value);
  }
  // Normalize aliases → public URL keys when only aliases are present.
  if (!next.get("from") && source.get("origin")) next.set("from", String(source.get("origin")));
  if (!next.get("to") && source.get("destination")) next.set("to", String(source.get("destination")));
  if (!next.get("depart") && source.get("departure_date")) next.set("depart", String(source.get("departure_date")));
  return next;
}

export function shouldRefreshResultsAfterCheckoutReturn(event: PageTransitionEvent): boolean {
  const leftForCheckout = didLeaveResultsForCheckout();
  const referrer = typeof document !== "undefined" ? document.referrer || "" : "";
  const fromCheckoutFlow = leftForCheckout || /\/booking\/|passenger|checkout/i.test(referrer);
  const backNav = Boolean(event.persisted) || navigationWasBackForward();
  return fromCheckoutFlow && backNav;
}
