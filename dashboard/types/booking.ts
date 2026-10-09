export type BookingStatus = "confirmed" | "pending" | "failed" | "cancelled";

export type PaymentStatus = "paid" | "unpaid" | "partial" | "pending";

export type TicketingStatus = "ticketed" | "unticketed" | "pending";

export type TripType = "one_way" | "return";

export type BookingRecord = {
  id: string;
  pnr: string;
  supplierReference: string | null;
  bookingDate: string;
  departureDate: string;
  returnDate: string | null;
  customerName: string;
  customerEmail: string;
  customerPhone: string;
  passengerCount: number;
  origin: string;
  destination: string;
  tripType: TripType;
  airline: string;
  supplier: string;
  bookingStatus: BookingStatus;
  paymentStatus: PaymentStatus;
  ticketingStatus: TicketingStatus;
  currency: string;
  totalAmount: number;
  amountPaid: number;
  agentOrSource: string;
  lastUpdated: string;
};

export type BookingSortField =
  | "bookingDate"
  | "departureDate"
  | "customer"
  | "route"
  | "amount"
  | "status"
  | "lastUpdated";

export type SortDirection = "asc" | "desc";

export type BookingsQuery = {
  q: string;
  status: BookingStatus | "all";
  payment: PaymentStatus | "all";
  ticketing: TicketingStatus | "all";
  supplier: string;
  airline: string;
  tripType: TripType | "all";
  bookingDateFrom: string;
  bookingDateTo: string;
  departureDateFrom: string;
  departureDateTo: string;
  page: number;
  pageSize: number;
  sort: BookingSortField;
  direction: SortDirection;
  selectedId: string | null;
  previewError: boolean;
};

export type BookingsSummaryMetrics = {
  totalDisplayed: number;
  confirmed: number;
  pending: number;
  cancelledOrFailed: number;
  paid: number;
  outstandingAmount: number;
  currency: string;
};

export type BookingsPageResult = {
  bookings: BookingRecord[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: BookingsSummaryMetrics;
  facets: {
    suppliers: string[];
    airlines: string[];
  };
};

export type BookingPassengerSummary = {
  displayName: string;
  type: string;
};

export type BookingFareSummary = {
  currency: string;
  baseFare: number;
  taxes: number;
  fees: number;
  markup: number;
  total: number;
};

export type BookingDetail = {
  summary: BookingRecord;
  itinerary: {
    route: string;
    airline: string;
    travelDate: string | null;
    returnDate: string | null;
  };
  passengers: BookingPassengerSummary[];
  fareSummary: BookingFareSummary | null;
  paymentSummary: {
    status: PaymentStatus | string;
    amountPaid: number;
    totalAmount: number;
    currency: string;
  } | null;
  pnrSummary: {
    pnr: string | null;
    supplierReference: string | null;
    channel?: string;
    supplier: string;
    supplierStatus?: string;
  } | null;
  ticketReadiness: {
    ticketingStatus: TicketingStatus | string;
    ticketCount: number;
  } | null;
  auditMetadata: {
    createdAt: string | null;
    updatedAt: string | null;
    bookingStatus: BookingStatus | string;
  } | null;
  documents?: BookingDocumentRow[];
  assignment?: BookingAssignmentState;
  paymentsHistory?: BookingPaymentHistoryRow[];
  cancellationState?: BookingCancellationState;
  refundState?: BookingRefundState;
  communicationLogs?: BookingCommunicationLogRow[];
  activityTimeline?: BookingActivityEvent[];
  operationalCapabilities?: BookingOperationalCapabilities;
};

export type BookingDocumentRow = {
  id: string;
  type: string;
  title: string;
  documentNumber: string | null;
  status: string;
  generatedAt: string | null;
  generatedBy: string | null;
  downloadPath: string;
};

export type BookingAssignmentState = {
  staffId: string | null;
  staffName: string | null;
  assignedAt: string | null;
  canAssign: boolean;
  assignableStaff: { id: number; name: string }[];
};

export type BookingPaymentHistoryRow = {
  id: string;
  amount: number;
  currency: string;
  method: string;
  status: string;
  reference: string | null;
  recordedAt: string | null;
};

export type BookingCancellationState = {
  bookingCancellationStatus: string;
  requests: {
    id: string;
    status: string;
    cancellationType: string;
    reason: string | null;
    requestedAt: string | null;
  }[];
};

export type BookingRefundState = {
  bookingRefundStatus: string;
  refunds: {
    id: string;
    amount: number;
    currency: string;
    method: string;
    status: string;
    reference: string | null;
    requestedAt: string | null;
  }[];
};

export type BookingCommunicationLogRow = {
  id: string;
  channel: string;
  event: string;
  status: string;
  recipientEmail: string | null;
  sentAt: string | null;
  errorSummary: string | null;
};

export type BookingActivityEvent = {
  type: string;
  title: string;
  occurredAt: string | null;
  details: string;
};

export type BookingOperationalCapabilities = {
  canAssignStaff: boolean;
  canAddNote: boolean;
  canRecordPayment: boolean;
  canRequestCancellation: boolean;
  canCreateRefund: boolean;
  communicationActions: Record<string, { enabled: boolean; reason: string | null }>;
  communicationSendGated: boolean;
};
