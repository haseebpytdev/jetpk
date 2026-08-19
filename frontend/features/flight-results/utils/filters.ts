import type { ActiveResultsFilters } from "../types";

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

export function parseFiltersFromSearchParams(params: URLSearchParams): ActiveResultsFilters {
  const filters: ActiveResultsFilters = {};
  FILTER_KEYS.forEach((key) => {
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
    if (value) {
      params.set(key, value);
    }
  });
  return params;
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
