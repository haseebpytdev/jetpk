import type { FlightOffer, FareFamilyOption, SearchFreshness } from "@/features/flight-results/types";

export type FallbackDetailsSection = {
  overview?: Record<string, unknown>;
  baggage?: BaggageDetailsContract;
  fare_breakdown?: PriceBreakdownContract;
  fare_rules?: FareRulesContract;
  supplier?: Record<string, unknown>;
};

export type BaggageDetailsContract = {
  checked?: string | null;
  cabin?: string | null;
  summary?: string | null;
  lines?: string[] | null;
  passenger_baggage?: Array<{ passenger_type?: string; checked?: string; cabin?: string }> | null;
  segment_baggage?: Array<{ segment_index?: number; route?: string; checked?: string; cabin?: string }> | null;
  unavailable_message?: string | null;
};

export type PriceBreakdownContract = {
  base_fare?: number | null;
  taxes?: number | null;
  supplier_total?: number | null;
  markup?: number | null;
  service_fee?: number | null;
  grand_total?: number | null;
  displayed_price?: number | null;
  displayed_currency?: string | null;
  currency?: string | null;
  passenger_pricing?: Record<string, unknown>[] | null;
  price_note?: string | null;
  component_breakdown_available?: boolean | null;
  component_breakdown_unavailable?: boolean | null;
};

export type FareRulesContract = {
  refundable?: boolean;
  refund_status?: string;
  change_allowed?: boolean | null;
  change_rule?: string | null;
  refund_rule?: string | null;
  penalty?: string | null;
  fare_basis?: string | null;
  booking_class?: string | null;
  cabin?: string | null;
  fare_family?: string | null;
  rule_lines?: string[] | null;
};

export type LayoverDisplay = {
  airport_code?: string;
  city?: string;
  duration_display?: string;
  overnight?: boolean;
  terminal_change?: boolean;
};

export type ReturnComboDetails = {
  combo_id: string;
  outbound_key?: string;
  return_key?: string;
  outbound_journey?: Record<string, unknown> | null;
  return_journey?: Record<string, unknown> | null;
  total_amount?: number | null;
  total_display?: string | null;
};

export type FlightOfferDetailsResponse = {
  success: boolean;
  status?: string;
  message?: string;
  search_id: string;
  offer_id: string;
  fare_option_key?: string | null;
  flow?: "one_way" | "return_combo" | "multicity_inquiry";
  offer: FlightOffer & {
    fallback_details?: FallbackDetailsSection | null;
    fallback_detail_sections_present?: Record<string, boolean>;
    layovers_display?: LayoverDisplay[];
    baggage_checked_display?: string | null;
    baggage_cabin_display?: string | null;
    baggage_summary_display?: string | null;
  };
  return_combo?: ReturnComboDetails | null;
  search_freshness?: SearchFreshness;
  revalidation_required?: boolean;
  multicity_inquiry_only?: boolean;
  inquiry_only_notice?: string | null;
};

export type RevalidationState =
  | "idle"
  | "loading"
  | "success"
  | "fare_change"
  | "unavailable"
  | "expired"
  | "timeout"
  | "error";

export type FlightDetailsContext = {
  searchId: string;
  offerId: string;
  fareOptionKey?: string;
  outboundKey?: string;
  comboId?: string;
  initialOffer?: FlightOffer;
  initialFareOptions?: FareFamilyOption[];
  intent?: "details" | "booking";
};

export type { FareFamilyOption, FlightOffer };
