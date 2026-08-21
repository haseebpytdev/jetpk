/** Laravel flight results JSON contract (JP-FE-05). */

export type SearchFreshness = {
  search_age_seconds?: number;
  search_age_display?: string;
  expires_at?: string;
  expires_in_seconds?: number;
  expires_display?: string;
  refresh_due?: boolean;
  refresh_due_display?: string;
  status?: string;
};

export type FlightSegmentDisplay = {
  segment_number?: number;
  origin?: string;
  destination?: string;
  origin_airport_code?: string;
  destination_airport_code?: string;
  origin_city?: string;
  destination_city?: string;
  departure_time_display?: string;
  departure_date_display?: string;
  arrival_time_display?: string;
  arrival_date_display?: string;
  arrival_day_offset_display?: string;
  arrival_day_offset?: string;
  duration_display?: string;
  flight_number?: string;
  marketing_carrier_code?: string;
  airline_code?: string;
  airline_name?: string;
  airline_logo_url?: string | null;
  operating_carrier_code?: string;
  operating_airline_code?: string;
  operating_airline_name?: string;
  cabin?: string;
  cabin_display?: string;
  booking_class?: string;
  aircraft_display?: string | null;
  terminal_departure?: string | null;
  terminal_arrival?: string | null;
  layover_after_display?: string;
};

export type FareFamilyOption = {
  option_key: string;
  name?: string;
  brand_code?: string;
  brand_name?: string;
  price_display?: string;
  displayed_price?: number | null;
  baggage?: string;
  cabin_baggage?: string | null;
  checked_baggage?: string | null;
  carry_on_summary?: string | null;
  check_in_summary?: string | null;
  refund_rule?: string;
  change_rule?: string;
  meal?: string;
  seat_selection?: string;
  is_synthetic_default?: boolean;
  is_grouped_offer_option?: boolean;
  source_offer_id?: string;
  selection_key_authoritative?: boolean;
  can_select?: boolean;
  selectable?: boolean;
};

export type FlightOffer = {
  offer_id: string;
  supplier_provider?: string;
  provider?: string;
  supplier_source_label?: string;
  airline_code?: string;
  airline_name?: string;
  airline_logo_url?: string | null;
  route?: string;
  departure_time?: string;
  arrival_time?: string;
  arrival_day_offset_display?: string;
  arrival_day_offset?: string;
  departure_city?: string;
  arrival_city?: string;
  departure_airport_code?: string;
  arrival_airport_code?: string;
  duration?: string;
  stops?: number;
  stops_label_display?: string;
  stops_display?: string;
  layover_summary_display?: string[];
  /** Laravel presentation field alias for layover_summary_display. */
  layover_summary?: string[];
  baggage?: string;
  refundable?: boolean;
  currency?: string;
  displayed_price?: number | null;
  price_display?: string;
  price_note?: string;
  can_book?: boolean;
  disabled_reason?: string | null;
  multicity_inquiry_only?: boolean;
  inquiry_only_notice?: string | null;
  inquiry_url?: string | null;
  flight_number?: string;
  cabin?: string;
  fare_family?: string;
  refund_rule?: string;
  change_rule?: string;
  operating_airline_code?: string;
  operating_airline_name?: string;
  seats_left?: number | null;
  segments?: FlightSegmentDisplay[];
  select_url?: string;
  details_url?: string;
  has_branded_fares?: boolean;
  has_fare_choice_options?: boolean;
  has_multiple_fare_choices?: boolean;
  branded_fares_selection_active?: boolean;
  fare_family_options_display?: FareFamilyOption[];
  branded_fares_display_options?: FareFamilyOption[];
  single_direct_fare_on_card?: boolean;
  offer_freshness?: Record<string, unknown>;
  layovers_display?: Array<{
    airport_code?: string;
    city?: string;
    airport_city?: string;
    duration_display?: string;
    duration_minutes?: number | null;
    overnight?: boolean;
    terminal_change?: boolean;
    label?: string;
  }>;
  baggage_checked_display?: string | null;
  baggage_cabin_display?: string | null;
  baggage_summary_display?: string | null;
  fallback_details?: Record<string, unknown> | null;
  base_fare?: number;
  taxes?: number;
  markup?: number;
  service_fee?: number;
  final_customer_price?: number;
};

