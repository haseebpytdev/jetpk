/**
 * UI sort keys map to Laravel `sort` query values on `flights.results.data`.
 * Blade reference: `results-page.blade.php` `#ota-filter-sort` — `cheapest` is the
 * authoritative lowest-price value (see Phase22CFlightSearchRulesTest).
 */
export type UiSortKey = "recommended" | "lowest_price" | "earliest_departure" | "latest_departure" | "fastest";

export const SORT_CONTROLS: Array<{ key: UiSortKey; label: string; laravelSort: string }> = [
  { key: "recommended", label: "Recommended", laravelSort: "recommended" },
  { key: "lowest_price", label: "Lowest Price", laravelSort: "cheapest" },
  { key: "earliest_departure", label: "Earliest Departure", laravelSort: "earliest_departure" },
  { key: "latest_departure", label: "Latest Departure", laravelSort: "latest_departure" },
  { key: "fastest", label: "Shortest Duration", laravelSort: "fastest" },
];

export function resolveLaravelSort(key: UiSortKey): string {
  return SORT_CONTROLS.find((item) => item.key === key)?.laravelSort ?? "recommended";
}

export function parseUiSort(value: string | null): UiSortKey {
  if (value === "cheapest") {
    return "lowest_price";
  }
  const allowed = SORT_CONTROLS.map((item) => item.key);
  if (value && (allowed as string[]).includes(value)) {
    return value as UiSortKey;
  }
  return "recommended";
}
