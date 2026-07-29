import type { StatusPresentation } from "@/features/standard-booking/types/review-payment";

export type PaginatedMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
};

export type DashboardMetric = {
  upcoming_trips: number;
  pending_payment: number;
  ticketing_pending: number;
  confirmed_bookings: number;
  total_bookings: number;
  open_support_cases: number;
  unread_notifications: number;
};

export type CustomerDashboardAction = {
  code: string;
  label: string;
  available: boolean;
  url?: string | null;
  reason_unavailable?: string | null;
};

export type CustomerBookingListItem = {
  booking_reference: string;
  booking_date?: string | null;
  trip_type: string;
  route: string;
  departure_date?: string | null;
  airline: string;
  passenger_count: number;
  total: number;
  currency: string;
  booking_status: StatusPresentation;
  payment_status: StatusPresentation;
  ticketing_status: StatusPresentation;
  pnr?: string | null;
  booking_type: "standard";
  detail_url: string;
  next_action?: { code: string; label: string; url: string | null } | null;
};

export type CustomerDashboardOverview = {
  ok: boolean;
  metrics: DashboardMetric;
  notifications_available: boolean;
  recent_bookings: CustomerBookingListItem[];
  upcoming_booking: CustomerBookingListItem | null;
  first_pending_payment_booking: CustomerBookingListItem | null;
  quick_actions: CustomerDashboardAction[];
};

export type CustomerPayment = {
  reference: string;
  booking_reference?: string | null;
  date?: string | null;
  payment_method: string;
  payment_method_label: string;
  amount: number;
  currency: string;
  payment_status: StatusPresentation;
  booking_status?: StatusPresentation | null;
  detail_url?: string | null;
  retry_available: boolean;
  receipt_available: boolean;
  source: "payment_proof" | "gateway";
};

export type CustomerInvoice = {
  invoice_number?: string | null;
  booking_reference?: string | null;
  issue_date?: string | null;
  amount?: number | null;
  currency: string;
  payment_status?: StatusPresentation | null;
  booking_status?: StatusPresentation | null;
  pdf_available: boolean;
  view_url?: string | null;
  download_url?: string | null;
  print_url?: string | null;
};

export type CustomerProfile = {
  ok: boolean;
  user: {
    name: string;
    email: string;
    username: string;
    email_verified: boolean;
    email_verified_at?: string | null;
  };
  profile: Record<string, string | null>;
  countries: Array<{ code: string; name: string }>;
  update_url: string;
  password_update_url: string;
  supported_fields: string[];
};

export type CustomerSupportCase = {
  reference: string;
  subject: string;
  category: string;
  category_label: string;
  booking_reference?: string | null;
  status: StatusPresentation;
  created_at?: string | null;
  updated_at?: string | null;
  detail_url: string;
  can_reply: boolean;
  can_close: boolean;
};

export type CustomerSupportReply = {
  id: number;
  author_name: string;
  author_role: "customer" | "staff";
  body: string;
  created_at?: string | null;
};

export type CustomerNotification = {
  id: string;
  title: string;
  message: string;
  created_at?: string | null;
  read: boolean;
  action_url?: string | null;
};

export type CustomerDashboardError = {
  ok: false;
  message: string;
  code?: string;
  errors?: Record<string, string[]>;
};
