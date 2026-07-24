import { mockBookings } from "@/mocks/booking-fixtures";
import { mockTransactions } from "@/mocks/payment-fixtures";
import type {
  DocumentType,
  ExchangeEligibility,
  FulfilmentStatus,
  IssueStatus,
  RefundEligibility,
  RefundStatus,
  TicketChannel,
  TicketPaymentStatus,
  TicketRecord,
  VoidStatus,
} from "@/types/ticket";

const DOCUMENT_TYPES: DocumentType[] = [
  "E-Ticket",
  "NDC Fulfilment Document",
  "EMD",
  "Manual Ticket Record",
  "Refund Document",
  "Void Record",
];

const CHANNELS: TicketChannel[] = ["Sabre GDS", "Sabre NDC", "One API", "Manual", "Mock"];

const ISSUE_STATUSES: IssueStatus[] = [
  "Pending",
  "Issued",
  "Partially Issued",
  "Blocked",
  "Failed",
  "Voided",
  "Refunded",
  "Not Applicable",
];

const FULFILMENT_STATUSES: FulfilmentStatus[] = [
  "Pending",
  "Fulfilled",
  "Partially Fulfilled",
  "Failed",
  "Cancelled",
  "Refunded",
];

const PAYMENT_STATUSES: TicketPaymentStatus[] = [
  "Unpaid",
  "Partially Paid",
  "Paid",
  "Refunded",
  "Partially Refunded",
  "Reconciliation Required",
];

const REFUND_ELIGIBILITIES: RefundEligibility[] = [
  "Eligible",
  "Not Eligible",
  "Airline Review Required",
  "Fare Rules Required",
  "Already Refunded",
  "Unknown",
  "Not Applicable",
];

const EXCHANGE_ELIGIBILITIES: ExchangeEligibility[] = [
  "Eligible",
  "Not Eligible",
  "Fare Rules Required",
  "Airline Review Required",
  "Already Exchanged",
  "Unknown",
  "Not Applicable",
];

const VOID_STATUSES: VoidStatus[] = [
  "Within Window",
  "Window Expired",
  "Voided",
  "Not Applicable",
  "Unknown",
];

const AGENT_BY_SOURCE: Record<string, string> = {
  "Agent — Lahore Central": "JP-AG-60001",
  "Agent — Karachi North": "JP-AG-60002",
  "Agent — Karachi South": "JP-AG-60003",
  "Agent — Islamabad": "JP-AG-60004",
};

const SUPPLIER_BY_NAME: Record<string, string> = {
  Sabre: "JP-SU-50001",
  Duffel: "JP-SU-50002",
};

function customerIdForBookingIndex(index: number): string {
  return `JP-CU-${String(40001 + index).padStart(5, "0")}`;
}

function pnrIdForBookingIndex(index: number): string {
  return `JP-PN-${String(70001 + index).padStart(5, "0")}`;
}

function maskedExternalId(index: number): string {
  const suffix = String(100 + (index % 900)).padStart(3, "0");
  return `157-XXXXXXX${suffix}`;
}

function channelForBooking(supplier: string, index: number): TicketChannel {
  if (supplier === "Duffel") {
    return index % 3 === 0 ? "One API" : "Sabre NDC";
  }
  if (supplier === "Sabre") {
    return index % 5 === 0 ? "Sabre NDC" : "Sabre GDS";
  }
  return CHANNELS[index % CHANNELS.length]!;
}

function transactionsForBooking(bookingId: string): string[] {
  return mockTransactions.filter((tx) => tx.bookingId === bookingId).map((tx) => tx.transactionId);
}

function issueStatusForBooking(
  ticketingStatus: string,
  documentType: DocumentType,
  index: number,
): IssueStatus {
  if (documentType === "Refund Document") return "Refunded";
  if (documentType === "Void Record") return "Voided";
  if (documentType === "Manual Ticket Record" && index % 7 === 0) return "Blocked";
  if (ticketingStatus === "ticketed") return "Issued";
  if (ticketingStatus === "pending") return "Pending";
  if (ticketingStatus === "unticketed" && index % 4 === 0) return "Blocked";
  return index % 9 === 0 ? "Failed" : "Pending";
}

