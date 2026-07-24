import type { PaymentStatus as BookingPaymentStatus, TripType } from "@/types/booking";

export type PnrReferenceType = "GDS PNR" | "NDC Order" | "One API Order" | "Manual Reference";

export type PnrChannel = "Sabre GDS" | "Sabre NDC" | "One API" | "Manual" | "Mock";

export type PnrLifecycleStatus =
  | "Active"
  | "Confirmed"
  | "On Hold"
  | "Pending Supplier"
  | "Partially Confirmed"
  | "Cancelled"
  | "Expired"
  | "Failed"
  | "Review Required";

export type PnrFulfilmentStatus =
  | "Not Required"
  | "Pending"
  | "Partially Fulfilled"
  | "Fulfilled"
  | "Failed"
  | "Refunded";

export type PnrTicketingStatus =
  | "Not Ticketed"
  | "Ready for Ticketing"
  | "Ticketing Blocked"
  | "Partially Ticketed"
  | "Ticketed"
  | "Failed"
  | "Voided"
  | "Refunded"
  | "Not Applicable";

export type PnrPaymentStatus = "Unpaid" | "Partially Paid" | "Paid" | "Pending" | "Refunded";

export type PnrCancellationEligibility =
  | "Eligible"
  | "Not Eligible"
  | "Supplier Review Required"
  | "Already Cancelled"
  | "Unknown"
  | "Not Applicable";

export type PnrQueueReviewStatus = "None" | "Review Required" | "Supplier Queue" | "Ticketing Queue";

export type PnrSortField =
  | "newest"
  | "oldest"
  | "departureDate"
  | "ticketingDeadline"
  | "lastActivity"
  | "travellerCount"
  | "bookingValue"
  | "statusPriority";

export type SortDirection = "asc" | "desc";

export type PnrRecord = {
  id: string;
  externalReference: string;
  referenceType: PnrReferenceType;
  channel: PnrChannel;
  supplierId: string;
  supplierName: string;
  airline: string;
  bookingId: string;
  customerId: string;
  customerName: string;
  agentId: string | null;
  agentName: string | null;
  travellerCount: number;
  travellerNames: string[];
  itinerarySummary: string;
  origin: string;
  destination: string;
  departureDate: string;
  returnDate: string | null;
  tripType: TripType;
  cabin: string;
  lifecycleStatus: PnrLifecycleStatus;
  fulfilmentStatus: PnrFulfilmentStatus;
  paymentStatus: PnrPaymentStatus;
  ticketingStatus: PnrTicketingStatus;
  ticketingDeadline: string | null;
  cancellationEligibility: PnrCancellationEligibility;
  queueReviewStatus: PnrQueueReviewStatus;
  createdDate: string;
  lastModifiedDate: string;
  lastSupplierActivity: string | null;
  linkedTicketIds: string[];
  linkedTransactionIds: string[];
  bookingValue: number;
  currency: string;
  notesSummary: string;
  showGdsTicketingLimitation: boolean;
};

export type PnrsQuery = {
  q: string;
  referenceType: PnrReferenceType | "all";
  channel: PnrChannel | "all";
  supplier: string;
  airline: string;
  lifecycleStatus: PnrLifecycleStatus | "all";
  fulfilmentStatus: PnrFulfilmentStatus | "all";
  ticketingStatus: PnrTicketingStatus | "all";
  paymentStatus: PnrPaymentStatus | "all";
  tripType: TripType | "all";
  hasAgent: "all" | "yes" | "no";
  reviewRequired: "all" | "yes" | "no";
  deadlineFrom: string;
  deadlineTo: string;
  departureFrom: string;
  departureTo: string;
  page: number;
  pageSize: number;
  sort: PnrSortField;
  direction: SortDirection;
  selectedId: string | null;
  previewError: boolean;
  previewLoading: boolean;
};

export type PnrsSummaryMetrics = {
  totalRecords: number;
  activeRecords: number;
  gdsPnrCount: number;
  ndcOrderCount: number;
  awaitingFulfilment: number;
  reviewRequired: number;
  approachingDeadline: number;
  currency: string;
};

export type PnrsPageResult = {
  pnrs: PnrRecord[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: PnrsSummaryMetrics;
  facets: {
    suppliers: string[];
    airlines: string[];
    referenceTypes: PnrReferenceType[];
    channels: PnrChannel[];
  };
};

export function mapBookingPaymentStatus(status: BookingPaymentStatus): PnrPaymentStatus {
  switch (status) {
    case "paid":
      return "Paid";
    case "partial":
      return "Partially Paid";
    case "pending":
      return "Pending";
    default:
      return "Unpaid";
  }
}
