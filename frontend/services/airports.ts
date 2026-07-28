import type { Airport } from "@/features/search/types";
import { laravelApiPath } from "@/services/flight-search";

type LaravelAirportRow = {
  iata?: string;
  iata_code?: string;
  name?: string;
  city?: string;
  country?: string;
};

function mapLaravelAirport(row: LaravelAirportRow): Airport | null {
  const iata = (row.iata ?? row.iata_code ?? "").toUpperCase();
  if (!iata) return null;

  return {
    iata,
    name: row.name ?? "",
    city: row.city ?? "",
    country: row.country ?? "",
  };
}

/**
 * Airport search boundary — Laravel `/airports/search` via same-origin `/laravel` proxy.
 */
export const AirportSearchService = {
  async search(query: string): Promise<Airport[]> {
    const normalized = query.trim();
    if (normalized.length < 2) return [];

    const params = new URLSearchParams({ q: normalized, limit: "8" });
    const response = await fetch(`${laravelApiPath("/airports/search")}?${params.toString()}`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      credentials: "include",
    });

    if (!response.ok) return [];

    const rows = (await response.json()) as LaravelAirportRow[];
    if (!Array.isArray(rows)) return [];

    return rows.map(mapLaravelAirport).filter((airport): airport is Airport => airport !== null);
  },

  async listPopular(): Promise<Airport[]> {
    return this.search("Pak");
  },
};