function paymentStatusForBooking(
  paymentStatus: string,
  documentType: DocumentType,
): TicketPaymentStatus {
  if (documentType === "Refund Document") return "Refunded";
  if (paymentStatus === "paid") return "Paid";
  if (paymentStatus === "partial") return "Partially Paid";
  if (paymentStatus === "pending") return "Reconciliation Required";
  return "Unpaid";
}

function refundStatusFor(documentType: DocumentType, index: number): RefundStatus {
  if (documentType === "Refund Document") return "Refunded";
  if (index % 11 === 0) return "Partially Refunded";
  if (index % 13 === 0) return "Pending";
  return "None";
}

function buildTicketFromBooking(index: number, documentTypeOverride?: DocumentType): TicketRecord {
  const booking = mockBookings[index]!;
  const id = `JP-TK-${String(80001 + index).padStart(5, "0")}`;
  const documentType = documentTypeOverride ?? DOCUMENT_TYPES[index % DOCUMENT_TYPES.length]!;
  const channel = channelForBooking(booking.supplier, index);
  const supplierId = SUPPLIER_BY_NAME[booking.supplier] ?? "JP-SU-50001";
  const agentId = AGENT_BY_SOURCE[booking.agentOrSource] ?? null;
  const issueStatus = issueStatusForBooking(booking.ticketingStatus, documentType, index);
  const fare = Math.round(booking.totalAmount * 0.72);
  const tax = booking.totalAmount - fare;
  const linkedTransactionIds = transactionsForBooking(booking.id);

  return {
    id,
    maskedExternalId: maskedExternalId(index),
    documentType,
    channel,
    airline: booking.airline,
    supplier: booking.supplier,
    supplierId,
    bookingId: booking.id,
    pnrOrderId: pnrIdForBookingIndex(index),
    customerId: customerIdForBookingIndex(index),
    travellerName: booking.customerName,
    agentId,
    origin: booking.origin,
    destination: booking.destination,
    itinerarySummary: `${booking.origin} → ${booking.destination} · ${booking.tripType.replace("_", " ")} · ${booking.airline}`,
    issueStatus,
    fulfilmentStatus: FULFILMENT_STATUSES[index % FULFILMENT_STATUSES.length]!,
    paymentStatus: paymentStatusForBooking(booking.paymentStatus, documentType),
    refundStatus: refundStatusFor(documentType, index),
    voidStatus: VOID_STATUSES[index % VOID_STATUSES.length]!,
    fare,
    tax,
    total: booking.totalAmount,
    currency: booking.currency,
    issueDate:
      booking.ticketingStatus === "ticketed" || documentType === "Refund Document"
        ? booking.bookingDate
        : null,
    travelDate: booking.departureDate,
    voidDeadline: index % 3 === 0 ? booking.departureDate : null,
    refundEligibility: REFUND_ELIGIBILITIES[index % REFUND_ELIGIBILITIES.length]!,
    exchangeEligibility: EXCHANGE_ELIGIBILITIES[index % EXCHANGE_ELIGIBILITIES.length]!,
    createdDate: booking.bookingDate,
    lastActivity: booking.lastUpdated.slice(0, 10),
    linkedTransactionIds,
    notesSummary: `Preview ${documentType.toLowerCase()} for booking ${booking.id} via ${channel}. ${booking.agentOrSource}.`,
  };
}

type SupplementSeed = {
  bookingIndex: number;
  documentType: DocumentType;
  issueStatus: IssueStatus;
  fulfilmentStatus: FulfilmentStatus;
  refundEligibility: RefundEligibility;
  voidStatus: VoidStatus;
  notesSuffix: string;
};

