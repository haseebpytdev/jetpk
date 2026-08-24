import type { ActiveResultsFilters } from "../types";

/**
 * Results facet keys. Search criteria `cabin` must NOT be treated as a facet —
 * use `cabin_filter` in the URL / results API so return-split offers are not
 * emptied by the search cabin leaking into filterOffers.
 */
const FILTER_KEYS: Array<keyof ActiveResultsFilters> = [
  "airline",
  "stops",
  "refundable",
  "cabin",
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
];

/** URL / Laravel query key for cabin facet (not search criteria `cabin`). */
export const CABIN_FILTER_QUERY_KEY = "cabin_filter";

export function parseFiltersFromSearchParams(params: URLSearchParams): ActiveResultsFilters {
  const filters: ActiveResultsFilters = {};
  FILTER_KEYS.forEach((key) => {
    if (key === "cabin") {
      const value = params.get(CABIN_FILTER_QUERY_KEY);
      if (value) {
        filters.cabin = value;
      }
      return;
    }
    const value = params.get(key);
    if (value) {
      filters[key] = value;
    }
  });
  return filters;
}

export function filtersToSearchParams(filters: ActiveResultsFilters): URLSearchParams {
  const params = new URLSearchParams();
  FILTER_KEYS.forEach((key) => {
    const value = filters[key];
    if (!value) return;
    if (key === "cabin") {
      params.set(CABIN_FILTER_QUERY_KEY, value);
      return;
    }
    params.set(key, value);
  });
  return params;
}

/** Serialize active facets for Laravel `/flights/results/data` (cabin → cabin_filter). */
export function appendFiltersToQuery(query: URLSearchParams, filters: ActiveResultsFilters): void {
  FILTER_KEYS.forEach((key) => {
    const value = filters[key];
    if (!value) return;
    if (key === "cabin") {
      query.set(CABIN_FILTER_QUERY_KEY, value);
      return;
    }
    query.set(key, value);
  });
}

export function countActiveFilters(filters: ActiveResultsFilters): number {
  return FILTER_KEYS.filter((key) => Boolean(filters[key])).length;
}

export function clearFilter(
  filters: ActiveResultsFilters,
  key: keyof ActiveResultsFilters,
): ActiveResultsFilters {
  const next = { ...filters };
  delete next[key];
  return next;
}
