/** Search mode tabs — mirrors Laravel trip_type vocabulary where applicable. */
export type SearchMode = "one_way" | "return" | "multi_city" | "group";

export type CabinClass = "economy" | "premium_economy" | "business";

export type Airport = {
  iata: string;
  name: string;
  city: string;
  country: string;
  /** Nearby IATA codes for fixture-only nearby-airport expansion. */
  nearby?: string[];
};

export type PassengerSelection = {
  adults: number;
  children: number;
  infants: number;
  cabin: CabinClass;
};

export type SearchOptions = {
  directFlightsOnly: boolean;
  includeNearbyAirports: boolean;
  flexibleDates: boolean;
};

export type FlightSegment = {
  id: string;
  from: Airport | null;
  to: Airport | null;
  departureDate: string;
};

export type SearchDraft = {
  mode: Exclude<SearchMode, "group">;
  segments: FlightSegment[];
  passengers: PassengerSelection;
  options: SearchOptions;
  submittedAt: string;
};

export type GroupSearchDraft = {
  origin: Airport | null;
  destination: string;
  category: string;
  travelDate: string;
  passengers: PassengerSelection;
  submittedAt: string;
};

export type GroupCategory = {
  slug: string;
  label: string;
};

export type CabinOption = {
  value: CabinClass;
  label: string;
};

/** Frontend-only multi-city segment cap until Laravel contract is connected. */
export const MULTI_CITY_MIN_SEGMENTS = 2;
export const MULTI_CITY_MAX_SEGMENTS = 6;
