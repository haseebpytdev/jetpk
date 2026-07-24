export type DocumentType =
  | "E-Ticket"
  | "NDC Fulfilment Document"
  | "EMD"
  | "Manual Ticket Record"
  | "Refund Document"
  | "Void Record";

export type TicketChannel = "Sabre GDS" | "Sabre NDC" | "One API" | "Manual" | "Mock";

export type IssueStatus =
  | "Pending"
  | "Issued"
  | "Partially Issued"
  | "Blocked"
  | "Failed"
  | "Voided"
  | "Refunded"
  | "Not Applicable";

export type FulfilmentStatus =
  | "Pending"
  | "Fulfilled"
  | "Partially Fulfilled"
  | "Failed"
  | "Cancelled"
  | "Refunded";

export type TicketPaymentStatus =
  | "Unpaid"
  | "Partially Paid"
  | "Paid"
  | "Refunded"
  | "Partially Refunded"
  | "Reconciliation Required";

export type RefundStatus =
  | "None"
  | "Pending"
  | "Partially Refunded"
  | "Refunded"
  | "Not Applicable";

export type RefundEligibility =
  | "Eligible"
  | "Not Eligible"
  | "Airline Review Required"
  | "Fare Rules Required"
  | "Already Refunded"
  | "Unknown"
  | "Not Applicable";

export type ExchangeEligibility =
  | "Eligible"
  | "Not Eligible"
  | "Fare Rules Required"
  | "Airline Review Required"
  | "Already Exchanged"
  | "Unknown"
  | "Not Applicable";

export type VoidStatus =
  | "Within Window"
  | "Window Expired"
  | "Voided"
  | "Not Applicable"
  | "Unknown";

export type TicketRecord = {
  id: string;
  maskedExternalId: string;
  documentType: DocumentType;
  channel: TicketChannel;
  airline: string;
  supplier: string;
  supplierId: string;
  bookingId: string;
  pnrOrderId: string;
  customerId: string;
  travellerName: string;
  agentId: string | null;
  origin: string;
  destination: string;
  itinerarySummary: string;
  issueStatus: IssueStatus;
  fulfilmentStatus: FulfilmentStatus;
  paymentStatus: TicketPaymentStatus;
  refundStatus: RefundStatus;
  voidStatus: VoidStatus;
  fare: number;
  tax: number;
  total: number;
  currency: string;
  issueDate: string | null;
  travelDate: string;
  voidDeadline: string | null;
  refundEligibility: RefundEligibility;
  exchangeEligibility: ExchangeEligibility;
  createdDate: string;
  lastActivity: string;
  linkedTransactionIds: string[];
  notesSummary: string;
};

export type TicketSortField =
  | "newest"
  | "oldest"
  | "travelDate"
  | "issueDate"
  | "totalValue"
  | "airline"
  | "statusPriority"
  | "lastActivity";

export type SortDirection = "asc" | "desc";

export type TicketsQuery = {
  q: string;
  documentType: DocumentType | "all";
  channel: TicketChannel | "all";
  airline: string;
  supplier: string;
  issueStatus: IssueStatus | "all";
  fulfilmentStatus: FulfilmentStatus | "all";
  paymentStatus: TicketPaymentStatus | "all";
  refundEligibility: RefundEligibility | "all";
  voidStatus: VoidStatus | "all";
  hasAgent: "all" | "yes" | "no";
  travelFrom: string;
  travelTo: string;
  issueFrom: string;
  issueTo: string;
  page: number;
  pageSize: number;
  sort: TicketSortField;
  direction: SortDirection;
  selectedId: string | null;
  previewError: boolean;
  previewLoading: boolean;
};

export type TicketsSummaryMetrics = {
  totalDocuments: number;
  issued: number;
  pending: number;
  blockedOrFailed: number;
  refunded: number;
  totalDocumentValue: number;
  upcomingTravel: number;
  currency: string;
};

export type TicketsPageResult = {
  tickets: TicketRecord[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: TicketsSummaryMetrics;
  facets: {
    airlines: string[];
    suppliers: string[];
    documentTypes: DocumentType[];
    channels: TicketChannel[];
  };
};