const SUPPLEMENT_TICKETS: SupplementSeed[] = [
  {
    bookingIndex: 0,
    documentType: "EMD",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Not Eligible",
    voidStatus: "Not Applicable",
    notesSuffix: "Seat selection EMD.",
  },
  {
    bookingIndex: 1,
    documentType: "NDC Fulfilment Document",
    issueStatus: "Pending",
    fulfilmentStatus: "Pending",
    refundEligibility: "Unknown",
    voidStatus: "Within Window",
    notesSuffix: "Awaiting NDC fulfilment.",
  },
  {
    bookingIndex: 2,
    documentType: "Manual Ticket Record",
    issueStatus: "Blocked",
    fulfilmentStatus: "Failed",
    refundEligibility: "Not Applicable",
    voidStatus: "Not Applicable",
    notesSuffix: "Manual record blocked pending review.",
  },
  {
    bookingIndex: 5,
    documentType: "EMD",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Fare Rules Required",
    voidStatus: "Window Expired",
    notesSuffix: "Baggage EMD issued.",
  },
  {
    bookingIndex: 7,
    documentType: "Refund Document",
    issueStatus: "Refunded",
    fulfilmentStatus: "Refunded",
    refundEligibility: "Already Refunded",
    voidStatus: "Not Applicable",
    notesSuffix: "Partial refund document.",
  },
  {
    bookingIndex: 9,
    documentType: "Void Record",
    issueStatus: "Voided",
    fulfilmentStatus: "Cancelled",
    refundEligibility: "Not Applicable",
    voidStatus: "Voided",
    notesSuffix: "Void record for reissue workflow preview.",
  },
  {
    bookingIndex: 11,
    documentType: "EMD",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Eligible",
    voidStatus: "Within Window",
    notesSuffix: "Meal upgrade EMD.",
  },
  {
    bookingIndex: 13,
    documentType: "NDC Fulfilment Document",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Airline Review Required",
    voidStatus: "Not Applicable",
    notesSuffix: "NDC order fulfilment document.",
  },
  {
    bookingIndex: 15,
    documentType: "Refund Document",
    issueStatus: "Refunded",
    fulfilmentStatus: "Refunded",
    refundEligibility: "Already Refunded",
    voidStatus: "Not Applicable",
    notesSuffix: "Refund document linked to payment reversal.",
  },
  {
    bookingIndex: 18,
    documentType: "EMD",
    issueStatus: "Partially Issued",
    fulfilmentStatus: "Partially Fulfilled",
    refundEligibility: "Not Eligible",
    voidStatus: "Unknown",
    notesSuffix: "Lounge access EMD partial.",
  },
  {
    bookingIndex: 20,
    documentType: "Manual Ticket Record",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Not Eligible",
    voidStatus: "Window Expired",
    notesSuffix: "Manual BSP ticket record.",
  },
  {
    bookingIndex: 23,
    documentType: "Void Record",
    issueStatus: "Voided",
    fulfilmentStatus: "Cancelled",
    refundEligibility: "Not Applicable",
    voidStatus: "Voided",
    notesSuffix: "Same-day void record.",
  },
  {
    bookingIndex: 24,
    documentType: "NDC Fulfilment Document",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Eligible",
    voidStatus: "Not Applicable",
    notesSuffix: "Duffel NDC fulfilment.",
  },
  {
    bookingIndex: 3,
    documentType: "EMD",
    issueStatus: "Pending",
    fulfilmentStatus: "Pending",
    refundEligibility: "Unknown",
    voidStatus: "Within Window",
    notesSuffix: "Pending ancillary EMD.",
  },
  {
    bookingIndex: 4,
    documentType: "Manual Ticket Record",
    issueStatus: "Failed",
    fulfilmentStatus: "Failed",
    refundEligibility: "Not Applicable",
    voidStatus: "Not Applicable",
    notesSuffix: "Failed manual ticket attempt.",
  },
  {
    bookingIndex: 6,
    documentType: "Refund Document",
    issueStatus: "Refunded",
    fulfilmentStatus: "Refunded",
    refundEligibility: "Already Refunded",
    voidStatus: "Not Applicable",
    notesSuffix: "Airline-initiated refund document.",
  },
  {
    bookingIndex: 8,
    documentType: "Void Record",
    issueStatus: "Voided",
    fulfilmentStatus: "Cancelled",
    refundEligibility: "Not Applicable",
    voidStatus: "Voided",
    notesSuffix: "Void before reissue.",
  },
  {
    bookingIndex: 10,
    documentType: "NDC Fulfilment Document",
    issueStatus: "Pending",
    fulfilmentStatus: "Pending",
    refundEligibility: "Fare Rules Required",
    voidStatus: "Within Window",
    notesSuffix: "NDC fulfilment queued.",
  },
  {
    bookingIndex: 12,
    documentType: "EMD",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Not Eligible",
    voidStatus: "Not Applicable",
    notesSuffix: "Extra baggage EMD.",
  },
  {
    bookingIndex: 14,
    documentType: "Manual Ticket Record",
    issueStatus: "Blocked",
    fulfilmentStatus: "Failed",
    refundEligibility: "Not Applicable",
    voidStatus: "Not Applicable",
    notesSuffix: "GDS ticketing blocked — informational only.",
  },
  {
    bookingIndex: 16,
    documentType: "Refund Document",
    issueStatus: "Refunded",
    fulfilmentStatus: "Refunded",
    refundEligibility: "Already Refunded",
    voidStatus: "Not Applicable",
    notesSuffix: "Customer refund document.",
  },
  {
    bookingIndex: 17,
    documentType: "EMD",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Eligible",
    voidStatus: "Window Expired",
    notesSuffix: "Priority boarding EMD.",
  },
  {
    bookingIndex: 19,
    documentType: "Void Record",
    issueStatus: "Voided",
    fulfilmentStatus: "Cancelled",
    refundEligibility: "Not Applicable",
    voidStatus: "Voided",
    notesSuffix: "Agent-requested void record.",
  },
  {
    bookingIndex: 21,
    documentType: "NDC Fulfilment Document",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Airline Review Required",
    voidStatus: "Not Applicable",
    notesSuffix: "One API fulfilment document.",
  },
  {
    bookingIndex: 22,
    documentType: "EMD",
    issueStatus: "Partially Issued",
    fulfilmentStatus: "Partially Fulfilled",
    refundEligibility: "Not Eligible",
    voidStatus: "Unknown",
    notesSuffix: "Wi-Fi package EMD.",
  },
  {
    bookingIndex: 0,
    documentType: "Refund Document",
    issueStatus: "Refunded",
    fulfilmentStatus: "Refunded",
    refundEligibility: "Already Refunded",
    voidStatus: "Not Applicable",
    notesSuffix: "Tax-only refund document.",
  },
  {
    bookingIndex: 5,
    documentType: "Void Record",
    issueStatus: "Voided",
    fulfilmentStatus: "Cancelled",
    refundEligibility: "Not Applicable",
    voidStatus: "Voided",
    notesSuffix: "Void record for schedule change.",
  },
  {
    bookingIndex: 7,
    documentType: "EMD",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Not Eligible",
    voidStatus: "Not Applicable",
    notesSuffix: "Sports equipment EMD.",
  },
  {
    bookingIndex: 9,
    documentType: "Manual Ticket Record",
    issueStatus: "Issued",
    fulfilmentStatus: "Fulfilled",
    refundEligibility: "Fare Rules Required",
    voidStatus: "Window Expired",
    notesSuffix: "Offline manual ticket.",
  },
  {
    bookingIndex: 11,
    documentType: "Refund Document",
    issueStatus: "Refunded",
    fulfilmentStatus: "Refunded",
    refundEligibility: "Already Refunded",
    voidStatus: "Not Applicable",
    notesSuffix: "Voluntary refund document.",
  },
];

