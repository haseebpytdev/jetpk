import type { StatusPresentation } from "@/features/standard-booking/types/review-payment";

export type PaginatedMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
};

export type AgentNavigationItem = {
  code: string;
  label: string;
  href: string;
  available: boolean;
};

export type AgentCapabilities = {
  ok: boolean;
  identity: {
    display_name: string;
    email: string;
    role: "agent" | "agent_staff";
    role_label: string;
    is_owner: boolean;
  };
  agency: { name: string };
  permissions: Record<string, boolean>;
  modules: Record<string, boolean>;
  navigation: AgentNavigationItem[];
};

export type AgentDashboardMetric = {
  total_bookings: number;
  pending_payment: number;
  ticketing_pending: number;
  confirmed_bookings: number;
  upcoming_trips: number;
  open_support_cases: number;
  unread_notifications: number;
  wallet_balance?: number;
  available_balance?: number;
  pending_deposits?: number;
  commission_earned?: number;
  commission_pending?: number;
};

export type AgentDashboardAction = {
  code: string;
  label: string;
  available: boolean;
  url?: string | null;
  reason_unavailable?: string | null;
};

export type AgentBookingListItem = {
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
  booking_type: "standard" | "group_ticketing";
  creator_name?: string | null;
  detail_url: string;
  commission?: { amount: number; currency: string; status: string } | null;
  next_action?: { code: string; label: string; url: string | null } | null;
};

export type AgentDashboardOverview = {
  ok: boolean;
  capabilities: AgentCapabilities;
  metrics: AgentDashboardMetric;
  notifications_available: boolean;
  wallet_summary?: {
    balance: number;
    available_balance: number;
    pending_deposits: number;
    credit_limit: number | null;
    credit_enabled: boolean;
    currency: string;
  } | null;
  recent_bookings: AgentBookingListItem[];
  upcoming_booking: AgentBookingListItem | null;
  first_pending_payment_booking: AgentBookingListItem | null;
  quick_actions: AgentDashboardAction[];
};

export type WalletSummary = {
  balance: number;
  available_balance: number;
  pending_deposits: number;
  credit_limit: number | null;
  credit_enabled: boolean;
  currency: string;
  wallet_status: string;
  last_updated?: string | null;
};

export type WalletLedgerEntry = {
  reference: string;
  date?: string | null;
  type: string;
  direction: "credit" | "debit";
  amount: number;
  currency: string;
  balance_after: number;
  booking_reference?: string | null;
  deposit_reference?: string | null;
  description: string;
  status: string;
  created_by?: string | null;
};

export type DepositRequest = {
  deposit_reference: string;
  requested_amount: number;
  currency: string;
  date?: string | null;
  method: string;
  proof_status: string;
  approval_status: StatusPresentation;
  credited_amount?: number | null;
  rejection_reason?: string | null;
  next_action: { code: string; label: string };
};

export type AgentPayment = {
  reference: string;
  booking_reference?: string | null;
  deposit_reference?: string | null;
  date?: string | null;
  method: string;
  method_label: string;
  amount: number;
  currency: string;
  payment_status: StatusPresentation;
  booking_status?: StatusPresentation | null;
  source: "wallet" | "deposit" | "payment_proof";
  retry_available: boolean;
  receipt_available: boolean;
  detail_url?: string | null;
};

export type AgentInvoice = {
  invoice_number?: string | null;
  booking_reference?: string | null;
  issue_date?: string | null;
  amount?: number | null;
  currency: string;
  payment_status?: StatusPresentation | null;
  booking_status?: StatusPresentation | null;
  agency_label?: string | null;
  pdf_available: boolean;
  view_url?: string | null;
  download_url?: string | null;
  print_url?: string | null;
};

export type AgentProfile = {
  ok: boolean;
  user: {
    name: string;
    email: string;
    username: string;
    email_verified: boolean;
    email_verified_at?: string | null;
    role_label: string;
  };
  personal_profile: Record<string, string | null | undefined>;
  agency_profile: Record<string, unknown>;
  capabilities: {
    can_edit_personal: boolean;
    can_edit_agency: boolean;
    can_view_agency: boolean;
  };
  countries: Array<{ code: string; name: string }>;
  personal_update_url: string;
  agency_update_url: string;
  password_update_url: string;
  supported_personal_fields: string[];
  supported_agency_fields: string[];
};

export type AgentSupportCase = {
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

export type AgentSupportReply = {
  id: number;
  author_name: string;
  author_role: "agent" | "staff";
  body: string;
  created_at?: string | null;
};