export type PairedReturnOption = {
  combo_id: string;
  outbound_key?: string;
  return_key?: string;
  outbound_journey?: Record<string, unknown>;
  return_journey?: Record<string, unknown>;
  total_amount?: number | null;
  total_display?: string;
  fare_family?: string;
  cabin?: string;
  baggage?: string;
  refundable?: boolean;
  can_book?: boolean;
  airline_name?: string;
  airline_code?: string;
  pairing_authority?: string;
};

export type OutboundOption = {
  outbound_key: string;
  journey_display?: {
    departure_time_display?: string;
    arrival_time_display?: string;
    duration_display?: string;
    duration_minutes?: number;
    stops?: number;
    stops_label_display?: string;
    layover_summary_display?: string[];
    airline_code?: string;
    airline_name?: string;
    airline_logo_url?: string | null;
    origin_airport_code?: string;
    destination_airport_code?: string;
    arrival_day_offset_display?: string;
  };
  from_total_amount?: number;
  from_total_display?: string;
  combo_count?: number;
  select_return_url?: string;
};

export type ResultsFilterMeta = {
  airlines?: Array<{ code: string; name: string; count: number }>;
  stops?: Array<{ value: string; label: string; count: number }>;
  refundable?: Array<{ value: string; label: string; count: number }>;
  cabin_classes?: Array<{ value: string; label: string; count: number }>;
  baggage_options?: Array<{ value: string; label: string; count: number }>;
  departure_windows?: Array<{ value: string; label: string; count: number }>;
  arrival_windows?: Array<{ value: string; label: string; count: number }>;
  duration_buckets?: Array<{ value: string; label: string; count: number }>;
  layover_airports?: Array<{ code: string; name: string; count: number }>;
  fare_families?: Array<{ value: string; label: string; count: number }>;
  price_range?: { min: number; max: number };
  duration_range?: { min: number; max: number };
};

export type FlightResultsDataResponse = {
  search_id: string;
  flow?: "return_split_outbound" | "return_pair";
  page: number;
  per_page: number;
  total: number;
  has_more: boolean;
  filters?: ResultsFilterMeta;
  offers?: FlightOffer[];
  outbound_options?: OutboundOption[];
  paired_options?: PairedReturnOption[];
  pairing_authority?: "SUPPLIER_RETURNED" | "SUPPLIER_VALIDATED" | "UNAVAILABLE";
  warnings?: string[];
  empty_message?: string;
  search_freshness?: SearchFreshness;
  message?: string;
  status?: string;
};

export type ReturnOptionsDataResponse = {
  flow: "return_split_return";
  search_id: string;
  outbound_key: string;
  outbound_journey?: Record<string, unknown>;
  outbound_meta?: Record<string, unknown>;
  cheapest_total?: number;
  return_options: Array<Record<string, unknown>>;
  page: number;
  per_page: number;
  total: number;
  has_more: boolean;
  search_freshness?: SearchFreshness;
};

export type NearbyDateStripRow = {
  date: string;
  label: string;
  cheapest_pkr: number | null;
  is_selected: boolean;
  search_url: string;
};

export type NearbyDatesResponse = {
  available: boolean;
  selected_date: string | null;
  dates: NearbyDateStripRow[];
};

export type FlightSearchInitFullResponse = {
  search_id: string;
  summary?: { text?: string };
  inline_display?: Record<string, unknown>;
  criteria?: Record<string, unknown>;
  warnings?: string[];
  initial_results_url?: string;
  results_page_url?: string;
  search_freshness?: SearchFreshness;
};

export type RevalidateOfferResponse = {
  success: boolean;
  status?: string;
  message?: string;
  passengers_url?: string;
  requires_fare_change_acceptance?: boolean;
  offer_freshness?: Record<string, unknown>;
  search_freshness?: SearchFreshness;
  revalidation?: Record<string, unknown>;
};

export type ResultsSortOption =
  | "recommended"
  | "price_desc"
  | "earliest_departure"
  | "latest_departure"
  | "fastest";

export type ActiveResultsFilters = {
  airline?: string;
  stops?: string;
  refundable?: string;
  cabin?: string;
  baggage?: string;
  departure_window?: string;
  arrival_window?: string;
  min_price?: string;
  max_price?: string;
  max_duration?: string;
  duration_bucket?: string;
  layover_airport?: string;
  fare_family?: string;
  bookable_only?: string;
  operating_airline?: string;
  flight_number?: string;
};

export type ResultsPageStatus =
  | "idle"
  | "initializing"
  | "loading"
  | "ready"
  | "empty"
  | "expired"
  | "error"
  | "failed";