function buildSupplementTicket(supplementIndex: number, seed: SupplementSeed): TicketRecord {
  const ticketIndex = 25 + supplementIndex;
  const base = buildTicketFromBooking(seed.bookingIndex, seed.documentType);
  return {
    ...base,
    id: `JP-TK-${String(80001 + ticketIndex).padStart(5, "0")}`,
    maskedExternalId: maskedExternalId(ticketIndex),
    documentType: seed.documentType,
    issueStatus: seed.issueStatus,
    fulfilmentStatus: seed.fulfilmentStatus,
    refundEligibility: seed.refundEligibility,
    voidStatus: seed.voidStatus,
    refundStatus: seed.documentType === "Refund Document" ? "Refunded" : base.refundStatus,
    paymentStatus:
      seed.documentType === "Refund Document"
        ? "Refunded"
        : seed.issueStatus === "Voided"
          ? "Paid"
          : base.paymentStatus,
    issueDate:
      seed.issueStatus === "Issued" ||
      seed.issueStatus === "Refunded" ||
      seed.issueStatus === "Voided"
        ? base.createdDate
        : null,
    notesSummary: `${base.notesSummary} ${seed.notesSuffix}`,
  };
}

/** Deterministic preview tickets and fulfilment documents — not production data. */
export const mockTickets: TicketRecord[] = [
  ...mockBookings.map((_, index) => buildTicketFromBooking(index)),
  ...SUPPLEMENT_TICKETS.map((seed, i) => buildSupplementTicket(i, seed)),
];

export function getTicketById(id: string): TicketRecord | undefined {
  return mockTickets.find((t) => t.id === id);
}

export const TICKET_FIXTURE_COUNT = mockTickets.length;
