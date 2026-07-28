import type { Airport } from "../types";
import { AIRPORT_FIXTURES } from "../fixtures/airports";

export function filterAirports(query: string, airports: Airport[] = AIRPORT_FIXTURES): Airport[] {
  const normalized = query.trim().toLowerCase();
  if (!normalized) return airports.slice(0, 8);

  return airports
    .filter((airport) => {
      const haystack = `${airport.iata} ${airport.city} ${airport.name} ${airport.country}`.toLowerCase();
      return haystack.includes(normalized);
    })
    .slice(0, 8);
}

export function findAirportByIata(iata: string, airports: Airport[] = AIRPORT_FIXTURES): Airport | undefined {
  return airports.find((airport) => airport.iata.toLowerCase() === iata.toLowerCase());
}

export function expandNearbyAirports(airport: Airport | null, airports: Airport[] = AIRPORT_FIXTURES): Airport[] {
  if (!airport) return [];
  const codes = new Set<string>([airport.iata, ...(airport.nearby ?? [])]);
  return airports.filter((item) => codes.has(item.iata));
}
