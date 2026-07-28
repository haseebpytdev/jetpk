import type { Airport } from "@/features/search/types";
import { AIRPORT_FIXTURES } from "@/features/search/fixtures/airports";
import { filterAirports } from "@/features/search/utils/airport-filter";

/**
 * Airport search boundary — fixtures today, Laravel `/airports/search` later.
 *
 * Future replacement:
 * ```ts
 * const response = await apiClient.get<Airport[]>("/airports/search", { q: query });
 * return response.data;
 * ```
 */
export const AirportSearchService = {
  async search(query: string): Promise<Airport[]> {
    return filterAirports(query, AIRPORT_FIXTURES);
  },

  async listPopular(): Promise<Airport[]> {
    return AIRPORT_FIXTURES.slice(0, 8);
  },
};
