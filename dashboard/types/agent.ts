export type AgentType =
  | "Retail Agent"
  | "Corporate Agent"
  | "Sub-Agent"
  | "Online Partner"
  | "Referral Partner"
  | "Internal Sales"
  | "Walk-in Desk";

export type AccountStatus = "Active" | "Inactive" | "Suspended" | "Review Required";

export type VerificationStatus = "Verified" | "Pending" | "Incomplete" | "Not Required";

export type CommercialStatus =
  | "Standard"
  | "Preferred"
  | "Credit Enabled"
  | "Prepaid Only"
  | "On Hold";

export type SettlementStatus =
  | "Current"
  | "Due"
  | "Overdue"
  | "Reconciliation Required"
  | "Not Applicable";

export type AgentRecord = {
  id: string;
  agencyName: string;
  tradingName: string;
  agentType: AgentType;
  city: string;
  country: string;
  operatingRegion: string;
  primaryContact: string;
  email: string;
  phone: string;
  accountStatus: AccountStatus;
  verificationStatus: VerificationStatus;
  commercialStatus: CommercialStatus;
  settlementStatus: SettlementStatus;
  preferredCurrency: string;
  commissionRatePercent: number;
  customerCount: number;
  travellerCount: number;
  bookingCount: number;
  confirmedBookingCount: number;
  cancelledBookingCount: number;
  ticketedBookingCount: number;
  grossBookingValue: number;
  totalPaid: number;
  outstandingCustomerBalance: number;
  commissionEarned: number;
  commissionPaid: number;
  commissionPending: number;
  refundExposure: number;
  lastBookingDate: string | null;
  lastPaymentDate: string | null;
  lastTicketActivity: string | null;
  createdDate: string;
  supportOwner: string;
  notesSummary: string;
  linkedCustomerIds: string[];
  linkedBookingIds: string[];
  linkedTransactionIds: string[];
  linkedPnrIds: string[];
  linkedTicketIds: string[];
  currency: string;
};

export type AgentSortField =
  | "agentName"
  | "newest"
  | "bookingCount"
  | "grossBookingValue"
  | "totalPaid"
  | "outstandingBalance"
  | "commissionPending"
  | "lastBookingDate"
  | "statusPriority";

export type SortDirection = "asc" | "desc";

export type AgentsQuery = {
  q: string;
  accountStatus: AccountStatus | "all";
  verificationStatus: VerificationStatus | "all";
  commercialStatus: CommercialStatus | "all";
  settlementStatus: SettlementStatus | "all";
  agentType: AgentType | "all";
  city: string;
  countryRegion: string;
  hasOutstandingBalance: "all" | "yes" | "no";
  hasPendingCommission: "all" | "yes" | "no";
  hasBookings: "all" | "yes" | "no";
  activityFrom: string;
  activityTo: string;
  page: number;
  pageSize: number;
  sort: AgentSortField;
  direction: SortDirection;
  selectedId: string | null;
  previewError: boolean;
  previewLoading: boolean;
};

export type AgentsSummaryMetrics = {
  totalAgents: number;
  activeAgents: number;
  verifiedAgents: number;
  agentsWithOverdueBalances: number;
  grossBookingValue: number;
  pendingCommission: number;
  currency: string;
};

export type AgentsPageResult = {
  agents: AgentRecord[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: AgentsSummaryMetrics;
  facets: {
    cities: string[];
    countries: string[];
    regions: string[];
    agentTypes: AgentType[];
  };
};
