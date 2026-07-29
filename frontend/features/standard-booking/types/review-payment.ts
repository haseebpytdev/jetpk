import type { BookingProgressStep } from "@/features/booking-progress";
import type { SelectedFlightSummary } from "./index";

export type PaymentMethodCode = "manual" | "card";

export type ReviewPaymentMethod = {
  code: PaymentMethodCode;
  canonical: string;
  label: string;
  description: string;
  available: boolean;
  fee: number | null;
  currency: string;
};

export type AuthoritativePricing = {
  currency: string;
  base_fare: number;
  taxes: number;
  service_charges: number;
  total: number;
  formatted_total: string;
  rows?: Array<Record<string, unknown>>;
  passenger_mix?: Record<string, number> | null;
};

export type FareChangeState = {
  fare_changed?: boolean;
  requires_acceptance?: boolean;
  old_total?: number;
  new_total?: number;
  old_total_formatted?: string;
  new_total_formatted?: string;
  accept_url?: string;
  decline_url?: string;
};

export type BookingReviewContext = {
  ok: boolean;
  booking_session: {
    id: string;
    status: string;
    expires_at?: string | null;
    server_time: string;
    progress: BookingProgressStep[];
  };
  booking_reference?: string | null;
  itinerary: SelectedFlightSummary;
  passengers: Array<Record<string, unknown>>;
  contact: Record<string, string>;
  documents: Array<Record<string, unknown>>;
  pricing: AuthoritativePricing;
  payment_methods: ReviewPaymentMethod[];
  terms: { required: boolean; terms_url: string; privacy_url: string };
  fare_change?: FareChangeState | null;
  submit_blocked: boolean;
  submit_blocked_reason?: string | null;
  notices: string[];
  next_actions: Record<string, string | null>;
};

export type ReviewSubmitResponse = {
  ok: boolean;
  status: string;
  booking_method?: string;
  payment_method_code?: PaymentMethodCode;
  next_url?: string;
  confirmation_handoff_url?: string;
  message?: string;
  fare_change?: FareChangeState | null;
  guest_abhipay_token?: string | null;
};

export type StatusPresentation = {
  code: string;
  label: string;
  terminal?: boolean;
};

export type CheckoutState = {
  ok: boolean;
  booking_session: {
    id: string;
    status: string;
    server_time: string;
    progress: BookingProgressStep[];
  };
  booking_reference?: string | null;
  booking_method: string;
  payment_method_code: PaymentMethodCode;
  booking_status: StatusPresentation;
  payment_status: StatusPresentation;
  pnr?: string | null;
  ticketing_status?: string;
  pricing: AuthoritativePricing;
  manual_payment?: ManualPaymentState | null;
  card_payment?: CardPaymentState | null;
  supplier_notice?: string | null;
  itinerary: SelectedFlightSummary;
  passengers: Array<Record<string, unknown>>;
  contact: Record<string, string>;
  documents_portal: Array<Record<string, unknown>>;
  support: Record<string, string | null>;
  confirmation_handoff_url?: string | null;
};

export type ManualPaymentState = {
  amount_due: number;
  currency: string;
  formatted_amount: string;
  instructions: string[];
  payment_status_label: string;
  proof_upload_supported: boolean;
  payment_reference_supported: boolean;
};

export type CardPaymentState = {
  can_start: boolean;
  show_pay_button: boolean;
  payable_amount: number;
  currency: string;
  formatted_amount: string;
  payment_status_label: string;
  blocked_message?: string | null;
  ticketing_note: string;
  start_endpoint?: string | null;
  latest_transaction_reference?: string | null;
};

export type PaymentStatusResponse = {
  ok: boolean;
  booking_reference?: string | null;
  payment_status: StatusPresentation;
  booking_status: StatusPresentation;
  transaction_reference?: string | null;
  poll?: { should_poll: boolean; interval_ms: number; max_attempts: number };
  message?: string;
};

export type CardPaymentStartResponse = {
  ok: boolean;
  status: string;
  redirect_url?: string;
  transaction_reference?: string;
  booking_reference?: string;
  message?: string;
};

export type InvoicePayload = {
  ok: boolean;
  invoice_number?: string | null;
  booking_reference?: string | null;
  issue_date?: string;
  customer: Record<string, string>;
  itinerary_summary: Record<string, string>;
  passenger_count: number;
  line_items: Array<Record<string, unknown>>;
  pricing: AuthoritativePricing;
  payment_method: string;
  payment_status: StatusPresentation;
  booking_status: StatusPresentation;
  company: Record<string, string | null>;
  pdf_available: boolean;
  pdf_download_path?: string | null;
  message?: string;
};
