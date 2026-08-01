import type { Airport } from "@/features/search/types";
import { laravelApiPath } from "@/services/flight-search";

type LaravelAirportRow = {
  iata?: string;
  iata_code?: string;
  name?: string;
  city?: string;
  country?: string;
};

export type AirportSearchResult =
  | { ok: true; data: Airport[] }
  | { ok: false; message: string; aborted?: boolean };

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
  async search(query: string, signal?: AbortSignal): Promise<AirportSearchResult> {
    const normalized = query.trim();
    if (normalized.length < 2) return { ok: true, data: [] };

    const params = new URLSearchParams({ q: normalized, limit: "8" });

    try {
      const response = await fetch(`${laravelApiPath("/airports/search")}?${params.toString()}`, {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        credentials: "include",
        signal,
      });

      if (!response.ok) {
        return { ok: false, message: "Unable to load airports. Please try again." };
      }

      const rows = (await response.json()) as LaravelAirportRow[];
      if (!Array.isArray(rows)) return { ok: true, data: [] };

      return {
        ok: true,
        data: rows.map(mapLaravelAirport).filter((airport): airport is Airport => airport !== null),
      };
    } catch (error) {
      if (error instanceof DOMException && error.name === "AbortError") {
        return { ok: false, message: "Request cancelled.", aborted: true };
      }
      return { ok: false, message: "Network error. Check your connection and try again." };
    }
  },

  async listPopular(signal?: AbortSignal): Promise<AirportSearchResult> {
    return this.search("Pak", signal);
  },
};
