import type { FlightSegment, GroupSearchDraft, PassengerSelection, SearchMode, SearchOptions } from "../types";

/** Laravel `trip_type` values used by PublicFlightSearchRequest. */
export type LaravelTripType = "one_way" | "round_trip" | "multi_city";

export type FlightSearchPayloadInput = {
  mode: Exclude<SearchMode, "group">;
  origin: string;
  destination: string;
  departureDate: string;
  returnDate?: string;
  segments?: FlightSegment[];
  passengers: PassengerSelection;
  options: SearchOptions;
};

export function mapSearchModeToLaravelTripType(mode: Exclude<SearchMode, "group">): LaravelTripType {
  if (mode === "return") return "round_trip";
  if (mode === "multi_city") return "multi_city";
  return "one_way";
}

/**
 * Build query parameters matching the JetPakistan Blade search form
 * (`flights-panel.blade.php` + `PublicFlightSearchRequest`).
 */
export function buildFlightSearchQueryParams(input: FlightSearchPayloadInput): URLSearchParams {
  const params = new URLSearchParams();
  const tripType = mapSearchModeToLaravelTripType(input.mode);

  params.set("trip_type", tripType);
  params.set("cabin", input.passengers.cabin);
  params.set("adults", String(input.passengers.adults));
  params.set("children", String(input.passengers.children));
  params.set("infants", String(input.passengers.infants));

  if (input.options.directFlightsOnly) {
    params.set("stops", "direct");
  }

  if (input.options.includeNearbyAirports) {
    params.set("include_nearby", "1");
  }

  if (input.options.flexibleDates && input.mode !== "multi_city") {
    params.set("flexible_dates", "1");
  }

  if (tripType === "multi_city") {
    const segments = input.segments ?? [];
    segments.forEach((segment) => {
      params.append("multi_from[]", segment.from?.iata ?? "");
      params.append("multi_to[]", segment.to?.iata ?? "");
      params.append("multi_depart[]", segment.departureDate);
    });
    return params;
  }

  params.set("from", input.origin);
  params.set("to", input.destination);
  params.set("depart", input.departureDate);

  if (tripType === "round_trip" && input.returnDate) {
    params.set("return_date", input.returnDate);
  }

  return params;
}

/**
 * Group ticketing homepage/search contract (`groups-panel.blade.php`,
 * `GroupTicketingSearchRequest`).
 */
export function buildGroupSearchQueryParams(
  input: Pick<GroupSearchDraft, "airline" | "sector" | "category" | "travelDate">,
): URLSearchParams {
  const params = new URLSearchParams();

  if (input.airline) {
    params.set("airline", input.airline);
  }

  if (input.sector) {
    params.set("sector", input.sector);
  }

  if (input.travelDate) {
    params.set("date_from", input.travelDate);
  }

  if (input.category && input.category !== "all") {
    params.set("category", input.category);
  }

  return params;
}

export function buildFlightSearchInitPath(query: URLSearchParams): string {
  return `/flights/results/search?${query.toString()}`;
}

export function buildFlightResultsPagePath(query: URLSearchParams): string {
  return `/flights/results?${query.toString()}`;
}

export function buildGroupSearchPagePath(query: URLSearchParams): string {
  return `/groups/search?${query.toString()}`;
}
