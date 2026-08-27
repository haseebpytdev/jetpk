export type GroupCategory = {
  slug: string;
  name: string;
  inventory_count?: number;
};

export type GroupFacets = {
  sectors: string[];
  airlines: Array<{ name: string }>;
  departure_dates: string[];
  categories: GroupCategory[];
};

export type GroupSearchFacetOption = {
  value: string;
  label: string;
  inventory_count?: number;
  image_url?: string | null;
  subtitle?: string | null;
};

export type GroupSearchFacetsResponse = {
  sectors: GroupSearchFacetOption[];
  airlines: GroupSearchFacetOption[];
  categories: GroupSearchFacetOption[];
  date_bounds: { minimum: string; maximum: string } | null;
  travel_date_match?: { mode: string; tolerance_days: number };
};

export type GroupSearchFacetsLoadState = "loading" | "loaded" | "empty" | "error";

export type GroupSearchFilters = {
  airline?: string;
  sector?: string;
  date_from?: string;
  category?: string;
  page?: number;
  sort?: string;
};

export type GroupBaggage = {
  display?: string;
  checked?: string | null;
  cabin?: string | null;
  raw?: string | null;
};

export type GroupPackage = {
  id?: number | null;
  public_id?: string | null;
  title: string;
  sector_code: string;
  route_line: string;
  origin_label?: string | null;
  dest_label?: string | null;
  departure_date?: string | null;
  departure_date_short?: string | null;
  departure_datetime_display?: string | null;
  arrival_time_display?: string | null;
  airline_name: string;
  airline_code?: string | null;
  airline_logo_url?: string | null;
  baggage?: GroupBaggage;
  baggage_line?: string;
  meal_label?: string | null;
  meal_status?: string | null;
  trip_type?: string | null;
  trip_type_label?: string | null;
  category_name?: string | null;
  category_slug?: string | null;
  price_formatted: string;
  currency: string;
  available_seats: number;
  seat_label: string;
  seats_badge_variant?: "ok" | "warn";
  cta_label?: string;
  cta_disabled?: boolean;
  cta_message?: string | null;
  bookable?: boolean;
  show_path?: string | null;
  passengers_path?: string | null;
  package_notes?: string | null;
  booking_conditions?: {
    hold_minutes: number;
    manual_payment_only: boolean;
    hold_starts_at_review_confirm: boolean;
  };
  seat_selection?: GroupSeatSelectionState;
};

export type GroupSeatSelectionState = {
  available: boolean;
  message?: string;
};

export type GroupSearchDataResponse = {
  filters: GroupSearchFilters;
  facets: GroupFacets;
  cards: GroupPackage[];
  total: number;
  page: number;
  per_page: number;
  has_more: boolean;
  bookable: boolean;
  count_label: string;
  status_message?: string | null;
  user_notice?: string | null;
  lock_state: GroupLockState;
};

export type GroupResultsPageResponse = {
  cards: GroupPackage[];
  total: number;
  page: number;
  per_page: number;
  has_more: boolean;
  bookable: boolean;
  count_label: string;
  user_notice?: string | null;
};

export type GroupLockState = {
  locked: boolean;
  unpaid_release_count: number;
  block_threshold: number;
  message?: string | null;
};

export type GroupPassenger = {
  title: string;
  first_name: string;
  last_name: string;
  gender: string;
  date_of_birth: string;
  nationality: string;
  document_type: "passport" | "national_id";
  passport_number: string;
  passport_issue_date?: string;
  passport_expiry: string;
  passenger_type?: string;
};

export type GroupContactDetails = {
  contact_name: string;
  contact_email: string;
  contact_phone: string;
};

export type GroupBookingPassenger = GroupPassenger & {
  full_name?: string;
};

export type GroupBookingStatus =
  | "pending_passenger_details"
  | "reserved_awaiting_payment"
  | "payment_pending"
  | "manual_payment_submitted"
  | "manual_payment_pending_review"
  | "confirmed"
  | "expired"
  | "cancelled"
  | "released"
  | "supplier_release_failed"
  | "failed"
  | "unknown";

export type GroupPaymentStatus = "verified" | "pending_review" | "awaiting_payment" | "expired" | "unknown";

export type GroupBookingReview = {
  id: number;
  reference: string;
  status: GroupBookingStatus;
  status_label: string;
  payment_status: GroupPaymentStatus;
  payment_status_label: string;
  seat_count: number;
  total_amount: number;
  total_formatted: string;
  currency: string;
  expires_at?: string | null;
  server_time?: string;
  hold_minutes: number;
  is_expired: boolean;
  is_releasable: boolean;
  is_payment_window_open: boolean;
  contact: {
    name?: string | null;
    email?: string | null;
    phone?: string | null;
  };
  passengers: GroupBookingPassenger[];
  inventory: GroupPackage;
  checkout_summary: Record<string, unknown>;
  progress: BookingProgressStep[];
  seat_selection?: GroupSeatSelectionState;
  hold_notice?: string;
  manual_payment_notice?: string;
};

export type GroupPaymentMethod = {
  value: "bank_transfer" | "office" | "cash";
  title: string;
  hint: string;
};

export type GroupPaymentInstructions = GroupBookingReview & {
  redirect_path?: string;
  payment_methods: GroupPaymentMethod[];
  payment_proof_supported: boolean;
  payment_reference_required: boolean;
  status_message?: string;
  instructions: string[];
  support: GroupSupportContacts;
};

export type GroupSupportContacts = {
  phone?: string | null;
  whatsapp?: string | null;
  email?: string | null;
  support_path: string;
};

export type GroupBookingConfirmation = GroupBookingReview & {
  hero: {
    title: string;
    subtitle: string;
    status_label: string;
  };
  support: GroupSupportContacts;
};

export type BookingProgressStep = {
  key: string;
  label: string;
  state: "completed" | "current" | "upcoming";
  href?: string | null;
};

export type GroupPassengersContext = {
  inventory: GroupPackage;
  seat_count: number;
  max_seats: number;
  countries: Array<{ code: string; name: string }>;
  checkout_summary: Record<string, unknown>;
  passenger_fields: Array<{ name: string; required: boolean }>;
  contact_fields: Array<{ name: string; required: boolean }>;
  progress: BookingProgressStep[];
  auth_required: boolean;
  lock_state?: GroupLockState;
};
