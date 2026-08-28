/**
 * UI sort keys map to Laravel `sort` query values on `flights.results.data`.
 * Blade reference: `results-page.blade.php` `#ota-filter-sort` — `cheapest` is the
 * authoritative lowest-price value (see Phase22CFlightSearchRulesTest).
 *
 * Default product sort is Cheapest (minimum authoritative customer-payable total).
 */
export type UiSortKey = "recommended" | "lowest_price" | "earliest_departure" | "latest_departure" | "fastest";

export const DEFAULT_UI_SORT: UiSortKey = "lowest_price";
export const DEFAULT_LARAVEL_SORT = "cheapest";

export const SORT_CONTROLS: Array<{ key: UiSortKey; label: string; laravelSort: string }> = [
  { key: "lowest_price", label: "Cheapest", laravelSort: "cheapest" },
  { key: "recommended", label: "Recommended", laravelSort: "recommended" },
  { key: "earliest_departure", label: "Earliest Departure", laravelSort: "earliest_departure" },
  { key: "latest_departure", label: "Latest Departure", laravelSort: "latest_departure" },
  { key: "fastest", label: "Shortest Duration", laravelSort: "fastest" },
];

export function resolveLaravelSort(key: UiSortKey): string {
  return SORT_CONTROLS.find((item) => item.key === key)?.laravelSort ?? DEFAULT_LARAVEL_SORT;
}

export function parseUiSort(value: string | null): UiSortKey {
  if (value === "cheapest" || value === "lowest_price") {
    return "lowest_price";
  }
  const allowed = SORT_CONTROLS.map((item) => item.key);
  if (value && (allowed as string[]).includes(value)) {
    return value as UiSortKey;
  }
  return DEFAULT_UI_SORT;
}
