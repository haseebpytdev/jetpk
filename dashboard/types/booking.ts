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
  currencyStatus?: "resolved" | "unresolved";
  currencySource?: string | null;
  baseFare: number;
  taxes: number;
  fees: number;
  markup: number;
  total: number;
};

export type BookingManagementDetail = {
  summary: BookingRecord;
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
  auditMetadata: {
    createdAt: string | null;
    updatedAt: string | null;
    bookingStatus: BookingRecord["bookingStatus"];
  } | null;
};
