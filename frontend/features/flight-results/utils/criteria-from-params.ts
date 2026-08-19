import type { FlightSearchPayloadInput } from "@/features/search/utils/laravel-payload";
import type { CabinClass } from "@/features/search/types";
import { findAirportByIata } from "@/features/search/utils/airport-filter";
import type { FlightSegment, SearchOptions, TripType } from "@/features/search/types";

export function criteriaFromSearchParams(params: URLSearchParams): FlightSearchPayloadInput | null {
  const tripType = params.get("trip_type");
  if (!tripType) return null;

  const mode =
    tripType === "round_trip" ? "return" : tripType === "multi_city" ? "multi_city" : "one_way";

  const from = params.get("from") ?? "";
  const to = params.get("to") ?? "";
  const depart = params.get("depart") ?? "";
  if (mode !== "multi_city" && (!from || !to || !depart)) {
    return null;
  }

  const segments: FlightSegment[] | undefined =
    mode === "multi_city"
      ? params.getAll("multi_from[]").map((origin, index) => ({
          id: `segment-${index + 1}`,
          from: findAirportByIata(origin) ?? null,
          to: findAirportByIata(params.getAll("multi_to[]")[index] ?? "") ?? null,
          departureDate: params.getAll("multi_depart[]")[index] ?? "",
        }))
      : undefined;

  if (mode === "multi_city" && (!segments || segments.length < 2)) {
    return null;
  }

  return {
    mode,
    origin: from,
    destination: to,
    departureDate: depart,
    returnDate: params.get("return_date") ?? undefined,
    segments,
    passengers: {
      adults: Number(params.get("adults") ?? "1"),
      children: Number(params.get("children") ?? "0"),
      infants: Number(params.get("infants") ?? "0"),
      cabin: (params.get("cabin") ?? "economy") as CabinClass,
    },
    options: {
      directFlightsOnly: params.get("stops") === "direct",
      includeNearbyAirports: params.get("include_nearby") === "1",
      flexibleDates: params.get("flexible_dates") === "1",
    },
  };
}

export function tripTypeFromParams(params: URLSearchParams): TripType {
  const tripType = params.get("trip_type");
  if (tripType === "round_trip") return "return";
  if (tripType === "multi_city") return "multi_city";
  return "one_way";
}

export function searchOptionsFromParams(params: URLSearchParams): SearchOptions {
  return {
    directFlightsOnly: params.get("stops") === "direct",
    includeNearbyAirports: params.get("include_nearby") === "1",
    flexibleDates: params.get("flexible_dates") === "1",
  };
}
