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
  group?: string;
};

export type AgentCapabilities = {
  ok: boolean;
  session_usable?: boolean;
  denial_reason?: string | null;
  identity: {
    display_name: string;
    email: string;
    role: "agent" | "agent_staff";
    role_label: string;
    is_owner: boolean;
    status?: string;
  };
  agency: { name: string; status?: string };
  permissions: Record<string, boolean>;
  modules: Record<string, boolean>;
  capabilities?: Record<string, boolean | Record<string, string>>;
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

export type BookingCreateEntry = {
  ok: boolean;
  booking_mode_active: boolean;
  agency_name: string;
  message: string;
  search_url: string;
  exit_url: string;
};

export type AgentStaffMember = {
  id: number;
  name: string;
  email: string;
  status: string;
  role_label: string;
  permissions_count: number;
  edit_url: string;
};

export type AgentStaffListResponse = {
  ok: boolean;
  staff: AgentStaffMember[];
  capabilities: {
    can_create: boolean;
    can_manage_permissions: boolean;
  };
  permission_labels: Record<string, string>;
};

export type AgentStaffDetail = {
  ok: boolean;
  staff: AgentStaffMember & {
    phone?: string | null;
    permissions: string[];
    account_type: string;
  };
  permission_labels: Record<string, string>;
  grouped_permissions: Record<string, Record<string, string>>;
  selected_permissions: string[];
  capabilities: {
    can_update: boolean;
    can_update_permissions: boolean;
    can_apply_template: boolean;
    can_deactivate: boolean;
  };
};

export type AgentReportsOverview = {
  ok: boolean;
  active_tab: string;
  has_live_data: boolean;
  summary: Record<string, number>;
  monthly_sales: Array<{ month: string; bookings: number; gross_sales: number }>;
  export_url: string;
  allowed_tabs: string[];
};

export type AgentCommissionOverview = {
  ok: boolean;
  balance: number;
  totals: { pending: number; approved: number; paid: number; currency: string };
  entries: Array<{
    id: number;
    booking_reference?: string | null;
    amount: number;
    currency: string;
    status: string;
    created_at?: string | null;
  }>;
  statements: Array<{
    id: number;
    reference: string;
    period_start?: string | null;
    period_end?: string | null;
    total_amount: number;
    status: string;
    detail_url: string;
  }>;
};

export type AgentAgencyProfile = {
  ok: boolean;
  details: Record<string, unknown>;
  capabilities: {
    can_edit_agency: boolean;
    can_view_wallet: boolean;
  };
  wallet_summary?: { balance: number; available_balance: number; currency: string } | null;
  update_url: string;
};

export type AgentSavedTraveler = {
  id: number | null;
  title: string;
  first_name: string;
  last_name: string;
  gender: string;
  date_of_birth?: string | null;
  nationality: string;
  document_type: string;
  document_number?: string | null;
  document_number_masked?: string | null;
  document_expiry?: string | null;
  issuing_country?: string | null;
  phone?: string | null;
  email?: string | null;
  is_default: boolean;
  edit_url?: string;
  delete_url?: string;
};

export type AgentFinanceStatementMovement = {
  date: string;
  type: string;
  description: string;
  reference: string;
  booking_reference?: string | null;
  debit: number;
  credit: number;
  running_balance: number;
  status: string;
  created_by?: string | null;
  approved_by?: string | null;
};

export type AgentFinanceStatement = {
  ok: boolean;
  agency: { name: string };
  period: { from: string; to: string };
  currency: string;
  opening_balance: number;
  closing_balance: number;
  total_debits: number;
  total_credits: number;
  movements: AgentFinanceStatementMovement[];
  reconciliation: {
    wallet_balance: number;
    ledger_liability: number;
    difference: number;
    status: string;
    matches: boolean;
  };
  export_url?: string | null;
  blade_fallback_url: string;
};

export type AgentAccountingLedgerTransaction = {
  id: number;
  transaction_ref: string;
  transaction_type: string;
  status: string;
  currency: string;
  amount_total: number;
  debit_total: number;
  credit_total: number;
  description: string;
  occurred_at?: string | null;
  posted_at?: string | null;
  booking_reference?: string | null;
  detail_url: string;
};

export type AgentAccountingLedgerOverview = {
  ok: boolean;
  summary: {
    wallet_balance: number;
    ledger_liability: number;
    difference: number;
    reconciliation_status: string;
    currency: string;
  };
  filters: Record<string, string>;
  transactions: AgentAccountingLedgerTransaction[];
  pagination: PaginatedMeta;
  blade_fallback_url: string;
};
