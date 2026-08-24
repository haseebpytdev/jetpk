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
  currencyStatus?: "resolved" | "unresolved";
  currencySource?: string | null;
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

export type BookingsQueue =
  | "needs_action"
  | "cancellations"
  | "refunds"
  | "ticketing"
  | "payment_review"
  | "supplier_pnr"
  | "all";

export type BookingsQuery = {
  q: string;
  status: BookingStatus | "all";
  payment: PaymentStatus | "all";
  ticketing: TicketingStatus | "all";
  supplier: string;
  airline: string;
  tripType: TripType | "all";
  queue: BookingsQueue;
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
  currencyStatus?: "resolved" | "unresolved";
  currencySource?: string | null;
  baseFare: number;
  taxes: number;
  fees: number;
  markup: number;
  total: number;
};

export type BookingOperationalCapabilities = {
  can_update_status?: boolean;
  can_prepare_pnr_context?: boolean;
  can_generate_pnr: boolean;
  can_retry_pnr: boolean;
  can_sync_pnr: boolean;
  can_record_payment: boolean;
  can_admin_mark_paid: boolean;
  can_issue_ticket: boolean;
  can_void_ticket: boolean;
  can_request_cancellation: boolean;
  can_cancel_supplier_booking: boolean;
  can_request_refund: boolean;
  can_generate_documents?: boolean;
  can_download_documents?: boolean;
  can_generate_receipt?: boolean;
  can_export_audit?: boolean;
  latest_payment_id?: string | null;
  reasons?: Record<string, string | null>;
  sabre_void_support?: string;
  allowed_status_values?: string[];
};

export type BookingManagementDetail = {
  summary: BookingRecord;
  localContact?: {
    email: string;
    phone: string;
    country: string;
  };
  localAmendment?: {
    canEditContact: boolean;
    canEditPassengers: boolean;
    contactPolicy: string;
    passengerPolicy: string;
    hasSupplierPnr: boolean;
  };
  passengers: BookingPassengerSummary[];
  fareSummary: BookingFareSummary | null;
  pnrSummary: {
    pnr: string | null;
    supplierReference: string | null;
    supplier: string;
    supplierStatus: string;
    channel?: string;
  } | null;
  ticketReadiness: {
    ticketingStatus: BookingRecord["ticketingStatus"];
    ticketCount: number;
  } | null;
  operationalCapabilities?: BookingOperationalCapabilities | null;
  auditMetadata: {
    createdAt: string | null;
    updatedAt: string | null;
    bookingStatus: BookingRecord["bookingStatus"];
  } | null;
  statusTimeline: BookingStatusTimelineEntry[];
  internalNotes: BookingInternalNote[];
  communications: BookingCommunicationEntry[];
  documents: BookingDocumentEntry[];
};

export type BookingStatusTimelineEntry = {
  occurredAt: string;
  eventType: string;
  actorName: string;
  fromStatus: string;
  toStatus: string;
  summary: string;
  note: string | null;
};

export type BookingInternalNote = {
  createdAt: string;
  authorName: string;
  noteType: string;
  note: string;
  customerVisible: boolean;
};

export type BookingCommunicationEntry = {
  sentAt: string;
  channel: string;
  event: string;
  status: string;
  recipient: string;
  subject: string | null;
};

export type BookingDocumentEntry = {
  documentId: string;
  documentType: string;
  title: string;
  status: string;
  generatedAt: string | null;
  generatedBy: string;
  downloadUrl?: string | null;
};
