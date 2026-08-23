import type { BookingProgressStep } from "@/features/booking-progress";

export type StandardBookingSessionStatus =
  | "passenger_details"
  | "review"
  | "expired"
  | "missing"
  | "unauthorized";

export type StandardBookingSession = {
  id: string;
  status: StandardBookingSessionStatus;
  expires_at?: string | null;
  server_time: string;
  next_url?: string | null;
  previous_url?: string | null;
  progress: BookingProgressStep[];
};

export type BookingSelectionContext = {
  search_id: string;
  offer_id: string;
  fare_option_key?: string;
  return_fare_option_key?: string;
  outbound_fare_option_key?: string;
  outbound_key?: string;
  combo_id?: string;
  from: string;
  to: string;
  depart: string;
  trip_type: string;
  return_date?: string;
  cabin: string;
};

export type BookingTravellerCounts = {
  adults: number;
  children: number;
  infants: number;
  total: number;
  expected: Array<{ index: number; type: string; label: string }>;
  lead_passenger_index: number;
};

export type PassengerFieldRequirement = {
  key: string;
  label: string;
  required: boolean;
  input_type: string;
  passenger_types?: string[];
  options?: string[];
  locked?: boolean;
};

export type TravelDocumentRequirement = {
  passport_required: boolean;
  national_id_allowed: boolean;
  passport_fields: PassengerFieldRequirement[];
  national_id_fields: PassengerFieldRequirement[];
  not_required_message?: string | null;
};

export type PassengerFormValues = {
  passenger_type: string;
  title: string;
  first_name: string;
  last_name: string;
  gender: string;
  date_of_birth: string;
  nationality: string;
  document_type: string;
  passport_number: string;
  passport_issuing_country: string;
  passport_expiry_date: string;
  passport_issue_date: string;
  national_id_number: string;
};

export type ContactFormValues = {
  contact_name: string;
  email: string;
  phone: string;
  phone_country_code: string;
  phone_number: string;
  country: string;
  create_account?: boolean;
  password?: string;
  password_confirmation?: string;
};

export type SelectedFlightSummary = {
  trip_type: string;
  route_label?: string | null;
  origin: string;
  destination: string;
  depart_date: string;
  return_date?: string;
  airline_name?: string | null;
  airline_code?: string | null;
  airline_logo_url?: string | null;
  flight_number?: string | null;
  cabin: string;
  fare_family?: string | null;
  stops?: number | null;
  duration?: string | null;
  baggage?: string | null;
  cabin_baggage?: string | null;
  checked_baggage?: string | null;
  meal?: string | null;
  refund_rule?: string | null;
  change_rule?: string | null;
  segments: Array<Record<string, unknown>>;
  return_segments: Array<Record<string, unknown>>;
  total_formatted?: string | null;
  currency: string;
  price_is_approximate?: boolean;
  price_needs_refresh?: boolean;
  return_split?: Record<string, unknown> | null;
  selected_fare_option_key?: string | null;
  selected_fare?: Record<string, unknown> | null;
};

export type SeatExtrasCapability = {
  seat_map_available: boolean;
  ancillaries_available: boolean;
  message: string;
  progress_step: string;
};

export type StandardPassengersContext = {
  ok: boolean;
  booking_session: StandardBookingSession;
  selection: BookingSelectionContext;
  itinerary: SelectedFlightSummary;
  travellers: BookingTravellerCounts;
  passenger_requirements: PassengerFieldRequirement[];
  contact_requirements: PassengerFieldRequirement[];
  document_requirements: TravelDocumentRequirement;
  existing_values: {
    passengers: Partial<PassengerFormValues>[];
    contact: Partial<ContactFormValues>;
  };
  checkout_summary: {
    total_formatted?: string | null;
    currency: string;
    passenger_counts: BookingTravellerCounts;
    lines?: Array<Record<string, unknown>>;
  };
  seat_extras_capability: SeatExtrasCapability;
  countries: Array<{ code: string; name: string }>;
  phone_dial_codes: Array<{ code: string; label: string }>;
  auth: {
    authenticated: boolean;
    can_create_account: boolean;
    agent_booking_mode: boolean;
    agent_contact_locked: boolean;
  };
  validation_result?: Record<string, unknown> | null;
  validation_alert?: string | null;
  fare_estimate_drift?: boolean;
  complex_itinerary_notice?: boolean;
  selected_fare?: Record<string, unknown> | null;
  consent?: {
    terms_version: string;
    privacy_version: string;
    terms_url: string;
    privacy_url: string;
    required: boolean;
    prechecked: boolean;
  };
  change_flight?: {
    safe: boolean;
    results_url?: string | null;
    abandon_url?: string;
  };
};

export type StandardPassengersSubmitResponse = {
  ok: boolean;
  status: string;
  next_url?: string;
  progress?: BookingProgressStep[];
  message?: string;
  redirect_url?: string;
};

export type BookingSessionError = {
  status: string;
  message: string;
  redirect_url?: string | null;
};
