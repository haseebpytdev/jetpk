export type SupplierCategory =
  | "Airline"
  | "GDS"
  | "NDC"
  | "Hotel"
  | "Ground Transport"
  | "Visa Service"
  | "Insurance"
  | "Ancillary Service"
  | "Payment Service"
  | "Consolidator";

export type OperationalStatus = "Active" | "Inactive" | "Maintenance" | "Restricted" | "Review Required";

export type IntegrationStatus = "Connected" | "Mock Only" | "Manual" | "Degraded" | "Disabled";

export type CredentialStatus = "Configured" | "Missing" | "Expiring Soon" | "Invalid" | "Not Required";

export type SettlementStatus =
  | "Current"
  | "Due"
  | "Overdue"
  | "Reconciliation Required"
  | "Not Applicable";

export type SupplierRecord = {
  id: string;
  supplierName: string;
  displayCode: string;
  supplierCategory: SupplierCategory;
  operatingRegion: string;
  operationalStatus: OperationalStatus;
  integrationStatus: IntegrationStatus;
  credentialStatus: CredentialStatus;
  settlementStatus: SettlementStatus;
  currency: string;
  bookingCount: number;
  confirmedBookingCount: number;
  failedBookingCount: number;
  totalBookingValue: number;
  totalPaidToSupplier: number;
  outstandingSettlement: number;
  refundExposure: number;
  lastBookingActivity: string | null;
  lastSettlementActivity: string | null;
  createdDate: string;
  supportContact: string;
  escalationContact: string;
  linkedBookingIds: string[];
  linkedTransactionIds: string[];
  notesSummary: string;
};

export type SupplierSortField =
  | "supplierName"
  | "newest"
  | "bookingCount"
  | "totalBookingValue"
  | "totalPaid"
  | "outstandingSettlement"
  | "lastActivity"
  | "statusPriority";

export type SortDirection = "asc" | "desc";

export type SuppliersQuery = {
  q: string;
  category: SupplierCategory | "all";
  operationalStatus: OperationalStatus | "all";
  integrationStatus: IntegrationStatus | "all";
  credentialStatus: CredentialStatus | "all";
  settlementStatus: SettlementStatus | "all";
  operatingRegion: string;
  hasOutstandingSettlement: "all" | "yes" | "no";
  activityFrom: string;
  activityTo: string;
  page: number;
  pageSize: number;
  sort: SupplierSortField;
  direction: SortDirection;
  selectedId: string | null;
  previewError: boolean;
  previewLoading: boolean;
};

export type SuppliersSummaryMetrics = {
  totalSuppliers: number;
  activeSuppliers: number;
  connectedSuppliers: number;
  suppliersRequiringReview: number;
  totalOutstandingSettlements: number;
  recentSupplierActivity: number;
  currency: string;
};

export type SuppliersPageResult = {
  suppliers: SupplierRecord[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: SuppliersSummaryMetrics;
  facets: {
    categories: SupplierCategory[];
    regions: string[];
    operationalStatuses: OperationalStatus[];
  };
};
